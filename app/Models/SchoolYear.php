<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Traits\BelongsToCourse;

class SchoolYear extends Model
{
    use BelongsToCourse;
    /**
     * @var list<string>
     */
    protected $fillable = [
        'course_id',
        'name',
        'start_date',
        'end_date',
        'is_active',
        'is_evaluation_enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
            'is_evaluation_enabled' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Section, $this>
     */
    /**
     * @return HasMany<Section, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    public function evaluationTemplates(): BelongsToMany
    {
        return $this->belongsToMany(EvaluationTemplate::class, 'school_year_evaluation_template');
    }
}
