<?php

namespace App\Support;

enum DocumentRequirementFileType: string
{
    case Pdf = 'pdf';
    case Word = 'word';
    case PdfAndWord = 'pdf_and_word';
    case Image = 'image';
    case Any = 'any';

    /**
     * Human-readable label for display in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pdf => 'PDF only',
            self::Word => 'Word document only',
            self::PdfAndWord => 'PDF or Word document',
            self::Image => 'Image (JPG/PNG)',
            self::Any => 'Any file type',
        };
    }

    /**
     * MIME types this case accepts, for validating an actual upload
     * (e.g. in a FormRequest's 'mimes' rule).
     *
     * @return list<string>
     */
    public function mimeTypes(): array
    {
        return match ($this) {
            self::Pdf => ['pdf'],
            self::Word => ['doc', 'docx'],
            self::PdfAndWord => ['pdf', 'doc', 'docx'],
            self::Image => ['jpg', 'jpeg', 'png'],
            self::Any => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
        };
    }
}