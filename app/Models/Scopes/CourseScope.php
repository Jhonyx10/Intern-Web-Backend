<?php

namespace App\Models\Scopes;

use App\Support\DeanPortalScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class CourseScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (Auth::check()) {
            $user = Auth::user();

            // Bypass the scope entirely if the user is a super_admin
            if ($user->hasRole('super_admin')) {
                return;
            }

            $courseId = null;

            if (DeanPortalScope::isPortalUser($user)) {
                $course = DeanPortalScope::course($user);

                if ($course === null) {
                    $builder->whereRaw('1 = 0');

                    return;
                }

                $courseId = $course->id;
            } elseif (! empty($user->course_id)) {
                $courseId = $user->course_id;
            }

            if ($courseId) {
                if (method_exists($model, 'applyCourseScope')) {
                    $model->applyCourseScope($builder, $courseId);
                } else {
                    $builder->where($model->getTable().'.course_id', $courseId);
                }
            }
        }
    }
}
