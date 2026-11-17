<?php

namespace App\Core;

class Flash
{
    /**
     * Stores a flash message in the session.
     *
     * @param  string  $type   success | error | info | warning
     * @param  string  $message
     */
    public static function set(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    /**
     * Pops all flash messages and clears the session key.
     *
     * @return array{type:string, message:string}[]
     */
    public static function all(): array
    {
        $messages = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $messages;
    }
}
