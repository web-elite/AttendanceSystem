<?php

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Flash;
use App\Models\User;
use App\Core\Database;
use App\Services\CalendarService;

class UsersController
{
    public static function index(): string
    {
        $users = User::all();
        return view('admin.users', [
            'users'  => $users,
            'csrf'   => Csrf::field(),
        ]);
    }

    public static function create(): string
    {
        return view('admin.user-form', [
            'user'  => null,
            'csrf'  => Csrf::field(),
        ]);
    }

    public static function store(): void
    {
        $name     = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'employee';

        if ($name === '' || $username === '') {
            Flash::set('error', 'نام و نام کاربری الزامی است.');
            header('Location: /admin/users/create');
            return;
        }

        if (strlen($password) < 8) {
            Flash::set('error', 'رمز عبور باید حداقل ۸ کاراکتر باشد.');
            header('Location: /admin/users/create');
            return;
        }

        if (User::findByUsername($username) !== null) {
            Flash::set('error', 'این نام کاربری قبلاً ثبت شده است.');
            header('Location: /admin/users/create');
            return;
        }

        User::create(array_merge([
            'name'          => $name,
            'username'      => $username,
            'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
            'role'          => in_array($role, ['admin', 'employee'], true) ? $role : 'employee',
            'is_active'     => 1,
            'created_at'    => date('Y-m-d H:i:s'),
        ], self::profileFromPost()));

        Flash::set('success', 'کاربر جدید ایجاد شد.');
        header('Location: /admin/users');
        exit;
    }

    public static function edit(int $id): string
    {
        $user = User::find($id);
        if ($user === null) {
            http_response_code(404);
            exit('کاربر یافت نشد.');
        }
        return view('admin.user-form', [
            'user'  => $user->toRow(),
            'csrf'  => Csrf::field(),
        ]);
    }

    public static function update(int $id): void
    {
        $user = User::find($id);
        if ($user === null) {
            Flash::set('error', 'کاربر یافت نشد.');
            header('Location: /admin/users');
            return;
        }

        $name     = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $role     = in_array($_POST['role'] ?? 'employee', ['admin', 'employee'], true) ? $_POST['role'] : 'employee';
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '' || $username === '') {
            Flash::set('error', 'نام و نام کاربری الزامی است.');
            header('Location: /admin/users/' . $id . '/update');
            return;
        }

        // Username uniqueness (excluding this user)
        $existing = User::findByUsername($username);
        if ($existing !== null && $existing->id !== $id) {
            Flash::set('error', 'این نام کاربری قبلاً ثبت شده است.');
            header('Location: /admin/users/' . $id);
            return;
        }

        // Optional profile fields (validated); returns null on format error → flash + back
        $profile = self::profileFromPost();
        if ($profile === null) {
            header('Location: /admin/users/' . $id);
            return;
        }

        $newPassword = $_POST['password'] ?? '';

        User::update($id, array_merge([
            'name'      => $name,
            'username'  => $username,
            'role'      => $role,
            'is_active' => $isActive,
        ], $profile));

        if ($newPassword !== '' && strlen($newPassword) < 8) {
            Flash::set('error', 'رمز عبور باید حداقل ۸ کاراکتر باشد.');
            header('Location: /admin/users/' . $id);
            return;
        }
        if ($newPassword !== '') {
            Database::query("UPDATE users SET password_hash = ? WHERE id = ?",
                [password_hash($newPassword, PASSWORD_ARGON2ID), $id]);
        }

        Flash::set('success', 'کاربر به‌روزرسانی شد.');
        header('Location: /admin/users');
        exit;
    }

    /**
     * Reads, trims, validates and normalizes the optional profile fields
     * from $_POST. All fields are optional — empty values become NULL.
     *
     *  - national_code: exactly 10 digits
     *  - email: valid email address
     *  - birth_date: Jalali "YYYY/MM/DD" → stored Gregorian "YYYY-MM-DD"
     *  - salary: positive integer (Toman)
     *
     * @return array|null Profile columns; null = validation failed (flash already set).
     */
    private static function profileFromPost(): ?array
    {
        $text = fn(string $key): ?string => (
            isset($_POST[$key]) && trim((string) $_POST[$key]) !== '' ? trim((string) $_POST[$key]) : null
        );

        $nationalCode = $text('national_code');
        if ($nationalCode !== null && !preg_match('/^\d{10}$/', str_replace([' ', '-'], '', $nationalCode))) {
            Flash::set('error', 'کد ملی باید دقیقاً ۱۰ رقم باشد.');
            return null;
        }
        if ($nationalCode !== null) {
            $nationalCode = str_replace([' ', '-'], '', $nationalCode);
        }

        $email = $text('email');
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flash::set('error', 'ایمیل معتبر نیست.');
            return null;
        }

        // Jalali birth date → Gregorian storage (single conversion point)
        $birthDate = $text('birth_date');
        if ($birthDate !== null) {
            try {
                $ts         = CalendarService::parseJalaliDate($birthDate);
                $birthDate  = date('Y-m-d', $ts);
            } catch (\Throwable) {
                Flash::set('error', 'قالب تاریخ تولد نامعتبر است. نمونه: 1370/05/15');
                return null;
            }
        }

        $salary = $text('salary');
        if ($salary !== null) {
            // Accept Persian digits and thousand separators (۱۲٬۰۰۰٬۰۰۰ ، 12,000,000)
            $normalized = strtr($salary, [
                '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
                '٬' => '', '،' => '', ',' => '', ' ' => '',
            ]);
            if (!preg_match('/^\d+$/', $normalized)) {
                Flash::set('error', 'حقوق باید عدد باشد.');
                return null;
            }
            $salary = (int) $normalized;
        }

        return [
            'email'         => $email,
            'national_code' => $nationalCode,
            'father_name'   => $text('father_name'),
            'birth_date'    => $birthDate,
            'birth_city'    => $text('birth_city'),
            'id_card_no'    => $text('id_card_no'),
            'specialty'     => $text('specialty'),
            'job_location'  => $text('job_location'),
            'position'      => $text('position'),
            'salary'        => $salary,
            'notes'         => $text('notes'),
        ];
    }
}
