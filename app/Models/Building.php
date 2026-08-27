<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Building extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'description',
        'latitude',
        'longitude',
        'geofence_radius_meters',
        'geofence_enabled',
        'geofence_polygon',
        'is_active',
    ];

    /**
     * Cast attributes.
     */
    protected $casts = [
        'geofence_polygon' => 'array', // Automatically casts GeoJSON JSON string to array
        'geofence_enabled' => 'boolean',
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'geofence_radius_meters' => 'integer',
    ];

    /**
     * Relationship to Parent Company
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}