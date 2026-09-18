<?php

namespace App\Services;

use App\Models\Course;
use App\Models\DocumentType;
use App\Models\DocumentRequirement;
use App\Models\StudentDocument;
use App\Support\DocumentRequirementFileType;
use Illuminate\Support\Collection;

class DocumentService
{
    public function createDocumentType(array $data): DocumentType
    {
        return DocumentType::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'is_required' => $data['is_required'],
            'recurrence' => $data['recurrence'] ?? 'none',
        ]);
    }

    /**
     * Create a new document requirement for a section (e.g. a dean posting
     * a new ask, like "Waiver Form — due Sept 20").
     *
     * @param  array{
     *     section_id: int,
     *     title: string,
     *     description?: string|null,
     *     deadline_at: string,
     *     accepted_file_types?: DocumentRequirementFileType|string,
     *     created_by_user_id?: int|null,
     *     is_active?: bool,
     * }  $data
     */
    public function createRequirement(array $data): DocumentRequirement
    {
        return DocumentRequirement::create([
            'document_type_id' => $data['document_type_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'accepted_file_types' => $data['accepted_file_types'] ?? DocumentRequirementFileType::PdfAndWord,
            'created_by_user_id' => $data['created_by_user_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * List document requirements, optionally scoped to a single section.
     * Eager-loads the section and the user who created it so the frontend
     * doesn't trigger N+1 queries rendering the list.
     *
     * @param  int|null  $sectionId
     */
    public function listRequirements(?int $sectionId = null): Collection
    {
        return DocumentRequirement::query()
            ->with(['documentType', 'createdBy'])
            ->when($sectionId, fn ($query) => $query->where('section_id', $sectionId))
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Sync the document requirements attached to a course (e.g. a dean
     * checking/unchecking items from the superadmin's master list).
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
            ->with(['documentType', 'createdBy'])
            ->get();
    }

    /**
     * Update a student document's review status (approve/reject), stamping
     * who reviewed it and when. A rejection requires a reason; approving
     * clears any prior rejection reason since it's no longer relevant.
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