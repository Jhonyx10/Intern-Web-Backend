<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourseMajor;
use Illuminate\Http\Request;

class CourseMajorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = CourseMajor::with(['course', 'programHead']);

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        return response()->json($query->orderBy('sort_order')->orderBy('name')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255',
            'program_head_user_id' => 'nullable|exists:users,id',
            'sort_order' => 'nullable|integer',
        ]);

        $major = CourseMajor::create($validated);

        return response()->json($major, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $major = CourseMajor::with(['course', 'programHead'])->findOrFail($id);
        return response()->json($major);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $major = CourseMajor::findOrFail($id);

        $validated = $request->validate([
            'course_id' => 'sometimes|exists:courses,id',
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:255',
            'program_head_user_id' => 'nullable|exists:users,id',
            'sort_order' => 'nullable|integer',
        ]);

        $major->update($validated);

        return response()->json($major);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $major = CourseMajor::findOrFail($id);
        $major->delete();

        return response()->json(null, 204);
    }
}
