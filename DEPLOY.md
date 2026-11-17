# مستندات استقرار روی VPS

> راهنمای گام‌به‌گام برای بالا آوردن سیستم حضور و غیاب روی یک سرور لینوکسی.
> الزامات: PHP 8.3+، دیتابیس SQLite، بدون فریمورک — فقط `endroid/qr-code` در Composer و `tailwindcss` + `html5-qrcode` در npm.

---

## ۱. آماده‌سازی سرور

```bash
# Debian/Ubuntu
sudo apt update && sudo apt install -y \
  nginx php8.3-fpm php8.3-sqlite3 php8.3-mbstring php8.3-curl \
  sqlite3 git cron certbot python3-certbot-nginx

# اوبونتو‌های قدیمی‌تر که php8.3 ندارند، مخزن ondrej/php را اضافه کنند:
# sudo add-apt-repository ppa:ondrej/php && sudo apt update
```

> سیستم بدون ext-intl کار می‌کند (تقویم جلالی با `CalendarService` و الگوریتم خالص PHP است).

## ۲. دریافت کد

```bash
sudo mkdir -p /var/www/crm
sudo chown $USER:$USER /var/www/crm
git clone <REPO_URL> /var/www/crm
cd /var/www/crm
```

## ۳. وابستگی‌ها و build

```bash
# PHP
composer install --no-dev --optimize-autoloader

# CSS (فقط برای تغییر در استایل لازم است؛ خروجی build در public/assets/app.css کامیت شده)
npm install
npm run build
```

## ۴. فایل `.env`

در ریشه پروژه (`/var/www/crm/.env`):

```env
APP_URL=https://crm.example.com
APP_SECRET=<رشته تصادفی ۶۴ کاراکتری>
DB_DATABASE=database/app.sqlite
```

تولید secret:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

**دسترسی‌ها — حیاتی:**

```bash
# دیتابیس و پوشه آن فقط برای کاربر سرویس PHP قابل نوشتن باشد
sudo chown -R www-data:www-data /var/www/crm/database
sudo chmod 750 /var/www/crm/database
sudo chmod 640 /var/www/crm/database/app.sqlite

# پوشه لاگ‌ها
sudo mkdir -p /var/www/crm/storage/logs
sudo chown -R www-data:www-data /var/www/crm/storage
```

> **نکته:** اگر `php8.3-fpm` با کاربر دیگری اجرا می‌شود (در `www.conf` ببینید)، همان کاربر را جایگزین `www-data` کنید.

## ۵. ساخت دیتابیس

```bash
cd /var/www/crm
php tools/build-db.php --seed
```

## ۶. تنظیم Nginx

`/etc/nginx/sites-available/crm`:

```nginx
server {
    listen 80;
    server_name crm.example.com;

    root /var/www/crm/public;
    index index.php;

    # مسیرهای استاتیک را مستقیم بده؛ فقط index.php نقطه ورود است
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param HTTPS $fastcgi_https;  # بعد از فعال‌سازی SSL خودکار درست می‌شود
    }

    # دسترسی به فایل‌های حساس زیر web root ممنوع
    location ~ /\.(env|git|sqlite) { deny all; }
    location ~* /(database|src|storage|tools|resources|vendor/tests) { deny all; }

    client_max_body_size 2m;
}
```

```bash
sudo ln -s /etc/nginx/sites-available/crm /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

> `.env` و `database/` بیرون `public/` هستند، پس اصلاً از وب در دسترس نیستند؛ قانون deny فقط لایه دفاعی اضافه است.

## ۷. SSL با Let's Encrypt (الزامی)

اسکن QR دوربین مرورگر (getUserMedia) فقط روی **HTTPS** کار می‌کند → بدون SSL، صفحه پانچ QR غیرفعال است.

```bash
sudo certbot --nginx -d crm.example.com
```

certbot خودش ریدایرکت 80→443 را اضافه می‌کند. تمدید خودکار:

```bash
sudo certbot renew --dry-run
```

## ۸. Cron jobs

کرونتاب کاربر root (یا www-data):

```bash
sudo crontab -e
```

```cron
# بستن خودکار خروج فراموش‌شده — روزانه ۰۰:۰۵ به وقت تهران
5 0 * * * php /var/www/crm/tools/close-forgotten-checkouts.php >> /var/www/crm/storage/logs/cron.log 2>&1

# پشتیبان‌گیری روزانه از دیتابیس — ۰۱:۳۰ به وقت تهران
30 1 * * * sqlite3 /var/www/crm/database/app.sqlite ".backup '/var/www/crm/storage/backups/app-$(date +\%F).sqlite'" && find /var/www/crm/storage/backups -name 'app-*.sqlite' -mtime +30 -delete
```

> اسکریپت بستن خروج فراموش‌شده:
> - فقط وقتی سیاست `forgotten_checkout = auto_close` باشد اجرا می‌شود (پیش‌فرض همین است).
> - برای هر روزِ تمام‌شده‌ای که آخرین پانچش `in` بوده، یک پانچ `out` تخمینی در `23:59:59` همان روز با `is_estimate=1` ثبت می‌کند.
> - ایدمپوتنت است: اجرای تکراری هیچ ردیف تکراری نمی‌سازد.
> - سیاست از پنل ادمین → تنظیمات قابل تغییر است (`auto_close` / `admin_manual`).

**مطمئن شوید سرور timezone را روی تهران دارد** (برای درستی ساعت cron):

```bash
sudo timedatectl set-timezone Asia/Tehran
```

## ۹. پشتیبان‌گیری و بازیابی

- روش بالا از دستور `.backup` SQLite استفاده می‌کند که **WAL-safe** است (دیتابیس در حال استفاده را ایمن کپی می‌کند).
- نگهداری ۳۰ روز در `storage/backups/`، بعد حذف خودکار.
- برای خارج از سرور (offsite)، سینک دوره‌ای همان پوشه به مقصد دیگر توصیه می‌شود.

**بازیابی:**

```bash
# سرویس را موقتاً خاموش کنید تا ردیف جدیدی نوشته نشود
sudo systemctl stop php8.3-fpm
cp storage/backups/app-2026-11-16.sqlite database/app.sqlite
sudo chown www-data:www-data database/app.sqlite
sudo systemctl start php8.3-fpm
```

## ۱۰. چک‌لیست نهایی استقرار

- [ ] PHP 8.3+ با اکستنشن‌های sqlite3, mbstring, curl
- [ ] `composer install --no-dev` اجرا شد
- [ ] `.env` با `APP_SECRET` تصادفی ساخته شد (مقدار پیش‌فرض `change-me-in-production` را عوض کنید)
- [ ] `php tools/build-db.php --seed` اجرا شد
- [ ] docroot انجینکس → `public/` و دسترسی به `database|src|storage|tools` مسدود است
- [ ] SSL فعال است (بدون آن اسکن QR دوربین کار نمی‌کند)
- [ ] کرون `close-forgotten-checkouts.php` روزانه ۰۰:۰۵ ثبت شد
- [ ] کرون پشتیبان‌گیری روزانه ثبت شد
- [ ] timezone سرور = `Asia/Tehran`
- [ ] ورود با کاربر ادمین تست شد و رمز پیش‌فرض seed عوض شد

## عیب‌یابی سریع

| نشانه | علت احتمالی | راه‌حل |
|---|---|---|
| صفحه سفید / 500 | لاگ `storage/logs/php-errors.log` یا `/var/log/nginx/error.log` | سطح خطا را موقتاً بالا ببرید |
| خطای FK در لاگ کرون | insert با `user_id` ناموجود | کاربر باید در جدول `users` موجود و فعال باشد |
| دوربین QR باز نمی‌شود | HTTPS نیست | certbot را اجرا کنید |
| `SQLITE_BUSY` | WAL + busy_timeout=5000 از قبل فعال است؛ معمولاً گذرا | اگر تکرار شد، کرون و وب هر دو را بررسی کنید |
| کرون چیزی نمی‌بندد | سیاست `forgotten_checkout = admin_manual` | در پنل ادمین → تنظیمات به `auto_close` برگردانید |
