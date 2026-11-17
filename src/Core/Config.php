<?php

namespace App\Core;

use App\Models\Setting;

class Config
{
    /**
     * Returns a typed setting value from the settings table.
     * Falls back to $default if the key is missing.
     *
     * @template T
     * @param  string  $key
     * @param  T|null  $default
     * @return T
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Setting::get($key);
        return $value !== null ? $value : $default;
    }

    /**
     * Returns a boolean setting (SQLite stores as 0/1 text).
     */
    public static function bool(string $key, bool $default = false): bool
    {
        $val = self::get($key, null);
        return $val === null ? $default : (bool) $val;
    }

    /**
     * Returns an integer setting.
     */
    public static function int(string $key, int $default = 0): int
    {
        $val = self::get($key, null);
        return $val === null ? $default : (int) $val;
    }

    /**
     * Returns a string setting.
     */
    public static function str(string $key, string $default = ''): string
    {
        $val = self::get($key, null);
        return $val === null ? $default : (string) $val;
    }

    /**
     * Returns a decoded JSON setting.
     */
    public static function json(string $key, mixed $default = null): mixed
    {
        $val = self::get($key, null);
        if ($val === null) {
            return $default;
        }
        $decoded = json_decode($val, true);
        return $decoded === null && $val !== 'null' ? $default : $decoded;
    }
}
