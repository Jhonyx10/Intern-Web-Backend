<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StudentDocumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Pull whatever document types actually exist so foreign keys resolve
        $documentTypeIds = DB::table('document_types')->pluck('id')->all();

        if (empty($documentTypeIds)) {
            $this->command?->warn('No document_types found. Seed document_types first.');
            return;
        }

        $studentIds = [1, 2];
        $now = Carbon::now();

        $documents = [];

        foreach ($studentIds as $studentId) {
            foreach ($documentTypeIds as $index => $documentTypeId) {
                $documents[] = [
                    'student_id'        => $studentId,
                    'document_type_id'  => $documentTypeId,
                    'file_path'         => "documents/student_{$studentId}/doc_type_{$documentTypeId}.pdf",
                    'original_filename' => "requirement_{$index}.pdf",
                    'file_size'         => rand(50_000, 2_000_000), // bytes
                    'mime_type'         => 'application/pdf',
                    'uploaded_at'       => $now->copy()->subDays(rand(1, 60)),
                    'notes'             => $index % 2 === 0 ? null : 'Submitted on time.',
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }
        }

        DB::table('student_documents')->insert($documents);
    }
}