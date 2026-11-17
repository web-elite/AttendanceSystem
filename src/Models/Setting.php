<?php

namespace App\Models;

use App\Core\Database;

class Setting
{
    /**
     * Returns the raw string value for a setting key, or null.
     */
    public static function get(string $key): ?string
    {
        $stmt = Database::query("SELECT value FROM settings WHERE key = ?", [$key]);
        $row  = $stmt->fetch();
        return $row ? $row['value'] : null;
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        $stmt = Database::query("SELECT key, value FROM settings");
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['key']] = $row['value'];
        }
        return $result;
    }

    public static function set(string $key, string $value): void
    {
        Database::query(
            "INSERT INTO settings (key, value) VALUES (?, ?)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value",
            [$key, $value]
        );
    }
}
