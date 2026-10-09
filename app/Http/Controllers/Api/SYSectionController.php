<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\User;
use App\Support\DeanPortalScope;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SYSectionController extends Controller
{
    // ── School Years ──────────────────────────────────────────────────

    public function indexSchoolYears()
    {
        $schoolYears = SchoolYear::with([
                'sections.course',
                'sections.courseMajor',
                'sections.coordinator',
                'sections.students',
                'evaluationTemplates:id,title'
            ])
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->get()
            ->map(function ($sy) {
                return [
                    'id'         => $sy->id,
                    'name'       => $sy->name,
                    'start_date' => $sy->start_date?->toDateString(),
                    'end_date'   => $sy->end_date?->toDateString(),
                    'is_active'  => $sy->is_active,
                    'is_evaluation_enabled' => $sy->is_evaluation_enabled,
                    'sections'   => $sy->sections->map(fn ($s) => [
                        'id'           => $s->id,
                        'name'         => $s->name,
                        'code'         => $s->code,
                        'course_major' => $s->courseMajor
                            ? ['id' => $s->courseMajor->id, 'name' => $s->courseMajor->name]
                            : null,
                        'coordinator'  => $s->coordinator
                            ? ['id' => $s->coordinator->id, 'name' => $s->coordinator->name]
                            : null,
                        'students_count' => $s->students->count(),
                    ]),
                    'evaluation_templates' => $sy->evaluationTemplates->map(fn ($t) => [
                        'id' => $t->id,
                        'title' => $t->title,
                    ]),
                ];
            });

        return response()->json($schoolYears);
    }

    public function currentSchoolYear(): JsonResponse
    {
        $schoolYear = SchoolYear::with([
                'sections.course',
                'sections.courseMajor',
                'sections.coordinator',
                'sections.students',
                'evaluationTemplates:id,title'
            ])
            ->where('is_active', true)
            ->first();

        if (! $schoolYear) {
            return response()->json(['message' => 'No active school year found.'], 404);
        }

        return response()->json([
            'id'         => $schoolYear->id,
            'name'       => $schoolYear->name,
            'semester'   => $schoolYear->semester,
            'start_date' => $schoolYear->start_date?->toDateString(),
            'end_date'   => $schoolYear->end_date?->toDateString(),
            'is_active'  => $schoolYear->is_active,
            'is_evaluation_enabled' => $schoolYear->is_evaluation_enabled,
            'sections'   => $schoolYear->sections->map(fn ($s) => [
                'id'             => $s->id,
                'name'           => $s->name,
                'code'           => $s->code,
                'course_id'      => $s->course_id,
                'course'         => $s->course ? ['id' => $s->course->id, 'name' => $s->course->name, 'code' => $s->course->code] : null,
                'course_major'   => $s->courseMajor ? ['id' => $s->courseMajor->id, 'name' => $s->courseMajor->name] : null,
                'coordinator'    => $s->coordinator ? ['id' => $s->coordinator->id, 'name' => $s->coordinator->name] : null,
                'students_count' => $s->students->count(),
            ]),
            'evaluation_templates' => $schoolYear->evaluationTemplates->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
            ]),
        ]);
    }


    public function storeSchoolYear(Request $request)
    {
        $this->ensureDeanHasCourse($request->user());

        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active'  => ['sometimes', 'boolean'],
            'is_evaluation_enabled' => ['sometimes', 'boolean'],
            'evaluation_template_ids' => ['nullable', 'array'],
            'evaluation_template_ids.*' => ['integer', 'exists:evaluation_templates,id'],
        ]);

        // If the new SY is active, deactivate all others for the same course scope
        if (!empty($validated['is_active'])) {
            SchoolYear::where('is_active', true)->update(['is_active' => false]);
        }

        $schoolYear = SchoolYear::create($validated);

        if (isset($validated['evaluation_template_ids'])) {
            $schoolYear->evaluationTemplates()->sync($validated['evaluation_template_ids']);
        }

        $schoolYear->load('evaluationTemplates:id,title');

        return response()->json($schoolYear, 201);
    }

    public function updateSchoolYear(Request $request, SchoolYear $schoolYear)
    {
        $validated = $request->validate([
            'name'       => ['sometimes', 'required', 'string', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active'  => ['sometimes', 'boolean'],
            'is_evaluation_enabled' => ['sometimes', 'boolean'],
            'evaluation_template_ids' => ['nullable', 'array'],
            'evaluation_template_ids.*' => ['integer', 'exists:evaluation_templates,id'],
        ]);

        if (!empty($validated['is_active'])) {
            SchoolYear::where('id', '!=', $schoolYear->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        $schoolYear->update($validated);

        if (array_key_exists('evaluation_template_ids', $validated)) {
            $schoolYear->evaluationTemplates()->sync($validated['evaluation_template_ids'] ?? []);
        }

        $schoolYear->load('evaluationTemplates:id,title');

        return response()->json($schoolYear);
    }

    public function destroySchoolYear(SchoolYear $schoolYear)
    {
        if ($schoolYear->sections()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a school year that still has sections. Remove all sections first.',
            ], 422);
        }

        $schoolYear->delete();

        return response()->json(['message' => 'School year deleted.']);
    }

    // ── Sections ──────────────────────────────────────────────────────

    public function indexSections(SchoolYear $schoolYear)
    {
        $sections = $schoolYear->sections()
            ->with(['course', 'courseMajor', 'coordinator'])
            ->get();

        return response()->json($sections);
    }

    public function storeSection(Request $request, SchoolYear $schoolYear)
    {
        $this->ensureDeanHasCourse($request->user());

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:50'],
            'course_id'           => ['required', 'integer', 'exists:courses,id'],
            'course_major_id'     => ['nullable', 'integer', 'exists:course_majors,id'],
            'coordinator_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $user = $request->user();
        $deanCourse = $user && DeanPortalScope::isPortalUser($user)
            ? DeanPortalScope::course($user)
            : null;

        if ($deanCourse !== null) {
            $validated['course_id'] = $deanCourse->id;

            if (
                $schoolYear->course_id !== null
                && (int) $schoolYear->course_id !== (int) $deanCourse->id
            ) {
                abort(404);
            }
        }

        if (! empty($validated['coordinator_user_id']) && $user?->hasRole('dean')) {
            $ownsCoordinator = User::query()
                ->whereKey($validated['coordinator_user_id'])
                ->where('created_by', $user->id)
                ->exists();

            if (! $ownsCoordinator) {
                throw ValidationException::withMessages([
                    'coordinator_user_id' => ['You can only assign coordinators you created.'],
                ]);
            }
        }

        $validated['school_year_id'] = $schoolYear->id;

        $section = Section::create($validated);

        return response()->json($section, 201);
    }

    public function updateSection(Request $request, SchoolYear $schoolYear, Section $section)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:50'],
        ]);

        $section->update($validated);

        return response()->json($section);
    }

    public function destroySection(SchoolYear $schoolYear, Section $section)
    {
        if ($section->students()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a section that still has students.',
            ], 422);
        }

        $section->delete();

        return response()->json(['message' => 'Section deleted.']);
    }

    public function sectionDetails($id)
    {
        return Section::with(['students.companies', 'course', 'courseMajor', 'coordinator', 'schoolYear'])->findOrFail($id);
    }

    private function ensureDeanHasCourse(?User $user): void
    {
        if ($user === null || ! $user->hasRole('dean')) {
            return;
        }

        if (DeanPortalScope::course($user) === null) {
            throw ValidationException::withMessages([
                'course_id' => ['You must be assigned to a department before managing years and sections.'],
            ]);
        }
    }

    public function coordinatorSections(Request $request): JsonResponse
    {
        $user = $request->user();

        // Fetch all sections where coordinator_user_id matches the logged-in user
        $sections = Section::where('coordinator_user_id', $user->id)
            ->with(['course', 'courseMajor', 'schoolYear', 'students.companies'])
            ->get();

        return response()->json($sections);
    }
}
