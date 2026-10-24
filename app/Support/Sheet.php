<?php

namespace App\Support;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/** Small helpers for reading Excel/CSV uploads with an unknown header row. */
class Sheet
{
    /** @return array<int, array<int, mixed>> */
    public static function rows(string $path): array
    {
        $book = IOFactory::load($path);
        $rows = [];
        foreach ($book->getAllSheets() as $sheet) {
            $data = $sheet->toArray(null, true, false, false);
            if (count($data) > count($rows)) {
                $rows = $data;
            }
        }

        return $rows;
    }

    /**
     * Find the header row: the first row (within the top 40) that has a cell matching every
     * required pattern. Returns [rowIndex, [field => columnIndex]] or null.
     *
     * @param  array<string, string>  $patterns  field => regex (case-insensitive)
     * @param  array<int, string>  $required
     */
    public static function findHeader(array $rows, array $patterns, array $required): ?array
    {
        foreach (array_slice($rows, 0, 40, true) as $i => $row) {
            $map = [];
            foreach ($patterns as $field => $regex) {
                foreach ($row as $col => $cell) {
                    $label = strtolower(trim((string) $cell));
                    if ($label !== '' && ! in_array($col, $map, true) && preg_match($regex, $label)) {
                        $map[$field] = $col;
                        break;
                    }
                }
            }
            if (count(array_intersect($required, array_keys($map))) === count($required)) {
                return [$i, $map];
            }
        }

        return null;
    }

    public static function date(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }
        if (is_numeric($value) && $value > 20000 && $value < 80000) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay();
        }
        $value = trim(preg_replace('/\s+/', ' ', (string) $value));
        $value = preg_replace('/\s+\d{1,2}:\d{2}(:\d{2})?.*$/', '', $value);
        foreach (['d/m/Y', 'd-m-Y', 'd.m.Y', 'd-M-Y', 'd M Y', 'd-M-y', 'd/m/y', 'd-m-y', 'Y-m-d', 'd M, Y', 'M d, Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
                if ($date && $date->format($format) === $value || $date && $date->year > 1990) {
                    return $date->startOfDay();
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    public static function money(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }
        $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', (string) $value));

        return is_numeric($clean) ? round((float) $clean, 2) : 0.0;
    }
}
