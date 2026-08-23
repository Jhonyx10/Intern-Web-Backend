<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Traits\BelongsToCourse;

class Student extends Model
{
    use BelongsToCourse;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'student_number',
        'first_name',
        'middle_name',
        'last_name',
        'section_id',
        'is_active',
        'last_document_alerts_seen_at',
        'last_document_review_alerts_seen_at',
    ];

    public function applyCourseScope(Builder $builder, int $courseId): void
    {
        $builder->whereHas('section', function ($q) use ($courseId) {
            $q->where('course_id', $courseId);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_document_alerts_seen_at' => 'datetime',
            'last_document_review_alerts_seen_at' => 'datetime',
        ];
    }

    public function fullName(): string
    {
        return trim(collect([$this->first_name, $this->middle_name, $this->last_name])->filter()->implode(' '));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Companies this student is assigned to.
     *
     * @return BelongsToMany<Company, $this>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)->withPivot(['supervisor_id', 'course_id'])->withTimestamps();
    }



    /**
     * @return HasMany<TimeLog, $this>
     */
    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class);
    }

    /**
     * @return HasOne<StudentFaceProfile, $this>
     */
    public function faceProfile(): HasOne
    {
        return $this->hasOne(StudentFaceProfile::class);
    }

    /**
     * @return HasMany<StudentDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(StudentDocument::class);
    }

    /**
     * @return HasOne<OjtSchedule, $this>
     */
    public function ojtSchedule(): HasOne
    {
        return $this->hasOne(OjtSchedule::class);
    }

    /**
     * @return HasMany<OjtAbsence, $this>
     */
    public function ojtAbsences(): HasMany
    {
        return $this->hasMany(OjtAbsence::class);
    }

    /**
     * @return HasOne<OjtEvaluation, $this>
     */
    public function ojtEvaluations(): HasMany
    {
        return $this->hasMany(OjtEvaluation::class);
    }

    /**
     * @return HasOne<OjtEvaluation, $this>
     */
    public function pendingOjtEvaluation(): HasOne
    {
        return $this->hasOne(OjtEvaluation::class)
            ->where('status', OjtEvaluation::STATUS_PENDING);
    }
}
