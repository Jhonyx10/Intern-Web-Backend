<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OjtSchedule extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_student_id',
        'hours_per_day',
        'days_per_week',
        'start_date',
        'time_in',
        'time_out',
        'status',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hours_per_day' => 'decimal:2',
            'start_date'    => 'date',
        ];
    }

    /**
     * The company_student pivot row this schedule belongs to.
     */
    public function companyStudent(): BelongsTo
    {
        // company_student is a plain pivot table; we access it via DB or define a model if needed.
        // For a direct relationship we assume a CompanyStudent model exists or use a raw FK.
        return $this->belongsTo(\App\Models\CompanyStudent::class, 'company_student_id');
    }
}
