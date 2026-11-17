<?php
// Variables: $user, $totalEmployees, $presentToday, $absentToday, $lateToday,
//            $pendingLeaves, $week, $recentPunches, $monthHours, $monthDays, $onTimeRate
//            $onLeaveToday, $presentTodayList, $absentList
use App\Core\View;

$maxWeek = 1;
foreach ($week as $d) {
    $maxWeek = max($maxWeek, $d['ins'] + $d['outs']);
}
$methodLabels = ['qrcode' => 'با QR', 'location' => 'با موقعیت مکانی', 'manual' => 'ثبت سریع'];
$presentPct = $totalEmployees > 0 ? (int) round($presentToday / $totalEmployees * 100) : 0;
?>

<div class="space-y-6">

<!-- Hero: today at a glance -->
<div class="card bg-base-100 border border-base-200 shadow-sm overflow-hidden anim-fade-up">
    <div class="card-body p-5 lg:p-6"
         style="background: radial-gradient(600px 220px at 90% -40%, color-mix(in oklab, var(--color-primary) 12%, transparent), transparent 65%);">
        <div class="flex flex-col lg:flex-row lg:items-center gap-6">
            <div class="flex-1 min-w-0">
                <div class="text-xs text-base-content/50 mb-1">نمای کلی امروز</div>
                <h1 class="font-black text-2xl leading-tight">وضعیت حضور پرسنل</h1>
                <div class="text-xs text-base-content/50 mt-1" data-jalali-date>—</div>
            </div>
            <div class="flex flex-wrap items-center gap-6">
                <div class="flex items-center gap-3">
                    <div class="radial-progress text-success" style="--value:<?= $presentPct ?>; --size:4.5rem; --thickness:5px;" role="progressbar" aria-label="درصد حضور">
                        <span class="text-sm font-black tabular-nums"><?= View::fa((string) $presentPct) ?>٪</span>
                    </div>
                    <div class="text-sm">
                        <div class="font-bold"><?= View::fa((string) $presentToday) ?> / <?= View::fa((string) $totalEmployees) ?></div>
                        <div class="text-xs text-base-content/50">حاضر امروز</div>
                    </div>
                </div>
                <div class="text-left">
                    <div class="text-3xl font-black tabular-nums leading-none" data-clock>--:--:--</div>
                    <div class="text-[11px] text-base-content/40 mt-1 text-center">ساعت محلی</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- KPI grid -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
    <div class="card bg-base-100 border border-base-200 anim-fade-up anim-delay-1">
        <div class="card-body p-4">
            <div class="flex items-center justify-between">
                <span class="text-xs text-base-content/50">حاضر امروز</span>
                <span class="text-success"><?= View::icon('trend-up', 'w-4 h-4') ?></span>
            </div>
            <div class="text-2xl font-black tabular-nums text-success"><?= View::fa((string) $presentToday) ?></div>
            <div class="text-[11px] text-base-content/40">از <?= View::fa((string) $totalEmployees) ?> کارمند فعال</div>
        </div>
    </div>
    <div class="card bg-base-100 border border-base-200 anim-fade-up anim-delay-2">
        <div class="card-body p-4">
            <div class="flex items-center justify-between">
                <span class="text-xs text-base-content/50">غایب امروز</span>
                <span class="text-error"><?= View::icon('trend-down', 'w-4 h-4') ?></span>
            </div>
            <div class="text-2xl font-black tabular-nums <?= $absentToday > 0 ? 'text-error' : '' ?>"><?= View::fa((string) $absentToday) ?></div>
            <div class="text-[11px] text-base-content/40">بدون ورود ثبت‌شده</div>
        </div>
    </div>
    <div class="card bg-base-100 border border-base-200 anim-fade-up anim-delay-3">
        <div class="card-body p-4">
            <div class="flex items-center justify-between">
                <span class="text-xs text-base-content/50">تاخیر امروز</span>
                <span class="<?= $lateToday > 0 ? 'text-warning' : 'text-success' ?>"><?= View::icon('clock', 'w-4 h-4') ?></span>
            </div>
            <div class="text-2xl font-black tabular-nums <?= $lateToday > 0 ? 'text-warning' : '' ?>"><?= View::fa((string) $lateToday) ?></div>
            <div class="text-[11px] text-base-content/40">ورود بعد از ساعت مقرر</div>
        </div>
    </div>
    <a href="/admin/leaves" class="card bg-base-100 border border-base-200 anim-fade-up anim-delay-4 hover:border-primary/40 transition-colors">
        <div class="card-body p-4">
            <div class="flex items-center justify-between">
                <span class="text-xs text-base-content/50">مرخصی در انتظار</span>
                <span class="text-accent"><?= View::icon('file', 'w-4 h-4') ?></span>
            </div>
            <div class="text-2xl font-black tabular-nums <?= $pendingLeaves > 0 ? 'text-accent' : '' ?>"><?= View::fa((string) $pendingLeaves) ?></div>
            <div class="text-[11px] text-base-content/40">نیازمند بررسی <?= $pendingLeaves > 0 ? '←' : '' ?></div>
        </div>
    </a>
</div>

<!-- Today at a glance: present / on-leave / absent -->
<div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
    <!-- Present today -->
    <div class="card bg-base-100 border border-base-200 anim-fade-up">
        <div class="card-body p-5 gap-3">
            <h2 class="card-title text-base gap-2">
                <span class="text-success"><?= View::icon('login', 'w-5 h-5') ?></span>حاضرین امروز
                <span class="badge badge-success badge-outline badge-sm ms-auto"><?= View::fa((string) count($presentTodayList)) ?></span>
            </h2>
            <?php if (empty($presentTodayList)): ?>
            <p class="text-sm text-base-content/40 py-4 text-center">هنوز ورودی ثبت نشده است.</p>
            <?php else: ?>
            <ul class="divide-y divide-base-200/70">
                <?php foreach ($presentTodayList as $p): ?>
                <li class="flex items-center gap-3 py-2">
                    <?= View::avatar($p['name'], 'avatar-circle !w-8 !h-8 text-[10px]') ?>
                    <span class="text-sm font-medium truncate flex-1"><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if (!empty($p['late'])): ?>
                    <span class="badge badge-warning badge-outline badge-sm">تاخیر</span>
                    <?php endif; ?>
                    <span class="text-xs text-base-content/50 tabular-nums w-12 text-left"><?= View::fa(date('H:i', strtotime($p['first_in']))) ?></span>
                    <span class="text-xs text-base-content/40 tabular-nums w-12 text-left"><?= $p['last_out'] ? View::fa(date('H:i', strtotime($p['last_out']))) : '—' ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- On leave today -->
    <div class="card bg-base-100 border border-base-200 anim-fade-up anim-delay-1">
        <div class="card-body p-5 gap-3">
            <h2 class="card-title text-base gap-2">
                <span class="text-accent"><?= View::icon('calendar', 'w-5 h-5') ?></span>مرخصی امروز
                <span class="badge badge-accent badge-outline badge-sm ms-auto"><?= View::fa((string) count($onLeaveToday)) ?></span>
            </h2>
            <?php if (empty($onLeaveToday)): ?>
            <p class="text-sm text-base-content/40 py-4 text-center">مرخصی تاییدشده‌ای برای امروز وجود ندارد.</p>
            <?php else: ?>
            <ul class="divide-y divide-base-200/70">
                <?php foreach ($onLeaveToday as $l): ?>
                <li class="flex items-center gap-3 py-2">
                    <?= View::avatar($l['user_name'] ?? '?', 'avatar-circle !w-8 !h-8 text-[10px]') ?>
                    <span class="text-sm font-medium truncate flex-1"><?= htmlspecialchars($l['user_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="badge badge-ghost badge-sm"><?= $l['type'] === 'hourly' ? 'ساعتی' : 'روزانه' ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Absent today -->
    <div class="card bg-base-100 border border-base-200 anim-fade-up anim-delay-2">
        <div class="card-body p-5 gap-3">
            <h2 class="card-title text-base gap-2">
                <span class="text-error"><?= View::icon('trend-down', 'w-5 h-5') ?></span>غایبین امروز
                <span class="badge badge-error badge-outline badge-sm ms-auto"><?= View::fa((string) count($absentList)) ?></span>
            </h2>
            <?php if (empty($absentList)): ?>
            <p class="text-sm text-base-content/40 py-4 text-center">همه حاضرند ✅</p>
            <?php else: ?>
            <ul class="divide-y divide-base-200/70">
                <?php foreach ($absentList as $a): ?>
                <li class="flex items-center gap-3 py-2">
                    <?= View::avatar($a['name'], 'avatar-circle !w-8 !h-8 text-[10px]') ?>
                    <span class="text-sm font-medium truncate flex-1"><?= htmlspecialchars($a['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="badge badge-error badge-outline badge-sm">بدون ورود</span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <?php if (!empty($onLeaveToday) && !empty($absentList)): ?>
            <p class="text-[11px] text-base-content/40 pt-1">کاربران مرخصی‌دار ممکن است در این لیست هم دیده شوند.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-5 gap-4">
    <!-- Weekly chart -->
    <div class="card bg-base-100 border border-base-200 anim-fade-up xl:col-span-3 self-start">
        <div class="card-body p-5 gap-4">
            <div class="flex items-center justify-between">
                <h2 class="card-title text-base gap-2"><?= View::icon('chart', 'w-5 h-5 text-primary') ?>ثبت‌های ۷ روز اخیر</h2>
                <span class="text-xs text-base-content/40">ورود + خروج</span>
            </div>
            <div class="flex items-end justify-between gap-2 h-44 pt-4" dir="ltr">
                <?php foreach ($week as $d): ?>
                <?php
                    $count = $d['ins'] + $d['outs'];
                    $pct = (int) round($count / $maxWeek * 100);
                ?>
                <div class="flex-1 flex flex-col items-center gap-2 h-full justify-end">
                    <span class="text-xs font-bold tabular-nums <?= $d['is_today'] ? 'text-primary' : 'text-base-content/60' ?>">
                        <?= $count > 0 ? View::fa((string) $count) : '' ?>
                    </span>
                    <div class="w-full max-w-10 flex items-end justify-center h-full">
                        <div class="bar-grow w-full rounded-t-md <?= $d['is_today'] ? 'bg-primary' : 'bg-primary/30' ?>"
                             data-percent="<?= $pct ?>"></div>
                    </div>
                    <span class="text-[10px] <?= $d['is_today'] ? 'text-primary font-bold' : 'text-base-content/40' ?>">
                        <?= htmlspecialchars($d['day'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Month summary -->
    <div class="card bg-base-100 border border-base-200 anim-fade-up anim-delay-1 xl:col-span-2 self-start">
        <div class="card-body p-5 gap-4">
            <h2 class="card-title text-base gap-2"><?= View::icon('calendar', 'w-5 h-5 text-secondary') ?>خلاصه ماه جاری (جلالی)</h2>
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-base-content/60">مجموع کارکرد</span>
                    <span class="font-black tabular-nums"><?= View::fa(number_format($monthHours, 1)) ?> ساعت</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-base-content/60">روزهای کاری ثبت‌شده</span>
                    <span class="font-black tabular-nums"><?= View::fa((string) $monthDays) ?> روز</span>
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-sm text-base-content/60">نرخ حضور به‌موقع</span>
                        <span class="font-black tabular-nums <?= $onTimeRate >= 80 ? 'text-success' : ($onTimeRate >= 50 ? 'text-warning' : 'text-error') ?>">
                            <?= View::fa((string) $onTimeRate) ?>٪
                        </span>
                    </div>
                    <progress class="progress <?= $onTimeRate >= 80 ? 'progress-success' : ($onTimeRate >= 50 ? 'progress-warning' : 'progress-error') ?> h-3" value="<?= $onTimeRate ?>" max="100"></progress>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2 pt-3 border-t border-base-200/60">
                <a href="/admin/reports" class="btn btn-outline btn-sm">
                    <?= View::icon('file', 'w-4 h-4') ?>گزارش‌ها
                </a>
                <a href="/admin/users" class="btn btn-outline btn-sm">
                    <?= View::icon('users', 'w-4 h-4') ?>مدیریت کاربران
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Recent punches -->
<div class="card bg-base-100 border border-base-200 anim-fade-up anim-delay-2">
    <div class="card-body p-5 gap-4">
        <div class="flex items-center justify-between">
            <h2 class="card-title text-base gap-2"><?= View::icon('clock', 'w-5 h-5 text-accent') ?>آخرین ثبت‌های ورود و خروج</h2>
            <a href="/admin/punches" class="btn btn-ghost btn-xs text-primary">مشاهده همه ←</a>
        </div>
        <?php if (empty($recentPunches)): ?>
        <div class="py-8 text-center text-base-content/40 text-sm flex flex-col items-center gap-3">
            <?= View::icon('clock', 'w-10 h-10 opacity-30') ?>
            <p>هنوز هیچ ورود یا خروجی ثبت نشده است.</p>
        </div>
        <?php else: ?>
        <ul class="divide-y divide-base-200/70">
            <?php foreach ($recentPunches as $r): ?>
            <li class="flex items-center gap-3 py-2.5">
                <?= View::avatar($r['user_name'] ?? '?', 'avatar-circle !w-9 !h-9 text-xs') ?>
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-semibold truncate"><?= htmlspecialchars($r['user_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="text-xs text-base-content/50">
                        <?= htmlspecialchars($methodLabels[$r['method']] ?? $r['method'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                </div>
                <span class="badge badge-sm <?= $r['type'] === 'in' ? 'badge-success' : 'badge-info' ?> badge-outline">
                    <?= $r['type'] === 'in' ? 'ورود' : 'خروج' ?>
                </span>
                <span class="text-sm font-bold tabular-nums w-14 text-left"><?= View::fa(date('H:i', strtotime($r['punched_at']))) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>

</div>
