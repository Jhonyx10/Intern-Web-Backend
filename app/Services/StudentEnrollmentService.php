<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StudentEnrollmentService
{
    public function create(array $data): Student
    {
        return DB::transaction(function () use ($data) {
            $role = Role::where('name', 'intern')->firstOrFail();

            $user = User::create([
                'name'     => trim($data['first_name'] . ' ' . $data['last_name']),
                'email'    => $data['student_number'],
                'password' => Hash::make($data['student_number']),
                'role_id'  => $role->id,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $data['user_id'] = $user->id;

            return Student::create($data);
        });
    }
}