<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    /**
     * Get system/department general settings.
     */
    public function getSettings(Request $request): JsonResponse
    {
        $user = $request->user();

        // Super Admin always uses the default system theme & logo
        if ($user && $user->hasRole('super_admin')) {
            return response()->json([
                'id' => null,
                'course_id' => null,
                'department_name' => null,
                'logo_path' => null,
                'logo_url' => null,
                'theme_color' => '#0b6e4f',
                'theme_color_hover' => null,
                'theme_color_soft' => null,
                'updated_at' => null,
            ]);
        }

        $courseId = null;

        if ($user) {
            if ($user->hasRole('dean')) {
                $courseId = $user->deanPortalCourse()?->id ?? $user->courseAsDean?->id ?? $user->course_id;
            } elseif ($user->hasRole('program_head')) {
                $courseId = $user->deanPortalCourse()?->id ?? $user->courseAsProgramHead?->id ?? $user->course_id;
            } elseif ($user->hasRole('coordinator')) {
                $courseId = $user->coordinatorCourse()?->id ?? $user->course_id;
            } else {
                $courseId = $user->course_id;
            }
        }

        $setting = null;
        if ($courseId) {
            $setting = Setting::where('course_id', $courseId)->first();
        }

        return response()->json([
            'id' => $setting?->id,
            'course_id' => $setting?->course_id,
            'department_name' => $setting?->department_name,
            'logo_path' => $setting?->logo_path,
            'logo_url' => $setting?->logo_url,
            'theme_color' => $setting?->theme_color ?? '#0b6e4f',
            'theme_color_hover' => $setting?->theme_color_hover,
            'theme_color_soft' => $setting?->theme_color_soft,
            'updated_at' => $setting?->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * Update department settings (Dean only).
     */
    public function updateDeanSettings(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('dean') && ! $user->hasRole('program_head') && ! $user->hasRole('super_admin')) {
            throw ValidationException::withMessages([
                'auth' => ['Only Deans or Program Heads can update department settings.'],
            ]);
        }

        $request->validate([
            'department_name' => 'nullable|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp,svg|max:2048',
            'remove_logo' => 'nullable|string',
            'theme_color' => 'nullable|string|max:50',
            'theme_color_hover' => 'nullable|string|max:50',
            'theme_color_soft' => 'nullable|string|max:50',
        ]);

        $courseId = null;
        if ($user->hasRole('dean')) {
            $courseId = $user->deanPortalCourse()?->id ?? $user->courseAsDean?->id ?? $user->course_id;
        } elseif ($user->hasRole('program_head')) {
            $courseId = $user->deanPortalCourse()?->id ?? $user->courseAsProgramHead?->id ?? $user->course_id;
        } else {
            $courseId = $user->course_id;
        }

        if (! $courseId) {
            throw ValidationException::withMessages([
                'course' => ['No department course is associated with this user account.'],
            ]);
        }

        $setting = Setting::firstOrNew(['course_id' => $courseId]);

        if ($request->input('department_name') !== null) {
            $setting->department_name = $request->input('department_name');
        }

        if ($request->hasFile('logo')) {
            if ($setting->logo_path && Storage::disk('public')->exists($setting->logo_path)) {
                Storage::disk('public')->delete($setting->logo_path);
            }
            $path = $request->file('logo')->store('department_logos', 'public');
            $setting->logo_path = $path;
        } elseif ($request->input('remove_logo') === 'true') {
            if ($setting->logo_path && Storage::disk('public')->exists($setting->logo_path)) {
                Storage::disk('public')->delete($setting->logo_path);
            }
            $setting->logo_path = null;
        }

        if ($request->filled('theme_color')) {
            $setting->theme_color = $request->input('theme_color');
        }
        if ($request->input('theme_color_hover') !== null) {
            $setting->theme_color_hover = $request->input('theme_color_hover');
        }
        if ($request->input('theme_color_soft') !== null) {
            $setting->theme_color_soft = $request->input('theme_color_soft');
        }

        $setting->updated_by = $user->id;
        $setting->save();

        return response()->json([
            'message' => 'Department settings updated successfully.',
            'settings' => [
                'id' => $setting->id,
                'course_id' => $setting->course_id,
                'department_name' => $setting->department_name,
                'logo_path' => $setting->logo_path,
                'logo_url' => $setting->logo_url,
                'theme_color' => $setting->theme_color,
                'theme_color_hover' => $setting->theme_color_hover,
                'theme_color_soft' => $setting->theme_color_soft,
            ],
        ]);
    }
}
