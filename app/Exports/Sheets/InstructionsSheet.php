<?php

// app/Exports/Sheets/InstructionsSheet.php
namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InstructionsSheet implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    public function title(): string
    {
        return 'Instructions';
    }

    public function columnWidths(): array
    {
        return ['A' => 90];
    }

    public function array(): array
    {
        return [
            ['How to use this template'],
            [''],
            ['1. Fill in the Students sheet'],
            ['Go to the "Students" tab. Each row is one student. Do not rename the column headers in row 1, and do not add extra columns — the columns must match exactly for the import to work.'],
            [''],
            ['2. Delete the example row'],
            ['Row 2 ("Kyla Santos Mendoza, 21-0143") is just a sample showing the expected format. Delete it or overwrite it with a real student before you upload.'],
            [''],
            ['3. Required fields'],
            ['First Name, Last Name, and Student Number are required for every row. Middle Name is optional and can be left blank.'],
            [''],
            ['4. Save and upload'],
            ['Save the file (keep it as .xlsx) and upload it back through the "Import Excel" tab on the Add Student form for the section. The system will show you which rows imported successfully and which had errors before anything is saved.'],
            [''],
            ['5. Section'],
            ["You don't need to fill in a section — the students will be added to whichever section you're uploading from."],
            [''],
            ['Example of a student row'],
            ['Column', 'Example', 'Notes'],
            ['First Name', 'Kyla', 'Required'],
            ['Middle Name', 'Santos', 'Optional — leave blank if none'],
            ['Last Name', 'Mendoza', 'Required'],
            ['Student Number', '21-0143', 'Required, must be unique'],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $this->applyStyles($sheet);
                $sheet->getProtection()->setSheet(true);
            },
        ];
    }

    private function applyStyles(Worksheet $sheet): void
    {
        $sheet->getShowGridlines(); // no-op guard for IDE; gridlines hidden below
        $sheet->setShowGridlines(false);

        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '2563EB'], 'name' => 'Arial'],
        ]);

        $stepRows = [3, 6, 9, 12, 15];
        foreach ($stepRows as $row) {
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => ['bold' => true, 'name' => 'Arial', 'size' => 11],
            ]);
        }

        $bodyRows = [4, 7, 10, 13, 16];
        foreach ($bodyRows as $row) {
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => ['name' => 'Arial', 'size' => 11],
                'alignment' => ['wrapText' => true, 'vertical' => 'top'],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(30);
        }
    }
}