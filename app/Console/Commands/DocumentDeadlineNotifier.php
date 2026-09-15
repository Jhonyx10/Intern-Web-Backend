<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\DocumentRequirement;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Notifications\DocumentDeadlineWarningNotification;
use App\Support\DocumentReviewStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DocumentDeadlineNotifier extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:document-deadlines';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send Firebase notifications to students who have upcoming document deadlines but have not yet submitted an approved document.';

    /**
     * Number of days before the deadline to send the notification.
     */
    protected int $daysWarning = 3;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $targetDate = Carbon::now()->addDays($this->daysWarning)->startOfDay();

        $this->info("Checking for document deadlines approaching on: {$targetDate->toDateString()}");

        // Find courses that have document requirements deadlines approaching
        $courses = Course::with(['documentRequirements' => function ($query) use ($targetDate) {
            $query->whereDate('course_document_requirement.deadline_at', $targetDate);
        }])->get();

        $notificationsSent = 0;

        foreach ($courses as $course) {
            if ($course->documentRequirements->isEmpty()) {
                continue;
            }

            // Get active students for this course
            $students = Student::whereHas('section', function ($q) use ($course) {
                $q->where('course_id', $course->id);
            })->where('is_active', true)->with('user')->get();

            foreach ($course->documentRequirements as $requirement) {
                $deadline = Carbon::parse($requirement->pivot->deadline_at);

                foreach ($students as $student) {
                    // Check if student has already submitted and got it approved
                    $hasApprovedDocument = StudentDocument::where('student_id', $student->id)
                        ->where('document_requirement_id', $requirement->id)
                        ->where('review_status', DocumentReviewStatus::Approved)
                        ->exists();

                    if (!$hasApprovedDocument) {
                        // Needs to submit (or is pending/rejected)
                        if ($student->user && $student->user->fcm_token) {
                            $student->user->notify(new DocumentDeadlineWarningNotification($requirement, $deadline));
                            $notificationsSent++;
                        }
                    }
                }
            }
        }

        $this->info("Sent {$notificationsSent} push notifications for document deadlines.");
    }
}
