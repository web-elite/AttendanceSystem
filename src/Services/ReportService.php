<?php

namespace App\Services;

use App\Core\Config;
use App\Core\Database;

class ReportService
{
    /**
     * Generates a per-working-day, per-user attendance report for a date range.
     *
     * - If $from/$to are empty, the current Jalali month is used (Jalali month boundary,
     *   NOT Gregorian — per AGENTS.md).
     * - Late/early counts and working-day filtering follow `holiday_mode` and the
     *   `min_in_time`/`max_out_time`/`grace_minutes` settings.
     *
     * @return array{
     *   from: string, to: string,
     *   rows: array<int, array>,           // one row per user per working day
     *   totals: array{total_days: int, total_hours: float, total_late: int, total_early: int},
     * }
     */
    public static function generate(string $from = '', string $to = '', string $userId = ''): array
    {
        $pdo = Database::conn();

        // ── Resolve the Gregorian Y-m-d range ──────────────────────────────
        [$fromYmd, $toYmd] = self::resolveRange($from, $to);

        // ── Settings ───────────────────────────────────────────────────────
        $minIn   = Config::str('min_in_time', '09:00');
        $maxOut  = Config::str('max_out_time', '17:00');
        $grace   = Config::int('grace_minutes', 0);
        $holidayMode = Config::str('holiday_mode', 'friday');

        $officialHolidays = ($holidayMode === 'official' || $holidayMode === 'both')
            ? self::officialHolidaysInRange($pdo, $fromYmd, $toYmd)
            : [];

        // ── Punch rows in range (all types) ───────────────────────────────
        $where = "date(p.punched_at) BETWEEN ? AND ?";
        $params = [$fromYmd, $toYmd];
        if ($userId !== '') {
            $where .= " AND p.user_id = ?";
            $params[] = (int) $userId;
        }
        $sql = "SELECT p.user_id, u.name AS user_name, p.punched_at, p.type, p.method,
                       p.note, p.edited_by, p.edited_at,
                       p.lat, p.lng, p.distance_m, p.inside_fence, p.is_estimate
                FROM punches p
                LEFT JOIN users u ON u.id = p.user_id
                WHERE $where
                ORDER BY u.name ASC, p.punched_at ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // ── Group punches per user per day ────────────────────────────────
        $byUserDay = [];   // [uid][dayYmd] = ['ins'=>[], 'outs'=>[], 'user_name'=>.., 'estimated'=>int]
        foreach ($rows as $row) {
            $uid = (int) $row['user_id'];
            $day = date('Y-m-d', strtotime($row['punched_at']));
            if (!isset($byUserDay[$uid][$day])) {
                $byUserDay[$uid][$day] = ['ins' => [], 'outs' => [], 'user_name' => $row['user_name'], 'estimated' => 0];
            }
            $byUserDay[$uid][$day]['user_name'] = $row['user_name'];
            if ((int) ($row['is_estimate'] ?? 0) === 1) {
                $byUserDay[$uid][$day]['estimated'] = 1;
            }
            if ($row['type'] === 'in') {
                $byUserDay[$uid][$day]['ins'][]  = strtotime($row['punched_at']);
            } else {
                $byUserDay[$uid][$day]['outs'][] = strtotime($row['punched_at']);
            }
        }

        // ── Build per-day summary rows (working days only) ────────────────
        $summary = [];
        $totals = ['total_days' => 0, 'total_hours' => 0.0, 'total_late' => 0, 'total_early' => 0];

        foreach ($byUserDay as $uid => $days) {
            foreach ($days as $dayYmd => $data) {
                if (!self::isWorkingDay($dayYmd, $holidayMode, $officialHolidays)) {
                    continue;
                }

                sort($data['ins']);
                sort($data['outs']);

                // Hours: pair each in with the next out (or now if forgotten).
                $hours = 0.0;
                $inCount = count($data['ins']);
                $now = time();
                for ($i = 0; $i < $inCount; $i++) {
                    $outTs = isset($data['outs'][$i]) ? $data['outs'][$i] : $now;
                    $hours += max(0.0, ($outTs - $data['ins'][$i]) / 3600.0);
                }

                // Late: earliest in-punch later than minIn + grace.
                $late = false;
                if ($inCount > 0) {
                    $deadline = self::timeWithGrace($dayYmd, $minIn, $grace);
                    $late = $data['ins'][0] > $deadline;
                }

                // Early: last out-punch before maxOut - grace (only when an out exists).
                $early = false;
                if (count($data['outs']) > 0) {
                    $endDeadline = self::timeWithGrace($dayYmd, $maxOut, -$grace);
                    $early = end($data['outs']) < $endDeadline;
                }

                $summary[] = [
                    'user_id'      => $uid,
                    'user_name'    => $data['user_name'],
                    'day'          => $dayYmd,
                    'jalali_day'   => self::toJalaliDisplay($dayYmd),
                    'first_in'     => $inCount ? date('H:i', $data['ins'][0]) : '',
                    'last_out'     => count($data['outs']) ? date('H:i', end($data['outs'])) : '',
                    'in_count'     => $inCount,
                    'out_count'    => count($data['outs']),
                    'hours'        => round($hours, 2),
                    'late'         => $late,
                    'early'        => $early,
                    'estimated'    => !empty($data['estimated']) ? 1 : 0,
                ];

                $totals['total_hours'] += $hours;
                if ($inCount > 0) {
                    $totals['total_days']++;
                }
                if ($late)  $totals['total_late']++;
                if ($early) $totals['total_early']++;
            }
        }

        usort($summary, static fn ($a, $b) =>
            strcmp($a['user_name'], $b['user_name']) ?: strcmp($a['day'], $b['day']));

        return [
            'from'   => $fromYmd,
            'to'     => $toYmd,
            'rows'   => $summary,
            'totals' => $totals,
        ];
    }

    /**
     * Resolves the Gregorian Y-m-d range. Empty $from/$to → current Jalali month.
     * @return array{0:string,1:string} [fromYmd, toYmd]
     */
    private static function resolveRange(string $from, string $to): array
    {
        $fromYmd = self::toYmd($from);
        $toYmd   = self::toYmd($to);

        if ($fromYmd === '' || $toYmd === '') {
            $range = CalendarService::currentJalaliMonthRange();
            $fromYmd = $fromYmd !== '' ? $fromYmd : date('Y-m-d', $range['start']);
            $toYmd   = $toYmd   !== '' ? $toYmd   : date('Y-m-d', $range['end']);
        }
        if ($fromYmd === '') $fromYmd = date('Y-m-d');
        if ($toYmd === '')   $toYmd   = $fromYmd;
        if ($toYmd < $fromYmd) [$fromYmd, $toYmd] = [$toYmd, $fromYmd];
        return [$fromYmd, $toYmd];
    }

    /**
     * Accepts "Y/M/D" or "Y-M-D" (Jalali or Gregorian ambiguous — treat as Gregorian Y-m-d
     * for DB filtering; the Jalali display conversion is applied separately) → "Y-m-d".
     */
    private static function toYmd(string $value): string
    {
        $value = trim(str_replace('/', '-', $value));
        if ($value === '' || !preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $value)) {
            return '';
        }
        [$y, $m, $d] = explode('-', $value);
        return sprintf('%04d-%02d-%02d', (int) $y, (int) $m, (int) $d);
    }

    /**
     * True if $ymd is a working day under the given holiday_mode.
     * Friday is a fixed weekly holiday (Iran). official/both modes also exclude
     * rows in the `holidays` table within range.
     */
    private static function isWorkingDay(string $ymd, string $mode, array $officialHolidays): bool
    {
        $weekday = (int) date('N', strtotime($ymd)); // 1=Mon .. 5=Fri .. 7=Sun
        // Friday (5) is always off in Iran.
        if ($weekday === 5) {
            return false;
        }
        if (($mode === 'official' || $mode === 'both') && in_array($ymd, $officialHolidays, true)) {
            return false;
        }
        // 'none' / 'friday' modes: only Friday is excluded.
        return true;
    }

    /**
     * Official holiday dates (Y-m-d) in [from,to] from the `holidays` table.
     * @return list<string>
     */
    private static function officialHolidaysInRange(\PDO $pdo, string $fromYmd, string $toYmd): array
    {
        $stmt = $pdo->prepare(
            "SELECT date FROM holidays WHERE date BETWEEN ? AND ? ORDER BY date"
        );
        $stmt->execute([$fromYmd, $toYmd]);
        return array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Timestamp for "HH:MM on $ymd" with a grace offset in minutes.
     */
    private static function timeWithGrace(string $ymd, string $hm, int $offsetMinutes): int
    {
        [$hh, $mm] = array_map('intval', explode(':', $hm . ':00'));
        return mktime($hh, $mm + $offsetMinutes, 0,
            (int) date('n', strtotime($ymd)),
            (int) date('j', strtotime($ymd)),
            (int) date('Y', strtotime($ymd))
        );
    }

    /**
     * Converts a Gregorian Y-m-d to a Jalali "Y/M/D" string for display.
     */
    private static function toJalaliDisplay(string $ymd): string
    {
        $g = CalendarService::gregorianToJalali(
            (int) date('Y', strtotime($ymd)),
            (int) date('n', strtotime($ymd)),
            (int) date('j', strtotime($ymd))
        );
        return sprintf('%04d/%02d/%02d', $g['jalali_year'], $g['jalali_month'], $g['jalali_day']);
    }

    /**
     * Converts a report array (per-day summary rows) to a CSV string.
     */
    public static function toCsv(array $report): string
    {
        $fh = fopen('php://temp', 'rw');

        fputcsv($fh, [
            'کارمند', 'تاریخ (هجری)', 'اولین ورود', 'آخرین خروج',
            'ساعات', 'تأخیر', 'مغایر پایان', 'ورودها', 'خروج‌ها', 'تخمینی',
        ], ';', '"', '');

        foreach ($report['rows'] as $row) {
            fputcsv($fh, [
                $row['user_name'] ?? '',
                $row['jalali_day'] ?? '',
                $row['first_in'] ?? '',
                $row['last_out'] ?? '',
                $row['hours'] ?? 0,
                !empty($row['late']) ? 'بله' : '',
                !empty($row['early']) ? 'بله' : '',
                $row['in_count'] ?? 0,
                $row['out_count'] ?? 0,
                !empty($row['estimated']) ? 'بله' : '',
            ], ';', '"', '');
        }

        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        return $csv;
    }
}
