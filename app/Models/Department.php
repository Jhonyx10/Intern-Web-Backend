<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Department extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return HasMany<Supervisor, $this>
     */
    public function supervisors(): HasMany
    {
        return $this->hasMany(Supervisor::class);
    }

    /**
     * @return HasOne<Supervisor, $this>
     */
    public function headSupervisor(): HasOne
    {
        return $this->hasOne(Supervisor::class)
            ->where('is_department_head', true)
            ->where('is_active', true);
    }

    /**
     * @return HasMany<Supervisor, $this>
     */
    public function activeSupervisors(): HasMany
    {
        return $this->hasMany(Supervisor::class)
            ->where('is_active', true)
            ->orderByDesc('is_department_head')
            ->orderBy('id');
    }

    /**
     * @return HasMany<Student, $this>
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
