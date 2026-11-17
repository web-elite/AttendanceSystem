<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Models\LeaveRequest;

class LeavesController
{
    /**
     * Lists all pending leave requests for admin review.
     */
    public static function index(): string
    {
        $pending = LeaveRequest::allPending();
        return view('admin.leaves', [
            'pending' => $pending,
            'csrf'    => Csrf::field(),
        ]);
    }

    /**
     * Approves a leave request.
     */
    public static function approve(int $id): void
    {
        LeaveRequest::setReviewed($id, 'approved', Auth::user()->id);
        Flash::set('success', 'درخواست مرخصی تایید شد.');
        header('Location: /admin/leaves');
        exit;
    }

    /**
     * Rejects a leave request.
     */
    public static function reject(int $id): void
    {
        LeaveRequest::setReviewed($id, 'rejected', Auth::user()->id);
        Flash::set('success', 'درخواست مرخصی رد شد.');
        header('Location: /admin/leaves');
        exit;
    }
}
