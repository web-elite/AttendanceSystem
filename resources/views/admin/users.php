<?php
// Variables: $users, $csrf
use App\Core\View;
?>
<div class="space-y-6">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-3">
            <span class="avatar-circle avatar-circle-lg bg-primary/10 text-primary shrink-0"><?= View::icon('users', 'w-5 h-5') ?></span>
            <div>
                <h1 class="text-xl font-bold leading-tight">مدیریت کاربران</h1>
                <p class="text-xs text-base-content/50 mt-0.5"><?= View::fa((string) count($users)) ?> کاربر ثبت‌شده</p>
            </div>
        </div>
        <a href="/admin/users/create" class="btn btn-primary btn-sm">
            <?= View::icon('plus', 'w-4 h-4') ?>کاربر جدید
        </a>
    </div>

    <div class="card bg-base-100 border border-base-200 anim-fade-up">
        <div class="card-body p-0">
            <?php if (empty($users)): ?>
            <div class="py-12 text-center text-base-content/40 text-sm flex flex-col items-center gap-3">
                <?= View::icon('users', 'w-10 h-10 opacity-30') ?>
                <p>کاربری ثبت نشده است.</p>
                <a href="/admin/users/create" class="btn btn-outline btn-sm">افزودن اولین کاربر</a>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="table table-zebra">
                    <thead>
                        <tr>
                            <th>نام</th>
                            <th>کد ملی</th>
                            <th>پوزیشن</th>
                            <th>حقوق (تومان)</th>
                            <th>نقش</th>
                            <th>وضعیت</th>
                            <th class="text-left">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr class="hover:bg-base-200/40">
                            <td>
                                <div class="flex items-center gap-3">
                                    <?= View::avatar($u['name'], 'avatar-circle !w-9 !h-9 text-xs') ?>
                                    <span class="font-semibold"><?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </td>
                            <td class="text-base-content/50 text-sm tabular-nums" dir="ltr"><?= htmlspecialchars((string) ($u['national_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?: '—' ?></td>
                            <td class="text-sm"><?= htmlspecialchars((string) ($u['position'] ?? ''), ENT_QUOTES, 'UTF-8') ?: '—' ?></td>
                            <td class="text-base-content/60 text-sm tabular-nums"><?= !empty($u['salary']) ? View::fa(number_format((float) $u['salary'])) : '—' ?></td>
                            <td>
                                <span class="badge badge-sm <?= $u['role'] === 'admin' ? 'badge-primary' : 'badge-ghost' ?>">
                                    <?= $u['role'] === 'admin' ? 'ادمین' : 'کارمند' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-sm <?= $u['is_active'] ? 'badge-success' : 'badge-error' ?> badge-outline">
                                    <?= $u['is_active'] ? 'فعال' : 'غیرفعال' ?>
                                </span>
                            </td>
                            <td class="text-left">
                                <a href="/admin/users/<?= (int) $u['id'] ?>" class="btn btn-ghost btn-xs btn-outline">ویرایش</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
