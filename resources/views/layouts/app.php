<?php
// Layout variables (set by View::render)
// $title, $authUser (logged-in identity from session — NOT the view's own $user),
// $flashMessages, $content, $isAdmin
use App\Core\View;
use App\Controllers\AuthController;

$uri = $_SERVER['REQUEST_URI'] ?? '';
$active = static function (string $prefix) use ($uri): string {
    return str_starts_with($uri, $prefix) ? 'sidebar-active' : '';
};
$authUser = $authUser ?? ($user ?? null); // back-compat if a view passed 'user'
$currentTheme = $authUser->theme ?? '';
if (!in_array($currentTheme, AuthController::themeWhitelist(), true)) {
    $currentTheme = 'light';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="<?= htmlspecialchars($currentTheme, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="SAMEORIGIN">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    <title><?= htmlspecialchars($title ?? 'حضور و غیاب', ENT_QUOTES, 'UTF-8') ?> — حضور و غیاب</title>
    <link rel="stylesheet" href="/assets/app.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-base-200 min-h-screen">

<?php if (!empty($authUser)): ?>
<div class="app-shell">

    <!-- Drawer state: checkbox + CSS :has() keeps the sidebar open
         across navigations (no JS state, CSP-safe). -->
    <input type="checkbox" id="sidebar-state" class="sr-only" aria-hidden="true" autocomplete="off">

    <!-- Mobile topbar -->
    <div class="lg:hidden fixed top-0 inset-x-0 z-40 navbar bg-base-100/95 backdrop-blur border-b border-base-300 px-4 shadow-sm">
        <div class="navbar-start">
            <button type="button" class="btn btn-ghost btn-square btn-sm" data-sidebar-toggle aria-controls="sidebar" aria-label="باز و بسته کردن منو">
                <svg class="w-6 h-6 swap-icon-open" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                <svg class="w-5 h-5 swap-icon-close" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
        <div class="navbar-center">
            <span class="font-black text-primary">حضور و غیاب</span>
        </div>
        <div class="navbar-end">
            <?= View::avatar($authUser->name ?? '', 'avatar-circle !w-8 !h-8 text-[10px]') ?>
        </div>
    </div>
    <div class="lg:hidden h-16"></div>

    <!-- Mobile drawer backdrop (a label: click toggles the checkbox) -->
    <label for="sidebar-state" class="sidebar-backdrop lg:hidden" data-sidebar-backdrop aria-hidden="true"></label>

    <!-- Sidebar -->
    <aside id="sidebar" class="app-sidebar bg-base-100 border-s border-base-300 flex flex-col shadow-sm" data-sidebar>
        <div class="p-5 border-b border-base-200">
            <a href="/" class="flex items-center gap-3">
                <span class="avatar-circle avatar-circle-lg bg-primary text-primary-content !w-12 !h-12">
                    <?= View::icon('clock', 'w-6 h-6') ?>
                </span>
                <span>
                    <span class="block font-black text-base leading-tight">حضور و غیاب</span>
                    <span class="block text-xs text-base-content/50">سامانه زمان‌بندی پرسنل</span>
                </span>
            </a>
        </div>

        <nav class="flex-1 overflow-y-auto p-3 space-y-1">
            <?php if ($isAdmin): ?>
            <div class="px-3 pt-2 pb-1 text-[11px] font-semibold text-base-content/40 tracking-wide">مدیریت</div>
            <a href="/admin/dashboard" class="side-link <?= $active('/admin/dashboard') ?>">
                <?= View::icon('dashboard', 'w-[18px] h-[18px]') ?><span>داشبورد</span>
            </a>
            <a href="/admin/users" class="side-link <?= $active('/admin/users') ?>">
                <?= View::icon('users', 'w-[18px] h-[18px]') ?><span>کاربران</span>
            </a>
            <a href="/admin/punches" class="side-link <?= $active('/admin/punches') ?>">
                <?= View::icon('clock', 'w-[18px] h-[18px]') ?><span>ثبت‌های ورود و خروج</span>
            </a>
            <a href="/admin/leaves" class="side-link <?= $active('/admin/leaves') ?>">
                <?= View::icon('calendar', 'w-[18px] h-[18px]') ?><span>مرخصی‌ها</span>
            </a>
            <a href="/admin/reports" class="side-link <?= $active('/admin/reports') ?>">
                <?= View::icon('chart', 'w-[18px] h-[18px]') ?><span>گزارش‌ها</span>
            </a>
            <a href="/admin/settings" class="side-link <?= $active('/admin/settings') ?>">
                <?= View::icon('settings', 'w-[18px] h-[18px]') ?><span>تنظیمات</span>
            </a>
            <?php else: ?>
            <div class="px-3 pt-2 pb-1 text-[11px] font-semibold text-base-content/40 tracking-wide">پرسنل</div>
            <a href="/dashboard" class="side-link <?= ($active('/dashboard') || $active('/punch') || $uri === '/' || $uri === '/dashboard') ? 'sidebar-active' : '' ?>">
                <?= View::icon('dashboard', 'w-[18px] h-[18px]') ?><span>داشبورد</span>
            </a>
            <a href="/leave-requests" class="side-link <?= $active('/leave-requests') ?>">
                <?= View::icon('calendar', 'w-[18px] h-[18px]') ?><span>مرخصی‌ها</span>
            </a>
            <?php endif; ?>
        </nav>

        <div class="p-3 border-t border-base-200">
            <!-- Theme picker (available to every logged-in user) -->
            <details class="mb-2 group" data-theme-picker>
                <summary class="side-link cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                    <?= View::icon('sun', 'w-[18px] h-[18px]') ?>
                    <span class="flex-1">ظاهر داشبورد</span>
                    <span class="text-[10px] font-mono text-base-content/40 group-open:hidden"><?= htmlspecialchars($currentTheme, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="hidden text-[10px] text-base-content/40 group-open:inline">بستن ✕</span>
                </summary>
                <form method="post" action="/theme" class="mt-2 grid grid-cols-2 gap-1.5 max-h-56 overflow-y-auto pl-1">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="back" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/', ENT_QUOTES, 'UTF-8') ?>">
                    <?php foreach (AuthController::themeWhitelist() as $t): ?>
                    <button type="submit" name="theme" value="<?= $t ?>" class="theme-chip<?= $t === $currentTheme ? ' is-active' : '' ?>" data-theme="<?= $t ?>" title="تم <?= $t ?>">
                        <span class="dots flex gap-1">
                            <span style="background:var(--color-primary)"></span>
                            <span style="background:var(--color-secondary)"></span>
                            <span style="background:var(--color-accent)"></span>
                        </span>
                        <span class="truncate"><?= $t ?></span>
                    </button>
                    <?php endforeach; ?>
                </form>
            </details>
            <div class="flex items-center gap-3 px-2 py-2">
                <?= View::avatar($authUser->name ?? '', 'avatar-circle') ?>
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-semibold truncate"><?= htmlspecialchars($authUser->name ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="text-xs text-base-content/50 truncate"><?= $isAdmin ? 'مدیر سیستم' : 'کارمند' ?></div>
                </div>
            </div>
            <a href="/logout" class="side-link text-error hover:!bg-error/10">
                <?= View::icon('logout', 'w-[18px] h-[18px]') ?><span>خروج از حساب</span>
            </a>
        </div>
    </aside>

    <!-- Main column -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Topbar -->
        <header class="hidden lg:flex sticky top-0 z-30 bg-base-100/80 backdrop-blur border-b border-base-300 px-6 py-3 items-center justify-between">
            <div>
                <h1 class="font-bold text-lg leading-tight"><?= htmlspecialchars($title ?? '', ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="text-xs text-base-content/50" data-jalali-date>—</p>
            </div>
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2 text-base-content/70">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success opacity-60"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-success"></span>
                    </span>
                    <span class="text-2xl font-bold tabular-nums" data-clock>--:--:--</span>
                </div>
                <div class="avatar-circle bg-primary/15 text-primary"><?= View::icon($isAdmin ? 'settings' : 'users', 'w-5 h-5') ?></div>
                <span class="text-sm font-semibold"><?= htmlspecialchars($authUser->name ?? '', ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </header>

        <!-- Flash toasts -->
        <?php if (!empty($flashMessages)): ?>
        <div class="px-4 sm:px-6 lg:px-8 pt-4 space-y-2 max-w-[100rem] w-full mx-auto">
            <?php foreach ($flashMessages as $msg): ?>
            <div class="alert <?= $msg['type'] === 'success' ? 'alert-success' : ($msg['type'] === 'error' ? 'alert-error' : 'alert-info') ?> shadow-lg anim-fade-up" data-flash role="status">
                <?= View::icon($msg['type'] === 'success' ? 'check' : ($msg['type'] === 'error' ? 'warning' : 'info'), 'w-5 h-5') ?>
                <span><?= htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Content -->
        <main class="flex-1 px-4 sm:px-6 lg:px-8 py-5 lg:py-8 w-full max-w-[100rem] mx-auto">
        <?= $content ?>
        </main>

        <footer class="px-6 py-4 text-center text-xs text-base-content/40 border-t border-base-300/50">
            <?php $jYear = explode('/', \App\Services\CalendarService::formatJalali(time()))[0]; ?>
            سامانه حضور و غیاب — <?= View::fa($jYear) ?>
        </footer>
    </div>
</div>
<?php else: ?>
<!-- Guest layout (login) -->
<?= $content ?>
<?php endif; ?>

<script src="/assets/app.js"></script>
</body>
</html>
