<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('course.{courseId}.supervisors', function ($user, $courseId) {
    // Dean of this exact course
    if ($user->hasRole('dean') && $user->courseAsDean?->id == $courseId) {
        return true;
    }

    // Program head of this exact course
    if ($user->hasRole('program_head') && $user->courseAsProgramHead?->id == $courseId) {
        return true;
    }

    // Coordinator of any active section under this course
    if ($user->hasRole('coordinator') && $user->coordinatedSections()->where('course_id', $courseId)->exists()) {
        return true;
    }

    return false;
});