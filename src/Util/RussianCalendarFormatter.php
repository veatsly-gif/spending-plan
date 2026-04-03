<?php

declare(strict_types=1);

namespace App\Util;

final class RussianCalendarFormatter
{
    /**
     * @var array<int, string>
     */
    private const MONTHS = [
        1 => 'январь',
        2 => 'февраль',
        3 => 'март',
        4 => 'апрель',
        5 => 'май',
        6 => 'июнь',
        7 => 'июль',
        8 => 'август',
        9 => 'сентябрь',
        10 => 'октябрь',
        11 => 'ноябрь',
        12 => 'декабрь',
    ];

    public static function monthYear(\DateTimeInterface $date): string
    {
        $month = (int) $date->format('n');
        $year = $date->format('Y');

        return (self::MONTHS[$month] ?? '').' '.$year;
    }
}

