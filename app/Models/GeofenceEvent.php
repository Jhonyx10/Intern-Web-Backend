<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeofenceEvent extends Model
{
    protected $fillable = [
        'time_log_id', 'student_id', 'event_type',
        'latitude', 'longitude', 'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function timeLog(): BelongsTo
    {
        return $this->belongsTo(TimeLog::class);
    }
}
