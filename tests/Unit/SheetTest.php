<?php

namespace Tests\Unit;

use App\Support\Sheet;
use PHPUnit\Framework\TestCase;

class SheetTest extends TestCase
{
    public function test_reads_common_bank_and_excel_date_formats(): void
    {
        foreach (['05/11/2026', '05-11-2026', '05.11.2026', '05-Nov-2026', '05 Nov 2026', '05/11/26', '2026-11-05', '05/11/2026 10:32:11'] as $value) {
            $this->assertSame('2026-11-05', Sheet::date($value)?->toDateString(), "Failed for $value");
        }
        $this->assertSame('2026-10-01', Sheet::date(46296)?->toDateString()); // Excel serial
        $this->assertNull(Sheet::date('Opening balance'));
        $this->assertNull(Sheet::date(''));
    }

    public function test_reads_money_with_commas_and_suffixes(): void
    {
        $this->assertSame(12345.5, Sheet::money('12,345.50'));
        $this->assertSame(1500.0, Sheet::money('₹ 1,500 Cr.'));
        $this->assertSame(0.0, Sheet::money(''));
        $this->assertSame(750.0, Sheet::money(750));
    }

    public function test_finds_header_row_below_title_rows(): void
    {
        $rows = [['PUNJAB NATIONAL BANK'], [], ['Txn Date', 'Description', 'Dr Amount', 'Cr Amount'], ['01/11/2026', 'X', '', '100']];
        [$index, $map] = Sheet::findHeader($rows, ['date' => '/date/', 'narration' => '/description/', 'credit' => '/^cr/'], ['date', 'credit']);
        $this->assertSame(2, $index);
        $this->assertSame(['date' => 0, 'narration' => 1, 'credit' => 3], $map);
    }
}
