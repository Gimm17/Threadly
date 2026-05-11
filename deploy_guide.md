# 🚀 Deploy Threadly ke `threadly.gimmhost.my.id` via cPanel SSH

> **Status:** ✅ Build Vite sudah di-push ke GitHub  
> **Document Root:** `/home/username/threadly.gimmhost.my.id/`  
> **Metode:** Langsung di dalam document root

---

## Arsitektur Deploy

```
/home/username/threadly.gimmhost.my.id/    ← Document Root = Laravel root
├── app/
├── config/
├── database/
├── routes/
├── storage/
├── vendor/
├── .env
├── .htaccess              ← Redirect semua request ke public/
└── public/
    ├── index.php
    ├── .htaccess
    ├── build/             ← Vite assets (sudah ada di Git)
    └── storage/ → symlink
```

---

## Copy-Paste Commands (Urutan)

Buka **cPanel → Terminal**, lalu jalankan satu per satu.

> Ganti `username` dengan cPanel username kamu di semua perintah.

---

### 1️⃣ Clone Repository

```bash
cd ~/threadly.gimmhost.my.id
git clone https://github.com/Gimm17/Threadly.git .
```

> Jika folder tidak kosong, bersihkan dulu:
> ```bash
> cd ~/threadly.gimmhost.my.id
> rm -rf * .[^.]*
> git clone https://github.com/Gimm17/Threadly.git .
> ```

> Jika `git` tidak ada:
> ```bash
> cd ~
> curl -L -o threadly.zip https://github.com/Gimm17/Threadly/archive/refs/heads/main.zip
> unzip threadly.zip
> cp -r Threadly-main/* Threadly-main/.[^.]* ~/threadly.gimmhost.my.id/
> rm -rf Threadly-main threadly.zip
> ```

---

### 2️⃣ Install Composer

```bash
cd ~/threadly.gimmhost.my.id
composer install --no-dev --optimize-autoloader --no-interaction
```

> Jika `composer` tidak ada:
> ```bash
> cd ~
> php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
> php composer-setup.php --install-dir=$HOME/bin --filename=composer
> php -r "unlink('composer-setup.php');"
> cd ~/threadly.gimmhost.my.id
> ~/bin/composer install --no-dev --optimize-autoloader --no-interaction
> ```

---

### 3️⃣ Setup .env

```bash
cd ~/threadly.gimmhost.my.id
cp .env.example .env
nano .env
```

**Ganti isi .env menjadi:**

```env
APP_NAME=ThreadsAI
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://threadly.gimmhost.my.id

APP_LOCALE=id
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=id_ID
APP_TIMEZONE=Asia/Jakarta

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=username_threadly
DB_USERNAME=username_threadly
DB_PASSWORD=PASSWORD_DB_KAMU

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=threadly.gimmhost.my.id

QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_HOST=mail.gimmhost.my.id
MAIL_PORT=465
MAIL_USERNAME=noreply@threadly.gimmhost.my.id
MAIL_PASSWORD=EMAIL_PASSWORD
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS="noreply@threadly.gimmhost.my.id"
MAIL_FROM_NAME="${APP_NAME}"

VITE_APP_NAME="${APP_NAME}"

TOKENROUTER_API_KEY=
TOKENROUTER_BASE_URL=https://api.tokenrouter.ai/v1

THREADS_APP_ID=
THREADS_APP_SECRET=
THREADS_REDIRECT_URI=${APP_URL}/auth/threads/callback

SEED_OWNER_NAME="Gimora Digital"
SEED_OWNER_EMAIL=admin@gimoradigital.id
SEED_OWNER_PASSWORD=GantiPasswordKuat123!
SEED_WORKSPACE_NAME="Gimora Digital"
SEED_THREADS_HANDLE=gimoradigital.id
```

Simpan: `Ctrl+O` → `Enter` → `Ctrl+X`

```bash
php artisan key:generate
```

---

### 4️⃣ Buat Database MySQL (di cPanel GUI)

1. cPanel → **MySQL Databases**
2. Create Database → isi nama (cPanel prefix otomatis: `cpaneluser_threadly`)
3. Create User → isi username + password
4. Add User to Database → centang **ALL PRIVILEGES**
5. Sesuaikan `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` di `.env`

---

### 5️⃣ Migrate & Seed

```bash
cd ~/threadly.gimmhost.my.id
php artisan migrate --force
php artisan db:seed --force
```

---

### 6️⃣ Arahkan Domain ke Folder `public/`

**Cara A: Ubah Document Root di cPanel (Recommended)**

1. cPanel → **Domains** (atau **Subdomains**)
2. Klik **Manage** pada `threadly.gimmhost.my.id`
3. Ubah Document Root menjadi:
   ```
   /home/username/threadly.gimmhost.my.id/public
   ```
4. Save

**Cara B: .htaccess di Root (Jika tidak bisa ubah document root)**

Buat `.htaccess` di root project:

```bash
cat > ~/threadly.gimmhost.my.id/.htaccess << 'EOF'
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
EOF
```

---

### 7️⃣ Storage Symlink

```bash
cd ~/threadly.gimmhost.my.id
php artisan storage:link
```

---

### 8️⃣ Permissions

```bash
cd ~/threadly.gimmhost.my.id
chmod -R 775 storage bootstrap/cache
chmod 600 .env
```

---

### 9️⃣ Cache & Optimize

```bash
cd ~/threadly.gimmhost.my.id
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

---

### 🔟 Cron Jobs (cPanel GUI)

cPanel → **Cron Jobs** → tambahkan:

**Scheduler:**
```
* * * * * cd /home/username/threadly.gimmhost.my.id && php artisan schedule:run >> /dev/null 2>&1
```

**Queue Worker:**
```
* * * * * cd /home/username/threadly.gimmhost.my.id && php artisan queue:work database --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

---

### ✅ Test!

Buka `https://threadly.gimmhost.my.id` di browser 🎉

---

## 🔄 Redeploy (Setiap Update)

Di lokal:
```bash
npm run build
git add -A
git commit -m "update: description"
git push origin main
```

Di server (cPanel Terminal):
```bash
cd ~/threadly.gimmhost.my.id
git pull origin main
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
```

---

## 🐛 Troubleshooting

| Problem | Solution |
|---------|----------|
| 500 Error | `tail -50 ~/threadly.gimmhost.my.id/storage/logs/laravel.log` |
| Blank page | `sed -i 's/APP_DEBUG=false/APP_DEBUG=true/' .env && php artisan config:clear` |
| CSS/JS not loading | Pastikan document root mengarah ke `public/` |
| Permission denied | `chmod -R 775 storage bootstrap/cache` |
| Class not found | `composer dump-autoload -o` |
| php artisan error | `php -v` → butuh PHP 8.2+ |
| Session/Cache error | `php artisan migrate` (tabel session & cache) |
