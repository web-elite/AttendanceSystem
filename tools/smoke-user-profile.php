<?php
/**
 * Smoke: user profile fields (migration 003)
 *  - create user with all optional fields
 *  - read back via model
 *  - update (clear a field)
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Models\User;
use App\Core\Database;

$pdo = Database::conn();

// 1) create with full profile
User::create([
    'name'          => 'تست مشخصات',
    'username'      => 'profile_test_' . time(),
    'password_hash' => password_hash('test12345678', PASSWORD_ARGON2ID),
    'role'          => 'employee',
    'is_active'     => 1,
    'created_at'    => date('Y-m-d H:i:s'),
    'email'         => 'test@example.com',
    'national_code' => '0012345678',
    'father_name'   => 'علی',
    'birth_date'    => '1991-08-06',       // 1370/05/15
    'birth_city'    => 'مشهد',
    'id_card_no'    => '123456789',
    'specialty'     => 'طراح رابط کاربری',
    'job_location'  => 'دفتر مرکزی',
    'position'      => 'Frontend Developer',
    'salary'        => 25000000,
    'notes'         => 'تست',
]);

$stmt = Database::query("SELECT * FROM users WHERE username LIKE 'profile_test_%' ORDER BY id DESC LIMIT 1");
$row = $stmt->fetch();
$id  = (int) $row['id'];

$u = User::find($id);
assert($u->email === 'test@example.com');
assert($u->national_code === '0012345678');
assert($u->father_name === 'علی');
assert($u->birth_city === 'مشهد');
assert($u->specialty === 'طراح رابط کاربری');
assert($u->position === 'Frontend Developer');
assert($u->salary === 25000000);

// 2) update: clear email, change position
User::update($id, [
    'name'          => $u->name,
    'username'      => $u->username,
    'role'          => 'employee',
    'is_active'     => 1,
    'email'         => null,
    'national_code' => '0012345678',
    'father_name'   => 'علی',
    'birth_date'    => '1991-08-06',
    'birth_city'    => 'مشهد',
    'id_card_no'    => '123456789',
    'specialty'     => 'طراح رابط کاربری',
    'job_location'  => 'دفتر مرکزی',
    'position'      => 'Senior Frontend',
    'salary'        => 30000000,
    'notes'         => null,
]);

$u2 = User::find($id);
assert($u2->email === null);
assert($u2->position === 'Senior Frontend');
assert($u2->salary === 30000000);
assert($u2->notes === null);

// 3) toRow contains new keys
$r = $u2->toRow();
foreach (['email','national_code','father_name','birth_date','birth_city','id_card_no','specialty','job_location','position','salary','notes'] as $k) {
    assert(array_key_exists($k, $r));
}

// cleanup — hard delete of test rows (test users only)
$pdo->prepare("DELETE FROM users WHERE username LIKE 'profile_test_%'")->execute();

echo "✅ profile smoke passed (create/read/update/toRow)\n";
