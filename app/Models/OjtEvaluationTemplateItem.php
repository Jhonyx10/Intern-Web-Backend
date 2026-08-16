<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OjtEvaluationTemplateItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'ojt_evaluation_template_id',
        'sort_order',
        'item_type',
        'label',
        'is_required',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_required' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<OjtEvaluationTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(OjtEvaluationTemplate::class, 'ojt_evaluation_template_id');
    }
}
