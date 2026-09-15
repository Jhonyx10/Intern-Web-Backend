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
        $documents = StudentDocument::with(['documentType','documentRequirement','reviewedBy'])
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
            'student.user',
            'documentType',
            'documentRequirement.documentType',
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
                ->orWhereHas('documentRequirement.documentType', function ($tq) use ($search) {
                    $tq->where('name', 'like', "%{$search}%");
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
        $documents = $this->documentService->listRequirements(
            $request->integer('document_type_id') ?: null
        );

        return response()->json($documents);
    }

    // POST /document-requirements
    public function storeRequirement(Request $request)
    {
        $validated = $request->validate([
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'deadline_at' => ['required', 'date'],
            'accepted_file_types' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['created_by_user_id'] = $request->user()->id;

        $requirement = $this->documentService->createRequirement($validated);

        return response()->json($requirement, 201);
    }

    // GET /document-types
    public function indexTypes()
    {
        return response()->json(
            \App\Models\DocumentType::query()->get()
        );
    }

    // POST /document-types
    public function storeDocumentType(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:document_types,code'],
            'name' => ['required', 'string', 'max:255'],
            'is_required' => ['required', 'boolean'],
        ]);

        $documentType = $this->documentService->createDocumentType($validated);

        return response()->json($documentType, 201);
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
            'deadline_at' => ['required', 'date'],
        ]);

        $requirements = $this->documentService->syncCourseRequirements(
            $course,
            $validated['document_requirement_ids'],
            $validated['deadline_at']
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
