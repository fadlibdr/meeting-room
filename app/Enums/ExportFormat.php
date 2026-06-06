<?php

declare(strict_types=1);

namespace App\Enums;

enum ExportFormat: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';

    public function extension(): string
    {
        return $this->value;
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::Csv => 'text/csv; charset=UTF-8',
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Csv => 'CSV',
            self::Xlsx => 'Excel (XLSX)',
        };
    }
}
