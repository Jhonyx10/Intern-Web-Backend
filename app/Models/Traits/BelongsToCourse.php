<?php

namespace App\Models\Traits;

use App\Models\Scopes\CourseScope;
use App\Support\DeanPortalScope;
use Illuminate\Support\Facades\Auth;

trait BelongsToCourse
{
    /**
     * Boot the trait to attach the global scope and handle auto-injecting course_id.
     */
    protected static function bootBelongsToCourse(): void
    {
        // 1. Add the Global Scope to filter queries for this model
        static::addGlobalScope(new CourseScope());

        // 2. Auto-inject the course_id during model creation if none is provided
        static::creating(function ($model) {
            if (Auth::check() && empty($model->course_id)) {
                $user = Auth::user();
                
                $courseId = null;

                if (DeanPortalScope::isPortalUser($user)) {
                    $course = DeanPortalScope::course($user);
                    if ($course) {
                        $courseId = $course->id;
                    }
                } elseif (!empty($user->course_id)) {
                    $courseId = $user->course_id;
                }

                // If we found a relevant course ID and the user is NOT super_admin (or even if they are and they happen to have one)
                // We'll inject it. Typically super_admin doesn't have a specific course, but if they do, we'll assign it.
                if ($courseId !== null && !$user->hasRole('super_admin')) {
                    $model->course_id = $courseId;
                }
            }
        });
    }
}
