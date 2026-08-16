<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentFaceProfile extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'student_id',
        'reference_image_path',
        'face_embedding',
        'enrolled_at',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'face_embedding' => 'array',
            'enrolled_at' => 'datetime',
            'is_active' => 'boolean',
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
