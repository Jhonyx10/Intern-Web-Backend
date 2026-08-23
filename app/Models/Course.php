<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'required_hours',
        'dean_user_id',
        'program_head_id',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dean(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dean_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function programHead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'program_head_id');
    }

    /**
     * @return HasMany<Section, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /**
     * @return HasMany<CourseMajor, $this>
     */
    public function majors(): HasMany
    {
        return $this->hasMany(CourseMajor::class)->orderBy('sort_order')->orderBy('name');
    }
}
