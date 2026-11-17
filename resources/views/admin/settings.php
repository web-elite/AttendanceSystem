<?php
// Variables: $settings (key→value map), $csrf, $neshanKey, $methodMeta
use App\Core\View;

$neshanLat = (float) ($settings['office_lat'] ?? 36.2870);
$neshanLng = (float) ($settings['office_lng'] ?? 59.6157);
$neshanRadius = (int) ($settings['radius_m'] ?? 150);
$hasNeshanKey = strlen(trim($neshanKey ?? '')) >= 12;

$activeMethods = json_decode($settings['methods'] ?? '[]', true) ?: [];
if ($activeMethods === []) {
    $activeMethods = ['manual'];
}
$methodMode = $settings['method_mode'] ?? 'combined';
$qrMode = $settings['qr_mode'] ?? 'rotating';
$geofenceOn = ($settings['geofence_enabled'] ?? '0') === '1';
?>
<div class="space-y-6">
    <div class="flex items-center gap-3">
        <span class="avatar-circle avatar-circle-lg bg-primary/10 text-primary shrink-0"><?= View::icon('settings', 'w-5 h-5') ?></span>
        <div>
            <h1 class="text-xl font-bold leading-tight">تنظیمات</h1>
            <p class="text-xs text-base-content/50 mt-0.5">قوانین ثبت حضور، محدوده مکانی و مرخصی‌ها</p>
        </div>
    </div>

    <form method="POST" action="/admin/settings/update" class="card bg-base-100 border border-base-200 anim-fade-up">
        <div class="card-body">
        <?= $csrf ?>

        <!-- ═══ Work hours ═══ -->
        <div class="divider my-4 border-base-200">ساعت کاری</div>
        <p class="text-xs text-base-content/50 -mt-2 mb-4 leading-relaxed">
            ورود قبل از «شروع کار» مشکلی ندارد؛ اگر بعد از آن باشد، تاخیر ثبت می‌شود.
            «ارفاق تاخیر» یعنی چند دقیقه اول تاخیر نادیده گرفته می‌شود و در گزارش شمرده نمی‌شود.
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="form-control">
                <label class="label"><span class="label-text">شروع کار (ساعت ورود)</span></label>
                <input type="time" name="min_in_time"
                       value="<?= htmlspecialchars($settings['min_in_time'] ?? '09:00', ENT_QUOTES, 'UTF-8') ?>"
                       class="input input-bordered w-full">
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">پایان کار (ساعت خروج)</span></label>
                <input type="time" name="max_out_time"
                       value="<?= htmlspecialchars($settings['max_out_time'] ?? '17:00', ENT_QUOTES, 'UTF-8') ?>"
                       class="input input-bordered w-full">
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">ارفاق تاخیر (دقیقه)</span></label>
                <input type="number" name="grace_minutes" min="0" max="120"
                       value="<?= (int) ($settings['grace_minutes'] ?? 0) ?>"
                       class="input input-bordered w-full">
                <label class="label"><span class="label-text-alt text-base-content/40">۰ تا ۱۲۰ دقیقه — صفر یعنی بدون ارفاق</span></label>
            </div>
        </div>

        <!-- ═══ Punch methods ═══ -->
        <div class="divider my-4 border-base-200">روش ثبت ورود و خروج</div>
        <p class="text-xs text-base-content/50 -mt-2 mb-4 leading-relaxed">
            مشخص کنید کارمندان با چه روش‌هایی می‌توانند ورود و خروج خود را ثبت کنند.
            در حالت «انتخابی» کارمند هر کدام از روش‌های فعال را که خواست استفاده می‌کند؛
            در حالت «اجباری» فقط همان یک روش در دسترس اوست.
        </p>
        <div class="space-y-2">
            <?php foreach ($methodMeta as $key => $meta): ?>
            <label class="flex items-start gap-3 rounded-xl border p-3 cursor-pointer transition-colors hover:border-primary/40 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                <input type="checkbox" name="methods[]" value="<?= $key ?>"
                       <?= in_array($key, $activeMethods, true) ? 'checked' : '' ?>
                       class="checkbox checkbox-primary mt-0.5">
                <span class="min-w-0">
                    <span class="block text-sm font-semibold"><?= htmlspecialchars($meta[0], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="block text-xs text-base-content/50 leading-relaxed mt-0.5"><?= htmlspecialchars($meta[1], ENT_QUOTES, 'UTF-8') ?></span>
                </span>
            </label>
            <?php endforeach; ?>
        </div>
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label class="flex items-center gap-3 rounded-xl border p-3 cursor-pointer transition-colors hover:border-primary/40 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                <input type="radio" name="method_mode" value="combined"
                       <?= $methodMode !== 'single' ? 'checked' : '' ?> class="radio radio-primary">
                <span class="min-w-0">
                    <span class="block text-sm font-semibold">روش انتخابی</span>
                    <span class="block text-xs text-base-content/50 mt-0.5">هر روشی از روش‌های بالا که فعال باشد، در دسترس کارمند است</span>
                </span>
            </label>
            <label class="flex items-center gap-3 rounded-xl border p-3 cursor-pointer transition-colors hover:border-primary/40 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                <input type="radio" name="method_mode" value="single"
                       <?= $methodMode === 'single' ? 'checked' : '' ?> class="radio radio-primary">
                <span class="min-w-0">
                    <span class="block text-sm font-semibold">یک روش اجباری</span>
                    <span class="block text-xs text-base-content/50 mt-0.5">باید فقط یک روش انتخاب شده باشد؛ همان برای همه الزامی است</span>
                </span>
            </label>
        </div>

        <!-- ═══ Location fence ═══ -->
        <div class="divider my-4 border-base-200">محدوده مکانی (اختیاری)</div>
        <label class="flex items-start gap-3 rounded-xl border p-3 cursor-pointer transition-colors hover:border-primary/40 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
            <input type="checkbox" name="geofence_enabled" value="1"
                   <?= $geofenceOn ? 'checked' : '' ?>
                   class="checkbox checkbox-primary mt-0.5">
            <span class="min-w-0">
                <span class="block text-sm font-semibold">فقط از محل کار بتوان ثبت کرد</span>
                <span class="block text-xs text-base-content/50 leading-relaxed mt-0.5">
                    هنگام ثبت، فاصله کارمند از محل کار حساب می‌شود؛ اگر بیرون از شعاع تعیین‌شده باشد، ثبت رد می‌شود.
                    فاصله هر ثبت در گزارش‌ها ذخیره می‌شود تا بعداً قابل بررسی باشد.
                </span>
            </span>
        </label>
        <?php if ($hasNeshanKey): ?>
        <!-- Neshan map picker (SDK self-hosted, CSP-safe) -->
        <div class="mt-4">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <label class="label py-0"><span class="label-text font-semibold">محل کار روی نقشه</span></label>
                <span class="text-xs text-base-content/50">روی نقشه کلیک/لمس کنید تا نقطه جابه‌جا شود</span>
            </div>
            <div class="neshan-map"
                 data-neshan-map
                 data-key="<?= htmlspecialchars($neshanKey, ENT_QUOTES, 'UTF-8') ?>"
                 data-lat="<?= $neshanLat ?>"
                 data-lng="<?= $neshanLng ?>"
                 data-radius="<?= $neshanRadius ?>"></div>
            <link rel="stylesheet" href="/assets/vendor/neshan/index.css">
            <script src="/assets/vendor/neshan/index.js"></script>
        </div>
        <?php endif; ?>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
            <div class="form-control">
                <label class="label"><span class="label-text">عرض جغرافیایی</span></label>
                <input type="text" id="office_lat" name="office_lat" inputmode="decimal" dir="ltr"
                       value="<?= htmlspecialchars($settings['office_lat'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="36.2871" class="input input-bordered w-full font-mono">
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">طول جغرافیایی</span></label>
                <input type="text" id="office_lng" name="office_lng" inputmode="decimal" dir="ltr"
                       value="<?= htmlspecialchars($settings['office_lng'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="59.6090" class="input input-bordered w-full font-mono">
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">شعاع مجاز (متر)</span></label>
                <input type="number" name="radius_m" id="radius_m" min="50" max="5000"
                       value="<?= $neshanRadius ?>"
                       class="input input-bordered w-full">
                <label class="label"><span class="label-text-alt text-base-content/40">در فضای بسته GPS نوسان دارد؛ کمتر از ۱۵۰ متر توصیه نمی‌شود</span></label>
            </div>
        </div>
        <?php if (!$hasNeshanKey): ?>
        <p class="text-xs text-base-content/50 mt-3 bg-base-200/60 rounded-lg p-3 leading-relaxed">
            برای انتخاب محل کار از روی نقشه، کلید API نشان را در فایل <code dir="ltr">.env</code> با کلید
            <code dir="ltr">NESHAN_API_KEY</code> تنظیم کنید. تا آن زمان مختصات را دستی وارد کنید.
        </p>
        <?php endif; ?>

        <!-- ═══ QR ═══ -->
        <div class="divider my-4 border-base-200">کد QR</div>
        <?php if (!in_array('qrcode', $activeMethods, true)): ?>
        <div class="alert alert-warning text-sm py-2.5 mb-4">
            <?= View::icon('warning', 'w-4 h-4 shrink-0') ?>
            <span>روش QR در بخش «روش ثبت» فعال نیست؛ این تنظیمات فعلاً اثری ندارد.</span>
        </div>
        <?php endif; ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label class="flex items-center gap-3 rounded-xl border p-3 cursor-pointer transition-colors hover:border-primary/40 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                <input type="radio" name="qr_mode" value="rotating"
                       <?= $qrMode !== 'static' ? 'checked' : '' ?> class="radio radio-primary">
                <span class="min-w-0">
                    <span class="block text-sm font-semibold">کد متغیر (امن‌تر)</span>
                    <span class="block text-xs text-base-content/50 leading-relaxed mt-0.5">کد هر چند ثانیه عوض می‌شود؛ عکس گرفتنش فایده ندارد و از راه دور قابل استفاده نیست</span>
                </span>
            </label>
            <label class="flex items-center gap-3 rounded-xl border p-3 cursor-pointer transition-colors hover:border-primary/40 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                <input type="radio" name="qr_mode" value="static"
                       <?= $qrMode === 'static' ? 'checked' : '' ?> class="radio radio-primary">
                <span class="min-w-0">
                    <span class="block text-sm font-semibold">کد ثابت (چاپ‌شده)</span>
                    <span class="block text-xs text-base-content/50 leading-relaxed mt-0.5">یک کد همیشگی که می‌توانید چاپ کنید و به دیوار بزنید</span>
                </span>
            </label>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4 items-start">
            <div class="form-control">
                <label class="label"><span class="label-text">هر چند ثانیه کد عوض شود</span></label>
                <input type="number" name="qr_ttl_seconds" min="30" max="300"
                       value="<?= (int) ($settings['qr_ttl_seconds'] ?? 60) ?>"
                       class="input input-bordered w-full"
                       <?= $qrMode === 'static' ? 'disabled' : '' ?>>
                <label class="label"><span class="label-text-alt text-base-content/40">۳۰ تا ۳۰۰ ثانیه — فقط برای کد متغیر</span></label>
            </div>
            <div class="form-control" data-qr-static-field>
                <label class="label"><span class="label-text">کد ثابت</span></label>
                <div class="flex gap-2">
                    <input type="text" name="qr_static_token"
                           value="<?= htmlspecialchars($settings['qr_static_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="خالی بگذارید تا ساخته شود"
                           class="input input-bordered w-full font-mono text-xs" dir="ltr"
                           <?= $qrMode !== 'static' ? 'disabled' : '' ?>>
                    <button type="button" class="btn btn-ghost btn-square" title="ساخت کد جدید" data-gen-token>↻</button>
                </div>
                <label class="label"><span class="label-text-alt text-base-content/40">در حالت ثابت استفاده می‌شود؛ خالی = هنگام نمایش کد ساخته می‌شود</span></label>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="/admin/qr" target="_blank" class="btn btn-outline btn-sm">
                <?= View::icon('qr', 'w-4 h-4') ?>نمایش و چاپ کد QR
            </a>
        </div>

        <!-- ═══ Policies ═══ -->
        <div class="divider my-4 border-base-200">قوانین دیگر</div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="form-control">
                <label class="label"><span class="label-text">اگر کارمند خروجش را فراموش کند</span></label>
                <select name="forgotten_checkout" class="select select-bordered w-full">
                    <option value="auto_close" <?= ($settings['forgotten_checkout'] ?? 'auto_close') === 'auto_close' ? 'selected' : '' ?>>خروجش آخر شب خودکار بسته شود</option>
                    <option value="admin_manual" <?= ($settings['forgotten_checkout'] ?? '') === 'admin_manual' ? 'selected' : '' ?>>تا مدیر دستی ثبت نکند باز بماند</option>
                </select>
                <label class="label"><span class="label-text-alt text-base-content/40">خروج‌های خودکار در گزارش «تخمینی» علامت می‌خورند</span></label>
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">روزهای تعطیل</span></label>
                <select name="holiday_mode" class="select select-bordered w-full">
                    <option value="none" <?= ($settings['holiday_mode'] ?? '') === 'none' ? 'selected' : '' ?>>هیچ روزی تعطیل نباشد</option>
                    <option value="friday" <?= ($settings['holiday_mode'] ?? 'friday') === 'friday' ? 'selected' : '' ?>>فقط جمعه‌ها</option>
                    <option value="official" <?= ($settings['holiday_mode'] ?? '') === 'official' ? 'selected' : '' ?>>جمعه‌ها + تعطیلات رسمی</option>
                    <option value="both" <?= ($settings['holiday_mode'] ?? '') === 'both' ? 'selected' : '' ?>>جمعه‌ها + همه تعطیلات رسمی</option>
                </select>
                <label class="label"><span class="label-text-alt text-base-content/40">در روزهای تعطیل، تاخیر و غیبت محاسبه نمی‌شود</span></label>
            </div>
        </div>

        <div class="card-actions mt-6 justify-end">
            <button type="submit" class="btn btn-primary">
                <?= View::icon('check', 'w-4 h-4') ?>ذخیره تنظیمات
            </button>
        </div>
        </div>
    </form>
</div>
