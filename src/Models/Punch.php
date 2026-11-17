<?php

namespace App\Models;

use App\Core\Database;

class Punch
{
    public static function create(array $data): array
    {
        $stmt = Database::query(
            "INSERT INTO punches (user_id, type, method, punched_at, lat, lng,
             distance_m, inside_fence, qr_token_hash, ip, user_agent, note)
             VALUES (:user_id, :type, :method, :punched_at, :lat, :lng,
             :distance_m, :inside_fence, :qr_token_hash, :ip, :user_agent, :note)",
            $data
        );
        return $data;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::query("SELECT * FROM punches WHERE id = ?", [$id]);
        $row  = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @return array<int, array>
     */
    public static function listWithFilters(
        string $from = '',
        string $to = '',
        string $userId = '',
        string $method = ''
    ): array
    {
        $where  = [];
        $params = [];

        if ($from !== '') {
            $where[]  = "date(p.punched_at, 'localtime') >= ?";
            $params[] = $from;
        }
        if ($to !== '') {
            $where[]  = "date(p.punched_at, 'localtime') <= ?";
            $params[] = $to;
        }
        if ($userId !== '') {
            $where[]  = "p.user_id = ?";
            $params[] = (int) $userId;
        }
        if ($method !== '') {
            $where[]  = "p.method = ?";
            $params[] = $method;
        }

        $sql = "SELECT p.*, u.name AS user_name
                FROM punches p
                LEFT JOIN users u ON u.id = p.user_id";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY p.punched_at DESC LIMIT 500";

        $stmt = Database::query($sql, $params);
        return $stmt->fetchAll();
    }
}
