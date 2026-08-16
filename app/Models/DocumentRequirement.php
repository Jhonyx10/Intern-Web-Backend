<?php

namespace App\Models;

use App\Support\DocumentRequirementFileType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentRequirement extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'section_id',
        'created_by_user_id',
        'title',
        'description',
        'deadline_at',
        'accepted_file_types',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deadline_at' => 'datetime',
            'accepted_file_types' => DocumentRequirementFileType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<StudentDocument, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(StudentDocument::class);
    }
}
