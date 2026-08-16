<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimeLog extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'student_id',
        'session_period',
        'task_note',
        'time_in',
        'time_out',
        'duration_minutes',
        'verification_method',
        'face_match_score',
        'device_info',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'time_in' => 'datetime',
            'time_out' => 'datetime',
            'face_match_score' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return HasMany<TimeLogTaskPhoto, $this>
     */
    public function taskPhotos(): HasMany
    {
        return $this->hasMany(TimeLogTaskPhoto::class);
    }
}
