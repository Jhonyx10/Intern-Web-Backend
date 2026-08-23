<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Setting extends Model
{
    protected $fillable = [
        'course_id',
        'department_name',
        'logo_path',
        'theme_color',
        'theme_color_hover',
        'theme_color_soft',
        'updated_by',
    ];

    protected $appends = [
        'logo_url',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return url(Storage::url($this->logo_path));
    }
}
