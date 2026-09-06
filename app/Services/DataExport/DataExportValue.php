<?php

namespace App\Services\DataExport;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Formats raw database values into spreadsheet friendly strings.
 *
 * Amounts are rendered with two decimals rather than the eight stored by the
 * database so accounting software and spreadsheets can sum them directly.
 */
class DataExportValue
{
    public const DAYS_PER_MONTH = 30.436875;

    public static function text(mixed $value): string
    {
        return $value === null ? '' : (string) $value;
    }

    public static function amount(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    public static function rate(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    public static function timestamp(mixed $value): string
    {
        return self::carbon($value)?->format('Y-m-d H:i:s') ?? '';
    }

    public static function date(mixed $value): string
    {
        return self::carbon($value)?->format('Y-m-d') ?? '';
    }

    public static function yesNo(mixed $value): string
    {
        return $value ? 'Yes' : 'No';
    }

    public static function fullName(?string $firstName, ?string $lastName): string
    {
        return trim(trim((string) $firstName).' '.trim((string) $lastName));
    }

    public static function className(?string $class): string
    {
        return $class === null || $class === '' ? '' : class_basename($class);
    }

    public static function percentage(float|int|string $numerator, float|int|string $denominator): string
    {
        if ((float) $denominator === 0.0) {
            return '0.00';
        }

        return number_format(((float) $numerator / (float) $denominator) * 100, 2, '.', '');
    }

    /**
     * Whole days between two timestamps. Negative when the end date is in the past.
     */
    public static function daysBetween(mixed $from, mixed $to): string
    {
        $start = self::carbon($from);
        $end = self::carbon($to);

        if ($start === null || $end === null) {
            return '';
        }

        return (string) (int) round($start->startOfDay()->diffInDays($end->startOfDay(), false));
    }

    public static function agingBucket(mixed $daysOutstanding): string
    {
        if ($daysOutstanding === '') {
            return '';
        }

        return match (true) {
            (int) $daysOutstanding <= 30 => '0-30 days',
            (int) $daysOutstanding <= 60 => '31-60 days',
            (int) $daysOutstanding <= 90 => '61-90 days',
            default => '90+ days',
        };
    }

    public static function billingCycle(mixed $periodInDays): string
    {
        return match ((int) $periodInDays) {
            0 => 'One-time',
            1 => 'Daily',
            7 => 'Weekly',
            14 => 'Every 2 weeks',
            30, 31 => 'Monthly',
            60 => 'Every 2 months',
            90, 91, 92 => 'Quarterly',
            180, 182, 183 => 'Every 6 months',
            365, 366 => 'Yearly',
            730, 731 => 'Every 2 years',
            1095, 1096 => 'Every 3 years',
            default => (int) $periodInDays.' days',
        };
    }

    /**
     * Normalises a recurring amount to its monthly contribution (MRR).
     */
    public static function monthlyValue(mixed $amount, mixed $periodInDays): string
    {
        $days = (int) $periodInDays;

        if ($days <= 0) {
            return self::amount(0);
        }

        return self::amount(((float) $amount / $days) * self::DAYS_PER_MONTH);
    }

    protected static function carbon(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->clone();
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
