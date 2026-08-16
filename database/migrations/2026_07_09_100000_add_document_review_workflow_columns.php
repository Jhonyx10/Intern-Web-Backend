<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_documents', function (Blueprint $table) {
            $table->string('review_status', 20)->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('rejection_reason')->nullable();

            $table->index(['document_requirement_id', 'review_status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('document_submission_alerts_seen_at')->nullable();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->timestamp('last_document_review_alerts_seen_at')->nullable();
        });

        DB::table('student_documents')
            ->whereNotNull('document_requirement_id')
            ->update(['review_status' => 'approved']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('last_document_review_alerts_seen_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('document_submission_alerts_seen_at');
        });

        Schema::table('student_documents', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by_user_id']);
            $table->dropIndex(['document_requirement_id', 'review_status']);
            $table->dropColumn([
                'review_status',
                'reviewed_at',
                'reviewed_by_user_id',
                'rejection_reason',
            ]);
        });
    }
};
