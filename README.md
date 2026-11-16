<div align="center">

# 🧵 Threadly

### AI-Powered Threads Content Planning, Copywriting, Image Studio & Scheduling App

[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com/)
[![Vue](https://img.shields.io/badge/Vue-3%20%2B%20Inertia.js%20v2-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)](https://vuejs.org/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-v4-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)](https://tailwindcss.com/)
[![AI](https://img.shields.io/badge/AI-TokenRouter%20API-6366F1?style=for-the-badge)](config/ai-models.php)

*Built for **Gimora Digital** — plan, write, generate poster visuals, schedule, and analyze Threads content with cPanel shared-hosting compatibility.*

</div>

---

## Stack

- Laravel 13, PHP 8.3+
- Vue 3, Inertia.js v2, Vite
- Tailwind CSS v4
- SQLite for local development, MySQL for cPanel production
- Database queue driver for shared-hosting compatibility
- TokenRouter AI using an OpenAI-compatible API

## AI Features

- Copywriting workflows use TokenRouter through `TOKENROUTER_BASE_URL=https://api.tokenrouter.com/v1`.
- Prompt templates are versioned in `config/ai-prompts.php` and include the default Gimora Digital brand profile.
- Model capability, endpoint routing, JSON-mode support, and cost estimates are managed in `config/ai-models.php`.
- `POST /ai/content-assist` returns hook variants, CTA, hashtags, and quality notes in one request to reduce token usage.
- Image Studio includes a poster workflow that enhances user briefs into structured visual prompts and can queue image generation on cPanel.

## Local Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run dev
php artisan serve
```

Default local seed user:

```text
Email: admin@gimoradigital.id
Password: password
```

## Production Notes

This app is designed for cPanel shared hosting. Keep the Laravel app protected and point the domain document root to `public/` whenever cPanel allows it.

After pulling updates on the server:

```bash
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
```

The production `.env` is never committed. Use `.env.example` as the shape only, then set real database, mail, TokenRouter, and Threads values directly on cPanel.

For cPanel-safe AI defaults, keep cache and queue on database drivers. Enable queued poster generation only when the scheduler cron is active:

```env
CACHE_STORE=database
QUEUE_CONNECTION=database
AI_IMAGE_QUEUE_ENABLED=true
```

See `HOSTING_CHANGES.md` for the deploy handoff checklist.

## Verification

```bash
php artisan route:list
php artisan test
npm run build
```

Commit `public/build` when deploying through the current cPanel flow that reads Vite build assets from Git.
