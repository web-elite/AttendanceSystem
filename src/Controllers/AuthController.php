<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Models\User;
use App\Services\RateLimiter;

class AuthController
{
    /**
     * Shows the login form.
     */
    public static function showLogin(): string
    {
        return view('auth.login', [
            'csrf' => Csrf::field(),
            'errors' => Flash::all(),
        ]);
    }

    /**
     * Handles the login POST.
     */
    public static function attempt(): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            Flash::set('error', 'نام کاربری و رمز عبور الزامی است.');
            header('Location: /login');
            return;
        }

        // Rate limit: 5 attempts per 15 min per IP
        if (RateLimiter::isLimited('login', 5, 900)) {
            Flash::set('error', 'تلاش‌های ناموفق زیاد است. لطفاً بعد از ۱۵ دقیقه دوباره تلاش کنید.');
            header('Location: /login');
            return;
        }

        $user = User::findByUsername($username);
        if ($user === null || !$user->is_active) {
            RateLimiter::record('login');
            Flash::set('error', 'نام کاربری یا رمز عبور اشتباه است.');
            header('Location: /login');
            return;
        }

        if (!password_verify($password, $user->password_hash)) {
            RateLimiter::record('login');
            Flash::set('error', 'نام کاربری یا رمز عبور اشتباه است.');
            header('Location: /login');
            return;
        }

        RateLimiter::reset('login');
        Auth::login($user);

        // A QR scan was done BEFORE login (guest scanned the wall code with
        // the native camera) → register that punch automatically now.
        if (!empty($_SESSION['pending_qr_hash'])) {
            \App\Controllers\PunchController::resumePendingScan();
        }

        header('Location: /');
        exit;
    }

    /**
     * Destroys the session and redirects to login.
     */
    public static function logout(): void
    {
        Auth::logout();
        header('Location: /login');
        exit;
    }

    /**
     * The daisyUI theme names compiled into app.css (35 built-ins).
     * Used both for validation and for rendering the picker.
     *
     * @return list<string>
     */
    public static function themeWhitelist(): array
    {
        return [
            'light', 'dark', 'cupcake', 'bumblebee', 'emerald', 'corporate',
            'synthwave', 'retro', 'cyberpunk', 'valentine', 'halloween', 'garden',
            'forest', 'aqua', 'lofi', 'pastel', 'fantasy', 'wireframe', 'black',
            'luxury', 'dracula', 'cmyk', 'autumn', 'business', 'acid', 'lemonade',
            'night', 'coffee', 'winter', 'dim', 'nord', 'sunset', 'caramellatte',
            'abyss', 'silk',
        ];
    }

    /**
     * Handles the theme-selection POST (any logged-in user, no admin check).
     */
    public static function setTheme(): void
    {
        $user = Auth::user();
        if ($user === null) {
            header('Location: /login');
            exit;
        }

        $theme = $_POST['theme'] ?? '';

        if (!in_array($theme, self::themeWhitelist(), true)) {
            Flash::set('error', 'تم انتخابی معتبر نیست.');
            header('Location: /');
            exit;
        }

        User::setTheme($user->id, $theme);
        Flash::set('success', 'ظاهر داشبورد با تم «' . $theme . '» ذخیره شد.');

        // PRG: redirect back to where the picker was used
        $back = $_POST['back'] ?? '/';
        $back = parse_url($back, PHP_URL_PATH) ?: '/'; // same-site path only
        header('Location: ' . $back);
        exit;
    }
}
