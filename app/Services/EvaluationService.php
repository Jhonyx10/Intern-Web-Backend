<?php

namespace App\Services;

use App\Models\EvaluationTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EvaluationService
{
    /**
     * Create a generic questionnaire and attach it to one or multiple courses.
     */
    public function createTemplate(array $data): EvaluationTemplate
    {
        return DB::transaction(function () use ($data) {
            // 1. Create the general evaluation questionnaire
            $template = EvaluationTemplate::create([
                'created_by_user_id' => $data['created_by_user_id'] ?? auth()->id(),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            // 2. Attach dynamic questionnaire fields
            if (!empty($data['items']) && is_array($data['items'])) {
                $itemsToInsert = array_map(function ($item, $index) {
                    return [
                        'sort_order' => $index + 1,
                        'item_type' => $item['item_type'],
                        'label' => $item['label'],
                        'options' => isset($item['options']) ? (is_array($item['options']) ? json_encode($item['options']) : $item['options']) : null,
                        'is_required' => $item['is_required'] ?? true,
                    ];
                }, $data['items'], array_keys($data['items']));

                $template->items()->createMany($itemsToInsert);
            }

            // 3. Attach to 1 or many courses via junction table
            if (!empty($data['course_ids']) && is_array($data['course_ids'])) {
                $template->courses()->sync($data['course_ids']);
            }

            return $template->load(['items', 'courses']);
        });
    }
}