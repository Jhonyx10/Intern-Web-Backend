<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeLogTaskPhoto extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'time_log_id',
        'student_id',
        'file_path',
        'original_filename',
        'file_size',
        'mime_type',
        'status',
        'submitted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TimeLog, $this>
     */
    public function timeLog(): BelongsTo
    {
        return $this->belongsTo(TimeLog::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }
}
