<?php

namespace App\Models;

use App\Core\Database;

class LeaveRequest
{
    public static function create(array $data): void
    {
        Database::query(
            "INSERT INTO leave_requests
             (user_id, type, start_at, end_at, reason, status, reviewed_by, reviewed_at, created_at)
             VALUES
             (:user_id, :type, :start_at, :end_at, :reason, :status,
              NULL, NULL, :created_at)",
            $data
        );
    }

    /**
     * @return array<int, array>
     */
    public static function forUser(int $userId): array
    {
        $stmt = Database::query(
            "SELECT lr.*, u.name AS user_name
             FROM leave_requests lr
             LEFT JOIN users u ON u.id = lr.user_id
             WHERE lr.user_id = ?
             ORDER BY lr.created_at DESC",
            [$userId]
        );
        return $stmt->fetchAll();
    }

    /**
     * @return array<int, array>
     */
    public static function allPending(): array
    {
        $stmt = Database::query(
            "SELECT lr.*, u.name AS user_name
             FROM leave_requests lr
             LEFT JOIN users u ON u.id = lr.user_id
             WHERE lr.status = 'pending'
             ORDER BY lr.created_at ASC"
        );
        return $stmt->fetchAll();
    }

    public static function setReviewed(int $id, string $status, int $adminId): void
    {
        Database::query(
            "UPDATE leave_requests
             SET status = ?, reviewed_by = ?, reviewed_at = ?
             WHERE id = ?",
            [$status, $adminId, date('Y-m-d H:i:s'), $id]
        );
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::query(
            "SELECT lr.*, u.name AS user_name
             FROM leave_requests lr
             LEFT JOIN users u ON u.id = lr.user_id
             WHERE lr.id = ?",
            [$id]
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
