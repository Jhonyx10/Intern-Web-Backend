<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Services\UserService;

class UserController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index()
    {
        return User::with('role')->whereNotIn('role_id', [1,6])->get(); 
    }

    public function show(User $user)
    {
        return $user;
    }

    public function store(Request $request)
    {
        $user = $this->userService->createAccount($request->all());
        return $user;
    }

    public function update(Request $request, User $user)
    {
        $user->update($request->all());
        return $user;
    }

    public function destroy(User $user)
    {
        $user->delete();
        return response()->noContent();
    }
}
