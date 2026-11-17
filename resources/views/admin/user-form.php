<?php
// Variables: $user (array or null), $csrf
use App\Core\View;
use App\Services\CalendarService;

$isNew = $user === null;

// Stored Gregorian birth date → Jalali for the form input (single conversion point)
$birthJalali = '';
if (!empty($user['birth_date'])) {
    try {
        $birthJalali = CalendarService::formatJalali(strtotime($user['birth_date']));
    } catch (\Throwable) {
        $birthJalali = '';
    }
}

/** Null-safe escaped value helper for optional fields */
$val = fn(?string $key): string => htmlspecialchars((string) ($user[$key] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<div class="space-y-6">
    <div class="flex items-center gap-3">
        <span class="avatar-circle avatar-circle-lg bg-primary/10 text-primary shrink-0"><?= View::icon('users', 'w-5 h-5') ?></span>
        <div>
            <h1 class="text-xl font-bold leading-tight"><?= $isNew ? 'کاربر جدید' : 'ویرایش کاربر' ?></h1>
            <p class="text-xs text-base-content/50 mt-0.5"><?= $isNew ? 'اطلاعات کارمند را تکمیل کنید' : 'اطلاعات «' . htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8') . '» را ویرایش کنید' ?></p>
        </div>
    </div>

    <form method="POST" action="<?= $isNew ? '/admin/users/create' : '/admin/users/' . (int) $user['id'] . '/update' ?>"
          class="card bg-base-100 border border-base-200 max-w-lg anim-fade-up">
        <div class="card-body gap-4">
        <?= $csrf ?>

        <div class="form-control">
            <label class="label"><span class="label-text">نام کامل</span></label>
            <input type="text" name="name" required
                   value="<?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   class="input input-bordered w-full">
        </div>

        <div class="form-control">
            <label class="label"><span class="label-text">نام کاربری</span></label>
            <input type="text" name="username" required
                   value="<?= htmlspecialchars($user['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   class="input input-bordered w-full">
        </div>

        <div class="form-control">
            <label class="label"><span class="label-text">رمز عبور <?= $isNew ? '' : '(تغییر در صورت نیاز)' ?></span></label>
            <input type="password" name="password" <?= $isNew ? 'required' : '' ?>
                   minlength="8" placeholder="حداقل ۸ کاراکتر"
                   class="input input-bordered w-full">
        </div>

        <div class="form-control">
            <label class="label"><span class="label-text">نقش</span></label>
            <select name="role" class="select select-bordered w-full">
                <option value="employee" <?= ($user['role'] ?? 'employee') === 'employee' ? 'selected' : '' ?>>کارمند</option>
                <option value="admin" <?= ($user['role'] ?? '') === 'admin' ? 'selected' : '' ?>>ادمین</option>
            </select>
        </div>

        <?php if (!$isNew): ?>
        <div class="form-control">
            <label class="label"><span class="label-text">وضعیت</span></label>
            <select name="is_active" class="select select-bordered w-full">
                <option value="1" <?= $user['is_active'] ? 'selected' : '' ?>>فعال</option>
                <option value="0" <?= !$user['is_active'] ? 'selected' : '' ?>>غیرفعال</option>
            </select>
        </div>
        <?php endif; ?>

        <?php
        // ── Optional profile section (HR data) ─────────────
        $profileFields = [
            'email'         => ['ایمیل', 'email', 'example@company.com', 'ltr'],
            'national_code' => ['کد ملی', 'text', '۱۰ رقم، بدون خط تیره', 'ltr'],
            'father_name'   => ['نام پدر', 'text', '', ''],
            'birth_date'    => ['تاریخ تولد (شمسی)', 'text', 'مثال: 1370/05/15', ''],
            'birth_city'    => ['شهر محل تولد', 'text', '', ''],
            'id_card_no'    => ['شماره شناسنامه', 'text', '', 'ltr'],
            'specialty'     => ['تخصص', 'text', '', ''],
            'job_location'  => ['موقعیت شغلی', 'text', '', ''],
            'position'      => ['پوزیشن (عنوان شغلی)', 'text', '', ''],
        ];
        foreach ($profileFields as $key => [$label, $type, $placeholder, $dir]):
            $v = $key === 'birth_date' ? $birthJalali : $val($key);
        ?>
        <div class="form-control">
            <label class="label"><span class="label-text"><?= $label ?> <span class="text-base-content/30 text-[10px]">(دلخواه)</span></span></label>
            <input type="<?= $type ?>" name="<?= $key ?>" placeholder="<?= $placeholder ?>"
                   value="<?= $v ?>"<?= $dir === 'ltr' ? ' dir="ltr"' : '' ?>
                   class="input input-bordered w-full">
        </div>
        <?php endforeach; ?>

        <div class="form-control">
            <label class="label"><span class="label-text">حقوق دریافتی (تومان) <span class="text-base-content/30 text-[10px]">(دلخواه)</span></span></label>
            <input type="text" name="salary" inputmode="numeric" placeholder="مثال: 25,000,000"
                   value="<?= !empty($user['salary']) ? htmlspecialchars((string) $user['salary'], ENT_QUOTES, 'UTF-8') : '' ?>"
                   dir="ltr" class="input input-bordered w-full">
        </div>

        <div class="form-control">
            <label class="label"><span class="label-text">توضیحات بیشتر <span class="text-base-content/30 text-[10px]">(دلخواه — تحصیلات، شماره تماس، …)</span></span></label>
            <textarea name="notes" rows="3" placeholder="…"
                      class="textarea textarea-bordered w-full"><?= $val('notes') ?></textarea>
        </div>

        <div class="card-actions mt-4 justify-end">
            <a href="/admin/users" class="btn btn-ghost">لغو</a>
            <button type="submit" class="btn btn-primary">
                <?= View::icon('check', 'w-4 h-4') ?><?= $isNew ? 'ایجاد' : 'ذخیره' ?>
            </button>
        </div>
        </div>
    </form>
</div>
