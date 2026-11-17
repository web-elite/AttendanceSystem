<?php

namespace App\Models;

use App\Core\Database;

class AuditLog
{
    /**
     * Records an admin action.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function record(
        int    $actorId,
        string $action,
        string $entity,
        int    $entityId,
        array  $meta = []
    ): void
    {
        Database::query(
            "INSERT INTO audit_logs (actor_id, action, entity, entity_id, meta, created_at)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $actorId,
                $action,
                $entity,
                $entityId,
                json_encode($meta, JSON_UNESCAPED_UNICODE),
                date('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * @return array<int, array>
     */
    public static function list(int $limit = 100): array
    {
        $stmt = Database::query(
            "SELECT al.*, u.name AS actor_name
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.actor_id
             ORDER BY al.created_at DESC
             LIMIT ?",
            [$limit]
        );
        return $stmt->fetchAll();
    }
}
