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

            // Apply scope based on Dean or Program Head assignment
            if (DeanPortalScope::isPortalUser($user)) {
                $course = DeanPortalScope::course($user);
                
                if ($course) {
                    $builder->where($model->getTable() . '.course_id', $course->id);
                }
            } 
            // Fallback: If the user model itself has a direct course_id attribute
            elseif (!empty($user->course_id)) {
                $builder->where($model->getTable() . '.course_id', $user->course_id);
            }
        }
    }
}
