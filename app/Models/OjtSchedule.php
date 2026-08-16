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
        'student_id',
        'hours_per_day',
        'days_per_week',
        'start_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hours_per_day' => 'decimal:2',
            'start_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
