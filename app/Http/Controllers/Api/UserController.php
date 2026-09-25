<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use App\Models\Role;
use App\Services\UserService;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index()
    {
        return User::with('role')->whereNotIn('role_id', [1, 6])->get();
    }

    public function show(User $user)
    {
        return $user->load('role');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role_id' => ['required', 'integer', Rule::exists(Role::class, 'id')],
            'is_active' => ['boolean'],
        ]);

        $user = $this->userService->createAccount($validated);
        return $user;
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'role_id' => ['required', 'integer', Rule::exists(Role::class, 'id')],
            'is_active' => ['boolean'],
        ]);

        $user->update($validated);
        return $user->load('role');
    }

    public function destroy(User $user)
    {
        $user->delete();
        return response()->noContent();
    }

    public function updateFcmToken(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $request->user()->update([
            'fcm_token' => $request->input('fcm_token'),
        ]);

        return response()->json(['message' => 'FCM token updated.']);
    }
}