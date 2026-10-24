<?php

namespace Tests\Unit;

use App\Support\IndianNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IndianNumberTest extends TestCase
{
    public static function words(): array
    {
        return [
            [0, 'Zero'],
            [5, 'Rupees Five Only'],
            [750, 'Rupees Seven Hundred Fifty Only'],
            [1200, 'Rupees One Thousand Two Hundred Only'],
            [21015, 'Rupees Twenty One Thousand Fifteen Only'],
            [100000, 'Rupees One Lakh Only'],
            [1234567.5, 'Rupees Twelve Lakh Thirty Four Thousand Five Hundred Sixty Seven and Fifty Paise Only'],
            [25000000, 'Rupees Two Crore Fifty Lakh Only'],
        ];
    }

    #[DataProvider('words')]
    public function test_amount_in_words(float $amount, string $expected): void
    {
        $this->assertSame($expected, IndianNumber::words($amount));
    }

    public function test_indian_digit_grouping(): void
    {
        $this->assertSame('0.00', IndianNumber::money(0));
        $this->assertSame('999.50', IndianNumber::money(999.5));
        $this->assertSame('1,200.00', IndianNumber::money(1200));
        $this->assertSame('1,23,456.78', IndianNumber::money(123456.78));
        $this->assertSame('1,00,00,000', IndianNumber::money(10000000, false));
        $this->assertSame('-4,500.00', IndianNumber::money(-4500));
        $this->assertSame('11.00', IndianNumber::money(10.999));
    }
}
