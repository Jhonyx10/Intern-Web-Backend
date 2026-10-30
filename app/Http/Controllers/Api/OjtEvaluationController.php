<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\Student;
use App\Models\EvaluationTemplate;
use App\Http\Requests\SubmitEvaluationRequest;
use App\Http\Requests\StoreEvaluationTemplateRequest;
use App\Services\EvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Throwable;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Kreait\Laravel\Firebase\Facades\Firebase;
use App\Http\Resources\EvaluationResource;

class OjtEvaluationController extends Controller
{
    public function __construct(
        protected EvaluationService $evaluationService
    ) {}


    public function index()
    {
        $user = auth()->user();
        $isSuperAdmin = $user && $user->role && in_array($user->role->name, ['superadmin', 'super_admin']);

        $query = EvaluationTemplate::with([
            'creator:id,name',
            'courses:id,code,name',
            'evaluations:id,evaluation_template_id',
            'schoolYears:id,name'
        ])
        ->withCount('items');

        if (!$isSuperAdmin) {
            $query->whereHas('schoolYears', function ($q) {
                $q->where('is_active', true);
            });
        }

        $templates = $query->latest()->get();

        $templates->transform(function ($t) use ($user) {
            $scopedStudentIds = \App\Support\DeanPortalScope::isPortalUser($user)
                ? \App\Support\DeanPortalScope::studentsQuery($user)->pluck('id')
                : null;

            $t->submitted_count = $t->evaluations()
                ->where('status', 'submitted')
                ->when($scopedStudentIds, fn($q) => $q->whereIn('student_id', $scopedStudentIds))
                ->count();

            // Created school year (oldest attached school year or current active fallback)
            $oldestSy = $t->schoolYears->sortBy('id')->first();
            $t->created_school_year = $oldestSy ? $oldestSy->name : 'N/A';

            // Total count of school years this template has been anchored to
            $t->anchored_school_years_count = $t->schoolYears->count();

            return $t;
        });

        return response()->json($templates);
    }

   public function store(StoreEvaluationTemplateRequest $request)
    {
        // Retrieve the fully validated data from your FormRequest
        $validated = $request->validated();

        $validated['created_by_user_id'] = $request->user()->id;
        
        // Use your EvaluationService which handles the transaction, items, and course sync correctly
        $template = $this->evaluationService->createTemplate($validated);

        return response()->json($template->load(['courses:id,code,name', 'items']), 201);
    }

    public function update(StoreEvaluationTemplateRequest $request, $id)
    {
        $validated = $request->validated();
        $template = EvaluationTemplate::findOrFail($id);

        \Illuminate\Support\Facades\DB::transaction(function () use ($template, $validated) {
            $template->update([
                'title'       => $validated['title'],
                'description' => $validated['description'] ?? null,
                'is_active'   => $validated['is_active'],
            ]);

            if (isset($validated['course_ids'])) {
                $template->courses()->sync($validated['course_ids']);
            }

            if (isset($validated['items'])) {
                // Remove out-dated items
                $template->items()->delete();
                
                // create or re-create items
                foreach ($validated['items'] as $itemData) {
                    $template->items()->create($itemData);
                }
            }
        });

        return response()->json($template->load(['courses:id,code,name', 'items']));
    }

   public function bulkAssign(Request $request)
    {
        $request->validate([
            'template_id' => 'required|integer',
            'course_id' => 'required|integer',
        ]);

        $activeSchoolYear = \App\Models\SchoolYear::where('is_active', true)->first();
        if ($activeSchoolYear && !$activeSchoolYear->is_evaluation_enabled) {
            return response()->json([
                'message' => 'Evaluations are currently disabled for the active school year.',
            ], 403);
        }

        $templateId = $request->input('template_id');
        $courseId = $request->input('course_id');

        // Call the service method with the 2 expected arguments
        $assignedCount = $this->evaluationService->bulkAssign($templateId, $courseId);

        return response()->json([
            'message' => "Successfully assigned evaluation to {$assignedCount} students.",
            'assigned_count' => $assignedCount,
        ]);
    }
    
    public function submit(SubmitEvaluationRequest $request, Evaluation $evaluation): JsonResponse
    {
        $validated = $request->validated();
        $responses = $validated['responses'];

        $evaluation->loadMissing('template.items');

        $computedScore = $this->calculateScore($evaluation, $responses);

        $evaluation->update([
            'responses' => $responses,
            'computed_score' => $computedScore,
            'status' => 'submitted',
            'submitted_at' => Carbon::now(),
        ]);

        Log::info('Evaluation submitted successfully', [
            'evaluation_id' => $evaluation->id,
            'student_id' => $evaluation->student_id,
            'course_id' => $evaluation->course_id,
            'computed_score' => $computedScore,
        ]);

        // Notify the intern that their evaluation has been filled up
        $this->sendSubmissionNotification($evaluation);

        return response()->json([
            'message' => 'Evaluation submitted successfully.',
            'evaluation' => $evaluation,
        ]);
    }

    public function unreadNotifications(Request $request): JsonResponse
    {
        return response()->json($request->user()->unreadNotifications);
    }

    public function markNotificationsAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'Notifications marked as read.']);
    }

    private function sendSubmissionNotification(Evaluation $evaluation): void
    {
        $evaluation->loadMissing('student.user');

        $notifTitle = 'Evaluation Completed';
        $notifBody = 'Your OJT evaluation has been filled up by your supervisor. You can now view your results.';

        if ($evaluation->student?->user) {
            $evaluation->student->user->notifications()->create([
                'id' => \Illuminate\Support\Str::uuid(),
                'type' => 'App\Notifications\EvaluationCompletedNotification',
                'data' => [
                    'title' => $notifTitle,
                    'body'  => $notifBody,
                ],
                'read_at' => null,
            ]);
        }

        $fcmToken = $evaluation->student?->user?->fcm_token;

        if (! $fcmToken) {
            return;
        }

        try {
            $message = CloudMessage::new()
                ->withToken($fcmToken)
                ->withNotification(FirebaseNotification::create(
                    $notifTitle,
                    $notifBody
                ));

            Firebase::messaging()->send($message);
        } catch (\Exception $e) {
            Log::error('Failed to send evaluation submission FCM notification: ' . $e->getMessage());
        }
    }

    private function calculateScore(Evaluation $evaluation, array $responses): ?float
    {
        $ratingItems = $evaluation->template->items->where('item_type', 'rating');

        if ($ratingItems->isEmpty()) {
            return null;
        }

        $totalEarned = 0;
        $totalPossible = 0;

        foreach ($ratingItems as $item) {
            if (isset($responses[$item->id])) {
                $max = $item->options['max'] ?? 5;
                $totalEarned += (int) $responses[$item->id];
                $totalPossible += (int) $max;
            }
        }

        return $totalPossible > 0 ? round(($totalEarned / $totalPossible) * 100, 2) : null;
    }

    public function showEvaluation($id)
    {
        $evaluation = EvaluationTemplate::with([
            'items', 
            'creator', 
            'evaluations:id,evaluation_template_id',
            'schoolYears:id,name'
        ])->findOrFail($id);

        return response()->json($evaluation);
    }

    /**
     * Return all evaluations submitted by supervisors for a given template.
     * Used by the EvaluationSubmissionsPage on the admin/dean side.
     */
    public function templateSubmissions(Request $request, $id): JsonResponse
    {
        // Verify the template exists
        EvaluationTemplate::findOrFail($id);

        $user = $request->user();

        $query = Evaluation::with([
                'template.items',
                'student:id,student_number,first_name,middle_name,last_name,section_id',
                'student.section:id,name,course_id',
                'student.section.course:id,code',
                'student.section.schoolYear:id,name',
                'supervisor:id,user_id,company_id',
                'supervisor.user:id,name',
                'supervisor.company:id,name',
            ])
            ->where('evaluation_template_id', $id)
            ->where('status', 'submitted');

        if (\App\Support\DeanPortalScope::isPortalUser($user)) {
            $scopedStudentIds = \App\Support\DeanPortalScope::studentsQuery($user)->pluck('id');
            $query->whereIn('student_id', $scopedStudentIds);
        }

        $evaluations = $query->latest('submitted_at')->get();

        $result = $evaluations->map(function (Evaluation $ev) {
            $student = $ev->student;
            $section = $student?->section;
            $course  = $section?->course;
            $sy      = $section?->schoolYear;

            return [
                'id'             => $ev->id,
                'computed_score' => $ev->computed_score,
                'submitted_at'   => $ev->submitted_at,
                'responses'      => $ev->responses,
                'items'          => $ev->template?->items,
                'student'        => $student ? [
                    'name'           => trim(collect([$student->first_name, $student->middle_name, $student->last_name])->filter()->implode(' ')),
                    'student_number' => $student->student_number,
                ] : null,
                'evaluator'    => $ev->supervisor?->user?->name,
                'company_name' => $ev->supervisor?->company?->name,
                'course_code'  => $course?->code,
                'section_code' => $section?->name,
                'school_year'  => $sy?->name,
            ];
        });

        return response()->json($result);
    }

    public function myEvaluations(Request $request, EvaluationService $service)
    {
        $student = $request->user()->student;

        return EvaluationResource::collection(
            $service->getStudentEvaluations(
                $student->id,
                $request->integer('course_id') ?: null,
                $request->string('status')->toString() ?: null,
            )
        );
    }

    public function myEvaluation(Request $request, Evaluation $evaluation): JsonResponse
    {
        $student = $request->user()->student;

        abort_if(! $student, 404);

        $found = $this->evaluationService->getStudentEvaluation($evaluation->id, $student->id);

        abort_if(! $found, 404, 'Evaluation not found.');

        return response()->json(new EvaluationResource($found));
    }

    /**
     * Centralised "Intern Evaluations" endpoint.
     *
     * - Supervisor  → evaluations for interns at their company
     * - Dean        → evaluations for students in their course
     * - Coordinator → evaluations for students in their sections
     */
    public function internEvaluations(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->role?->name;

        $query = Evaluation::with([
            'template.items',
            'student:id,student_number,first_name,middle_name,last_name,section_id',
            'student.section:id,name,course_id',
            'student.section.course:id,code',
            'supervisor:id,user_id,company_id',
            'supervisor.user:id,name',
            'supervisor.company:id,name',
        ]);

        if ($role === 'supervisor') {
            $supervisor = \App\Models\Supervisor::where('user_id', $user->id)
                ->where('is_active', true)
                ->first();

            if (! $supervisor || ! $supervisor->company_id) {
                return response()->json(['data' => []]);
            }

            // Students currently assigned to this supervisor's company
            $studentIds = $supervisor->company
                ->students()
                ->wherePivot('status', 'active')
                ->pluck('students.id');

            $query->whereIn('student_id', $studentIds);
        } elseif (\App\Support\DeanPortalScope::isPortalUser($user)) {
            $studentIds = \App\Support\DeanPortalScope::studentsQuery($user)->pluck('id');
            $query->whereIn('student_id', $studentIds);
        } elseif ($role === 'coordinator') {
            // Coordinator → students in their assigned sections
            $sectionIds = \App\Models\Section::where('coordinator_user_id', $user->id)->pluck('id');
            $studentIds = \App\Models\Student::whereIn('section_id', $sectionIds)->pluck('id');
            $query->whereIn('student_id', $studentIds);
        } else {
            return response()->json(['data' => []], 403);
        }

        $evaluations = $query->latest('updated_at')->get();

        $result = $evaluations->map(function (Evaluation $ev) {
            $student = $ev->student;
            $section = $student?->section;
            $course  = $section?->course;

            return [
                'id'              => $ev->id,
                'status'          => $ev->status,
                'computed_score'  => $ev->computed_score,
                'submitted_at'    => $ev->submitted_at,
                'created_at'      => $ev->created_at,
                'responses'       => $ev->responses,
                'student'         => $student ? [
                    'id'             => $student->id,
                    'first_name'     => $student->first_name,
                    'middle_name'    => $student->middle_name,
                    'last_name'      => $student->last_name,
                    'student_number' => $student->student_number,
                ] : null,
                'section_name'    => $section?->name,
                'section_id'      => $section?->id,
                'course_code'     => $course?->code,
                'evaluator'       => $ev->supervisor?->user?->name,
                'company_name'    => $ev->supervisor?->company?->name,
                'template'        => $ev->template ? [
                    'id'          => $ev->template->id,
                    'title'       => $ev->template->title,
                    'description' => $ev->template->description,
                    'items'       => $ev->template->items,
                ] : null,
            ];
        });

        return response()->json(['data' => $result]);
    }
}