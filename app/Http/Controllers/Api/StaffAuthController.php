<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StaffLoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class StaffAuthController extends Controller
{
    /**
     * Authenticate staff portal users (email + password) via Passport PAT.
     */
   public function login(StaffLoginRequest $request): JsonResponse
{
    $user = User::query()
        ->with('role', 'courseAsDean')
        ->where('email', $request->validated('email'))
        ->first();

    if ($user === null || !Hash::check($request->validated('password'), $user->password)) {
        throw ValidationException::withMessages([
            'email' => ['Invalid email or password.'],
        ]);
    }

    if (! $user->is_active) {
        throw ValidationException::withMessages([
            'email' => ['Your account is inactive.'],
        ]);
    }

    if ($user->hasRole('intern')) {
        throw ValidationException::withMessages([
            'email' => ['Intern accounts must use the mobile API login.'],
        ]);
    }

    $token = $user->createToken('web-spa');

    return response()->json([
        'token_type' => 'Bearer',
        'access_token' => $token->accessToken,
        'expires_at' => $token->token->expires_at?->toIso8601String(),
        'user' => $this->userPayload($user),
    ]);
}

public function me(Request $request): JsonResponse
{
    $user = $request->user()->loadMissing('role', 'courseAsDean');

    return response()->json([
        'user' => $this->userPayload($user),
    ]);
}

/**
 * @return array<string, mixed>
 */
private function userPayload(User $user): array
{
    return [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'is_active' => $user->is_active,
        'role' => $user->role ? [
            'id' => $user->role->id,
            'name' => $user->role->name,
            'label' => $user->role->label,
        ] : null,
        'course' => $this->coursePayload($user),
    ];
}

/**
 * Only surfaces a course when the logged-in user is a dean or coordinator
 * with one currently assigned. Returns null otherwise.
 *
 * @return array<string, mixed>|null
 */
private function coursePayload(User $user): ?array
{
    $course = match ($user->role?->name) {
        'dean' => $user->courseAsDean,
        'coordinator' => $user->coordinatorCourse(),
        default => null,
    };

    if ($course === null) {
        return null;
    }

    return [
        'id' => $course->id,
        'code' => $course->code,
        'name' => $course->name,
    ];
}

}