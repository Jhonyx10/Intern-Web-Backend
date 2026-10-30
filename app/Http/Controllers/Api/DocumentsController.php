<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\StudentDocument;
use App\Services\DocumentService;
use Illuminate\Validation\Rule;

class DocumentsController extends Controller
{
     protected $documentService;

    public function __construct(DocumentService $documentService)
    {
        $this->documentService = $documentService;
    }

    public function fetchStudentDocuments($id)
    {
        $documents = StudentDocument::with(['student.section.course', 'student.section.schoolYear', 'documentRequirement', 'reviewedBy'])
                                    ->where('student_id', $id)
                                    ->get();

        return response()->json([
            'message' => 'success',
            $documents
            ]);
    }

    public function fetchSubmittedDocuments(Request $request)
    {
        $user = $request->user();

        $query = StudentDocument::with([
            'student.section.course',
            'student.section.schoolYear',
            'student.user',
            'documentRequirement',
            'reviewedBy'
        ]);

        if ($user->hasRole('super_admin') || $user->hasRole('admin')) {
            if ($request->filled('course_id')) {
                $courseId = $request->integer('course_id');
                $query->whereHas('student.section', function ($q) use ($courseId) {
                    $q->where('course_id', $courseId);
                });
            }
        } else {
            $courseId = $user->deanPortalCourse()?->id
                ?? $user->coordinatorCourse()?->id
                ?? $user->course_id;

            if ($courseId) {
                $query->whereHas('student.section', function ($q) use ($courseId) {
                    $q->where('course_id', $courseId);
                });
            }
        }

        // Filter by school year: specific ID, "all", or default to active school year
        if ($request->filled('school_year_id')) {
            $syId = $request->input('school_year_id');
            if ($syId !== 'all') {
                $query->whereHas('student.section', function ($q) use ($syId) {
                    $q->where('school_year_id', (int) $syId);
                });
            }
        } elseif (!$request->boolean('all_school_years')) {
            $activeSY = \App\Models\SchoolYear::where('is_active', true)->first();
            if ($activeSY) {
                $query->whereHas('student.section', function ($q) use ($activeSY) {
                    $q->where('school_year_id', $activeSY->id);
                });
            }
        }

        if ($request->filled('search')) {
            $search = trim($request->string('search'));
            $query->where(function ($q) use ($search) {
                $q->whereHas('student', function ($sq) use ($search) {
                    $sq->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('student_number', 'like', "%{$search}%");
                })
                ->orWhereHas('documentRequirement', function ($rq) use ($search) {
                    $rq->where('title', 'like', "%{$search}%");
                })
                ->orWhereHas('student.section.course', function ($cq) use ($search) {
                    $cq->where('name', 'like', "%{$search}%")
                       ->orWhere('code', 'like', "%{$search}%");
                })
                ->orWhere('original_filename', 'like', "%{$search}%");
            });
        }

        $documents = $query->latest('uploaded_at')->latest('id')->get();

        return response()->json($documents);
    }

    public function webViewDocument($id)
    {
        $document = StudentDocument::findOrFail($id);

        if (!$document->file_path) {
            abort(404, 'Document path not set.');
        }

        $fullPath = storage_path('app/public/' . ltrim($document->file_path, '/'));
        if (!file_exists($fullPath)) {
            $fullPath = storage_path('app/' . ltrim($document->file_path, '/'));
        }

        if (!file_exists($fullPath)) {
            abort(404, 'File physical asset missing.');
        }

        return response()->file($fullPath, [
            'Content-Type' => $document->mime_type ?: 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . ($document->original_filename ?: 'document.pdf') . '"',
        ]);
    }

    // GET /document-requirements
    public function index(Request $request)
    {
        $documents = $this->documentService->listRequirements();

        return response()->json($documents);
    }

    public function storeRequirement(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'accepted_file_types' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'recurrence' => ['nullable', Rule::in(['none', 'daily', 'weekly'])],
            'document_type' => ['nullable', Rule::in(['common', 'departmental'])],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
            'deadline_at' => ['nullable', 'date'],
        ]);

        $validated['created_by_user_id'] = $request->user()->id;

        $requirement = $this->documentService->createRequirement($validated);

        if (!empty($validated['course_ids'])) {
            $pivotData = [];
            foreach ($validated['course_ids'] as $courseId) {
                $pivotData[$courseId] = ['deadline_at' => $validated['deadline_at'] ?? null];
            }
            $requirement->courses()->sync($pivotData);
        }

        return response()->json($requirement, 201);
    }

    // GET /courses/{course}/document-requirements
    public function courseRequirements(int $course)
    {
        $requirements = $this->documentService->listCourseRequirements($course);

        return response()->json($requirements);
    }

    // PUT /courses/{course}/document-requirements
    public function syncCourseRequirements(Request $request, int $course)
    {
        $validated = $request->validate([
            'document_requirement_ids' => ['present', 'array'],
            'document_requirement_ids.*' => ['integer'],
            'deadline_at' => ['nullable', 'date'],
        ]);

        $requirements = $this->documentService->syncCourseRequirements(
            $course,
            $validated['document_requirement_ids'],
            $validated['deadline_at'] ?? null
        );

        return response()->json($requirements);
    }

    public function updateStatus(Request $request, int $document)
    {
        $validated = $request->validate([
            'review_status' => ['required', Rule::in(['approved', 'rejected', 'pending'])],
            'rejection_reason' => ['required_if:review_status,rejected', 'nullable', 'string', 'max:1000'],
        ]);

        $validated['reviewed_by_user_id'] = $request->user()->id;

        $updated = $this->documentService->updateStudentDocumentStatus($validated, $document);

        return response()->json($updated);
    }
}
