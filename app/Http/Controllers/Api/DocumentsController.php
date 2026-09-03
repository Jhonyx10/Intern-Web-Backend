<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\StudentDocument;

class DocumentsController extends Controller
{
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
}
