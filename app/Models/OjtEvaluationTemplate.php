<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OjtEvaluationTemplate extends Model
{
    public const ITEM_TYPE_RATING = 'rating_question';

    public const ITEM_TYPE_TEXT = 'text_area';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'section_id',
        'created_by_user_id',
        'name',
        'description',
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
     * @return HasMany<OjtEvaluationTemplateItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OjtEvaluationTemplateItem::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return HasMany<OjtEvaluation, $this>
     */
    public function evaluations(): HasMany
    {
        return $this->hasMany(OjtEvaluation::class, 'evaluation_template_id');
    }

    public function hasBeenUsed(): bool
    {
        return $this->evaluations()->exists();
    }
}
