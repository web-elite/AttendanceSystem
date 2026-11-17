<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Models\LeaveRequest;

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

        LeaveRequest::create([
            'user_id'    => $user->id,
            'type'       => $type,
            'start_at'   => $startAt,
            'end_at'     => $endAt,
            'reason'     => $reason,
            'status'     => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        Flash::set('success', 'درخواست مرخصی ثبت شد.');
        header('Location: /leave-requests');
        exit;
    }
}
