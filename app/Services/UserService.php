<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Support\DeanPortalScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

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
        if (! $role) {
            return [];
        }

        $query = User::query()
            ->where('role_id', $role->id)
            ->with(['course', 'coordinatedSections.course']);

        $actor = Auth::user();

        if ($actor?->hasRole('dean')) {
            $query->where('created_by', $actor->id);
        } elseif ($actor && ! $actor->hasRole('super_admin')) {
            $query->withGlobalScope('course', new \App\Models\Scopes\CourseScope());
        }

        return $query->get()->map(function ($c) {
            $internsAssigned = 0;
            foreach ($c->coordinatedSections as $sec) {
                $internsAssigned += $sec->students()->count();
            }

            $department = $c->course?->name
                ?? $c->activeCoordinatorSection()?->course?->name
                ?? $c->coordinatedSections->first()?->course?->name
                ?? 'N/A';

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
        $query = User::query();
        $actor = Auth::user();

        if ($actor?->hasRole('dean')) {
            $query->where('created_by', $actor->id);
        }

        return $query->findOrFail($id);
    }

    public function createCoordinator($data)
    {
        $role = Role::where('name', 'Coordinator')->orWhere('name', 'coordinator')->first();
        if ($role) {
            $data['role_id'] = $role->id;
        }

        $actor = Auth::user();

        if ($actor) {
            $data['created_by'] = $actor->id;

            if ($actor->hasRole('dean')) {
                $course = DeanPortalScope::course($actor);

                if ($course === null) {
                    throw ValidationException::withMessages([
                        'course_id' => ['You must be assigned to a department before creating coordinators.'],
                    ]);
                }

                $data['course_id'] = $course->id;
            }
        }

        return User::create($data);
    }

    public function updateCoordinator($id, $data)
    {
        $user = $this->getCoordinatorById($id);
        $user->update($data);
        return $user;
    }

    public function deleteCoordinator($id)
    {
        $user = $this->getCoordinatorById($id);
        $user->delete();
        return ['message' => 'Deleted successfully'];
    }
}
