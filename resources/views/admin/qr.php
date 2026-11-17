<?php
// Variables: $qrMode (rotating|static), $token (raw token), $expiresIn (seconds|null), $dataUri
use App\Core\View;
?>
<div class="space-y-6 print:!hidden" data-qr-page>
    <div class="flex items-center gap-3">
        <span class="avatar-circle avatar-circle-lg bg-primary/10 text-primary shrink-0"><?= View::icon('qr', 'w-5 h-5') ?></span>
        <div>
            <h1 class="text-xl font-bold leading-tight">نمایش کد QR</h1>
            <p class="text-xs text-base-content/50 mt-0.5">این صفحه را روی مانیتور ورودی نمایش دهید یا کد را چاپ کنید</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <!-- The code itself -->
        <div class="lg:col-span-2 card bg-base-100 border border-base-200 anim-fade-up">
            <div class="card-body items-center text-center">
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-base-200">
                    <img src="<?= htmlspecialchars($dataUri, ENT_QUOTES, 'UTF-8') ?>"
                         alt="کد QR ثبت حضور"
                         class="w-64 h-64 sm:w-72 sm:h-72 object-contain block"
                         data-qr-img>
                </div>
                <p class="text-sm font-semibold mt-4">ثبت ورود و خروج با اسکن کد QR</p>
                <p class="text-xs text-base-content/50 leading-relaxed max-w-sm">
                    کارمند در صفحه داشبورد خود گزینه «اسکن کد QR» را می‌زند و دوربین گوشی را روی این کد نگه می‌دارد.
                </p>
            </div>
        </div>

        <!-- Side panel: mode info + countdown + actions -->
        <div class="space-y-4">
            <?php if ($qrMode === 'rotating'): ?>
            <div class="card bg-base-100 border border-base-200 anim-fade-up anim-delay-1">
                <div class="card-body gap-2">
                    <h2 class="font-bold text-sm flex items-center gap-2">
                        <?= View::icon('clock', 'w-4 h-4 text-primary') ?>کد متغیر
                    </h2>
                    <p class="text-xs text-base-content/50 leading-relaxed">
                        این کد هر
                        <strong><?= View::fa((string) ($expiresIn ?? 60)) ?></strong>
                        ثانیه عوض می‌شود و بعد از استفاده یک‌بارمصرف است. صفحه باید باز بماند تا کد تازه نمایش دهد.
                    </p>
                    <div class="mt-2">
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="text-base-content/60">تعویض کد بعدی</span>
                            <span class="font-mono font-bold text-primary" data-qr-countdown><?= View::fa((string) ($expiresIn ?? 60)) ?></span>
                        </div>
                        <progress class="progress progress-primary w-full" max="<?= (int) ($expiresIn ?? 60) ?>" value="<?= (int) ($expiresIn ?? 60) ?>" data-qr-progress></progress>
                    </div>
                    <label class="label cursor-pointer justify-start gap-2 mt-1 py-1">
                        <input type="checkbox" class="toggle toggle-primary toggle-sm" checked data-qr-sound>
                        <span class="label-text text-xs">هشدار صوتی هنگام تعویض کد</span>
                    </label>
                </div>
            </div>
            <?php else: ?>
            <div class="card bg-base-100 border border-base-200 anim-fade-up anim-delay-1">
                <div class="card-body gap-2">
                    <h2 class="font-bold text-sm flex items-center gap-2">
                        <?= View::icon('pin', 'w-4 h-4 text-primary') ?>کد ثابت
                    </h2>
                    <p class="text-xs text-base-content/50 leading-relaxed">
                        این کد همیشه یکی است. می‌توانید چاپش کنید و به دیوار محل کار بزنید؛ نیازی به باز ماندن صفحه نیست.
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <div class="card bg-base-100 border border-base-200 anim-fade-up anim-delay-2">
                <div class="card-body gap-2">
                    <button type="button" class="btn btn-primary w-full" data-qr-print>
                        <?= View::icon('download', 'w-4 h-4') ?>چاپ کد
                    </button>
                    <button type="button" class="btn btn-ghost btn-sm w-full" data-qr-refresh>
                        <?= View::icon('qr', 'w-4 h-4') ?>تازه‌سازی دستی
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print-only sheet (everything else hides when printing) -->
<div class="hidden print:block text-center" dir="rtl">
    <h1 class="text-2xl font-black mb-1">ثبت ورود و خروج</h1>
    <p class="text-sm mb-6">دوربین گوشی را روی کد زیر بگیرید</p>
    <img src="<?= htmlspecialchars($dataUri, ENT_QUOTES, 'UTF-8') ?>"
         alt="کد QR"
         class="w-[9cm] h-[9cm] mx-auto object-contain">
    <p class="text-xs mt-6 opacity-60">
        <?= $qrMode === 'static' ? 'این کد دائمی است و منقضی نمی‌شود.' : 'توجه: این کد متغیر است؛ پس از چاپ فقط در همان لحظه معتبر است. برای چاپ، حالت «کد ثابت» را در تنظیمات انتخاب کنید.' ?>
    </p>
</div>
