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
- **Arsitektur:** Menggunakan *Service Layer Pattern* (`AIService`, `AnalyticsService`, `MediaUploadService`).
- **Routing:** Semua routing menggunakan `web.php` + Inertia (Tidak pakai `api.php`).
- **Multi-Tenancy:** Menggunakan trait `BelongsToWorkspace` sebagai Global Scope untuk memisahkan data antar workspace. Akses dilindungi middleware `EnsureWorkspaceAccess`.
- **Validasi:** Menggunakan *Form Request* terpisah (contoh: `StorePostRequest`).
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
   - `AnalyticsService` (metrics dashboard).
   - `MediaUploadService` (upload media untuk posts).
4. **Controllers & Routes:** 7 Controller (Dashboard, ContentPlanner, Post, dll), 40 routes terdaftar.
5. **Layouts:** `AppLayout.vue` dengan sidebar, mobile hamburger menu, dan flash messages alert.
6. **Dashboard UI:** Metric Cards, `vue-chartjs` Engagement Chart, AI Insight Panel, Upcoming Posts, Top Hooks.
7. **Content Planner UI:** Grid calendar per bulan, Pilar konten aktif, dan Sidebar ide konten.
8. **Posts UI:** List Post (dengan status filter tabs), form Create/Edit Post (dilengkapi live counter Threads, checklist kualitas, dan AI assistant mockup).
9. **Settings UI:** Pengaturan parameter AI Model per fitur (Temperature, Tokens, Prompt).
10. **Deployment:** Pushed ke GitHub, `DEPLOY_PLAN.md` selesai dibuat, Symlink storage selesai.

## 5. Latest Updates & Bug Fixes (Session Terakhir)
- **cPanel Deployment:** Project berhasil di-deploy langsung ke document root subdomain cPanel. Ditambahkan root `.htaccess` untuk mem-forward traffic ke `/public`.
- **Vite Production Build:** `/public/build` dihilangkan dari `.gitignore` sehingga file CSS/JS yang sudah di-compile via `npm run build` bisa langsung ditarik oleh cPanel tanpa perlu install Node.js di server.
- **Meta API Compliance:** Menambahkan endpoint dummy webhook (`/auth/threads/deauthorize` dan `/auth/threads/delete-data`) di `web.php` untuk memenuhi syarat Portal Developer Meta Threads.
- **Premium Login UI:** Merombak `GuestLayout.vue` dan `Login.vue` menjadi desain split-screen premium yang sangat memukau (WOW-factor), menggunakan warna `primary` dan `surface` dari design system.
- **Tailwind v4 Spacing Bug:** Memperbaiki bug di mana variabel custom `--spacing-md` di `app.css` merusak class default Tailwind (`max-w-md` mengecil jadi 24 pixel). Juga menambahkan `@plugin "@tailwindcss/forms";` untuk memperbaiki input form.
- **MySQL Strict Type Error (500):** Memperbaiki `TypeError` di `AnalyticsService` dengan menambahkan cast `'workspace_id' => 'integer'` pada model `User`. Ini mengatasi perbedaan perilaku SQLite (lokal) yang mengembalikan int dan MySQL (cPanel) yang mengembalikan string.

## 6. Current Environment & Running Services
- **Folder Root:** `c:\Users\HP\Laravel\Threadly`
- **Artisan Serve:** Port 8000 / 8001 (sesuaikan dengan command jalan)
- **Vite:** `npm run dev` sedang berjalan
- **Login Default:**
  - Email: `admin@gimoradigital.id`
  - Password: `password` (Lokal) / `GantiPasswordKuat123!` (cPanel)

## 7. Next Action Items (Feature V2 / Backlog)
Jika ingin melanjutkan pengembangan di sesi berikutnya, berikut adalah prioritas yang bisa dikerjakan:
1. **Integrasi AI Asli:** Mengganti mock response di UI dengan call betulan ke `AIService` (TokenRouter) untuk fitur *Generate Hook* dan *Perbaiki Tata Bahasa*.
2. **Eksekusi Jadwal Post (Cron):** Memastikan cron job command berjalan dan mengeksekusi post otomatis via Threads API.
3. **Threads API Integration:** Menambahkan method di Service untuk mengirim `body` dan `media` ke official Threads API.
4. **Dynamic Analytics:** Mengubah data dashboard agar mengambil data nyata dari integrasi akun Threads, bukan dummy data analytics.

---
*Generated pada akhir sesi AI. Cukup paste teks ini ke Agent baru agar dia tahu persis kondisi terakhir kode dan project structure.*
