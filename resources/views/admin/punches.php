<?php
// Variables: $punches, $users, $csrf, $filters (from/to are Jalali strings)
use App\Core\View;

$methodLabels = ['qrcode' => 'با QR', 'location' => 'با موقعیت مکانی', 'manual' => 'ثبت سریع'];
$qs = static function (array $keep, string $preset) use ($filters): string {
    $params = array_merge($keep, ['preset' => $preset]);
    return '/admin/punches?' . http_build_query($params);
};
$baseKeep = [
    'user_id' => $filters['user_id'] ?? '',
    'method'  => $filters['method'] ?? '',
];
$activePreset = $_GET['preset'] ?? '';
?>
<div class="space-y-6">
    <div class="flex items-center gap-3">
        <span class="avatar-circle avatar-circle-lg bg-primary/10 text-primary shrink-0"><?= View::icon('clock', 'w-5 h-5') ?></span>
        <div>
            <h1 class="text-xl font-bold leading-tight">سوابق ورود و خروج</h1>
            <p class="text-xs text-base-content/50 mt-0.5"><?= View::fa((string) count($punches)) ?> رکورد در نتایج</p>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" action="/admin/punches" class="card bg-base-100 border border-base-200 anim-fade-up">
        <div class="card-body p-4 space-y-3">
            <!-- Quick presets -->
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs text-base-content/50">بازه سریع:</span>
                <a href="<?= $qs($baseKeep, 'today') ?>" class="btn btn-xs <?= $activePreset === 'today' ? 'btn-primary' : 'btn-ghost btn-outline' ?>">امروز</a>
                <a href="<?= $qs($baseKeep, 'week') ?>" class="btn btn-xs <?= $activePreset === 'week' ? 'btn-primary' : 'btn-ghost btn-outline' ?>">۷ روز اخیر</a>
                <a href="<?= $qs($baseKeep, 'month') ?>" class="btn btn-xs <?= $activePreset === 'month' ? 'btn-primary' : 'btn-ghost btn-outline' ?>">این ماه</a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
                <div>
                    <label class="label"><span class="label-text">از تاریخ (شمسی)</span></label>
                    <input type="text" name="from" value="<?= htmlspecialchars($filters['from'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="۱۴۰۵/۰۸/۲۰" dir="ltr" class="input input-bordered w-full"
                           data-jdp data-jdp-only-date autocomplete="off">
                </div>
                <div>
                    <label class="label"><span class="label-text">تا تاریخ (شمسی)</span></label>
                    <input type="text" name="to" value="<?= htmlspecialchars($filters['to'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="۱۴۰۵/۰۸/۲۶" dir="ltr" class="input input-bordered w-full"
                           data-jdp data-jdp-only-date autocomplete="off">
                </div>
                <div>
                    <label class="label"><span class="label-text">کارمند</span></label>
                    <select name="user_id" class="select select-bordered w-full">
                        <option value="">همه</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?= (int) $u['id'] ?>" <?= ($filters['user_id'] ?? '') === (string) $u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="label"><span class="label-text">روش</span></label>
                    <select name="method" class="select select-bordered w-full">
                        <option value="">همه روش‌ها</option>
                        <option value="manual" <?= ($filters['method'] ?? '') === 'manual' ? 'selected' : '' ?>>ثبت سریع</option>
                        <option value="location" <?= ($filters['method'] ?? '') === 'location' ? 'selected' : '' ?>>با موقعیت مکانی</option>
                        <option value="qrcode" <?= ($filters['method'] ?? '') === 'qrcode' ? 'selected' : '' ?>>با QR</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">
                    <?= View::icon('check', 'w-4 h-4') ?>فیلتر
                </button>
            </div>
        </div>
    </form>

    <!-- Table -->
    <div class="card bg-base-100 border border-base-200 anim-fade-up anim-delay-1">
        <div class="card-body p-0">
            <?php if (empty($punches)): ?>
            <div class="py-12 text-center text-base-content/40 text-sm flex flex-col items-center gap-3">
                <?= View::icon('clock', 'w-10 h-10 opacity-30') ?>
                <p>رکوردی با این فیلترها یافت نشد.</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="table table-zebra">
                    <thead>
                        <tr>
                            <th>کارمند</th>
                            <th>نوع</th>
                            <th>زمان</th>
                            <th>روش</th>
                            <th>فاصله</th>
                            <th>یادداشت</th>
                            <th class="text-left">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($punches as $p): ?>
                        <tr class="hover:bg-base-200/40">
                            <td>
                                <div class="flex items-center gap-2.5">
                                    <?= View::avatar($p['user_name'] ?? '?', 'avatar-circle !w-8 !h-8 text-[10px]') ?>
                                    <span class="font-semibold whitespace-nowrap"><?= htmlspecialchars($p['user_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-sm <?= $p['type'] === 'in' ? 'badge-success' : 'badge-info' ?> badge-outline">
                                    <?= $p['type'] === 'in' ? 'ورود' : 'خروج' ?>
                                </span>
                            </td>
                            <td class="text-base-content/60 text-sm whitespace-nowrap"><?= View::fa(\App\Services\CalendarService::formatJalali(strtotime($p['punched_at'])) . ' ' . date('H:i', strtotime($p['punched_at']))) ?></td>
                            <td class="text-sm text-base-content/60"><?= htmlspecialchars($methodLabels[$p['method']] ?? $p['method'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-base-content/60 text-sm whitespace-nowrap">
                                <?= $p['distance_m'] !== null ? View::fa(number_format((float) $p['distance_m'])) . ' متر' : '—' ?>
                            </td>
                            <td class="text-base-content/50 text-sm max-w-40 truncate"><?= htmlspecialchars($p['note'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-left">
                                <a href="/admin/punches/<?= (int) $p['id'] ?>/edit" class="btn btn-ghost btn-xs btn-outline">ویرایش</a>
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
