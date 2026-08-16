<?php

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Str;

class UserService
{
    public function createAccount($data)
    {
        $user = User::create($data);
        return $user;
    }

    public function getAllCoordinators()
    {
        $role = Role::where('name', 'Coordinator')->orWhere('name', 'coordinator')->first();
        if (!$role) return [];

        return User::where('role_id', $role->id)
            ->with(['coordinatedSections.course'])
            ->get()
            ->map(function ($c) {
                $internsAssigned = 0;
                foreach($c->coordinatedSections as $sec) {
                    $internsAssigned += $sec->students()->count();
                }

                $department = 'N/A';
                if ($c->courseAsDean) {
                    $department = $c->courseAsDean->name;
                } else if ($c->activeCoordinatorSection() && $c->activeCoordinatorSection()->course) {
                    $department = $c->activeCoordinatorSection()->course->name;
                } else if ($c->coordinatedSections->first() && $c->coordinatedSections->first()->course) {
                    $department = $c->coordinatedSections->first()->course->name;
                }

                return [
                    'id' => (string) $c->id,
                    'name' => $c->name,
                    'email' => $c->email,
                    'department' => $department,
                    'internsAssigned' => $internsAssigned,
                    'status' => $c->is_active ? 'active' : 'invited',
                ];
            });
    }

    public function getCoordinatorById($id)
    {
        return User::findOrFail($id);
    }

    public function createCoordinator($data)
    {
        $role = Role::where('name', 'Coordinator')->orWhere('name', 'coordinator')->first();
        if ($role) {
            $data['role_id'] = $role->id;
        }
        $data['password'] = bcrypt(Str::random(10));
        return User::create($data);
    }

    public function updateCoordinator($id, $data)
    {
        $user = User::findOrFail($id);
        $user->update($data);
        return $user;
    }

    public function deleteCoordinator($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return ['message' => 'Deleted successfully'];
    }
}
