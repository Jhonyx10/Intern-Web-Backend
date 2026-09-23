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
        $adminRole = Role::where('name', 'Admin')->orWhere('name', 'admin')->first();

        if ($adminRole) {
            $isCreatingAdmin = isset($data['role_id']) && (int) $data['role_id'] === $adminRole->id;

            if ($isCreatingAdmin) {
                $adminExists = User::where('role_id', $adminRole->id)->exists();

                if ($adminExists) {
                    throw ValidationException::withMessages([
                        'role_id' => ['An admin account already exists. Only one admin account is allowed.'],
                    ]);
                }
            }
        }

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

        $coordinator = $query
            ->with([
                'course',
                'coordinatedSections.course',
                'coordinatedSections.courseMajor',
                'coordinatedSections.schoolYear',
                'coordinatedSections.students',
            ])
            ->findOrFail($id);

        $sections = $coordinator->coordinatedSections->map(fn ($s) => [
            'id'             => $s->id,
            'name'           => $s->name,
            'code'           => $s->code,
            'course'         => $s->course ? ['id' => $s->course->id, 'code' => $s->course->code, 'name' => $s->course->name] : null,
            'course_major'   => $s->courseMajor ? ['id' => $s->courseMajor->id, 'name' => $s->courseMajor->name] : null,
            'school_year'    => $s->schoolYear ? ['id' => $s->schoolYear->id, 'name' => $s->schoolYear->name, 'is_active' => $s->schoolYear->is_active] : null,
            'students_count' => $s->students->count(),
            'is_active'      => $s->is_active,
        ]);

        return response()->json([
            'id'       => $coordinator->id,
            'name'     => $coordinator->name,
            'email'    => $coordinator->email,
            'is_active' => $coordinator->is_active,
            'course'   => $coordinator->course
                ? ['id' => $coordinator->course->id, 'code' => $coordinator->course->code, 'name' => $coordinator->course->name]
                : null,
            'sections' => $sections,
        ]);
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
