<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Services\StudentEnrollmentService;
use App\Exports\StudentsImportTemplateExport;
use App\Imports\StudentsImport;
use Maatwebsite\Excel\Facades\Excel;

class StudentController extends Controller
{

    public function __construct(
        private readonly StudentEnrollmentService $enrollmentService
    ) {}

    /**
     * Display a listing of the students.
     */
    public function index(Request $request): JsonResponse
    {
        $unassigned = $request->boolean('unassigned');
        $defaultPerPage = $unassigned ? 50 : 5;
        $perPage = min(100, max(1, (int) $request->input('per_page', $defaultPerPage)));

        $query = Student::with(['section']);

        if ($unassigned) {
            $query->whereDoesntHave('companies', function ($q) {
                $q->where('company_student.status', 'active');
            });
        }

        $students = $query->paginate($perPage);

        return response()->json(['data' => $students]);
    }

    /**
     * Store a newly created student.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_number' => ['required', 'string', 'unique:students,student_number'],
            'first_name'     => ['required', 'string'],
            'middle_name'    => ['nullable', 'string'],
            'last_name'      => ['required', 'string'],
            'section_id'     => ['required', 'integer', Rule::exists(Section::class, 'id')],
            'is_active'      => ['required', 'boolean'],
        ]);

        $student = $this->enrollmentService->create($validated);

        return response()->json(['data' => $student], 201);

    }

    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                function ($attribute, $value, $fail) {
                    $extension = strtolower($value->getClientOriginalExtension());
                    if (! in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
                        $fail('The file field must be a file of type: xlsx, xls, csv.');
                    }
                },
            ],
            'section_id' => ['required', 'integer', Rule::exists(Section::class, 'id')],
        ]);

        $import = new StudentsImport($validated['section_id'], $this->enrollmentService);
        Excel::import($import, $request->file('file'));

        $failures = collect($import->failures())->map(fn ($failure) => [
            'row'       => $failure->row(),
            'attribute' => $failure->attribute(),
            'errors'    => $failure->errors(),
        ]);

        return response()->json([
            'imported' => $import->createdCount(),
            'failures' => $failures,
        ]);
    }
    public function downloadImportTemplate()
    {
        return Excel::download(new StudentsImportTemplateExport(), 'students-import-template.xlsx');
    }
    /**
     * Display the specified student.
     */
    public function show(int $id): JsonResponse
    {
        $student = Student::with([
            'section.course',
            'section.courseMajor',
            'section.coordinator',
            'companies.schedules.creator',
            'ojtSchedule',
            'timeLogs.taskPhotos',
            'documents.documentType',
            'documents.documentRequirement',
            'geofenceExcursions.points',
            'ojtEvaluations.template.items',
            'documents.reviewedBy'
        ])->findOrFail($id);

        return response()->json(['data' => $student]);
    }

    /**
     * Update the specified student.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $student = Student::findOrFail($id);
        $validated = $request->validate([
            'student_number' => ['sometimes', 'string', Rule::unique(Student::class)->ignore($student->id)],
            'first_name' => ['sometimes', 'string'],
            'middle_name' => ['nullable', 'string'],
            'last_name' => ['sometimes', 'string'],
            'section_id' => ['sometimes', 'integer', Rule::exists(Section::class, 'id')],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $student->update($validated);
        $student->load(['section', 'companies']);
        return response()->json(['data' => $student]);
    }

    /**
     * Remove the specified student.
     */
    public function destroy(int $id): JsonResponse
    {
        $student = Student::findOrFail($id);
        $student->delete();
        return response()->json(null, 204);
    }

}