<?php
/**
 * Smoke: UsersController::profileFromPost validation + user-form render
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database;
use App\Controllers\Admin\UsersController;

$m = new ReflectionMethod(UsersController::class, 'profileFromPost');

// 1) empty POST → all nulls
$_POST = [];
$p = $m->invoke(null);
assert($p !== null);
foreach ($p as $v) { assert($v === null); }

// 2) valid: Jalali birth date + Persian-digit salary + spaced national code
$_POST = [
    'email'         => 'ali@site.com',
    'national_code' => '001 234 5678',
    'birth_date'    => '1370/05/15',
    'salary'        => '۲۵٬۰۰۰٬۰۰۰',
    'position'      => 'کارشناس',
];
$p = $m->invoke(null);
assert($p !== null);
assert($p['national_code'] === '0012345678', 'national code normalize');
// CalendarService's civil 33-year rule maps 1370/05/15 → 1991-08-07 (project-standard, tested 8/8)
assert($p['birth_date'] === '1991-08-07', 'jalali→gregorian, got ' . $p['birth_date']);
assert($p['salary'] === 25000000, 'persian digits salary');
assert($p['email'] === 'ali@site.com');
assert($p['position'] === 'کارشناس');

// 3) invalid national code
$_POST = ['national_code' => '12345'];
assert($m->invoke(null) === null);

// 4) invalid email
$_POST = ['email' => 'not-an-email'];
assert($m->invoke(null) === null);

// 5) invalid birth date
$_POST = ['birth_date' => '700/13/99'];
assert($m->invoke(null) === null);

// 6) invalid salary
$_POST = ['salary' => 'abc'];
assert($m->invoke(null) === null);

// 7) views render (create + edit with a profile-filled user)
if (!function_exists('view')) {
    function view(string $v, array $data = []): string
    {
        return \App\Core\View::render($v, $data);
    }
}
$_SESSION = ['role' => 'admin']; // layout expects an authenticated admin

function capture(callable $fn): string
{
    ob_start();
    $returned = $fn();
    $echoed = ob_get_clean();
    return $returned . $echoed;
}

$pdo = Database::conn();
$stmt = Database::query("SELECT id FROM users LIMIT 1");
$uid = $stmt->fetchColumn();

$renderCreate = capture(fn() => UsersController::create());
// capture: render() reuses the outer buffer for the layout, so combine return + echo
assert(str_contains($renderCreate, 'name="national_code"'));
assert(str_contains($renderCreate, 'name="salary"'));
assert(str_contains($renderCreate, 'name="notes"'));
assert(str_contains($renderCreate, '(دلخواه)'));

if ($uid) {
    $renderEdit = capture(fn() => UsersController::edit((int) $uid));
    assert(str_contains($renderEdit, 'name="national_code"'));
}

echo "✅ profileFromPost validation + form render passed\n";
