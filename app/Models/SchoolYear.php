<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        ];
    }

    /**
     * @return HasMany<Section, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }
}
