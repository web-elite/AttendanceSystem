<?php
// Variables: $user, $todayPunches, $hasIn, $hasOut, $csrf, $month, $week,
//            $pendingLeaves, $myLeaves, $allowedMethods, $methodMode, $geofenceOn
use App\Core\View;

$leaveStatusLabels = ['pending' => 'در انتظار', 'approved' => 'تاییدشده', 'rejected' => 'ردشده'];
$leaveStatusBadge  = ['pending' => 'badge-warning', 'approved' => 'badge-success', 'rejected' => 'badge-error'];

$firstIn  = null;
$lastOut  = null;
foreach ($todayPunches as $p) {
    if ($p['type'] === 'in') {
        $firstIn = $firstIn ?? strtotime($p['punched_at']);
    } else {
        $lastOut = strtotime($p['punched_at']);
    }
}

// Which methods the admin allows; employee sees only these.
$allowedMethods = $allowedMethods ?? ['manual'];
if ($methodMode === 'single' && count($allowedMethods) > 1) {
    $allowedMethods = [$allowedMethods[0]];
}
$defaultMethod = in_array('manual', $allowedMethods, true) ? 'manual' : $allowedMethods[0];

$methodCards = [
    'manual'   => ['icon' => 'check',   'title' => 'ثبت سریع',        'hint' => 'بدون نیاز به دوربین یا GPS'],
    'location' => ['icon' => 'pin',     'title' => 'با موقعیت مکانی', 'hint' => 'بررسی می‌شود داخل محل کار باشید'],
    'qrcode'   => ['icon' => 'qr',      'title' => 'اسکن کد QR',      'hint' => 'کد روی صفحه ورودی را اسکن کنید'],
];
$methodLabels = [
    'qrcode'   => 'با QR',
    'location' => 'با موقعیت مکانی',
    'manual'   => 'ثبت سریع',
];

$totals = $month['totals'] ?? ['total_days' => 0, 'total_hours' => 0.0, 'total_late' => 0, 'total_early' => 0];
$maxWeek = 1;
foreach ($week as $d) {
    $maxWeek = max($maxWeek, $d['ins'] + $d['outs']);
}
$hour  = (int) date('G');
$greet = $hour < 12 ? 'صبح بخیر' : ($hour < 17 ? 'بعدازظهر بخیر' : 'شب بخیر');

// Next action: first punch of the day is "in"; after an "in" without an "out" it becomes "out".
$defaultType = ($hasIn && !$hasOut) ? 'out' : 'in';
$isIn = $defaultType === 'in';
?>

<div class="space-y-6">

<!-- Hero -->
<div class="card bg-base-100 border border-base-200 shadow-sm overflow-hidden anim-fade-up">
    <div class="card-body p-5 lg:p-6"
         style="background: radial-gradient(600px 200px at 90% -30%, color-mix(in oklab, var(--color-primary) 12%, transparent), transparent 65%);">
        <div class="flex flex-col lg:flex-row lg:items-center gap-5">
            <div class="flex items-center gap-4 flex-1 min-w-0">
                <?= View::avatar($user->name ?? '', 'avatar-circle avatar-circle-lg shrink-0') ?>
                <div class="min-w-0">
                    <div class="text-base-content/50 text-xs"><?= htmlspecialchars($greet, ENT_QUOTES, 'UTF-8') ?> 👋</div>
                    <div class="font-black text-xl truncate"><?= htmlspecialchars($user->name ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="text-xs text-base-content/50 mt-0.5" data-jalali-date>—</div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div class="text-left">
                    <div class="text-3xl font-black tabular-nums leading-none" data-clock>--:--:--</div>
                    <div class="text-[11px] text-base-content/40 mt-1 text-center">ساعت محلی</div>
                </div>
                <?php if ($hasIn && $hasOut): ?>
                    <span class="badge badge-success gap-1.5 py-3 px-3"><?= View::icon('check', 'w-4 h-4') ?>روز کاری کامل شد</span>
                <?php elseif ($hasIn): ?>
                    <span class="badge badge-warning gap-1.5 py-3 px-3"><?= View::icon('clock', 'w-4 h-4') ?>در حال کار</span>
                <?php elseif ($hasOut): ?>
                    <span class="badge badge-info gap-1.5 py-3 px-3"><?= View::icon('logout', 'w-4 h-4') ?>خروج ثبت شد</span>
                <?php else: ?>
                    <span class="badge badge-ghost gap-1.5 py-3 px-3"><?= View::icon('info', 'w-4 h-4') ?>امروز هنوز چیزی ثبت نشده</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-base-200/60">
            <div class="flex items-center gap-1.5 text-sm">
                <?= View::icon('login', 'w-4 h-4 text-success') ?>
                <span class="text-base-content/50">اولین ورود:</span>
                <span class="font-bold tabular-nums"><?= $firstIn ? View::fa(date('H:i', $firstIn)) : '—' ?></span>
            </div>
            <div class="flex items-center gap-1.5 text-sm">
                <?= View::icon('logout', 'w-4 h-4 text-info') ?>
                <span class="text-base-content/50">آخرین خروج:</span>
                <span class="font-bold tabular-nums"><?= $lastOut ? View::fa(date('H:i', $lastOut)) : '—' ?></span>
            </div>
            <div class="flex items-center gap-1.5 text-sm">
                <?= View::icon('pin', 'w-4 h-4 text-base-content/40') ?>
                <span class="text-base-content/50">ثبت‌های امروز:</span>
                <span class="font-bold tabular-nums"><?= View::fa((string) count($todayPunches)) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Month KPIs -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
    <div class="card bg-base-100 border border-base-200 anim-fade-up anim-delay-1">
        <div class="card-body p-4">
            <div class="flex items-center justify-between">
                <span class="text-xs text-base-content/50">کارکرد این ماه</span>
                <span class="text-primary"><?= View::icon('clock', 'w-4 h-4') ?></span>
            </div>
            <div class="text-2xl font-black tabular-nums"><?= View::fa(number_format((float) $totals['total_hours'], 1)) ?></div>
            <div class="text-[11px] text-base-content/40">ساعت (ماه جلالی جاری)</div>
        </div>
    </div>
    <div class="card bg-base-100 border border-base-200 anim-fade-up anim-delay-2">
        <div class="card-body p-4">
            <div class="flex items-center justify-between">
                <span class="text-xs text-base-content/50">روزهای حضور</span>
                <span class="text-secondary"><?= View::icon('calendar', 'w-4 h-4') ?></span>
            </div>
            <div class="text-2xl font-black tabular-nums"><?= View::fa((string) (int) $totals['total_days']) ?></div>
            <div class="text-[11px] text-base-content/40">روز کاری</div>
        </div>
    </div>
    <div class="card bg-base-100 border border-base-200 anim-fade-up anim-delay-3">
        <div class="card-body p-4">
            <div class="flex items-center justify-between">
                <span class="text-xs text-base-content/50">تاخیرها</span>
                <span class="<?= (int) $totals['total_late'] > 0 ? 'text-warning' : 'text-success' ?>">
                    <?= View::icon((int) $totals['total_late'] > 0 ? 'trend-down' : 'check', 'w-4 h-4') ?>
                </span>
            </div>
            <div class="text-2xl font-black tabular-nums <?= (int) $totals['total_late'] > 0 ? 'text-warning' : '' ?>">
                <?= View::fa((string) (int) $totals['total_late']) ?>
            </div>
            <div class="text-[11px] text-base-content/40">بار در این ماه</div>
        </div>
    </div>
    <div class="card bg-base-100 border border-base-200 anim-fade-up anim-delay-4">
        <div class="card-body p-4">
            <div class="flex items-center justify-between">
                <span class="text-xs text-base-content/50">مرخصی در انتظار</span>
                <span class="text-accent"><?= View::icon('file', 'w-4 h-4') ?></span>
            </div>
            <div class="text-2xl font-black tabular-nums <?= $pendingLeaves > 0 ? 'text-accent' : '' ?>">
                <?= View::fa((string) (int) $pendingLeaves) ?>
            </div>
            <div class="text-[11px] text-base-content/40">درخواست در بررسی</div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-5 gap-4">
    <!-- Punch form -->
    <div class="card bg-base-100 border border-base-200 anim-fade-up xl:col-span-2 self-start">
        <div class="card-body p-5 gap-4">
            <h2 class="card-title text-base gap-2">
                <?= View::icon($isIn ? 'login' : 'logout', 'w-5 h-5 ' . ($isIn ? 'text-success' : 'text-info')) ?>
                <?= $isIn ? 'ثبت ورود' : 'ثبت خروج' ?>
            </h2>
            <?php if ($hasIn && $hasOut): ?>
            <p class="text-xs text-base-content/50 -mt-2">ورود و خروج امروزتان ثبت شده؛ ثبت جدید همچنان امکان‌پذیر است.</p>
            <?php endif; ?>

            <form method="POST" action="/punch" class="space-y-4" data-punch-form>
                <?= $csrf ?>
                <!-- What is being registered (fixed by today's status) -->
                <input type="hidden" name="type" value="<?= $defaultType ?>">

                <?php if (count($allowedMethods) === 1): ?>
                <input type="hidden" name="method" value="<?= htmlspecialchars($allowedMethods[0], ENT_QUOTES, 'UTF-8') ?>">
                <?php else: ?>
                <div>
                    <span class="text-xs font-medium text-base-content/70">با چه روشی؟</span>
                    <div class="grid grid-cols-1 gap-2 mt-2">
                        <?php foreach ($allowedMethods as $m): $card = $methodCards[$m] ?? null; if (!$card) continue; ?>
                        <label class="flex items-center gap-3 rounded-xl border p-3 cursor-pointer transition-colors hover:border-primary/40 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                            <input type="radio" name="method" value="<?= $m ?>" data-punch-method
                                   class="radio radio-primary radio-sm"
                                   <?= $m === $defaultMethod ? 'checked' : '' ?>>
                            <span class="flex items-center gap-2 min-w-0">
                                <span class="text-primary shrink-0"><?= View::icon($card['icon'], 'w-5 h-5') ?></span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold"><?= $card['title'] ?></span>
                                    <span class="block text-[11px] text-base-content/50"><?= $card['hint'] ?></span>
                                </span>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (in_array('location', $allowedMethods, true)): ?>
                <div data-panel="location" class="<?= count($allowedMethods) === 1 && $allowedMethods[0] === 'location' ? '' : 'hidden' ?>">
                    <div class="rounded-lg border border-dashed border-base-300 p-3 text-sm text-base-content/60 flex items-center gap-2">
                        <?= View::icon('pin', 'w-4 h-4 shrink-0') ?>
                        <span id="geo-status">در حال دریافت موقعیت…</span>
                    </div>
                    <div id="geo-retry" class="hidden mt-2">
                        <button type="button" id="geo-retry-btn"
                                class="btn btn-sm btn-outline btn-primary w-full">
                            <?= View::icon('pin', 'w-4 h-4') ?> تلاش دوباره برای دریافت موقعیت
                        </button>
                    </div>
                    <input type="hidden" name="lat" id="lat">
                    <input type="hidden" name="lng" id="lng">
                </div>
                <?php endif; ?>

                <?php if ($geofenceOn && !in_array('location', $allowedMethods, true)): ?>
                <!-- Geofence is enforced server-side for every punch method — capture
                     GPS even when "location" is not one of the allowed methods. -->
                <div data-panel="location" class="hidden">
                    <div class="rounded-lg border border-dashed border-base-300 p-3 text-sm text-base-content/60 flex items-center gap-2">
                        <?= View::icon('pin', 'w-4 h-4 shrink-0') ?>
                        <span id="geo-status">در حال دریافت موقعیت…</span>
                    </div>
                    <div id="geo-retry" class="hidden mt-2">
                        <button type="button" id="geo-retry-btn"
                                class="btn btn-sm btn-outline btn-primary w-full">
                            <?= View::icon('pin', 'w-4 h-4') ?> تلاش دوباره برای دریافت موقعیت
                        </button>
                    </div>
                    <input type="hidden" name="lat" id="lat">
                    <input type="hidden" name="lng" id="lng">
                </div>
                <?php endif; ?>

                <?php if (in_array('qrcode', $allowedMethods, true)): ?>
                <div data-panel="qrcode" class="<?= count($allowedMethods) === 1 && $allowedMethods[0] === 'qrcode' ? '' : 'hidden' ?> space-y-3">
                    <!-- <label class="form-control">
                        <span class="label"><span class="label-text font-medium">کد نمایش‌داده‌شده روی صفحه ورودی</span></span>
                        <input type="text" name="qr_token" id="qr-token" placeholder="کد را اینجا وارد کنید"
                               class="input input-bordered w-full" dir="ltr" autocomplete="off">
                    </label>
                    <div class="flex items-center gap-2 text-xs text-base-content/50">
                        <span class="h-px flex-1 bg-base-300"></span>
                        یا
                        <span class="h-px flex-1 bg-base-300"></span>
                    </div> -->
                    <button type="button" data-qr-camera-btn class="btn btn-outline btn-primary w-full">
                        <?= View::icon('qr', 'w-5 h-5') ?>
                        اسکن با دوربین گوشی
                    </button>
                    <div id="qr-reader" class="hidden rounded-xl overflow-hidden border border-base-300"></div>
                    <div id="qr-camera-status" class="hidden text-sm"></div>
                </div>
                <?php endif; ?>

                <!-- Optional note, collapsed by default to keep the form simple -->
                <details class="group">
                    <summary class="cursor-pointer select-none text-xs text-base-content/50 hover:text-base-content/80 list-none [&::-webkit-details-marker]:hidden">
                        + افزودن یادداشت (اختیاری)
                    </summary>
                    <input type="text" name="note" class="input input-bordered w-full mt-2" maxlength="255"
                           placeholder="مثلاً: دورکاری، ماموریت، …">
                </details>

                <button type="submit" class="btn btn-primary btn-lg w-full h-14 text-base">
                    <?= View::icon($isIn ? 'login' : 'logout', 'w-6 h-6') ?>
                    <?= $isIn ? 'ثبت ورود' : 'ثبت خروج' ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Weekly activity chart -->
    <div class="card bg-base-100 border border-base-200 anim-fade-up anim-delay-1 xl:col-span-3 self-start">
        <div class="card-body p-5 gap-4">
            <div class="flex items-center justify-between">
                <h2 class="card-title text-base gap-2"><?= View::icon('chart', 'w-5 h-5 text-secondary') ?>فعالیت ۷ روز اخیر</h2>
                <span class="text-xs text-base-content/40">تعداد ثبت در روز</span>
            </div>

            <div class="flex items-end justify-between gap-2 h-40 pt-4" dir="ltr">
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
</div>

    <!-- Today timeline + my leave requests -->
    <div class="grid grid-cols-1 xl:grid-cols-5 gap-4">
    <div class="card bg-base-100 border border-base-200 anim-fade-up anim-delay-2 xl:col-span-3 self-start">
        <div class="card-body p-5 gap-4">
            <div class="flex items-center justify-between">
                <h2 class="card-title text-base gap-2"><?= View::icon('clock', 'w-5 h-5 text-accent') ?>تایم‌لاین امروز</h2>
                <?php if (!empty($todayPunches)): ?>
                <span class="badge badge-ghost badge-sm"><?= View::fa((string) count($todayPunches)) ?> رکورد</span>
                <?php endif; ?>
            </div>

            <?php if (empty($todayPunches)): ?>
            <div class="py-8 text-center text-base-content/40 text-sm flex flex-col items-center gap-3">
                <?= View::icon('clock', 'w-10 h-10 opacity-30') ?>
                <p>برای امروز هنوز چیزی ثبت نشده است.</p>
                <p class="text-xs">ورود خود را از فرم کنار ثبت کنید.</p>
            </div>
            <?php else: ?>
            <ul class="timeline timeline-vertical timeline-compact">
                <?php foreach ($todayPunches as $p): ?>
                <li>
                    <hr class="bg-base-300" />
                    <div class="timeline-middle">
                        <span class="w-8 h-8 rounded-full flex items-center justify-center
                                     <?= $p['type'] === 'in' ? 'bg-success/15 text-success' : 'bg-info/15 text-info' ?>">
                            <?= View::icon($p['type'] === 'in' ? 'login' : 'logout', 'w-4 h-4') ?>
                        </span>
                    </div>
                    <div class="timeline-end timeline-box bg-base-100 border-base-200 py-2 px-4 flex items-center gap-3">
                        <span class="font-bold tabular-nums"><?= View::fa(date('H:i', strtotime($p['punched_at']))) ?></span>
                        <span class="badge badge-sm <?= $p['type'] === 'in' ? 'badge-success' : 'badge-info' ?> badge-outline">
                            <?= $p['type'] === 'in' ? 'ورود' : 'خروج' ?>
                        </span>
                        <span class="text-xs text-base-content/50">
                            <?= htmlspecialchars($methodLabels[$p['method']] ?? $p['method'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <?php if (!empty($p['note'])): ?>
                        <span class="text-xs text-base-content/40 truncate max-w-40">— <?= htmlspecialchars($p['note'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>
                    <hr class="bg-base-300" />
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- My leave requests -->
    <div class="card bg-base-100 border border-base-200 anim-fade-up anim-delay-3 xl:col-span-2 self-start">
        <div class="card-body p-5 gap-4">
            <div class="flex items-center justify-between">
                <h2 class="card-title text-base gap-2"><?= View::icon('file', 'w-5 h-5 text-secondary') ?>درخواست‌های مرخصی من</h2>
                <a href="/leave-requests" class="btn btn-ghost btn-xs text-primary">همه ←</a>
            </div>

            <?php if (empty($myLeaves)): ?>
            <div class="py-6 text-center text-base-content/40 text-sm flex flex-col items-center gap-3">
                <?= View::icon('calendar', 'w-10 h-10 opacity-30') ?>
                <p>هنوز درخواستی ثبت نکرده‌اید.</p>
            </div>
            <?php else: ?>
            <ul class="divide-y divide-base-200/70">
                <?php foreach ($myLeaves as $lr): ?>
                <?php
                    $status = $lr['status'] ?? 'pending';
                    $fromTs = strtotime($lr['start_at'] ?? '');
                    $toTs   = strtotime($lr['end_at'] ?? '');
                ?>
                <li class="flex items-center gap-3 py-2.5">
                    <span class="badge badge-sm <?= $leaveStatusBadge[$status] ?? 'badge-ghost' ?> badge-outline shrink-0">
                        <?= htmlspecialchars($leaveStatusLabels[$status] ?? $status, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-medium">
                            <?= $lr['type'] === 'hourly' ? 'مرخصی ساعتی' : 'مرخصی روزانه' ?>
                        </div>
                        <div class="text-xs text-base-content/50 tabular-nums">
                            <?= View::fa(\App\Services\CalendarService::formatJalali($fromTs)) ?>
                            <?= $toTs && $toTs != $fromTs ? ' تا ' . View::fa(\App\Services\CalendarService::formatJalali($toTs)) : '' ?>
                        </div>
                    </div>
                    <?php if (!empty($lr['reason'])): ?>
                    <span class="text-xs text-base-content/40 truncate max-w-32"><?= htmlspecialchars($lr['reason'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <a href="/leave-requests" class="btn btn-outline btn-sm w-full">
                <?= View::icon('calendar', 'w-4 h-4') ?>ثبت درخواست جدید
            </a>
        </div>
    </div>
    </div>
</div>

