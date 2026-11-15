# AGENTS.md — قواعد ذهنی پروژه حضور و غیاب

> منبع حقیقت: `PROJECT_BRAIN.md`. قبل از هر کاری آن را بخوان.

## همیشه / هرگز

**همیشه:**
- PHP خالص 8.3+، بدون فریمورک، بدون ORM. کوئری PDO با prepared statement.
- escape کردن همه خروجی‌ها: `htmlspecialchars($x, ENT_QUOTES, 'UTF-8')`.
- CSRF توکن روی همه فرم‌های POST + الگوی PRG (ریدایرکت بعد از POST).
- تقویم جلالی‌-محور: هر ورودی و خروجی کاربر شمسی است؛ ذخیره فقط Unix timestamp / میلادی؛ تبدیل فقط با `CalendarService` (الگوریتم خالص PHP، بدون پکیج و بدون ext-intl برای تقویم). تایم‌زون یکنواخت `Asia/Tehran`.
- مرز «ماه جاری» و گزارش ماهانه = ماه جلالی، نه میلادی.
- بررسی گِئوفنس و قواعد ساعت فقط سمت سرور.
- RTL فارسی با Tailwind v4 و فونت Vazirmatn.

**هرگز:**
- هیچ بسته‌ای اضافه نکن (YAGNI). فقط `endroid/qr-code` در composer و `tailwindcss` + `html5-qrcode` در npm.
- هیچ فریمورک (Laravel/Slim)، Vite، jQuery، وابستگی جاوااسکریپتی دیگر.
- MySQL-specific syntax در کوئری‌ها (باید روی SQLite اجرا شود و مهاجرت آسان بماند).
- منطق امنیتی در جاوااسکریپت سمت کلاینت.
- حذف فیزیکی رکورد کاربر (فقط `is_active = 0`).

## ساختار

- `public/index.php` تنها نقطه ورود وب؛ هر چیز دیگر خارج از web root.
- کلاس‌ها در `src/` با autoload PSR-4 (namespace `App\`).
- کنترلر = اکشن‌های ساده؛ مدل = کوئری‌های PDO؛ سرویس = QrService / GeoService / ReportService / RateLimiter / CalendarService.
- مایگریشن‌ها: فایل‌های SQL شماره‌دار در `database/migrations/`، اجرا با `php tools/build-db.php`.

## نام‌گذاری

- جدول: جمع + snake_case (`punches`, `leave_requests`). ستون: snake_case.
- اکشن URL: kebab-case (`/leave-requests`). متد PHP: camelCase. کلاس: PascalCase.

## قراردادهای دامنه (domain)

- `punches.type`: `in` / `out` — `method`: `qrcode` / `location` / `manual`.
- `leave_requests.type`: `hourly` / `daily` — `status`: `pending` / `approved` / `rejected`.
- QR چرخشی: توکن HMAC + nonce یک‌بارمصرف + TTL ۳۰–۶۰ ثانیه (قابل تنظیم ادمین)؛ hash ذخیره شود نه توکن خام.
- فاصله گِئوفنس: فرمول Haversine؛ ثبت `distance_m` و `inside_fence` در هر پانچ برای ممیزی.
- هر ویرایش ادمین روی پانچ → یک ردیف در `audit_logs`.
- `holidays.date` فرمت `YYYY-MM-DD` میلادی (ورودی ادمین شمسی است و تبدیل ذخیره می‌شود)؛ `is_annual = 1` یعنی هر سال بر اساس همان ماه/روز جلالی تکرار می‌شود (نوروز = ۱/۱ جلالی).
- `CalendarService` تنها نقطه تبدیل تاریخ در کل پروژه است: jalaliToGregorian / gregorianToJalali + فرمت/پارس شمسی.
- `holiday_mode`: `none` / `friday` / `official` / `both` — روز کاری = روزی که طبق این حالت تعطیل نباشد؛ گزارش‌ها و شمارش تاخیر فقط برای روزهای کاری.
- خروج فراموش‌شده: `forgotten_checkout` = `auto_close` (نیمه‌شب با پرچم تخمینی) یا `admin_manual` — فقط منطق ادمین، نه هاردکد.

## نکات محیطی

- دیتابیس: SQLite فایل `database/app.sqlite` با `PRAGMA journal_mode=WAL; busy_timeout=5000`.
- اسکن QR دوربین مرورگر نیازمند HTTPS است → روی VPS SSL اجباری.
- build CSS: `npm run build` (tailwind CLI) → خروجی در `public/assets/`.
