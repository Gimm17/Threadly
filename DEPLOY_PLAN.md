# 🚀 Deploy Plan — Threadly (ThreadsAI Manager)
## Shared Hosting cPanel Deployment Guide

> **IMPORTANT:** Panduan ini khusus untuk shared hosting dengan cPanel. Tidak memerlukan Docker, Redis, atau Supervisor.

---

## 1. Prerequisites

| Requirement | Minimum | Recommended |
|------------|---------|-------------|
| PHP | 8.2 | **8.3+** |
| MySQL | 5.7 | **8.0+** |
| Composer | 2.x | Latest |
| Node.js | 18.x | **20.x** (local build only) |

### PHP Extensions Required
```
BCMath, Ctype, Fileinfo, JSON, Mbstring, OpenSSL, PDO, PDO_MySQL, Tokenizer, XML, cURL
```

---

## 2. Directory Structure

```
/home/username/
├── public_html/          ← Domain root (isi public/ Laravel ONLY)
│   ├── index.php         ← Modified entry point
│   ├── .htaccess
│   ├── build/            ← Vite build output
│   └── storage/          ← Symlink → ../threadly/storage/app/public
│
├── threadly/             ← Laravel app (DI LUAR public_html!)
│   ├── app/
│   ├── config/
│   ├── database/
│   ├── storage/
│   ├── vendor/
│   └── .env
```

> **SECURITY:** JANGAN taruh seluruh project di public_html/

---

## 3. Deployment Steps

### Step 1: Build lokal
```bash
npm run build
```

### Step 2: Upload & Install
```bash
cd ~/threadly
git clone https://github.com/Gimm17/Threadly.git .
composer install --no-dev --optimize-autoloader
```

### Step 3: Environment
```bash
cp .env.example .env
# Edit .env: APP_DEBUG=false, DB MySQL, MAIL SMTP, dll
php artisan key:generate
```

### Step 4: Database
```bash
php artisan migrate --force
php artisan db:seed --force
```

### Step 5: public_html/index.php
```php
<?php
use Illuminate\Http\Request;
define('LARAVEL_START', microtime(true));
require __DIR__.'/../threadly/vendor/autoload.php';
$app = require_once __DIR__.'/../threadly/bootstrap/app.php';
$app->usePublicPath(__DIR__);
$app->handleRequest(Request::capture());
```

### Step 6: Storage symlink
```bash
ln -s /home/username/threadly/storage/app/public /home/username/public_html/storage
```

### Step 7: Cache & Permissions
```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
chmod -R 775 storage bootstrap/cache
chmod 600 .env
```

### Step 8: Cron Jobs (cPanel)
```
* * * * * cd /home/username/threadly && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home/username/threadly && php artisan queue:work database --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

---

## 4. Redeploy
```bash
cd ~/threadly
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
# Re-upload public/build/ dari lokal
```
