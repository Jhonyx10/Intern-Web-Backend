<?php

namespace App\Http\Requests;

use App\Models\Evaluation;
use Illuminate\Foundation\Http\FormRequest;

class SubmitEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $evaluation = $this->route('evaluation');

        // Resolve string/int model binding if route parameter wasn't auto-bound
        if (!$evaluation instanceof Evaluation) {
            $evaluation = Evaluation::with('template.items')->findOrFail($evaluation);
        } else {
            $evaluation->loadMissing('template.items');
        }

        $rules = [
            'responses' => ['required', 'array'],
        ];

        foreach ($evaluation->template->items as $item) {
            $fieldKey = "responses.{$item->id}";
            $fieldRules = [];

            // Requirement rule
            $fieldRules[] = $item->is_required ? 'required' : 'nullable';

            // Type-specific rules
            switch ($item->item_type) {
                case 'rating':
                    $min = $item->options['min'] ?? 1;
                    $max = $item->options['max'] ?? 5;
                    $fieldRules[] = 'integer';
                    $fieldRules[] = "between:{$min},{$max}";
                    break;

                case 'single_choice':
                    $allowedChoices = $item->options['choices'] ?? [];
                    $fieldRules[] = 'string';
                    if (!empty($allowedChoices)) {
                        $fieldRules[] = 'in:' . implode(',', $allowedChoices);
                    }
                    break;

                case 'multiple_choice':
                    $fieldRules[] = 'array';
                    break;

                case 'text':
                case 'textarea':
                    $fieldRules[] = 'string';
                    break;
            }

            $rules[$fieldKey] = $fieldRules;
        }

        return $rules;
    }
}