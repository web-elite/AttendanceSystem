<?php

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Services\ReportService;
use App\Services\CalendarService;

class ReportsController
{
    /**
     * Shows the report form (date range, user, export CSV).
     *
     * Date inputs are JALALI "YYYY/MM/DD"; converted to Gregorian here.
     * Empty from/to → current Jalali month (handled inside ReportService).
     */
    public static function index(): string
    {
        $preset = $_GET['preset'] ?? '';
        [$fromJ, $toJ] = self::presetRange($preset);

        if ($fromJ === null) {
            $fromJ = trim((string) ($_GET['from'] ?? ''));
        }
        if ($toJ === null) {
            $toJ = trim((string) ($_GET['to'] ?? ''));
        }
        $userId = $_GET['user_id'] ?? '';

        // Convert Jalali → Gregorian for the report engine.
        $fromYmd = $fromJ !== '' ? CalendarService::jalaliToYmd($fromJ) : '';
        $toYmd   = $toJ   !== '' ? CalendarService::jalaliToYmd($toJ)   : '';
        if ($fromJ !== '' && $fromYmd === '') { $fromJ = ''; }
        if ($toJ !== '' && $toYmd === '')   { $toJ = ''; }

        $report = ReportService::generate($fromYmd, $toYmd, $userId);

        // Show the effective range back as Jalali (what the admin sees).
        $fromJ = $fromJ !== '' ? $fromJ : CalendarService::formatJalali(strtotime($report['from']));
        $toJ   = $toJ   !== '' ? $toJ   : CalendarService::formatJalali(strtotime($report['to']));

        // CSV export
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="report-'
                . $report['from'] . '-' . $report['to'] . '.csv"');
            exit(ReportService::toCsv($report));
        }

        return view('admin.reports', [
            'report'  => $report,
            'csrf'    => Csrf::field(),
            'filters' => [
                'from'    => $fromJ,
                'to'      => $toJ,
                'user_id' => $userId,
            ],
        ]);
    }

    /**
     * Quick range presets (Jalali). Returns [fromJalali, toJalali];
     * [null, null] when the preset is unknown.
     */
    private static function presetRange(string $preset): array
    {
        return match ($preset) {
            'today' => [CalendarService::formatJalali(time()), CalendarService::formatJalali(time())],
            'week'  => [CalendarService::formatJalali(strtotime('-6 days')), CalendarService::formatJalali(time())],
            'month' => (static function () {
                $r = CalendarService::currentJalaliMonthRange();
                return [CalendarService::formatJalali($r['start']), CalendarService::formatJalali($r['end'])];
            })(),
            default => [null, null],
        };
    }
}
