<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeofenceExcursionPoint extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function excursion(): BelongsTo
    {
        return $this->belongsTo(GeofenceExcursion::class, 'excursion_id');
    }
}
