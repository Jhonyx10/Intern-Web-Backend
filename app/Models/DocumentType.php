<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'is_required',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
        ];
    }

    /**
     * @return HasMany<StudentDocument, $this>
     */
    public function studentDocuments(): HasMany
    {
        return $this->hasMany(StudentDocument::class);
    }
}
