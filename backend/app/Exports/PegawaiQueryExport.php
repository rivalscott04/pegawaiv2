<?php

namespace App\Exports;

use App\Support\PegawaiLifecycle;
use App\Support\PegawaiSpreadsheetIdentifiers;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class PegawaiQueryExport extends DefaultValueBinder implements FromQuery, WithChunkReading, WithColumnFormatting, WithCustomValueBinder, WithHeadings, WithMapping
{
    public function __construct(
        private readonly Builder $query,
        /** @var list<string> */
        private readonly array $columns
    ) {}

    public function query(): Builder
    {
        return $this->query;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function headings(): array
    {
        return $this->columns;
    }

    public function columnFormats(): array
    {
        $formats = [];
        foreach (array_values($this->columns) as $i => $col) {
            if (PegawaiSpreadsheetIdentifiers::isIdentifierColumn((string) $col)) {
                $letter = Coordinate::stringFromColumnIndex($i + 1);
                $formats[$letter] = NumberFormat::FORMAT_TEXT;
            }
        }

        return $formats;
    }

    public function bindValue(Cell $cell, $value): bool
    {
        if (is_array($value)) {
            $value = \json_encode($value);
        }

        $row = $cell->getRow();
        $colIndex = Coordinate::columnIndexFromString($cell->getColumn());
        $field = $this->columns[$colIndex - 1] ?? null;

        if ($row >= 1 && is_string($field) && PegawaiSpreadsheetIdentifiers::isIdentifierColumn($field)) {
            if ($value === null || $value === '') {
                $cell->setValue('');

                return true;
            }
            $cell->setValueExplicit(PegawaiSpreadsheetIdentifiers::excelStringValue($value), DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function map($row): array
    {
        return array_map(function (string $column) use ($row) {
            $value = null;

            if ($column === 'tmt_pensiun') {
                $d = PegawaiLifecycle::akanPensiunDate($row);
                $value = $d !== null ? $d->format('Y-m-d') : null;
            } else {
                $value = $row->{$column} ?? null;
            }

            if (PegawaiSpreadsheetIdentifiers::isIdentifierColumn($column)) {
                return PegawaiSpreadsheetIdentifiers::excelStringValue($value);
            }
            if ($value === null) {
                return '';
            }
            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d H:i:s');
            }

            return (string) $value;
        }, $this->columns);
    }
}

