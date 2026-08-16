<?php

namespace App\Models;

use App\Support\DocumentReviewStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentDocument extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'student_id',
        'document_type_id',
        'document_requirement_id',
        'file_path',
        'original_filename',
        'file_size',
        'mime_type',
        'uploaded_at',
        'notes',
        'review_status',
        'reviewed_at',
        'reviewed_by_user_id',
        'rejection_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'review_status' => DocumentReviewStatus::class,
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
     * @return BelongsTo<DocumentType, $this>
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    /**
     * @return BelongsTo<DocumentRequirement, $this>
     */
    public function documentRequirement(): BelongsTo
    {
        return $this->belongsTo(DocumentRequirement::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
