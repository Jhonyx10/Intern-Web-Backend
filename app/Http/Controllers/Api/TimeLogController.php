<?php

namespace App\Http\Controllers\Api;

use App\Models\TimeLog;
use App\Services\EvaluationService;
use Illuminate\Http\JsonResponse;

class TimeLogController extends Controller
{
    public function approve(TimeLog $timeLog, EvaluationService $evaluationService): JsonResponse
    {
        // 1. Mark time log as approved
        $timeLog->update(['status' => 'approved']);

        // 2. Fetch the student with fresh time log relations
        $student = $timeLog->student;

        // 3. Trigger progress check & auto-assign
        $evaluation = $evaluationService->checkAndAutoAssignEvaluation($student);

        return response()->json([
            'message' => 'Time log approved successfully.',
            'evaluation_created' => $evaluation !== null,
        ]);
    }
}