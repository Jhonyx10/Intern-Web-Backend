<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\BelongsToCourse;

class CourseMajor extends Model
{
    use BelongsToCourse;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'course_id',
        'name',
        'code',
        'program_head_user_id',
        'sort_order',
    ];

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function programHead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'program_head_user_id');
    }

    /**
     * @return HasMany<Section, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }
}
