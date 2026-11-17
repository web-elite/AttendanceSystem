<?php

namespace App\Core;

class Csrf
{
    private const FIELD = '_token';

    /**
     * Returns the current session CSRF token, creating one if absent.
     */
    public static function token(): string
    {
        if (empty($_SESSION[self::FIELD])) {
            $_SESSION[self::FIELD] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::FIELD];
    }

    /**
     * Validates the POSTed token against the session token.
     * Exits with 419 on failure.
     */
    public static function verify(): void
    {
        $sent = $_POST[self::FIELD] ?? '';
        if (empty($sent) || !hash_equals($_SESSION[self::FIELD] ?? '', $sent)) {
            http_response_code(419);
            exit('CSRF token validation failed. Please try again.');
        }
    }

    /**
     * HTML input for embedding in forms.
     */
    public static function field(): string
    {
        $t = self::token();
        return '<input type="hidden" name="' . self::FIELD . '" value="' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '">';
    }
}
