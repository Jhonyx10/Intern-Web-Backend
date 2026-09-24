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
        return $this->belongsTo(User::class, 'user_id');
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
        return $this->belongsToMany(Company::class)
            ->withPivot(['id','supervisor_id', 'course_id', 'status', 'removal_reason'])
            ->withTimestamps();
    }

     public function buildings(): BelongsToMany
    {
        return $this->belongsToMany(Building::class, 'building_assignments')
                    ->using(BuildingAssignment::class)
                    ->withPivot('id', 'assigned_by', 'is_active', 'date_start', 'date_end')
                    ->withTimestamps();
    }

    public function buildingAssignments(): HasMany
    {
        return $this->hasMany(BuildingAssignment::class);
    }

    public function activeBuildings(): BelongsToMany
    {
        return $this->buildings()->wherePivot('is_active', true);
    }
    /**
     * Alias relationship for singular company access.
     *
     * @return BelongsToMany<Company, $this>
     */
    public function company(): BelongsToMany
    {
        return $this->companies();
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
     * @return \Illuminate\Database\Eloquent\Relations\HasOneThrough
     */
    public function ojtSchedule(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(
            OjtSchedule::class,
            CompanyStudent::class,
            'student_id',         // Foreign key on intermediate table (CompanyStudent)
            'company_student_id', // Foreign key on target table (OjtSchedule)
            'id',                 // Local key on students table
            'id'                  // Local key on intermediate table
        );
    }

    /**
     * @return HasMany<OjtAbsence, $this>
     */
    public function ojtAbsences(): HasMany
    {
        return $this->hasMany(OjtAbsence::class);
    }

    /**
     * @return HasOne<Evaluation, $this>
     */
    public function ojtEvaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    /**
     * @return HasOne<Evaluation, $this>
     */
    public function pendingOjtEvaluation(): HasOne
    {
        return $this->hasOne(Evaluation::class)
            ->where('status', Evaluation::STATUS_PENDING);
    }

    /**
     * @return HasMany<GeofenceExcursion, $this>
     */
    public function geofenceExcursions(): HasMany
    {
        return $this->hasMany(GeofenceExcursion::class, 'intern_id');
    }
}
