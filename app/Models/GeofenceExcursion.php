<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeofenceExcursion extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function intern(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'intern_id');
    }

    public function points(): HasMany
    {
        return $this->hasMany(GeofenceExcursionPoint::class, 'excursion_id');
    }
}
