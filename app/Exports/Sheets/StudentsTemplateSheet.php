<?php

// app/Exports/Sheets/StudentsTemplateSheet.php
namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentsTemplateSheet implements FromArray, WithHeadings, WithTitle, WithColumnWidths, WithEvents
{
    private const ACCENT = '2563EB';
    private const LIGHT_FILL = 'EFF6FF';
    private const EXAMPLE_GRAY = '6B7280';

    public function title(): string
    {
        return 'Students';
    }

    public function headings(): array
    {
        return ['First Name', 'Middle Name', 'Last Name', 'Student Number'];
    }

    public function array(): array
    {
        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18,
            'B' => 18,
            'C' => 18,
            'D' => 18,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $this->styleHeader($sheet);
                $this->styleExampleRow($sheet);
                $this->prepareDataEntryArea($sheet);
                $this->protectSheet($sheet);
            },
        ];
    }

    private function styleHeader(Worksheet $sheet): void
    {
        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'name' => 'Arial'],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::ACCENT],
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']],
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->freezePane('A2');
    }

    private function styleExampleRow(Worksheet $sheet): void
    {
        $sheet->getStyle('A2:D2')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => self::EXAMPLE_GRAY], 'name' => 'Arial', 'size' => 10],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']],
            ],
        ]);
        $sheet->setCellValue('E2', '← example row, delete or overwrite');
        $sheet->getStyle('E2')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => self::EXAMPLE_GRAY], 'name' => 'Arial', 'size' => 9],
        ]);
        $sheet->getColumnDimension('E')->setWidth(32);
    }

    private function prepareDataEntryArea(Worksheet $sheet): void
    {
        // rows 3–202: unlocked, lightly filled, bordered — this is what teachers actually fill in
        $sheet->getStyle('A3:D202')->applyFromArray([
            'font' => ['name' => 'Arial', 'size' => 11],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::LIGHT_FILL],
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']],
            ],
            'protection' => ['locked' => Protection::PROTECTION_UNPROTECTED],
        ]);
    }

    private function protectSheet(Worksheet $sheet): void
    {
        // header + example row stay locked; only A3:D202 (set above) is editable.
        // No password — this is a UX guard against accidental header edits, not a security boundary.
        $sheet->getProtection()->setSheet(true);
        $sheet->getProtection()->setInsertRows(false);
        $sheet->getProtection()->setDeleteRows(true);
        $sheet->getProtection()->setFormatCells(false);
    }
}