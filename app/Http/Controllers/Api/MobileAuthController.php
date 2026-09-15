<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentFaceProfile;
use App\Models\User;
use App\Services\PythonMicroservice;
use App\Support\FaceEmbedding;
use App\Support\FaceMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    public function __construct(private readonly PythonMicroservice $python) {}
    /**
     * Mobile login for Intern/Student using student_number and password.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_number' => ['required', 'string'],
            'password' => ['required', 'string'],
            'fcm_token' => ['nullable', 'string'],
        ]);

        $student = Student::query()
            ->with(['user.role', 'faceProfile', 'section.course.settings'])
            ->where('student_number', $validated['student_number'])
            ->first();

        $user = $student?->user;

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'student_number' => ['Invalid student ID or password.'],
            ]);
        }

        if (! $user->is_active || ! $student->is_active) {
            throw ValidationException::withMessages([
                'student_number' => ['Your account is inactive.'],
            ]);
        }

        if (! $user->hasRole('intern')) {
            throw ValidationException::withMessages([
                'student_number' => ['This login is for intern accounts only.'],
            ]);
        }

        if (isset($validated['fcm_token'])) {
            $user->update(['fcm_token' => $validated['fcm_token']]);
        }

        $token = $user->createToken('mobile-api');

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => $this->userPayload($user),
            'student' => [
                'id' => $student->id,
                'student_number' => $student->student_number,
                'full_name' => $student->fullName(),
            ],
            'section' => [
                'name' => $student->section->name,
                'code' => $student->section->code,
            ],
            'course' => [
                'course_name' => $student->section->course->name,
                'required_hrs' => $student->section->course->required_hours,
            ],
            'settings' => $student->section->course->settings
        ]);
    }

/**
     * Face Recognition Login for Intern/Student using an Image upload.
     */
    public function faceLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_number' => ['nullable', 'string'],
            'image' => ['required', 'image', 'max:5120'], // Max 5MB
            'fcm_token' => ['nullable', 'string'],
        ]);

        // Hand image to Python service to get the embedding array
        $embeddingList = $this->python->extractEmbedding($request->file('image'));
        $scannedEmbedding = FaceEmbedding::normalize($embeddingList);

        $matchingStudent = null;
        $bestDistance = 999.0;
        $threshold = (float) config('services.face.match_threshold', 0.45);

        if (! empty($validated['student_number'])) {
            $student = Student::query()
                ->with(['user.role', 'faceProfile'])
                ->where('student_number', $validated['student_number'])
                ->first();

            if (! $student || ! $student->faceProfile || ! $student->faceProfile->is_active || empty($student->faceProfile->face_embedding)) {
                throw ValidationException::withMessages([
                    'image' => ['No face profile enrolled for this student number.'],
                ]);
            }

            $distance = FaceMatcher::euclideanDistance($student->faceProfile->face_embedding, $scannedEmbedding);

            if ($distance <= $threshold) {
                $matchingStudent = $student;
                $bestDistance = $distance;
            }
        } else {
            $faceProfiles = StudentFaceProfile::with(['student.user.role'])
                ->where('is_active', true)
                ->whereNotNull('face_embedding')
                ->get();

            foreach ($faceProfiles as $fp) {
                if (! $fp->student || ! $fp->student->is_active || ! $fp->student->user || ! $fp->student->user->is_active) {
                    continue;
                }

                $dist = FaceMatcher::euclideanDistance($fp->face_embedding, $scannedEmbedding);

                if ($dist < $bestDistance && $dist <= $threshold) {
                    $bestDistance = $dist;
                    $matchingStudent = $fp->student;
                }
            }
        }

        if (! $matchingStudent) {
            throw ValidationException::withMessages([
                'image' => ['Face recognition failed. Face did not match any enrolled student profile.'],
            ]);
        }

        $user = $matchingStudent->user;
        
        if (isset($validated['fcm_token'])) {
            $user->update(['fcm_token' => $validated['fcm_token']]);
        }

        $token = $user->createToken('mobile-face-api');

        return response()->json([
            'message' => 'Facial recognition authentication successful.',
            'face_match_score' => round($bestDistance, 4),
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => $this->userPayload($user),
            'student' => [
                'id' => $matchingStudent->id,
                'student_number' => $matchingStudent->student_number,
                'full_name' => $matchingStudent->fullName(),
            ],
        ]);
    }

    /**
     * Enroll face embedding for the authenticated intern/student.
     */
    public function enrollFace(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'max:5120'], // Max 5MB
        ]);

        $user = $request->user();
        $student = Student::where('user_id', $user->id)->first();

        if (! $student) {
            return response()->json(['message' => 'Student record not found for this user.'], 404);
        }

        try {
            // Get embedding from Python Service
            $imageFile = $request->file('image');
            $embeddingList = $this->python->extractEmbedding($imageFile);
            $embedding = FaceEmbedding::normalize($embeddingList);

            // Optional: Save original image for review (in storage/app/public/faces)
            $referenceImagePath = $imageFile->store('faces', 'public');

            $faceProfile = StudentFaceProfile::updateOrCreate(
                ['student_id' => $student->id],
                [
                    'face_embedding' => $embedding,
                    'reference_image_path' => $referenceImagePath,
                    'enrolled_at' => now(),
                    'is_active' => true,
                ]
            );

            return response()->json([
                'message' => 'Face profile enrolled successfully.',
                'profile' => [
                    'id' => $faceProfile->id,
                    'enrolled_at' => $faceProfile->enrolled_at?->toIso8601String(),
                    'is_active' => $faceProfile->is_active,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Face extraction error: ' . $e->getMessage());
            
            // If the error was thrown by our own ValidationException, pass it through directly
            if ($e instanceof ValidationException) {
                throw $e;
            }

            return response()->json([
                'message' => 'An error occurred connecting to the face recognition service.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Logout for Intern/Student mobile user (revoke current access token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Successfully logged out.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        $user->loadMissing('role');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $user->role_id,
            'is_active' => $user->is_active,
            'role' => $user->role ? [
                'id' => $user->role->id,
                'name' => $user->role->name,
                'label' => $user->role->label,
            ] : null,
        ];
    }
}
