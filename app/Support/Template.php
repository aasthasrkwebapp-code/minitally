<?php

namespace App\Support;

class Template
{
    /** Replace {placeholders} in a message written on the Settings page. */
    public static function fill(string $text, array $values): string
    {
        return strtr($text, collect($values)->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => (string) $v])->all());
    }
}
