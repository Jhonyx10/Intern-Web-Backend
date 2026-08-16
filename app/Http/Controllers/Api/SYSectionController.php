<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Section;
use App\Models\SchoolYear;

class SYSectionController extends Controller
{
    // ── School Years ──────────────────────────────────────────────────

    public function indexSchoolYears()
    {
        $schoolYears = SchoolYear::with(['sections.course', 'sections.courseMajor'])
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
                    'sections'   => $sy->sections->map(fn ($s) => [
                        'id'   => $s->id,
                        'name' => $s->name,
                        'code' => $s->code,
                    ]),
                ];
            });

        return response()->json($schoolYears);
    }

    public function storeSchoolYear(Request $request)
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active'  => ['sometimes', 'boolean'],
        ]);

        // If the new SY is active, deactivate all others for the same course scope
        if (!empty($validated['is_active'])) {
            SchoolYear::where('is_active', true)->update(['is_active' => false]);
        }

        $schoolYear = SchoolYear::create($validated);

        return response()->json($schoolYear, 201);
    }

    public function updateSchoolYear(Request $request, SchoolYear $schoolYear)
    {
        $validated = $request->validate([
            'name'       => ['sometimes', 'required', 'string', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active'  => ['sometimes', 'boolean'],
        ]);

        if (!empty($validated['is_active'])) {
            SchoolYear::where('id', '!=', $schoolYear->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        $schoolYear->update($validated);

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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:50'],
            'course_id'           => ['required', 'integer', 'exists:courses,id'],
            'course_major_id'     => ['nullable', 'integer', 'exists:course_majors,id'],
            'coordinator_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

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
        return Section::with(['students', 'course', 'courseMajor', 'coordinator', 'schoolYear'])->findOrFail($id);
    }
}
