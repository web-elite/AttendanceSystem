<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Models\LeaveRequest;
use App\Services\ReportService;
use App\Services\CalendarService;

class DashboardController
{
    /**
     * Admin dashboard: today's stats + pending leaves + weekly graph + recent punches.
     */
    public static function index(): string
    {
        $pdo = Database::conn();
        $today = date('Y-m-d');

        // Total employees
        $totalEmployees = (int) $pdo->query(
            "SELECT COUNT(*) FROM users WHERE role = 'employee' AND is_active = 1"
        )->fetchColumn();

        // Present today (has an "in" punch)
        $presentStmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT p.user_id) FROM punches p
             WHERE p.type = 'in' AND date(p.punched_at) = ?"
        );
        $presentStmt->execute([$today]);
        $present = (int) $presentStmt->fetchColumn();

        // ── Late arrivals today (first in-punch after min_in_time + grace) ──
        $minIn  = Config::str('min_in_time', '09:00');
        $grace  = Config::int('grace_minutes', 0);
        [$h, $m] = array_map('intval', explode(':', $minIn));
        $deadlineTs = mktime($h, $m + $grace, 0);
        $lateStmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT p.user_id) FROM punches p
             WHERE p.type = 'in' AND date(p.punched_at) = ? AND time(p.punched_at) > time(?)"
        );
        $lateStmt->execute([$today, date('H:i:s', $deadlineTs)]);
        $lateToday = (int) $lateStmt->fetchColumn();

        // ── Pending leave requests ──────────────────────────────────────
        $pendingLeaves = count(LeaveRequest::allPending());

        // ── Employees on approved leave TODAY (hourly or daily) ─────────
        // start_at / end_at are full datetimes (YYYY-MM-DD HH:MM:SS),
        // so compare the DATE part of both with today.
        $leaveTodayStmt = $pdo->prepare(
            "SELECT lr.id, lr.type, lr.start_at, lr.end_at, u.name AS user_name
             FROM leave_requests lr
             JOIN users u ON u.id = lr.user_id
             WHERE lr.status = 'approved'
               AND date(lr.start_at) <= ? AND date(lr.end_at) >= ?
             ORDER BY lr.start_at ASC
             LIMIT 10"
        );
        $leaveTodayStmt->execute([$today, $today]);
        $onLeaveToday = $leaveTodayStmt->fetchAll();

        // ── Today's punch list per employee (first in / last out) ───────
        $dayStmt = $pdo->prepare(
            "SELECT u.id, u.name,
                    MIN(CASE WHEN p.type = 'in'  THEN p.punched_at END) AS first_in,
                    MAX(CASE WHEN p.type = 'out' THEN p.punched_at END) AS last_out
             FROM users u
             LEFT JOIN punches p
                    ON p.user_id = u.id AND date(p.punched_at) = ?
             WHERE u.role = 'employee' AND u.is_active = 1
             GROUP BY u.id, u.name
             ORDER BY u.name ASC"
        );
        $dayStmt->execute([$today]);
        $dayRows = $dayStmt->fetchAll();

        $lateCut = date('H:i:s', $deadlineTs);
        $presentTodayList = [];
        $absentList = [];
        foreach ($dayRows as $r) {
            if ($r['first_in'] !== null) {
                $isLate = substr($r['first_in'], 11, 8) > $lateCut;
                $presentTodayList[] = [
                    'name'     => $r['name'],
                    'first_in' => $r['first_in'],
                    'last_out' => $r['last_out'],
                    'late'     => $isLate,
                ];
            } else {
                $absentList[] = ['name' => $r['name']];
            }
        }

        // ── Weekly punches (last 7 days, in + out) ──────────────────────
        $weekStmt = $pdo->prepare(
            "SELECT date(p.punched_at) AS d,
                    SUM(CASE WHEN p.type = 'in' THEN 1 ELSE 0 END) AS ins,
                    SUM(CASE WHEN p.type = 'out' THEN 1 ELSE 0 END) AS outs
             FROM punches p
             WHERE date(p.punched_at) >= date(?, '-6 days')
             GROUP BY d ORDER BY d"
        );
        $weekStmt->execute([$today]);
        $weekRows = $weekStmt->fetchAll();

        // Normalize into 7 buckets (oldest → today) with Jalali weekday labels
        $faWeekdays = [1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه', 4 => 'پنجشنبه', 5 => 'جمعه', 6 => 'شنبه', 0 => 'یکشنبه'];
        $byDay = [];
        foreach ($weekRows as $r) {
            $byDay[$r['d']] = $r;
        }
        $week = [];
        for ($i = 6; $i >= 0; $i--) {
            $ts = strtotime("-$i days");
            $d = date('Y-m-d', $ts);
            $r = $byDay[$d] ?? null;
            $week[] = [
                'day'      => $faWeekdays[(int) date('w', $ts)],
                'ins'      => $r ? (int) $r['ins'] : 0,
                'outs'     => $r ? (int) $r['outs'] : 0,
                'is_today' => $i === 0,
            ];
        }

        // ── Recent punches (last 8, with user names) ────────────────────
        $recentStmt = $pdo->prepare(
            "SELECT p.id, p.type, p.method, p.punched_at, u.name AS user_name
             FROM punches p
             LEFT JOIN users u ON u.id = p.user_id
             ORDER BY p.punched_at DESC
             LIMIT 8"
        );
        $recentStmt->execute();
        $recentPunches = $recentStmt->fetchAll();

        // ── Current Jalali month summary ───────────────────────────────
        $monthRange = CalendarService::currentJalaliMonthRange();
        $month = ReportService::generate(date('Y-m-d', $monthRange['start']), date('Y-m-d', $monthRange['end']));

        // On-time rate this month (days without any late arrival)
        $onDays  = 0;
        $allDays = 0;
        foreach ($month['rows'] ?? [] as $row) {
            $allDays++;
            if (empty($row['late'])) {
                $onDays++;
            }
        }
        $onTimeRate = $allDays > 0 ? (int) round($onDays / $allDays * 100) : 100;

        return view('admin.dashboard', [
            'user'           => Auth::user(),
            'totalEmployees' => $totalEmployees,
            'presentToday'   => $present,
            'absentToday'    => max(0, $totalEmployees - $present),
            'lateToday'      => $lateToday,
            'pendingLeaves'  => $pendingLeaves,
            'week'           => $week,
            'recentPunches'  => $recentPunches,
            'onLeaveToday'   => $onLeaveToday,
            'presentTodayList' => $presentTodayList,
            'absentList'     => $absentList,
            'monthHours'     => (float) ($month['totals']['total_hours'] ?? 0),
            'monthDays'      => (int) ($month['totals']['total_days'] ?? 0),
            'onTimeRate'     => $onTimeRate,
        ]);
    }
}
