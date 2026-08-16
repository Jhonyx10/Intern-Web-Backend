<?php

namespace App\Exports;

use App\Exports\Sheets\StudentsTemplateSheet;
use App\Exports\Sheets\InstructionsSheet;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class StudentsImportTemplateExport implements Export, WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new StudentsTemplateSheet(),
            new InstructionsSheet(),
        ];
    }
}