<?php

namespace App\Support;

/** Indian-style money helpers: 1,23,456.00 and "One Lakh Twenty Three Thousand ..." */
class IndianNumber
{
    private const ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];

    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    public static function words(float $amount): string
    {
        $amount = round($amount, 2);
        $rupees = (int) floor($amount);
        $paise = (int) round(($amount - $rupees) * 100);

        if ($rupees === 0 && $paise === 0) {
            return 'Zero';
        }

        $text = $rupees > 0 ? 'Rupees '.self::integerWords($rupees) : '';
        if ($paise > 0) {
            $text .= ($text ? ' and ' : '').self::integerWords($paise).' Paise';
        }

        return $text.' Only';
    }

    public static function integerWords(int $n): string
    {
        if ($n === 0) {
            return 'Zero';
        }
        $parts = [];
        foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand'], [100, 'Hundred']] as [$size, $label]) {
            if ($n >= $size) {
                $count = intdiv($n, $size);
                $parts[] = ($size === 10000000 ? self::integerWords($count) : self::belowHundred($count)).' '.$label;
                $n %= $size;
            }
        }
        if ($n > 0) {
            $parts[] = self::belowHundred($n);
        }

        return implode(' ', $parts);
    }

    private static function belowHundred(int $n): string
    {
        if ($n < 20) {
            return self::ONES[$n];
        }

        return trim(self::TENS[intdiv($n, 10)].' '.self::ONES[$n % 10]);
    }

    public static function money(float|string|null $amount, bool $decimals = true): string
    {
        $amount = (float) $amount;
        $negative = $amount < 0;
        $amount = abs($amount);
        $whole = (string) (int) floor($amount);
        $fraction = sprintf('%02d', (int) round(($amount - floor($amount)) * 100));
        if ($fraction === '100') {
            $whole = (string) ((int) $whole + 1);
            $fraction = '00';
        }
        if (strlen($whole) > 3) {
            $last3 = substr($whole, -3);
            $rest = substr($whole, 0, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $whole = $rest.','.$last3;
        }

        return ($negative ? '-' : '').$whole.($decimals ? '.'.$fraction : '');
    }
}
