<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\BelongsToCourse;

class Company extends Model
{
    use BelongsToCourse;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'course_id',
        'company_request_id',
        'name',
        'address',
        'latitude',
        'longitude',
        'geofence_radius_meters',
        'geofence_enabled',
        'geofence_polygon',
        'contact_person',
        'contact_email',
        'contact_phone',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'geofence_radius_meters' => 'integer',
            'geofence_enabled' => 'boolean',
            'geofence_polygon' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<CompanyRequest, $this>
     */
    public function companyRequest(): BelongsTo
    {
        return $this->belongsTo(CompanyRequest::class);
    }

    /**
     * Intern/student users assigned to this company.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * @return HasMany<Department, $this>
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /**
     * Students assigned to this company.
     *
     * @return BelongsToMany<Student, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)->withPivot(['supervisor_id', 'course_id'])->withTimestamps();
    }

    /**
     * @return HasMany<Supervisor, $this>
     */
    public function supervisors(): HasMany
    {
        return $this->hasMany(Supervisor::class);
    }
}
