<?php

namespace App\Imports;

use App\Services\StudentEnrollmentService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StudentsImport implements ToCollection, WithHeadingRow
{
    private array $created = [];
    private array $failures = [];

    public function __construct(
        private int $sectionId,
        private StudentEnrollmentService $enrollmentService,
    ) {}

    public function collection(Collection $rows): void
    {
        $seenInFile = [];

        DB::transaction(function () use ($rows, &$seenInFile) {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // account for the heading row

                // A row is "blank" if every relevant cell is empty — guards
                // against phantom trailing rows some spreadsheets carry past
                // the real data (leftover formatting, stray edits, etc.).
                $isBlank = collect(['first_name', 'last_name', 'middle_name', 'student_number'])
                    ->every(fn ($key) => blank($row[$key] ?? null));

                if ($isBlank) {
                    continue;
                }

                $studentNumber = (string) ($row['student_number'] ?? '');

                if ($studentNumber !== '' && isset($seenInFile[$studentNumber])) {
                    $this->failures[] = [
                        'row'       => $rowNumber,
                        'attribute' => 'student_number',
                        'errors'    => ['This student number appears more than once in the file.'],
                    ];
                    continue;
                }

                $validator = Validator::make($row->toArray(), [
                    'first_name'     => ['required', 'string'],
                    'last_name'      => ['required', 'string'],
                    'student_number' => ['required', 'string', 'unique:students,student_number'],
                ]);

                if ($validator->fails()) {
                    foreach ($validator->errors()->messages() as $attribute => $errors) {
                        $this->failures[] = [
                            'row'       => $rowNumber,
                            'attribute' => $attribute,
                            'errors'    => $errors,
                        ];
                    }
                    continue;
                }

                $seenInFile[$studentNumber] = true;

                $student = $this->enrollmentService->create([
                    'student_number' => $studentNumber,
                    'first_name'     => $row['first_name'],
                    'middle_name'    => $row['middle_name'] ?? null,
                    'last_name'      => $row['last_name'],
                    'section_id'     => $this->sectionId,
                    'is_active'      => true,
                ]);

                $this->created[] = $student;
            }
        });
    }

    public function createdCount(): int
    {
        return count($this->created);
    }

    public function failures(): array
    {
        return $this->failures;
    }
}