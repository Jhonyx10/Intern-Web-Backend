<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseMajor;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DeanPortalScope
{
    public static function isPortalUser(User $user): bool
    {
        return $user->hasRole('dean') || $user->hasRole('program_head');
    }

    public static function course(User $user): ?Course
    {
        if ($user->hasRole('dean')) {
            $user->loadMissing('courseAsDean');

            return $user->courseAsDean;
        }

        if ($user->hasRole('program_head')) {
            $user->loadMissing('courseMajorAsProgramHead.course');

            return $user->courseMajorAsProgramHead?->course;
        }

        return null;
    }

    public static function major(User $user): ?CourseMajor
    {
        if (! $user->hasRole('program_head')) {
            return null;
        }

        $user->loadMissing('courseMajorAsProgramHead');

        return $user->courseMajorAsProgramHead;
    }

    /**
     * @return Builder<Section>
     */
    public static function sectionsQuery(User $user): Builder
    {
        $course = self::course($user);

        if ($course === null) {
            return Section::query()->whereRaw('1 = 0');
        }

        $query = Section::query()->where('course_id', $course->id);

        $major = self::major($user);

        if ($major !== null) {
            $query->where('course_major_id', $major->id);
        }

        return $query;
    }

    /**
     * @return Builder<Student>
     */
    public static function studentsQuery(User $user): Builder
    {
        return Student::query()->whereHas('section', function (Builder $query) use ($user) {
            $course = self::course($user);

            if ($course === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where('course_id', $course->id);

            $major = self::major($user);

            if ($major !== null) {
                $query->where('course_major_id', $major->id);
            }
        });
    }

    public static function sectionBelongsToScope(User $user, Section $section): bool
    {
        $course = self::course($user);

        if ($course === null || $section->course_id !== $course->id) {
            return false;
        }

        $major = self::major($user);

        if ($major !== null) {
            return $section->course_major_id === $major->id;
        }

        return true;
    }

    public static function studentBelongsToScope(User $user, Student $student): bool
    {
        return self::studentsQuery($user)
            ->whereKey($student->id)
            ->exists();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function coursePayload(User $user): ?array
    {
        $course = self::course($user);

        if ($course === null) {
            return null;
        }

        $major = self::major($user);

        return [
            'id' => $course->id,
            'code' => $course->code,
            'name' => $course->name,
            'required_hours' => $course->required_hours,
            'is_active' => $course->is_active,
            'portal_role' => $user->hasRole('program_head') ? 'program_head' : 'dean',
            'major' => $major ? [
                'id' => $major->id,
                'code' => $major->code,
                'name' => $major->name,
                'display_name' => trim(
                    ($major->code ? "{$course->code}-{$major->code}" : $course->code)
                    .' — '
                    .$major->name,
                ),
            ] : null,
        ];
    }
}
