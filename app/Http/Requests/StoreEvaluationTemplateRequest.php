<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEvaluationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],

            // 1-to-Many course assignments via junction table
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['integer', 'exists:courses,id'],

            // Dynamic items validation
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_type' => ['required', 'string', 'in:rating,text,textarea,single_choice,multiple_choice'],
            'items.*.label' => ['required', 'string', 'max:255'],
            'items.*.is_required' => ['boolean'],
            'items.*.options' => ['nullable', 'array'],
        ];
    }
}