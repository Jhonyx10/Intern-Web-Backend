<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvaluationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'status' => $this->status,
            'computed_score' => $this->computed_score,
            'submitted_at' => $this->submitted_at,
            'responses' => $this->responses,
            'created_at' => $this->created_at,
            'template' => [
                'id' => $this->template->id,
                'title' => $this->template->title,
                'description' => $this->template->description,
                'items' => $this->whenLoaded('template', fn () =>
                    $this->template->items->map(fn ($item) => [
                        'id' => $item->id,
                        'sort_order' => $item->sort_order,
                        'item_type' => $item->item_type,
                        'label' => $item->label,
                        'options' => $item->options,
                        'is_required' => $item->is_required,
                    ])
                ),
            ],
        ];
    }
}