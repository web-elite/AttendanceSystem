<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Models\LeaveRequest;
use App\Services\CalendarService;

class LeaveController
{
    /**
     * Lists the current user's leave requests.
     */
    public static function index(): string
    {
        $user = Auth::user();
        $requests = LeaveRequest::forUser($user->id);

        return view('employee.leaves', [
            'user'     => $user,
            'requests' => $requests,
            'csrf'     => Csrf::field(),
        ]);
    }

    /**
     * Creates a new leave request (hourly or daily).
     */
    public static function create(): void
    {
        $user = Auth::user();

        $type     = $_POST['type'] ?? 'hourly';
        $startAt  = $_POST['start_at'] ?? '';
        $endAt    = $_POST['end_at'] ?? '';
        $reason   = trim($_POST['reason'] ?? '');

        if (!in_array($type, ['hourly', 'daily'], true)) {
            Flash::set('error', 'نوع مرخصی نامعتبر است.');
            header('Location: /leave-requests');
            return;
        }

        if ($startAt === '' || $endAt === '') {
            Flash::set('error', 'شروع و پایان الزامی است.');
            header('Location: /leave-requests');
            return;
        }

        if ($reason === '') {
            Flash::set('error', 'دلیل مرخصی الزامی است.');
            header('Location: /leave-requests');
            return;
        }

        // User input is Jalali ("1405/08/20" or "1405/08/20 09:30"); storage
        // is Gregorian Y-m-d H:i:s (CalendarService is the only converter).
        $startStore = self::jalaliToStore($startAt, $type === 'hourly');
        $endStore   = self::jalaliToStore($endAt,   $type === 'hourly');

        if ($startStore === null || $endStore === null) {
            Flash::set('error', 'قالب تاریخ نامعتبر است. نمونه درست: 1405/08/20' . ($type === 'hourly' ? ' 09:30' : ''));
            header('Location: /leave-requests');
            return;
        }

        if (strtotime($endStore) < strtotime($startStore)) {
            Flash::set('error', 'پایان مرخصی نمی‌تواند قبل از شروع آن باشد.');
            header('Location: /leave-requests');
            return;
        }

        LeaveRequest::create([
            'user_id'    => $user->id,
            'type'       => $type,
            'start_at'   => $startStore,
            'end_at'     => $endStore,
            'reason'     => $reason,
            'status'     => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        Flash::set('success', 'درخواست مرخصی ثبت شد.');
        header('Location: /leave-requests');
        exit;
    }

    /**
     * Converts a Jalali date (or datetime) from the picker to the storage
     * format "Y-m-d H:i:s". Daily leaves store midnight boundaries; hourly
     * keeps the chosen time (defaults to 00:00 when time is missing).
     */
    private static function jalaliToStore(string $raw, bool $withTime): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $parts  = preg_split('/\s+/', $raw, 2);
        $dateIn = $parts[0];
        $timeIn = $withTime ? (trim($parts[1] ?? '') ?: '00:00:00') : '00:00:00';

        try {
            $dayTs = CalendarService::parseJalaliDate($dateIn);
        } catch (\Throwable) {
            return null;
        }

        if (!preg_match('/^(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?$/', $timeIn, $m)) {
            return null;
        }
        $h = (int) $m[1];
        $mi = (int) $m[2];
        $s = (int) ($m[3] ?? 0);
        if ($h > 23 || $mi > 59 || $s > 59) {
            return null;
        }

        return date('Y-m-d', $dayTs) . sprintf(' %02d:%02d:%02d', $h, $mi, $s);
    }
}
