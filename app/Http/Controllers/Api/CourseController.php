<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Course;

class CourseController extends Controller
{
    public function index()
    {
        return Course::with(['dean', 'majors'])->get();
    }

    public function show(Course $course)
    {
        return $course->load(['dean', 'majors']);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:courses,code',
            'name' => 'required|string',
            'required_hours' => 'required|integer|min:0',
            'dean_user_id' => 'required|exists:users,id',
            'is_active' => 'boolean',
        ]);

        $course = Course::create($validated);
        
        return response()->json($course->load(['dean', 'majors']), 201);
    }

    public function update(Request $request, Course $course)
    {
        $validated = $request->validate([
            'code' => 'sometimes|string|unique:courses,code,' . $course->id,
            'name' => 'sometimes|string',
            'required_hours' => 'sometimes|integer|min:0',
            'dean_user_id' => 'sometimes|exists:users,id',
            'is_active' => 'boolean',
        ]);

        $course->update($validated);
        
        return $course->load(['dean', 'majors']);
    }

    public function destroy(Course $course)
    {
        $course->delete();
        
        return response()->noContent();
    }
}
