# 🧠 Project Context — Threadly (ThreadsAI Manager)

> **File ini dibuat untuk mempermudah perpindahan antar AI Agent.** 
> Berikan file ini (atau paste isinya) sebagai `System Prompt` atau `Context` saat memulai sesi baru dengan agent lain.

---

## 1. Project Overview
- **Nama Project:** Threadly (sebelumnya ThreadsAI Manager)
- **Tujuan:** Platform Social Content Intelligence (all-in-one) untuk manajemen konten Threads.
- **Repository:** https://github.com/Gimm17/Threadly.git
- **Deployment Target:** Shared Hosting cPanel (Lihat `DEPLOY_PLAN.md`)

## 2. Tech Stack
- **Backend:** Laravel 13, PHP 8.3+
- **Frontend:** Vue 3 (Composition API), Inertia.js v2, Vite 7
- **Styling:** Tailwind CSS v4 (menggunakan syntax `@theme` di `app.css`)
- **Database:** SQLite (Lokal) / MySQL 8.0+ (Production)
- **Icons & Fonts:** `@tabler/icons-vue`, Font lokal `@fontsource/inter`

## 3. Architecture & Design Decisions
- **Arsitektur:** Menggunakan *Service Layer Pattern* (`AIService`, `AnalyticsService`, `InsightService`, `PostReminderService`, `MediaUploadService`).
- **Routing:** Semua routing menggunakan `web.php` + Inertia (Tidak pakai `api.php`).
- **Multi-Tenancy:** Menggunakan trait `BelongsToWorkspace` sebagai Global Scope untuk memisahkan data antar workspace. Akses dilindungi middleware `EnsureWorkspaceAccess`.
- **Validasi:** Menggunakan *Form Request* terpisah (contoh: `StorePostRequest`).
- **Scheduler:** Laravel Scheduler via `routes/console.php` — reminder setiap 5 menit, daily insights jam 07:00.
- **Queue:** Database driver (cPanel-safe), jobs untuk reminder & insight generation.
- **UI/UX Design:** Mengikuti panduan desain dari **Gimora Digital**:
  - Warna Sidebar: Deep Navy (`#1A3263`)
  - Warna Highlight/Active: Amber (`#FFC570`)
  - Background: Surface Dark/Light mode compatible (saat ini dominan clean UI dengan slate-400 inactive text).

## 4. Status Implementasi (Done - MVP Stage)
Semua 10 stage pengembangan MVP telah **SELESAI**:

1. **Scaffold:** Setup Laravel Breeze, Tailwind v4, Vite, Font, dan Config selesai.
2. **Database:** 10 Migrations (workspaces, users, posts, dll), Models dengan relasi & scopes, 5 Seeders (berhasil jalan dengan dummy data).
3. **Backend Services:** 
   - `AIService` (wrapper untuk TokenRouter AI, bypass SSL verify untuk cPanel).
   - `AnalyticsService` (metrics dashboard + engagement chart).
   - `MediaUploadService` (upload media untuk posts).
   - `InsightService` (**NEW** — generate & cache daily AI insights per workspace).
   - `PostReminderService` (**NEW** — schedule email reminders sebelum jadwal post).
4. **Controllers & Routes:** 9 Controller (Dashboard, ContentPlanner, Post, AiAssist, Analytics, Settings/General, Settings/AiModelConfig, dll), 50+ routes terdaftar.
5. **Layouts:** `AppLayout.vue` dengan sidebar (ditambah link Analytics & Settings/General), mobile hamburger menu, dan flash messages alert.
6. **Dashboard UI:** Metric Cards, `vue-chartjs` Engagement Chart, AI Insight Panel, Upcoming Posts, Top Hooks.
7. **Content Planner UI:** Grid calendar per bulan, Pilar konten aktif, dan Sidebar ide konten.
8. **Posts UI:** List Post (dengan status filter tabs), form Create/Edit Post (dilengkapi live counter Threads, checklist kualitas, dan **AI assistant terintegrasi nyata** ke TokenRouter API).
9. **Settings UI:** Pengaturan parameter AI Model per fitur (Temperature, Tokens, Prompt) + **General Settings** (timezone, reminder offset, workspace info).
10. **Deployment:** Pushed ke GitHub, `DEPLOY_PLAN.md` selesai dibuat, Symlink storage selesai.

## 5. Latest Updates & Bug Fixes

### Session Terbaru (11 Mei 2026) — AI Integration, Scheduler, Analytics & Settings

#### ✅ Integrasi AI Asli (Bukan Mock Lagi)
- **`AiAssistController`** (NEW): Controller untuk endpoint AI yang nyata, dengan 3 method:
  - `generateHook()` — Generate hook/opening line via TokenRouter API (`hook_gen` feature).
  - `improveText()` — Perbaiki/rewrite teks post via API (`copywriting` feature).
  - `generateIdeas()` — Generate ide konten dalam format JSON array.
- **Route baru** di `web.php`:
  - `POST /ai/generate-hook` → `ai.generate-hook`
  - `POST /ai/improve-text` → `ai.improve-text`
  - `POST /ai/generate-ideas` → `ai.generate-ideas`
- **`Posts/Create.vue` & `Posts/Edit.vue`**: Tombol "Generate Hook" dan "Perbaiki Tata Bahasa" sekarang call API asli via `axios`, bukan mock. Termasuk loading state dan error handling.
- **`PostController`**: Update `store()` dan `update()` untuk menyimpan `ai_model_used` saat post dibuat/diedit dengan bantuan AI.

#### ✅ Activity Logging
- **`PostController`**: Sekarang log activity ke tabel `activity_logs` saat post dibuat (`post.created`), diupdate (`post.updated`), dihapus (`post.deleted`), dipublish (`post.published`), dan dicancel (`post.cancelled`).
- **`DashboardController`**: Mengirim `recentActivity` ke dashboard (10 latest activity logs).

#### ✅ Scheduler & Automated Jobs
- **`SendPostReminderJob`** (NEW): Job untuk kirim email reminder sebelum scheduled post. Menggunakan `PostReminderMail` mailable.
- **`GenerateDailyInsightsJob`** (NEW): Job untuk generate AI daily insights untuk semua workspace. Memanggil `InsightService`.
- **`PostReminderMail`** (NEW): Mailable dengan template Blade (`views/mail/post-reminder.blade.php`).
- **`PostReminderService`** (NEW): Service untuk schedule reminder berdasarkan `scheduled_at - offset_minutes`. Dipanggil otomatis saat post disave dengan status `scheduled`.
- **`InsightService`** (NEW): Service lengkap untuk:
  - Gather context dari DB (post stats, analytics, top posts).
  - Build prompt untuk AI.
  - Call AIService dengan feature `insight`.
  - Parse response dan cache ke `workspace.workspace_meta`.
  - Serve cached insights jika masih fresh (< 24 jam).
- **`routes/console.php`**: Scheduler terdaftar:
  - `everyFiveMinutes()` — cek post yang butuh reminder.
  - `dailyAt('07:00')` — generate daily insights.
  - `daily()` — prune failed jobs > 7 hari.

#### ✅ Analytics Page
- **`AnalyticsController`** (NEW): Controller untuk halaman Analytics dengan method:
  - `index()` — Tampilkan metrics, chart data, top posts, dan post performance.
  - `storeSnapshot()` — Manual input analytics snapshot.
- **`AnalyticsService`** (UPDATED): Ditambahkan method `getEngagementChart()` yang return data untuk chart.
- **`Analytics/Index.vue`** (NEW): Halaman Analytics lengkap dengan:
  - 4 MetricCard (Followers, Engagement Rate, Total Impressions, Scheduled Posts).
  - Chart engagement (menggunakan `vue-chartjs`).
  - Top Performing Posts list.
  - Post Performance table.
  - Form manual input analytics snapshot.

#### ✅ General Settings Page
- **`Settings/GeneralController`** (NEW): Controller untuk settings umum workspace:
  - `index()` — Tampilkan form settings.
  - `update()` — Update workspace settings (name, threads_handle, timezone, reminder offset, threads_api_mode).
- **`Settings/General.vue`** (NEW): Halaman settings dengan form untuk:
  - Workspace name & Threads handle.
  - Timezone selection.
  - Reminder offset (menit sebelum jadwal post).
  - Threads API mode (manual/api).

#### ✅ Config & Layout Updates
- **`config/threadly.php`** (NEW): Config file baru untuk app-specific settings:
  - `reminder_offset_minutes` (default: 30).
  - `insights_cache_hours` (default: 24).
  - `max_media_per_post` (default: 4).
  - `threads_char_limit` (default: 500).
- **`AppLayout.vue`** (UPDATED): Sidebar navigation ditambah link:
  - Analytics (icon `IconChartBar`).
  - Settings/General (icon `IconSettings`).

#### ✅ Build & Deploy
- **`npm run build`** dijalankan — semua asset Vue/JS/CSS di-compile ke `/public/build/`.
- **Git commit & push** ke GitHub: `c47e180` — 59 files changed, 3235 insertions.

### Session Sebelumnya
- **cPanel Deployment:** Project berhasil di-deploy langsung ke document root subdomain cPanel. Ditambahkan root `.htaccess` untuk mem-forward traffic ke `/public`.
- **Vite Production Build:** `/public/build` dihilangkan dari `.gitignore` sehingga file CSS/JS yang sudah di-compile via `npm run build` bisa langsung ditarik oleh cPanel tanpa perlu install Node.js di server.
- **Meta API Compliance:** Menambahkan endpoint dummy webhook (`/auth/threads/deauthorize` dan `/auth/threads/delete-data`) di `web.php` untuk memenuhi syarat Portal Developer Meta Threads.
- **Premium Login UI:** Merombak `GuestLayout.vue` dan `Login.vue` menjadi desain split-screen premium yang sangat memukau (WOW-factor), menggunakan warna `primary` dan `surface` dari design system.
- **Tailwind v4 Spacing Bug:** Memperbaiki bug di mana variabel custom `--spacing-md` di `app.css` merusak class default Tailwind (`max-w-md` mengecil jadi 24 pixel). Juga menambahkan `@plugin "@tailwindcss/forms";` untuk memperbaiki input form.
- **MySQL Strict Type Error (500):** Memperbaiki `TypeError` di `AnalyticsService` dengan menambahkan cast `'workspace_id' => 'integer'` pada model `User`. Ini mengatasi perbedaan perilaku SQLite (lokal) yang mengembalikan int dan MySQL (cPanel) yang mengembalikan string.

## 6. File Baru yang Ditambahkan di Sesi Terakhir

```
app/Http/Controllers/AiAssistController.php       — AI endpoint controller (hook, improve, ideas)
app/Http/Controllers/AnalyticsController.php       — Analytics page controller
app/Http/Controllers/Settings/GeneralController.php — General settings controller
app/Jobs/SendPostReminderJob.php                   — Queue job: send post reminder email
app/Jobs/GenerateDailyInsightsJob.php              — Queue job: generate AI daily insights
app/Mail/PostReminderMail.php                      — Mailable for post reminders
app/Services/InsightService.php                    — AI insight generation & caching
app/Services/PostReminderService.php               — Reminder scheduling logic
config/threadly.php                                — App-specific config values
resources/js/Pages/Analytics/Index.vue             — Analytics dashboard page
resources/js/Pages/Settings/General.vue            — General settings page
resources/views/mail/post-reminder.blade.php       — Email template for reminders
```

## 7. Current Environment & Running Services
- **Folder Root:** `c:\Users\HP\Laravel\Threadly`
- **Artisan Serve:** Port 8000 / 8001 (sesuaikan dengan command jalan)
- **Vite:** `npm run dev` sedang berjalan
- **Login Default:**
  - Email: `admin@gimoradigital.id`
  - Password: `password` (Lokal) / `GantiPasswordKuat123!` (cPanel)

## 8. Routes Lengkap Terdaftar

```
GET  /dashboard                        → DashboardController@index
GET  /content-planner                  → ContentPlannerController@index
GET  /content-planner/calendar-data    → ContentPlannerController@calendarData
POST/PUT/DELETE /content-pillars       → ContentPillarController (resource)
POST/PUT/DELETE /content-ideas         → ContentIdeaController (resource)
GET/POST        /posts                 → PostController (resource)
GET/PUT/DELETE  /posts/{id}            → PostController (resource)
GET             /posts/create          → PostController@create
GET             /posts/{id}/edit       → PostController@edit
POST            /posts/{id}/publish    → PostController@markPublished
POST            /posts/{id}/cancel     → PostController@cancel
GET             /analytics             → AnalyticsController@index
POST            /analytics/snapshot    → AnalyticsController@storeSnapshot
POST            /ai/generate-hook      → AiAssistController@generateHook
POST            /ai/improve-text       → AiAssistController@improveText
POST            /ai/generate-ideas     → AiAssistController@generateIdeas
GET             /settings/general      → Settings\GeneralController@index
PUT             /settings/general      → Settings\GeneralController@update
GET             /settings/ai-models    → Settings\AiModelConfigController@index
PUT             /settings/ai-models/{config} → Settings\AiModelConfigController@update
ANY             /auth/threads/deauthorize    → (dummy webhook)
ANY             /auth/threads/delete-data    → (dummy webhook)
```

## 9. Scheduled Tasks (cPanel Cron)

```
Setiap 5 menit  → Cek post yang butuh reminder email (window ±3 menit dari offset)
Setiap hari 07:00 → Generate daily AI insights untuk semua workspace
Setiap hari     → Prune failed queue jobs > 7 hari
```

**cPanel cron command:** `cd /home/user/threadly && php artisan schedule:run >> /dev/null 2>&1`

## 10. Next Action Items (Feature V2 / Backlog)
Jika ingin melanjutkan pengembangan di sesi berikutnya, berikut adalah prioritas yang bisa dikerjakan:

1. ~~**Integrasi AI Asli:**~~ ✅ DONE — Generate Hook & Perbaiki Tata Bahasa sudah call ke TokenRouter API.
2. ~~**Scheduled Tasks (Cron):**~~ ✅ DONE — Scheduler terdaftar di `console.php`, reminder & insight jobs siap.
3. ~~**Analytics Page:**~~ ✅ DONE — Halaman analytics dengan chart, metrics, dan manual snapshot.
4. ~~**General Settings:**~~ ✅ DONE — Settings timezone, reminder offset, workspace info.
5. **Threads API Integration:** Menambahkan method di Service untuk mengirim `body` dan `media` ke official Threads API (auto-publish mode).
6. **Dynamic Analytics dari API:** Mengubah data analytics agar mengambil data nyata dari Threads API, bukan hanya manual input.
7. **Notification System:** Tambahkan in-app notification selain email reminder.
8. **Content Idea → Post Conversion:** Endpoint `convertToPost` di ContentIdeaController perlu diimplementasikan.
9. **Hook Template Library:** CRUD untuk `hook_templates` dan integrasi dengan AI scoring.
10. **Image Studio (V2):** AI image generation via DALL-E 3, Stability AI, dll.
11. **Multi-tenant Billing (V3):** Workspace per client, billing via Midtrans/Xendit.

---
*Terakhir diupdate: 11 Mei 2026 — Sesi AI Integration, Scheduler, Analytics & Settings. Commit: `c47e180`*
*Cukup paste teks ini ke Agent baru agar dia tahu persis kondisi terakhir kode dan project structure.*
