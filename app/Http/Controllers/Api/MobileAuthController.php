<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentFaceProfile;
use App\Models\User;
use App\Support\FaceEmbedding;
use App\Support\FaceMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    /**
     * Mobile login for Intern/Student using student_number and password.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_number' => ['required', 'string'],
            'password'       => ['required', 'string'],
        ]);

        $student = Student::query()
            ->with(['user.role', 'faceProfile'])
            ->where('student_number', $validated['student_number'])
            ->first();

        $user = $student?->user;

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'student_number' => ['Invalid student number or password.'],
            ]);
        }

        if (! $user->is_active || ! $student->is_active) {
            throw ValidationException::withMessages([
                'student_number' => ['Your account is inactive.'],
            ]);
        }

        $token = $user->createToken('mobile-api');

        return response()->json([
            'token_type'   => 'Bearer',
            'access_token' => $token->plainTextToken,
            'user'         => [
                'id'        => $user->id,
                'name'      => $user->name,
                'email'     => $user->email,
                'role'      => $user->role?->name,
            ],
            'student'      => [
                'id'             => $student->id,
                'student_number' => $student->student_number,
                'first_name'     => $student->first_name,
                'last_name'      => $student->last_name,
                'face_enrolled'  => $student->faceProfile !== null && $student->faceProfile->is_active,
            ],
        ]);
    }

    /**
     * Face Recognition Login for Intern/Student using 128-D face embedding array.
     */
    public function faceLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_number' => ['nullable', 'string'],
            'embedding'      => ['required', 'array', 'size:' . FaceEmbedding::LENGTH],
            'embedding.*'    => ['numeric'],
        ]);

        $scannedEmbedding = FaceEmbedding::normalize($validated['embedding']);

        $matchingStudent = null;
        $bestDistance = 999.0;
        $threshold = (float) config('services.face.match_threshold', 0.6);

        if (! empty($validated['student_number'])) {
            $student = Student::query()
                ->with(['user.role', 'faceProfile'])
                ->where('student_number', $validated['student_number'])
                ->first();

            if (! $student || ! $student->faceProfile || ! $student->faceProfile->is_active || empty($student->faceProfile->face_embedding)) {
                throw ValidationException::withMessages([
                    'embedding' => ['No face profile enrolled for this student number.'],
                ]);
            }

            $distance = FaceMatcher::euclideanDistance($student->faceProfile->face_embedding, $scannedEmbedding);

            if ($distance <= $threshold) {
                $matchingStudent = $student;
                $bestDistance = $distance;
            }
        } else {
            // Scan across all enrolled active face profiles
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
                'embedding' => ['Face recognition failed. Face did not match any enrolled student profile.'],
            ]);
        }

        $user = $matchingStudent->user;
        $token = $user->createToken('mobile-face-api');

        return response()->json([
            'message'          => 'Facial recognition authentication successful.',
            'face_match_score' => round($bestDistance, 4),
            'token_type'       => 'Bearer',
            'access_token'     => $token->plainTextToken,
            'user'             => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role?->name,
            ],
            'student'          => [
                'id'             => $matchingStudent->id,
                'student_number' => $matchingStudent->student_number,
                'first_name'     => $matchingStudent->first_name,
                'last_name'      => $matchingStudent->last_name,
                'face_enrolled'  => true,
            ],
        ]);
    }

    /**
     * Enroll face embedding for the authenticated intern/student.
     */
    public function enrollFace(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'embedding'      => ['required', 'array', 'size:' . FaceEmbedding::LENGTH],
            'embedding.*'    => ['numeric'],
            'reference_image' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $student = Student::where('user_id', $user->id)->first();

        if (! $student) {
            return response()->json(['message' => 'Student record not found for this user.'], 404);
        }

        $embedding = FaceEmbedding::normalize($validated['embedding']);

        $faceProfile = StudentFaceProfile::updateOrCreate(
            ['student_id' => $student->id],
            [
                'face_embedding'       => $embedding,
                'reference_image_path' => $validated['reference_image'] ?? null,
                'enrolled_at'          => now(),
                'is_active'            => true,
            ]
        );

        return response()->json([
            'message'      => 'Face profile enrolled successfully.',
            'face_profile' => [
                'id'          => $faceProfile->id,
                'enrolled_at' => $faceProfile->enrolled_at?->toIso8601String(),
                'is_active'   => $faceProfile->is_active,
            ],
        ]);
    }
}
