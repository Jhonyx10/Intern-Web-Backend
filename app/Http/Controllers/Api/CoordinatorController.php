<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Services\UserService;
use App\Models\Role;
use App\Models\Course;
use Illuminate\Validation\Rule;

class CoordinatorController extends Controller
{
    public function __construct(private UserService $userService) {}

    public function index()
    {
        return $this->userService->getAllCoordinators();
    }

    public function show(User $user)
    {
        return $this->userService->getCoordinatorById($user->id);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'course_id' => ['required', 'integer', Rule::exists(Course::class, 'id')],
        ]);

        $coordinatorRole = Role::where('name', 'coordinator')->firstOrFail();

        $validated['role_id'] = $coordinatorRole->id;

        return $this->userService->createCoordinator($validated);
    }

    public function update(Request $request, User $user)
    {
        return $this->userService->updateCoordinator($user->id, $request->all());
    }

    public function destroy(User $user)
    {
        return $this->userService->deleteCoordinator($user->id);
    }
}
