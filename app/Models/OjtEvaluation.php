<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OjtEvaluation extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'student_id',
        'supervisor_id',
        'opened_by_user_id',
        'evaluation_template_id',
        'status',
        'rating',
        'comments',
        'responses',
        'evaluation_date',
        'opened_at',
        'submitted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'responses' => 'array',
            'evaluation_date' => 'date',
            'opened_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OjtEvaluationTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(OjtEvaluationTemplate::class, 'evaluation_template_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Supervisor, $this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Supervisor::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }
}
