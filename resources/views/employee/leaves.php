<?php
// Variables: $user, $requests, $csrf
use App\Core\View;
?>
<div class="space-y-6">
    <div class="flex items-center gap-3">
        <span class="avatar-circle avatar-circle-lg bg-accent/10 text-accent shrink-0"><?= View::icon('calendar', 'w-5 h-5') ?></span>
        <div>
            <h1 class="text-xl font-bold leading-tight">درخواست مرخصی</h1>
            <p class="text-xs text-base-content/50 mt-0.5">ثبت درخواست و پیگیری وضعیت آن</p>
        </div>
    </div>

    <div class="card bg-base-100 border border-base-200 anim-fade-up">
        <div class="card-body">
            <h2 class="card-title text-base">درخواست جدید</h2>
            <form method="POST" action="/leave-requests" class="space-y-4">
                <?= $csrf ?>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="label"><span class="label-text">نوع</span></label>
                        <select name="type" class="select select-bordered w-full">
                            <option value="hourly">ساعتی</option>
                            <option value="daily">روزانه</option>
                        </select>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">شروع</span></label>
                        <input type="text" name="start_at" placeholder="YYYY/MM/DD"
                               class="input input-bordered w-full">
                    </div>
                    <div>
                        <label class="label"><span class="label-text">پایان</span></label>
                        <input type="text" name="end_at" placeholder="YYYY/MM/DD"
                               class="input input-bordered w-full">
                    </div>
                </div>
                <div>
                    <label class="label"><span class="label-text">دلیل</span></label>
                    <textarea name="reason" rows="3" class="textarea textarea-bordered w-full"
                              placeholder="دلیل مرخصی..."></textarea>
                </div>
                <div class="card-actions">
                    <button type="submit" class="btn btn-primary">
                        <?= View::icon('check', 'w-4 h-4') ?>ثبت درخواست
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card bg-base-100 border border-base-200 anim-fade-up anim-delay-1">
        <div class="card-body p-0">
            <div class="px-5 pt-5 pb-2 flex items-center justify-between">
                <h2 class="font-bold">تاریخچه</h2>
                <?php if (!empty($requests)): ?>
                <span class="badge badge-ghost badge-sm"><?= View::fa((string) count($requests)) ?> درخواست</span>
                <?php endif; ?>
            </div>
            <?php if (empty($requests)): ?>
            <div class="py-12 text-center text-base-content/40 text-sm flex flex-col items-center gap-3">
                <?= View::icon('calendar', 'w-10 h-10 opacity-30') ?>
                <p>هنوز درخواستی ثبت نکرده‌اید.</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="table table-zebra">
                    <thead>
                        <tr>
                            <th>نوع</th>
                            <th>شروع</th>
                            <th>پایان</th>
                            <th>دلیل</th>
                            <th>وضعیت</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($requests as $r): ?>
                        <tr class="hover:bg-base-200/40">
                            <td>
                                <span class="badge badge-sm badge-outline <?= $r['type'] === 'hourly' ? 'badge-secondary' : 'badge-primary' ?>">
                                    <?= $r['type'] === 'hourly' ? 'ساعتی' : 'روزانه' ?>
                                </span>
                            </td>
                            <td class="text-sm text-base-content/60 whitespace-nowrap"><?= View::fa(htmlspecialchars(\App\Services\CalendarService::formatJalali(strtotime($r['start_at'])), ENT_QUOTES, 'UTF-8')) ?></td>
                            <td class="text-sm text-base-content/60 whitespace-nowrap"><?= View::fa(htmlspecialchars(\App\Services\CalendarService::formatJalali(strtotime($r['end_at'])), ENT_QUOTES, 'UTF-8')) ?></td>
                            <td class="text-base-content/50 text-sm max-w-40 truncate"><?= htmlspecialchars($r['reason'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="badge badge-sm <?=
                                    ($r['status'] === 'approved' ? 'badge-success' :
                                    ($r['status'] === 'rejected' ? 'badge-error' :
                                                         'badge-warning'))
                                ?>">
                                    <?= ($r['status'] === 'approved' ? 'تایید' : ($r['status'] === 'rejected' ? 'رد' : 'در انتظار')) ?>
                                </span>
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
