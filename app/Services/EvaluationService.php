<?php

namespace App\Services;

use App\Models\Evaluation;
use App\Models\EvaluationTemplate;
use App\Models\Student;
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

    /**
     * Bulk assign an evaluation template to all eligible active students in a course
     * who are placed in a company and linked with a supervisor.
     */
    public function bulkAssign(int $templateId, int $courseId): int
    {
        // Fetch students who:
        // 1. Belong to a section within the target course
        // 2. Are actively assigned to a company for this specific course via pivot
        $students = Student::whereHas('section', function ($query) use ($courseId) {
                $query->where('course_id', $courseId);
            })
            ->whereHas('companies', function ($query) use ($courseId) {
                $query->where('company_student.course_id', $courseId);
            })
            ->with(['companies' => function ($query) use ($courseId) {
                $query->where('company_student.course_id', $courseId);
            }])
            ->get();

        if ($students->isEmpty()) {
            return 0;
        }

        $assignedCount = 0;

        DB::transaction(function () use ($students, $templateId, $courseId, &$assignedCount) {
            foreach ($students as $student) {
                // Extract the supervisor assigned to this student in the company pivot table
                $pivotData = $student->companies->first()?->pivot;
                $supervisorId = $pivotData?->supervisor_id ?? null;

                // Safely create record (prevents duplicates if run multiple times)
                $evaluation = Evaluation::firstOrCreate(
                    [
                        'evaluation_template_id' => $templateId,
                        'student_id' => $student->id,
                        'course_id' => $courseId,
                    ],
                    [
                        'evaluator_id' => $supervisorId,
                        'responses' => [],
                        'status' => 'pending',
                    ]
                );

                if ($evaluation->wasRecentlyCreated) {
                    $assignedCount++;
                }
            }
        });

        Log::info("Bulk assigned evaluation template #{$templateId} to {$assignedCount} students in course #{$courseId}.");

        return $assignedCount;
    }
}