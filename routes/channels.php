<?php

use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('course.{courseId}.supervisors', function ($user, $courseId) {
    // Dean of this exact course
    if ($user->hasRole('dean') && $user->courseAsDean?->id == $courseId) {
        return true;
    }

    // Coordinator of any active section under this course
    if ($user->hasRole('coordinator') && $user->coordinatedSections()->where('course_id', $courseId)->exists()) {
        return true;
    }

    return false;
});

Broadcast::channel('company.{companyId}.locations', function ($user, $companyId) {
    if ($user->hasRole('supervisor')) {
        return (int) $user->company_id === (int) $companyId;
    }

    if ($user->hasRole('coordinator')) {
        return Company::where('id', $companyId)
            ->whereHas('students.section', fn ($q) =>
                $q->whereIn('id', $user->coordinatedSections()->pluck('id'))
            )->exists();
    }

    if ($user->hasRole('dean') || $user->hasRole('admin') || $user->hasRole('superadmin')) {
        return true;
    }

    return false;
});