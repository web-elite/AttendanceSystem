<?php
// Variables: $punch, $csrf
use App\Core\View;
?>
<div class="space-y-6">
    <div class="flex items-center gap-3">
        <span class="avatar-circle avatar-circle-lg bg-primary/10 text-primary shrink-0"><?= View::icon('clock', 'w-5 h-5') ?></span>
        <div>
            <h1 class="text-xl font-bold leading-tight">ویرایش ثبت #<?= View::fa((string) $punch['id']) ?></h1>
            <p class="text-xs text-base-content/50 mt-0.5">اصلاح رکورد حضور و غیاب</p>
        </div>
    </div>

    <form method="POST" action="/admin/punches/<?= (int) $punch['id'] ?>/update"
          class="card bg-base-100 border border-base-200 max-w-lg anim-fade-up">
        <div class="card-body gap-4">
        <?= $csrf ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="label"><span class="label-text">نوع</span></label>
                <select name="type" class="select select-bordered w-full">
                    <option value="in" <?= $punch['type'] === 'in' ? 'selected' : '' ?>>ورود</option>
                    <option value="out" <?= $punch['type'] === 'out' ? 'selected' : '' ?>>خروج</option>
                </select>
            </div>
            <div>
                <label class="label"><span class="label-text">روش</span></label>
                <select name="method" class="select select-bordered w-full">
                    <option value="manual" <?= $punch['method'] === 'manual' ? 'selected' : '' ?>>دستی</option>
                    <option value="location" <?= $punch['method'] === 'location' ? 'selected' : '' ?>>موقعیت</option>
                    <option value="qrcode" <?= $punch['method'] === 'qrcode' ? 'selected' : '' ?>>QR</option>
                </select>
            </div>
        </div>

        <div class="form-control">
            <label class="label"><span class="label-text">تاریخ و ساعت (شمسی — مثال: 1405/01/15 09:30)</span></label>
            <?php
                // Show the stored Gregorian datetime as Jalali for the admin.
                $punchTs  = strtotime($punch['punched_at']);
                $jDate    = \App\Services\CalendarService::formatJalali($punchTs);
                $jTime    = date('H:i', $punchTs);
                $jDisplay = $jDate . ' ' . $jTime;
            ?>
            <input type="text" name="punched_at" required data-jdp
                   value="<?= htmlspecialchars($jDisplay, ENT_QUOTES, 'UTF-8') ?>"
                   class="input input-bordered w-full" dir="ltr" placeholder="YYYY/MM/DD HH:MM">
        </div>

        <div class="form-control">
            <label class="label"><span class="label-text">یادداشت</span></label>
            <textarea name="note" rows="3" class="textarea textarea-bordered w-full">
<?= htmlspecialchars($punch['note'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <div class="alert alert-info text-sm py-2.5">
            <?= View::icon('info', 'w-4 h-4 shrink-0') ?>
            <span>هر تغییر در لاگ ممیزی ثبت می‌شود.</span>
        </div>

        <div class="card-actions mt-2 justify-end">
            <a href="/admin/punches" class="btn btn-ghost">بازگشت</a>
            <button type="submit" class="btn btn-primary">
                <?= View::icon('check', 'w-4 h-4') ?>ذخیره
            </button>
        </div>
        </div>
    </form>
</div>
