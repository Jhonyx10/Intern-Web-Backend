<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\DeanPortalScope;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role_id', 'course_id', 'created_by', 'is_active', 'document_submission_alerts_seen_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function createdUsers(): HasMany
    {
        return $this->hasMany(User::class, 'created_by');
    }

    public function hasRole(string $role): bool
    {
        return $this->role?->name === $role;
    }

    public function applyCourseScope(Builder $builder, int $courseId): void
    {
        $builder->where(function ($query) use ($courseId) {
            $query->where($this->getTable() . '.course_id', $courseId)
                  ->orWhereHas('courseAsDean', fn($q) => $q->where('id', $courseId))
                  ->orWhereHas('coordinatedSections', fn($q) => $q->where('course_id', $courseId))
                  ->orWhereHas('courseMajorAsProgramHead', fn($q) => $q->where('course_id', $courseId));
        });
    }

    public function isDeanPortalUser(): bool
    {
        return DeanPortalScope::isPortalUser($this);
    }

    public function deanPortalCourse(): ?Course
    {
        return DeanPortalScope::course($this);
    }

    public function deanPortalMajor(): ?CourseMajor
    {
        return DeanPortalScope::major($this);
    }

    /**
     * @return HasOne<Course, $this>
     */
    public function courseAsDean(): HasOne
    {
        return $this->hasOne(Course::class, 'dean_user_id');
    }

    /**
     * @return HasOne<CourseMajor, $this>
     */
    public function courseMajorAsProgramHead(): HasOne
    {
        return $this->hasOne(CourseMajor::class, 'program_head_user_id');
    }

    /**
     * Companies assigned to this intern/student user.
     *
     * @return BelongsToMany<Company, $this>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)->withTimestamps();
    }

    /**
     * @return HasMany<CompanyRequest, $this>
     */
    public function companyRequests(): HasMany
    {
        return $this->hasMany(CompanyRequest::class);
    }

    /**
     * @return HasMany<Section, $this>
     */
    public function coordinatedSections(): HasMany
    {
        return $this->hasMany(Section::class, 'coordinator_user_id');
    }

    public function activeCoordinatorSection(): ?Section
    {
        return $this->coordinatedSections()
            ->where('is_active', true)
            ->whereHas('schoolYear', fn ($query) => $query->where('is_active', true))
            ->with('course')
            ->first();
    }

    public function coordinatorCourse(): ?Course
    {
        return $this->activeCoordinatorSection()?->course;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'document_submission_alerts_seen_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
