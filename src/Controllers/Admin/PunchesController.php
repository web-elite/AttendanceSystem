<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Models\Punch;
use App\Core\Database;
use App\Models\AuditLog;
use App\Services\CalendarService;

class PunchesController
{
    /**
     * Lists punches (filterable by date range, user, method).
     *
     * Date inputs are JALALI "YYYY/MM/DD" (the whole app is Jalali-first).
     * They are converted here to Gregorian Y-m-d for the DB query, and
     * the Jalali strings are passed back so the inputs keep showing what
     * the admin typed.
     */
    public static function index(): string
    {
        $preset = $_GET['preset'] ?? '';
        [$fromJ, $toJ] = self::presetRange($preset);

        // Preset wins when given; otherwise use the typed Jalali values.
        if ($fromJ === null) {
            $fromJ = trim((string) ($_GET['from'] ?? ''));
        }
        if ($toJ === null) {
            $toJ = trim((string) ($_GET['to'] ?? ''));
        }
        $userId = $_GET['user_id'] ?? '';
        $method = $_GET['method'] ?? '';

        $fromYmd = $fromJ !== '' ? CalendarService::jalaliToYmd($fromJ) : '';
        $toYmd   = $toJ   !== '' ? CalendarService::jalaliToYmd($toJ)   : '';
        // Drop invalid Jalali dates so they don't filter out everything.
        if ($fromJ !== '' && $fromYmd === '') { $fromJ = ''; }
        if ($toJ !== '' && $toYmd === '')   { $toJ = ''; }

        $punches = Punch::listWithFilters($fromYmd, $toYmd, $userId, $method);
        $users   = \App\Models\User::all();

        return view('admin.punches', [
            'punches' => $punches,
            'users'   => $users,
            'csrf'    => Csrf::field(),
            'filters' => [
                'from'   => $fromJ,
                'to'     => $toJ,
                'user_id' => $userId,
                'method' => $method,
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

    /**
     * Shows the edit form for a single punch.
     */
    public static function edit(int $id): string
    {
        $punch = Punch::find($id);
        if ($punch === null) {
            http_response_code(404);
            exit('رکورد مورد نظر یافت نشد.');
        }
        return view('admin.punch-form', [
            'punch' => $punch,
            'csrf'  => Csrf::field(),
        ]);
    }

    /**
     * Updates a punch (admin edit → audit log).
     */
    public static function update(int $id): void
    {
        $punch = Punch::find($id);
        if ($punch === null) {
            Flash::set('error', 'رکورد مورد نظر یافت نشد.');
            header('Location: /admin/punches');
            return;
        }

        $newType    = $_POST['type'] ?? $punch['type'];
        $newMethod  = $_POST['method'] ?? $punch['method'];
        $newNote    = $_POST['note'] ?? $punch['note'];

        // The admin form submits a Jalali datetime "YYYY/MM/DD[ HH:MM[:SS]]".
        // Parse it to a Gregorian "YYYY-MM-DD HH:MM:SS" for storage.
        $rawPunchedAt = trim($_POST['punched_at'] ?? $punch['punched_at']);
        $newPunchedAt = self::normalizePunchedAt($rawPunchedAt);
        if ($newPunchedAt === null) {
            Flash::set('error', 'قالب تاریخ نامعتبر است. نمونه صحیح: 1405/01/15 09:30');
            header('Location: /admin/punches/' . (int) $id . '/edit');
            return;
        }

        $pdo = Database::conn();
        $pdo->prepare(
            "UPDATE punches SET type = ?, punched_at = ?, method = ?, note = ?,
             edited_by = ?, edited_at = ? WHERE id = ?"
        )->execute([
            $newType, $newPunchedAt, $newMethod, $newNote,
            Auth::user()->id, date('Y-m-d H:i:s'), $id,
        ]);

        // Audit log
        AuditLog::record(
            Auth::user()->id,
            'punch_edit',
            'punches',
            $id,
            [
                'from' => $punch,
                'to'   => ['type' => $newType, 'punched_at' => $newPunchedAt, 'method' => $newMethod, 'note' => $newNote],
            ]
        );

        Flash::set('success', 'ثبت به‌روزرسانی شد.');
        header('Location: /admin/punches');
        exit;
    }

    /**
     * Normalizes a submitted punch datetime to Gregorian "YYYY-MM-DD HH:MM:SS".
     *
     * Accepts:
     *  - Jalali "1405/01/15", "1405/01/15 09:30", "1405-01-15 09:30:45"
     *  - Legacy Gregorian "2026-03-05 09:30:00" (passed through unchanged)
     *
     * @return string|null Null when the input cannot be parsed.
     */
    private static function normalizePunchedAt(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        // Split date and optional time parts (first space).
        $parts  = preg_split('/\s+/', $raw, 2);
        $dateIn = $parts[0];
        $timeIn = $parts[1] ?? '00:00:00';

        // Gregorian passthrough (legacy values: 4-digit year < 1700 is Jalali).
        $year = (int) strtok($dateIn, '/-');
        if ($year >= 1700) {
            $ts = strtotime($raw);
            return $ts === false ? null : date('Y-m-d H:i:s', $ts);
        }

        // Jalali date → Gregorian.
        try {
            $dayTs = \App\Services\CalendarService::parseJalaliDate($dateIn);
        } catch (\Throwable $e) {
            return null;
        }

        // Normalize time to H:H:M:S.
        if (preg_match('/^(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?$/', trim($timeIn), $m)) {
            $h = (int) $m[1]; $mi = (int) $m[2]; $s = (int) ($m[3] ?? 0);
            if ($h > 23 || $mi > 59 || $s > 59) {
                return null;
            }
            $greg = \App\Services\CalendarService::jalaliToGregorian(
                (int) strtok($dateIn, '/-'),
                (int) explode('/', str_replace('-', '/', $dateIn))[1],
                (int) explode('/', str_replace('-', '/', $dateIn))[2]
            );
            return sprintf(
                '%04d-%02d-%02d %02d:%02d:%02d',
                $greg['gregorian_year'], $greg['gregorian_month'], $greg['gregorian_day'],
                $h, $mi, $s
            );
        }

        return null;
    }
}
