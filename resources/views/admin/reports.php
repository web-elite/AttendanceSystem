<?php
// Variables: $report, $csrf, $filters
use App\Core\View;
?>
<div class="space-y-6">
    <div class="flex items-center gap-3">
        <span class="avatar-circle avatar-circle-lg bg-primary/10 text-primary shrink-0"><?= View::icon('chart', 'w-5 h-5') ?></span>
        <div>
            <h1 class="text-xl font-bold leading-tight">گزارش‌های حضور و غیاب</h1>
            <p class="text-xs text-base-content/50 mt-0.5">بازه جلالی را انتخاب و مشاهده کنید</p>
        </div>
    </div>

    <form method="GET" action="/admin/reports" class="card bg-base-100 border border-base-200 anim-fade-up">
        <div class="card-body p-4 space-y-3">
            <!-- Quick presets -->
            <?php $activePreset = $_GET['preset'] ?? ''; ?>
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs text-base-content/50">بازه سریع:</span>
                <a href="/admin/reports?preset=today" class="btn btn-xs <?= $activePreset === 'today' ? 'btn-primary' : 'btn-ghost btn-outline' ?>">امروز</a>
                <a href="/admin/reports?preset=week" class="btn btn-xs <?= $activePreset === 'week' ? 'btn-primary' : 'btn-ghost btn-outline' ?>">۷ روز اخیر</a>
                <a href="/admin/reports?preset=month" class="btn btn-xs <?= $activePreset === 'month' ? 'btn-primary' : 'btn-ghost btn-outline' ?>">این ماه</a>
            </div>
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="label"><span class="label-text">از تاریخ (شمسی)</span></label>
                    <input type="text" name="from" value="<?= htmlspecialchars($filters['from'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="۱۴۰۵/۰۸/۰۱" dir="ltr" class="input input-bordered">
                </div>
                <div>
                    <label class="label"><span class="label-text">تا تاریخ (شمسی)</span></label>
                    <input type="text" name="to" value="<?= htmlspecialchars($filters['to'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="۱۴۰۵/۰۸/۳۱" dir="ltr" class="input input-bordered">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <?= View::icon('check', 'w-4 h-4') ?>نمایش
                    </button>
                    <a href="/admin/reports?from=<?= urlencode($filters['from'] ?? '') ?>&to=<?= urlencode($filters['to'] ?? '') ?>&user_id=<?= urlencode($filters['user_id'] ?? '') ?>&export=csv"
                       class="btn btn-ghost">
                        <?= View::icon('file', 'w-4 h-4') ?>خروجی CSV
                    </a>
                </div>
            </div>
            <p class="text-[11px] text-base-content/40">نمونه تاریخ معتبر: ۱۴۰۵/۰۸/۱۵ — اگر خالی بگذارید، ماه جاری نمایش داده می‌شود.</p>
        </div>
    </form>

    <?php if ($report !== null): ?>
        <!-- Totals -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
            <div class="card bg-base-100 border border-base-200 anim-fade-up anim-delay-1">
                <div class="card-body p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-base-content/50">روزهای کاری</span>
                        <span class="text-primary"><?= View::icon('calendar', 'w-4 h-4') ?></span>
                    </div>
                    <div class="text-2xl font-black tabular-nums"><?= View::fa((string) $report['totals']['total_days']) ?></div>
                </div>
            </div>
            <div class="card bg-base-100 border border-base-200 anim-fade-up anim-delay-2">
                <div class="card-body p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-base-content/50">مجموع ساعت</span>
                        <span class="text-secondary"><?= View::icon('clock', 'w-4 h-4') ?></span>
                    </div>
                    <div class="text-2xl font-black tabular-nums"><?= View::fa(number_format((float) $report['totals']['total_hours'], 1)) ?></div>
                </div>
            </div>
            <div class="card bg-base-100 border border-base-200 anim-fade-up anim-delay-3">
                <div class="card-body p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-base-content/50">تاخیر</span>
                        <span class="<?= $report['totals']['total_late'] > 0 ? 'text-warning' : 'text-success' ?>"><?= View::icon('clock', 'w-4 h-4') ?></span>
                    </div>
                    <div class="text-2xl font-black tabular-nums <?= $report['totals']['total_late'] > 0 ? 'text-warning' : '' ?>"><?= View::fa((string) $report['totals']['total_late']) ?></div>
                </div>
            </div>
            <div class="card bg-base-100 border border-base-200 anim-fade-up anim-delay-4">
                <div class="card-body p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-base-content/50">تعجیل</span>
                        <span class="text-info"><?= View::icon('trend-up', 'w-4 h-4') ?></span>
                    </div>
                    <div class="text-2xl font-black tabular-nums"><?= View::fa((string) $report['totals']['total_early']) ?></div>
                </div>
            </div>
        </div>

        <!-- Rows -->
        <div class="card bg-base-100 border border-base-200 anim-fade-up anim-delay-2">
            <div class="card-body p-0">
                <?php if (empty($report['rows'])): ?>
                <div class="py-12 text-center text-base-content/40 text-sm flex flex-col items-center gap-3">
                    <?= View::icon('chart', 'w-10 h-10 opacity-30') ?>
                    <p>در این بازه داده‌ای ثبت نشده است.</p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="table table-sm table-zebra">
                        <thead>
                            <tr>
                                <th>کارمند</th>
                                <th>تاریخ (هجری)</th>
                                <th>اولین ورود</th>
                                <th>آخرین خروج</th>
                                <th>ساعات</th>
                                <th>تأخیر</th>
                                <th>مغایر پایان</th>
                                <th>تخمینی</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                            $esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
                        ?>
                        <?php foreach ($report['rows'] as $row): ?>
                            <tr class="hover:bg-base-200/40">
                                <td class="font-medium whitespace-nowrap"><?= $esc($row['user_name'] ?? '') ?></td>
                                <td class="text-base-content/60 whitespace-nowrap"><?= $esc($row['jalali_day'] ?? '') ?></td>
                                <td class="text-base-content/60 tabular-nums"><?= $esc($row['first_in'] ?? '') ?></td>
                                <td class="text-base-content/60 tabular-nums"><?= $esc($row['last_out'] ?? '') ?></td>
                                <td class="tabular-nums"><?= View::fa(number_format((float) ($row['hours'] ?? 0), 1)) ?></td>
                                <td><?= !empty($row['late']) ? '<span class="badge badge-warning badge-sm">تأخیر</span>' : '<span class="text-base-content/30">—</span>' ?></td>
                                <td><?= !empty($row['early']) ? '<span class="badge badge-info badge-sm">مغایر</span>' : '<span class="text-base-content/30">—</span>' ?></td>
                                <td><?= !empty($row['estimated']) ? '<span class="badge badge-outline badge-sm">تخمینی</span>' : '' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
