<?php

namespace App\Services;

/**
 * Jalali (Persian/Solar) calendar utilities in pure PHP.
 * No external packages, no ext-intl dependency.
 *
 * All date conversions go through this service —
 * it is the single source of truth for the Jalali ↔ Gregorian bridge.
 */
class CalendarService
{
    /**
     * Converts a Gregorian date to Jalali.
     *
     * @return array{jalali_year: int, jalali_month: int, jalali_day: int}
     */
    public static function gregorianToJalali(int $gy, int $gm, int $gd): array
    {
        $jd = self::jdFromGregorian($gy, $gm, $gd);
        $j = self::jdToJalali($jd);
        return [
            'jalali_year'  => $j['jalali_year'],
            'jalali_month' => $j['jalali_month'],
            'jalali_day'   => $j['jalali_day'],
        ];
    }

    /**
     * Converts a Jalali date to Gregorian.
     *
     * @return array{gregorian_year: int, gregorian_month: int, gregorian_day: int}
     */
    public static function jalaliToGregorian(int $jy, int $jm, int $jd): array
    {
        $result = self::jdToGregorian(self::jalaliToJd($jy, $jm, $jd));
        return [
            'gregorian_year'  => $result['gregorian_year'],
            'gregorian_month' => $result['gregorian_month'],
            'gregorian_day'   => $result['gregorian_day'],
        ];
    }

    /**
     * Parses a Jalali date string "YYYY/MM/DD" to a Unix timestamp.
     *
     * @return int Unix timestamp (Asia/Tehran timezone)
     */
    public static function parseJalaliDate(string $jalaliDate): int
    {
        // Accept both "1405/01/01" and "1405-01-01" (HTML date inputs use dashes).
        $parts = preg_split('/[\/\-]/', trim($jalaliDate));
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('Invalid Jalali date format: ' . $jalaliDate);
        }
        [$jy, $jm, $jd] = array_map('intval', $parts);

        // Validate
        if ($jy < 0 || $jy > 32767) {
            throw new \OutOfRangeException('Jalali year out of range.');
        }
        $jMaxDay = self::jalaliMonthLength($jy, $jm);
        if ($jm < 1 || $jm > 12 || $jd < 1 || $jd > $jMaxDay) {
            throw new \InvalidArgumentException("Invalid Jalali date: $jalaliDate");
        }

        $greg = self::jalaliToGregorian($jy, $jm, $jd);
        $ts   = mktime(12, 0, 0, $greg['gregorian_month'], $greg['gregorian_day'], $greg['gregorian_year']);
        return $ts;
    }

    /**
     * Formats a Unix timestamp as a Jalali date string "YYYY/MM/DD".
     */
    public static function formatJalali(int $timestamp): string
    {
        $date = new \DateTime('@' . $timestamp);
        $date-> setTimezone(new \DateTimeZone('Asia/Tehran'));

        $gy = (int) $date->format('Y');
        $gm = (int) $date->format('n');
        $gd = (int) $date->format('j');

        $result = self::gregorianToJalali($gy, $gm, $gd);
        return sprintf('%04d/%02d/%02d', $result['jalali_year'], $result['jalali_month'], $result['jalali_day']);
    }

    /**
     * Converts a Jalali date string "YYYY/MM/DD" to a Gregorian "Y-m-d"
     * (for DB date filters). Accepts "/" or "-" separators and non-padded parts.
     * Returns "" when the input is not a valid Jalali date.
     */
    public static function jalaliToYmd(string $jalaliDate): string
    {
        try {
            $ts = self::parseJalaliDate($jalaliDate);
        } catch (\Throwable) {
            return '';
        }
        return date('Y-m-d', $ts);
    }

    /**
     * Returns the Jalali month length for a given Jalali year and month.
     */
    public static function jalaliMonthLength(int $jy, int $jm): int
    {
        // Jalali months 1-6: 31 days, 7-11: 30 days, 12: 29 (28 in non-leap)
        if ($jm <= 6) {
            return 31;
        }
        if ($jm <= 11) {
            return 30;
        }
        return self::isJalaliLeapYear($jy) ? 30 : 29;
    }

    /**
     * Returns the current Jalali month boundaries as Unix timestamps.
     */
    public static function currentJalaliMonthRange(): array
    {
        $now = time();
        $date = new \DateTime('@' . $now);
        $date-> setTimezone(new \DateTimeZone('Asia/Tehran'));

        $gy = (int) $date->format('Y');
        $gm = (int) $date->format('n');
        $gd = (int) $date->format('j');

        $current = self::gregorianToJalali($gy, $gm, $gd);

        // First day of current Jalali month
        $firstJalali = [
            'jy' => $current['jalali_year'],
            'jm' => $current['jalali_month'],
            'jd' => 1,
        ];
        $gregFirst = self::jalaliToGregorian($firstJalali['jy'], $firstJalali['jm'], $firstJalali['jd']);
        $startTs = mktime(0, 0, 0, $gregFirst['gregorian_month'], $gregFirst['gregorian_day'], $gregFirst['gregorian_year']);

        // Last day of current Jalali month
        $lastJalaliDay = self::jalaliMonthLength($current['jalali_year'], $current['jalali_month']);
        $lastJalali   = [
            'jy' => $current['jalali_year'],
            'jm' => $current['jalali_month'],
            'jd' => $lastJalaliDay,
        ];
        $gregLast = self::jalaliToGregorian($lastJalali['jy'], $lastJalali['jm'], $lastJalali['jd']);
        $endTs   = mktime(23, 59, 59, $gregLast['gregorian_month'], $gregLast['gregorian_day'], $gregLast['gregorian_year']);

        return ['start' => $startTs, 'end' => $endTs, 'jalali_year' => $current['jalali_year'], 'jalali_month' => $current['jalali_month']];
    }

    // ── Astronomical Julian Day bridge ────────────────────────────
    //
    // Gregorian ↔ JD is exact (jdFromGregorian / jdToGregorian).
    // JD ↔ Jalali uses day-count accumulation anchored at the calibrated epoch
    // JALALI_EPOCH_JD (= 1948321) such that jalaliToJd(1405,1,1) == jdFromGregorian(2026,3,21),
    // combined with the civil 33-year leap rule (matches the Iranian civil calendar).

    /** Astronomical JD of Jalali 1/1/1. */
    private const JALALI_EPOCH_JD = 1948321;

    /**
     * jdToJalali: astronomical JD → [jalali_year, jalali_month, jalali_day].
     * Binary-searches the Jalali year via jalaliStartDay, then walks the months.
     */
    private static function jdToJalali(int $jd): array
    {
        $f = $jd - self::JALALI_EPOCH_JD; // days from J1/1/1 (0-based)

        $lo = 1;
        $hi = 2820;
        while (self::jalaliStartDay($hi) <= $f) {
            $hi *= 2;
        }
        // Find the first y where jalaliStartDay(y) > $f; the answer is that y - 1.
        while ($lo < $hi) {
            $mid = intdiv($lo + $hi, 2);
            if (self::jalaliStartDay($mid) <= $f) {
                $lo = $mid + 1;
            } else {
                $hi = $mid;
            }
        }
        $jy = $lo - 1;
        $dayInYear = $f - self::jalaliStartDay($jy); // 0-based

        $jm = 1;
        while (true) {
            $len = self::jalaliMonthLength($jy, $jm);
            if ($dayInYear < $len) {
                break;
            }
            $dayInYear -= $len;
            $jm++;
        }
        return [
            'jalali_year'  => $jy,
            'jalali_month' => $jm,
            'jalali_day'   => $dayInYear + 1,
        ];
    }

    /**
     * jalaliToJd: [jalali_year, jalali_month, jalali_day] → astronomical JD.
     */
    private static function jalaliToJd(int $jy, int $jm, int $jd): int
    {
        $dayOffset = 0;
        for ($i = 1; $i < $jm; $i++) {
            $dayOffset += self::jalaliMonthLength($jy, $i);
        }
        $dayOffset += $jd - 1; // 0-based day within the month
        return self::JALALI_EPOCH_JD + self::jalaliStartDay($jy) + $dayOffset;
    }

    /**
     * Days in Jalali years 1..($y-1) = start-day offset of year $y.
     * O($y); for the years used here (≤ a few thousand) this is fine.
     */
    private static function jalaliStartDay(int $y): int
    {
        $days = 0;
        for ($i = 1; $i < $y; $i++) {
            $days += 365 + (self::isJalaliLeapYear($i) ? 1 : 0);
        }
        return $days;
    }

    /** Whether Jalali year $jy is a leap year (366 days, 12th month = 30). */
    private static function isJalaliLeapYear(int $jy): bool
    {
        $pos = ($jy - 1) % 33;
        return in_array($pos, [3, 7, 11, 15, 19, 23, 27, 31], true);
    }

    /** Gregorian Y/M/D → astronomical Julian Day. */
    private static function jdFromGregorian(int $y, int $m, int $d): int
    {
        $a = intdiv(14 - $m, 12);
        $yy = $y + 4800 - $a;
        $mm = $m + 12 * $a - 3;
        return $d
            + intdiv(153 * $mm + 2, 5)
            + 365 * $yy
            + intdiv($yy, 4)
            - intdiv($yy, 100)
            + intdiv($yy, 400)
            - 32045;
    }

    /**
     * Astronomical Julian Day → Gregorian Y/M/D.
     * Inverts jdFromGregorian via a binary search over the year,
     * then an exact day-within-year solve. Guarantees round-trip with jdFromGregorian.
     *
     * @return array{gregorian_year: int, gregorian_month: int, gregorian_day: int}
     */
    private static function jdToGregorian(int $jd): array
    {
        // Binary search the Gregorian year whose day-range contains $jd.
        $lo = 1;
        $hi = 10000;
        while (self::jdFromGregorian($hi, 3, 1) <= $jd) {
            $hi = intdiv($hi * 2, 1);
            if ($hi > 1000000) {
                break;
            }
        }
        while ($lo < $hi) {
            $mid = intdiv($lo + $hi, 2);
            if (self::jdFromGregorian($mid, 3, 1) <= $jd) {
                $lo = $mid + 1;
            } else {
                $hi = $mid;
            }
        }
        $gy = $lo; // first year whose March-1 is after $jd → candidate is $gy or $gy-1
        // $jd falls in year $gy-1 or $gy. Determine by comparing to Jan-1 of $gy.
        if (self::jdFromGregorian($gy, 1, 1) > $jd) {
            $gy = $gy - 1;
        }
        // Now find month + day within year $gy.
        $gm = 1;
        while (true) {
            $nextJd = $gm < 12
                ? self::jdFromGregorian($gy, $gm + 1, 1)
                : self::jdFromGregorian($gy + 1, 1, 1);
            if ($jd < $nextJd) {
                break;
            }
            $gm++;
        }
        $gday = $jd - self::jdFromGregorian($gy, $gm, 1) + 1;
        return [
            'gregorian_year'  => $gy,
            'gregorian_month' => $gm,
            'gregorian_day'   => $gday,
        ];
    }
}
