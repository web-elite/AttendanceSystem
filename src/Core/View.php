<?php

namespace App\Core;

class View
{
    /**
     * Renders a view file with the given data and returns the HTML string.
     *
     * If the view path is not a layout fragment, it is wrapped in the
     * application layout (resources/views/layouts/app.php).
     *
     * @param  array<string, mixed>  $data  May include: 'user', 'title', 'isAdmin'
     */
    public static function render(string $view, array $data = []): string
    {
        $view = str_replace('.', '/', $view);
        $file = dirname(__DIR__, 2) . '/resources/views/' . $view . '.php';
        if (!file_exists($file)) {
            http_response_code(500);
            exit('View not found: ' . $view);
        }

        // Render the inner section first
        ob_start();
        $sectionData = $data;
        extract($sectionData, EXTR_SKIP);
        require $file;
        $content = ob_get_clean();

        // Wrap in the application layout (skip if view is itself a layout)
        $layout = dirname(__DIR__, 2) . '/resources/views/layouts/app.php';
        if (strpos($view, 'layouts/') === 0 || !file_exists($layout)) {
            return $content;
        }

        // Populate layout variables.
        // $authUser is ALWAYS the logged-in identity from the session, so every
        // page gets the sidebar. It is separate from a view's own 'user' data
        // (e.g. admin/user-form passes the edited user as 'user').
        $data['content']        = $content;
        $data['flashMessages']  = \App\Core\Flash::all();
        $data['authUser']       = \App\Core\Auth::user();
        $data['isAdmin']        = \App\Core\Auth::isAdmin();
        $data['title']          = $data['title'] ?? 'حضور و غیاب';

        extract($data, EXTR_SKIP);
        require $layout;
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────
    //  UI helpers: icons, avatars, Jalali dates, badges
    //  Shared by all views so markup stays consistent.
    // ─────────────────────────────────────────────────────────────

    /**
     * Inline SVG icon (24×24, stroke). Free of external libraries and
     * CSP-safe (no script involved). $name maps to the icon registry below.
     */
    public static function icon(string $name, string $class = 'w-5 h-5'): string
    {
        $icons = [
            // name => [path-data, fill?]
            'dashboard'  => ['<path d="M3 12l9-8 9 8M5 10v10a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1V10" stroke-linejoin="round"/>'],
            'users'      => ['<path d="M17 21v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M13 7a4 4 0 11-8 0 4 4 0 018 0zM21 21v-2a4 4 0 00-3-3.85M15.5 3.15a4 4 0 010 7.7" stroke-linecap="round" stroke-linejoin="round"/>'],
            'clock'      => ['<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round" stroke-linejoin="round"/>'],
            'calendar'   => ['<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18" stroke-linecap="round"/>'],
            'chart'      => ['<path d="M4 20V10M10 20V4M16 20v-9M21 20H3" stroke-linecap="round"/>'],
            'check'      => ['<path d="M20 6L9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/>'],
            'x'          => ['<path d="M18 6L6 18M6 6l12 12" stroke-linecap="round"/>'],
            'logout'     => ['<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9" stroke-linecap="round" stroke-linejoin="round"/>'],
            'login'      => ['<path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4M10 17l5-5-5-5M15 12H3" stroke-linecap="round" stroke-linejoin="round"/>'],
            'edit'       => ['<path d="M11 4H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2v-5M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4 9.5-9.5z" stroke-linecap="round" stroke-linejoin="round"/>'],
            'plus'       => ['<path d="M12 5v14M5 12h14" stroke-linecap="round"/>'],
            'download'   => ['<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3" stroke-linecap="round" stroke-linejoin="round"/>'],
            'pin'        => ['<path d="M12 21s-7-5.1-7-11a7 7 0 1114 0c0 5.9-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>'],
            'qr'         => ['<path d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z" stroke-linejoin="round"/><path d="M6 6h.01M17 6h.01M6 17h.01M17 17h.01" stroke-linecap="round"/>'],
            'warning'    => ['<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><path d="M12 9v4M12 17h.01" stroke-linecap="round"/>'],
            'info'       => ['<circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01" stroke-linecap="round"/>'],
            'settings'   => ['<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09a1.65 1.65 0 00-1-1.51 1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 110-4h.09a1.65 1.65 0 001.51-1 1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33h.08a1.65 1.65 0 001-1.51V3a2 2 0 114 0v.09a1.65 1.65 0 001 1.51h.08a1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82v.08a1.65 1.65 0 001.51 1H21a2 2 0 110 4h-.09a1.65 1.65 0 00-1.51 1z" stroke-linecap="round" stroke-linejoin="round"/>'],
            'trend-up'   => ['<path d="M23 6l-9.5 9.5-5-5L1 18M17 6h6v6" stroke-linecap="round" stroke-linejoin="round"/>'],
            'trend-down' => ['<path d="M23 18l-9.5-9.5-5 5L1 6M17 18h6v-6" stroke-linecap="round" stroke-linejoin="round"/>'],
            'file'       => ['<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8" stroke-linecap="round" stroke-linejoin="round"/>'],
            'filter'     => ['<path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z" stroke-linejoin="round"/>'],
            'power'      => ['<path d="M18.36 6.64a9 9 0 11-12.73 0M12 2v10" stroke-linecap="round" stroke-linejoin="round"/>'],
            'moon'       => ['<path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" stroke-linejoin="round"/>'],
            'sun'        => ['<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32l1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" stroke-linecap="round"/>'],
        ];

        $body = implode('', $icons[$name] ?? $icons['info']);

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" '
            . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" '
            . 'class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true">'
            . $body
            . '</svg>';
    }

    /**
     * Colored avatar circle with the user's initials (RTL-safe).
     */
    public static function avatar(string $name, string $extra = ''): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $p) {
            $initials .= mb_substr($p, 0, 1, 'UTF-8');
        }
        if ($initials === '') {
            $initials = '؟';
        }

        $palette = ['bg-primary/15 text-primary', 'bg-secondary/15 text-secondary', 'bg-accent/20 text-accent', 'bg-info/15 text-info', 'bg-success/15 text-success'];
        $idx     = crc32($name) % count($palette);

        return '<span class="avatar-circle ' . $palette[$idx] . ' ' . htmlspecialchars($extra, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($initials, ENT_QUOTES, 'UTF-8')
            . '</span>';
    }

    /**
     * Formats a Unix timestamp as a full Jalali date with Persian weekday.
     * Example: شنبه ۱۴۰۳/۰۷/۰۶
     */
    public static function jalali(int $timestamp, bool $withWeekday = true): string
    {
        $weekdays = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'];
        $text     = \App\Services\CalendarService::formatJalali($timestamp);
        if ($withWeekday) {
            $text = $weekdays[(int) date('w', $timestamp)] . ' ' . $text;
        }
        return $text;
    }

    /**
     * Converts Latin digits to Persian digits within a string.
     */
    public static function fa(string $text): string
    {
        return strtr($text, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ]);
    }
}
