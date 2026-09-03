<?php

namespace App\Models;

use App\Models\Traits\BelongsToCourse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EvaluationTemplate extends Model
{
    use BelongsToCourse;

    protected $fillable = ['title', 'description', 'is_active', 'created_by_user_id'];

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_evaluation_template');
    }

    public function items()
    {
        return $this->hasMany(EvaluationTemplateItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Intercepts CourseScope to handle many-to-many relationship instead of direct course_id
     */
    public function applyCourseScope(Builder $builder, int|string $courseId): void
    {
        $builder->whereHas('courses', function (Builder $query) use ($courseId) {
            $query->where('courses.id', $courseId);
        });
    }
}