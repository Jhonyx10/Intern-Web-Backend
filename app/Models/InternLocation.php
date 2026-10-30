<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternLocation extends Model
{
    protected $fillable = [
        'student_id', 'user_id', 'company_id',
        'latitude', 'longitude', 'accuracy_meters', 'recorded_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy_meters' => 'float',
        'recorded_at' => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}