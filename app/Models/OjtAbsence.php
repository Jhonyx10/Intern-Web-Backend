<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OjtAbsence extends Model
{
    public const STATUS_DETECTED = 'detected';

    public const STATUS_JUSTIFIED = 'justified';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'student_id',
        'absence_date',
        'status',
        'reason',
        'proof_file_path',
        'proof_original_filename',
        'proof_file_size',
        'proof_mime_type',
        'justification_submitted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'absence_date' => 'date',
            'justification_submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function isJustified(): bool
    {
        return $this->status === self::STATUS_JUSTIFIED;
    }

    public function needsJustification(): bool
    {
        return $this->status === self::STATUS_DETECTED;
    }
}
