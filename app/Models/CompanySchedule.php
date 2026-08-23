<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySchedule extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'start_date',
        'time_in',
        'lunch_break',
        'time_out',
        'supervisor_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
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
     * Supervisor who created/manages this schedule.
     *
     * @return BelongsTo<Supervisor, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Supervisor::class, 'supervisor_id');
    }
}
