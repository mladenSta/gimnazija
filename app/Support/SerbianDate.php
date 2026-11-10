<?php

namespace App\Support;

use DateTimeInterface;

/**
 * Dates as used in Serbia / Bosnia: 25.11.2026. and "25 nov 2026".
 */
class SerbianDate
{
    private const MONTHS_SHORT = ['jan', 'feb', 'mar', 'apr', 'maj', 'jun', 'jul', 'avg', 'sep', 'okt', 'nov', 'dec'];

    private const MONTHS = ['januar', 'februar', 'mart', 'april', 'maj', 'jun', 'jul', 'avgust', 'septembar', 'oktobar', 'novembar', 'decembar'];

    public static function short(?DateTimeInterface $date): string
    {
        return $date?->format('d.m.Y.') ?? '';
    }

    public static function long(?DateTimeInterface $date): string
    {
        return $date ? $date->format('j').'. '.self::MONTHS[(int) $date->format('n') - 1].' '.$date->format('Y').'.' : '';
    }

    public static function monthShort(DateTimeInterface $date): string
    {
        return self::MONTHS_SHORT[(int) $date->format('n') - 1];
    }
}
