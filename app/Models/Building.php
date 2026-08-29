<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    protected $casts = [
        'geofence_polygon' => 'array',
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

    /**
     * All students ever assigned to this building (pivot = BuildingAssignment).
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'building_assignments')
                    ->using(BuildingAssignment::class)
                    ->withPivot('id', 'assigned_by', 'is_active', 'date_start', 'date_end')
                    ->withTimestamps();
    }

    /**
     * Only students currently active in this building.
     */
    public function activeStudents(): BelongsToMany
    {
        return $this->students()->wherePivot('is_active', true);
    }

    /**
     * Raw assignment rows for this building (handy for history/audit views).
     */
    public function buildingAssignments(): HasMany
    {
        return $this->hasMany(BuildingAssignment::class);
    }
}