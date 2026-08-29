<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class BuildingAssignment extends Pivot
{
    protected $fillable = [
        'student_id',
        'building_id',
        'assigned_by',
        'date_start',
        'date_end',
        'is_active',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
