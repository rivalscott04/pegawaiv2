<?php

namespace App\Support;

/**
 * NIP / nip_lama must stay exact digit strings: Excel must not treat them as numbers
 * (scientific notation, rounding). CSV opened in Excel needs the ="…" text trick.
 */
final class PegawaiSpreadsheetIdentifiers
{
    public const IDENTIFIER_COLUMNS = ['nip', 'nip_lama'];

    public static function isIdentifierColumn(string $column): bool
    {
        return in_array($column, self::IDENTIFIER_COLUMNS, true);
    }

    public static function excelStringValue(mixed $value): string
    {
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
    }

    /**
     * Plain string for fputcsv (non-identifier columns).
     */
    public static function plainCsvScalar(mixed $value): string
    {
        return self::excelStringValue($value);
    }

    /**
     * CSV cell value that Excel imports as text (no 1.23E+18).
     */
    public static function csvFieldForExcel(string $column, mixed $value): string
    {
        if (!self::isIdentifierColumn($column)) {
            return self::plainCsvScalar($value);
        }

        $s = self::excelStringValue($value);
        if ($s === '') {
            return '';
        }

        return '="' . str_replace('"', '""', $s) . '"';
    }
}
