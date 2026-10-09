<?php

namespace App\Services;

use App\Models\Course;
use App\Models\DocumentRequirement;
use App\Models\StudentDocument;
use App\Support\DocumentRequirementFileType;
use Illuminate\Support\Collection;

class DocumentService
{
    /**
     * Create a new document requirement.
     *
     * @param  array{
     *     title: string,
     *     description?: string|null,
     *     accepted_file_types?: DocumentRequirementFileType|string,
     *     created_by_user_id?: int|null,
     *     is_active?: bool,
     *     recurrence?: string,
     * }  $data
     */
    public function createRequirement(array $data): DocumentRequirement
    {
        return DocumentRequirement::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'accepted_file_types' => $data['accepted_file_types'] ?? DocumentRequirementFileType::PdfAndWord,
            'created_by_user_id' => $data['created_by_user_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'recurrence' => $data['recurrence'] ?? 'none',
        ]);
    }

    /**
     * List document requirements, optionally scoped to a single section.
     *
     * @param  int|null  $sectionId
     */
    public function listRequirements(?int $sectionId = null): Collection
    {
        return DocumentRequirement::query()
            ->with(['createdBy'])
            ->when($sectionId, fn ($query) => $query->where('section_id', $sectionId))
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Sync the document requirements attached to a course.
     *
     * @param  int    $courseId
     * @param  array<int>  $documentRequirementIds
     * @return Collection  the course's requirements after syncing
     */
    public function syncCourseRequirements(int $courseId, array $documentRequirementIds, string $deadlineAt): Collection
    {
        $course = Course::findOrFail($courseId);

        $validIds = DocumentRequirement::whereIn('id', $documentRequirementIds)
            ->pluck('id')
            ->all();

        $syncData = collect($validIds)
            ->mapWithKeys(fn ($id) => [
                $id => ['deadline_at' => $deadlineAt],
            ])
            ->all();

        $course->documentRequirements()->sync($syncData);

        return $course->documentRequirements()->get();
    }

    public function listCourseRequirements(int $courseId): Collection
    {
        $course = Course::findOrFail($courseId);

        return $course->documentRequirements()
            ->with(['createdBy'])
            ->get();
    }

    /**
     * Update a student document's review status (approve/reject).
     *
     * @param  array{
     *     review_status: string,
     *     rejection_reason?: string|null,
     * }  $data
     */
    public function updateStudentDocumentStatus(array $data, int $documentId): StudentDocument
    {
        $document = StudentDocument::findOrFail($documentId);

        $document->update([
            'review_status' => $data['review_status'],
            'rejection_reason' => $data['review_status'] === 'rejected'
                ? ($data['rejection_reason'] ?? null)
                : null,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $data['reviewed_by_user_id'] ?? null,
        ]);

        return $document->fresh(['documentRequirement', 'student', 'reviewedBy']);
    }
}