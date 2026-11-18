<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final class DateRange
{
    /**
     * Exclusive upper bound for an inclusive calendar-date filter.
     *
     * Comparing "< next day" keeps the predicate sargable on indexed DATE
     * columns (unlike whereDate()) and also matches drivers such as SQLite
     * that persist a 00:00:00 time component alongside the date.
     */
    public static function endExclusive(DateTimeInterface|string $date): string
    {
        return CarbonImmutable::parse($date)->addDay()->toDateString();
    }
}
