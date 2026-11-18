<?php

namespace App\Core;

use App\Models\User;

class Auth
{
    private const COOKIE = 'crm_auth';

    /**
     * Logs in a user: sets a secure cookie, regenerates session ID.
     */
    public static function login(User $user): void
    {
        session_regenerate_id(true);
        $token = bin2hex(random_bytes(32));
        $_SESSION['auth_token'] = $token;
        $_SESSION['user_id']    = $user->id;
        $_SESSION['role']       = $user->role;

        setcookie(self::COOKIE, $token, [
            'expires'  => time() + 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
        ]);
    }

    /**
     * Logs out the current user.
     */
    public static function logout(): void
    {
        unset($_SESSION['auth_token'], $_SESSION['user_id'], $_SESSION['role']);
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']
            );
        }
        session_destroy();
    }

    /**
     * Returns the current authenticated user from the DB, or null.
     */
    public static function user(): ?User
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        $user = User::find((int) $_SESSION['user_id']);
        if ($user === null || !$user->is_active) {
            self::logout();
            return null;
        }
        return $user;
    }

    /**
     * Checks whether the user is authenticated.
     */
    public static function check(): bool
    {
        return self::user() !== null;
    }

    /**
     * Checks whether the current user has the admin role.
     */
    public static function isAdmin(): bool
    {
        return ($_SESSION['role'] ?? null) === 'admin';
    }
}
