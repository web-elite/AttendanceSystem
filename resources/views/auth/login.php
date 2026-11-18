<?php
// Variables: $csrf, $errors
use App\Core\View;
?>
<div class="min-h-screen flex items-center justify-center px-4 py-10"
     style="background:
            radial-gradient(1200px 600px at 85% -10%, color-mix(in oklab, var(--color-primary) 18%, transparent), transparent 60%) no-repeat,
            radial-gradient(900px 500px at 10% 110%, color-mix(in oklab, var(--color-secondary) 14%, transparent), transparent 60%) no-repeat;">
    <div class="w-full max-w-md anim-fade-up">

        <div class="text-center mb-8">
            <span class="avatar-circle avatar-circle-lg mx-auto mb-4 !w-16 !h-16 bg-primary text-primary-content shadow-lg shadow-primary/30">
                <?= View::icon('clock', 'w-8 h-8') ?>
            </span>
            <div class="font-black text-3xl tracking-tight">حضور و غیاب</div>
            <div class="text-base-content/50 text-sm mt-1">سامانه هوشمند ثبت حضور پرسنل</div>
        </div>

        <div class="card bg-base-100/95 backdrop-blur shadow-xl border border-base-200">
            <div class="card-body gap-5">
                <div>
                    <h1 class="card-title justify-center text-lg">ورود به حساب کاربری</h1>
                    <p class="text-center text-xs text-base-content/50 mt-1">اطلاعات ورود خود را وارد کنید</p>
                </div>

                <?php if (($_GET['scan'] ?? '') === '1'): ?>
                <div class="alert alert-info text-sm py-2.5 gap-2.5" role="status">
                    <?= View::icon('qr', 'w-5 h-5 shrink-0') ?>
                    <div class="leading-relaxed">
                        ورود خود را ثبت کنید — بلافاصله بعد از ورود، حضور شما به‌صورت خودکار ثبت می‌شود.
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                <div class="space-y-2">
                    <?php foreach ($errors as $e): ?>
                    <div class="alert alert-error text-sm py-2 anim-pop-in">
                        <?= View::icon('warning', 'w-5 h-5') ?>
                        <span><?= htmlspecialchars($e['message'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="/login" class="space-y-4">
                    <?= $csrf ?>
                    <label class="form-control">
                        <span class="label"><span class="label-text font-medium">نام کاربری</span></span>
                        <div class="relative">
                            <span class="absolute inset-y-0 start-0 ps-3 flex items-center text-base-content/40 pointer-events-none">
                                <?= View::icon('users', 'w-4 h-4') ?>
                            </span>
                            <input type="text" id="username" name="username" required autocomplete="username"
                                   class="input input-bordered w-full ps-10"
                                   value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="نام کاربری خود را وارد کنید">
                        </div>
                    </label>
                    <label class="form-control">
                        <span class="label"><span class="label-text font-medium">رمز عبور</span></span>
                        <div class="relative">
                            <span class="absolute inset-y-0 start-0 ps-3 flex items-center text-base-content/40 pointer-events-none">
                                <?= View::icon('power', 'w-4 h-4') ?>
                            </span>
                            <input type="password" id="password" name="password" required autocomplete="current-password"
                                   class="input input-bordered w-full ps-10" placeholder="••••••••">
                        </div>
                    </label>
                    <button type="submit" class="btn btn-primary w-full mt-2">
                        <?= View::icon('login', 'w-5 h-5') ?>
                        ورود به سامانه
                    </button>
                </form>
            </div>
        </div>

        <p class="text-center text-xs text-base-content/40 mt-6">© سامانه حضور و غیاب</p>
    </div>
</div>
