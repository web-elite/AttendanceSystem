<?php

namespace App\Services;

use App\Core\Database;

class RateLimiter
{
    /**
     * Checks whether the current IP is rate-limited for the given action.
     *
     * @param  string  $action     e.g. 'login'
     * @param  int     $max        max attempts in the window
     * @param  int     $windowSecs window in seconds
     * @return bool true = limited (should block)
     */
    public static function isLimited(string $action, int $max = 5, int $windowSecs = 900): bool
    {
        $pdo = Database::conn();
        $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            action TEXT NOT NULL,
            ip TEXT NOT NULL,
            created_at TEXT NOT NULL
        )");

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Clean up old entries
        $pdo->prepare(
            "DELETE FROM rate_limits WHERE created_at < datetime('now', '-1 day')"
        )->execute();

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM rate_limits
             WHERE action = ? AND ip = ? AND created_at >= datetime('now', ?)"
        );
        $stmt->execute([$action, $ip, '-' . $windowSecs . ' seconds']);
        $count = (int) $stmt->fetchColumn();

        return $count >= $max;
    }

    /**
     * Records one attempt.
     */
    public static function record(string $action): void
    {
        $pdo = Database::conn();
        $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            action TEXT NOT NULL,
            ip TEXT NOT NULL,
            created_at TEXT NOT NULL
        )");

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $pdo->prepare(
            "INSERT INTO rate_limits (action, ip, created_at) VALUES (?, ?, datetime('now'))"
        )->execute([$action, $ip]);
    }

    /**
     * Resets the counter (e.g. after a successful login).
     */
    public static function reset(string $action): void
    {
        $pdo = Database::conn();
        $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            action TEXT NOT NULL,
            ip TEXT NOT NULL,
            created_at TEXT NOT NULL
        )");

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $pdo->prepare(
            "DELETE FROM rate_limits WHERE action = ? AND ip = ?"
        )->execute([$action, $ip]);
    }
}
