<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\EvaluationTemplate;
use App\Http\Requests\SubmitEvaluationRequest;
use App\Http\Requests\StoreEvaluationTemplateRequest;
use App\Services\EvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Throwable;

class OjtEvaluationController extends Controller
{
    public function __construct(
        protected EvaluationService $evaluationService
    ) {}


    public function index()
    {
       $templates = EvaluationTemplate::with([
            'creator:id,name',
            'courses:id,code,name'
        ])
        ->withCount('items')
        ->latest()
        ->get();

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

   public function bulkAssign(Request $request)
    {
        $request->validate([
            'template_id' => 'required|integer',
            'course_id' => 'required|integer',
        ]);

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
        $evaluation = EvaluationTemplate::with(['items', 'creator'])->findOrFail($id);

        return response()->json($evaluation);
    }
}