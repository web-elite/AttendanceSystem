<?php
// Variables: $pending, $csrf
use App\Core\View;
?>
<div class="space-y-6">
    <div class="flex items-center gap-3">
        <span class="avatar-circle avatar-circle-lg bg-accent/10 text-accent shrink-0"><?= View::icon('calendar', 'w-5 h-5') ?></span>
        <div>
            <h1 class="text-xl font-bold leading-tight">درخواست‌های مرخصی</h1>
            <p class="text-xs text-base-content/50 mt-0.5">
                <?= !empty($pending) ? View::fa((string) count($pending)) . ' درخواست در انتظار بررسی' : 'همه درخواست‌ها بررسی شده‌اند' ?>
            </p>
        </div>
    </div>

    <?php if (empty($pending)): ?>
    <div class="card bg-base-100 border border-base-200 anim-fade-up">
        <div class="card-body py-12 text-center text-base-content/40 text-sm flex flex-col items-center gap-3">
            <?= View::icon('check', 'w-10 h-10 opacity-30') ?>
            <p>درخواست مرخصی در انتظار ندارید.</p>
            <p class="text-xs">درخواست‌های جدید کارکنان اینجا نمایش داده می‌شوند.</p>
        </div>
    </div>
    <?php else: ?>
    <div class="space-y-4">
        <?php foreach ($pending as $r): ?>
        <div class="card bg-base-100 border border-base-200 anim-fade-up hover:border-accent/40 transition-colors">
            <div class="card-body p-5 gap-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <?= View::avatar($r['user_name'] ?? '?', 'avatar-circle !w-10 !h-10 text-sm') ?>
                        <div>
                            <div class="font-bold"><?= htmlspecialchars($r['user_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                            <span class="badge badge-xs badge-outline <?= $r['type'] === 'hourly' ? 'badge-secondary' : 'badge-primary' ?>">
                                <?= $r['type'] === 'hourly' ? 'ساعتی' : 'روزانه' ?>
                            </span>
                        </div>
                    </div>
                    <span class="text-sm text-base-content/60 flex items-center gap-1.5 bg-base-200/50 rounded-lg px-3 py-1.5">
                        <?= View::icon('calendar', 'w-4 h-4 opacity-50') ?>
                        <?= View::fa(htmlspecialchars(\App\Services\CalendarService::formatJalali(strtotime($r['start_at'])), ENT_QUOTES, 'UTF-8')) ?> تا <?= View::fa(htmlspecialchars(\App\Services\CalendarService::formatJalali(strtotime($r['end_at'])), ENT_QUOTES, 'UTF-8')) ?>
                    </span>
                </div>
                <p class="text-base-content/70 text-sm leading-relaxed border-r-2 border-base-300 pr-3">
                    <?= htmlspecialchars($r['reason'], ENT_QUOTES, 'UTF-8') ?>
                </p>
                <div class="card-actions justify-end pt-1">
                    <form method="POST" action="/admin/leaves/<?= (int) $r['id'] ?>/approve">
                        <?= $csrf ?>
                        <button type="submit" class="btn btn-success btn-sm">
                            <?= View::icon('check', 'w-4 h-4') ?>تایید
                        </button>
                    </form>
                    <form method="POST" action="/admin/leaves/<?= (int) $r['id'] ?>/reject">
                        <?= $csrf ?>
                        <button type="submit" class="btn btn-error btn-outline btn-sm">رد</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
