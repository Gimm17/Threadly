# IMPLEMENTATION PLAN — (Threadky) ThreadsAI Manager
**Project:** (Threadky) ThreadsAI Manager — Social Content Intelligence Platform  
**Client / Owner:** Gimora Digital (@gimoradigital.id)  
**Stack:** Laravel 13 · Vue 3 · Inertia.js · Tailwind CSS v4 · MySQL (prod) / SQLite (dev)  
**Deploy:** cPanel shared hosting — gimmhost.my.id  
**AI Provider:** TokenRouter (https://api.tokenrouter.com/v1) — OpenAI-compatible, multi-model  
**Scope V1 Core:** Dashboard · Content Planner · Post Scheduler  
**Authored for:** Antigravity Coding Agent — Opus 4.6  

---

## TABLE OF CONTENTS

1. [Project Overview](#1-project-overview)
2. [System Architecture](#2-system-architecture)
3. [Entity Relationship Diagram (ERD)](#3-entity-relationship-diagram-erd)
4. [Database Schema — Full Migrations](#4-database-schema--full-migrations)
5. [Product Requirements Document (PRD)](#5-product-requirements-document-prd)
6. [Feature Specifications — V1 Core](#6-feature-specifications--v1-core)
7. [File & Folder Structure](#7-file--folder-structure)
8. [API Routes & Controller Map](#8-api-routes--controller-map)
9. [Service Layer Design](#9-service-layer-design)
10. [Frontend Component Tree](#10-frontend-component-tree)
11. [Environment Configuration](#11-environment-configuration)
12. [Deployment Guide — cPanel](#12-deployment-guide--cpanel)
13. [Future Roadmap (V2+)](#13-future-roadmap-v2)
14. [Coding Agent Instructions](#14-coding-agent-instructions)

---

## 1. PROJECT OVERVIEW

### 1.1 What Is This?

ThreadsAI Manager is a private web application for Gimora Digital to manage, plan, write, schedule, and analyze social media content for the Threads platform (@gimoradigital.id). The system is AI-powered — every content creation touchpoint is backed by the TokenRouter AI gateway, which provides access to dozens of models (Claude, GPT-4o, Gemini, Llama, etc.) that can be switched per-task from an admin panel.

The app is designed from day one to be multi-tenant ready, meaning the same codebase can serve other business owners (B2B SaaS) in a future release with minimal structural changes.

### 1.2 V1 Core Scope

| Module | Description | Status |
|---|---|---|
| Dashboard | Metrics overview, AI insights, upcoming posts, analytics summary | V1 |
| Content Planner | Content pillars, topic ideas, editorial calendar | V1 |
| Post Scheduler | Create, schedule, edit, and manage posts queue | V1 |
| Hook Generator | AI hook generation per topic | V2 |
| Copywriting AI | Full post writer, CTA builder, thread splitter | V2 |
| Image Studio | Multi-provider image/video generation + prompt library | V2 |
| Analytics Deep Dive | Per-post analytics, engagement trend charts | V2 |
| SaaS / Multi-tenant | Workspace per client, billing (Midtrans/Xendit) | V3 |

### 1.3 Design Principles

- **Mobile-first, but desktop-optimized dashboard** — sidebar collapses on mobile.
- **AI is optional-but-present** — every module works without AI; AI enhances speed.
- **Multi-tenant architecture from schema level** — even in single-user V1, all tables carry `workspace_id` for zero-migration scale-up.
- **Provider-agnostic AI** — model, temperature, and max tokens configurable per-feature from admin.
- **cPanel-safe** — no Docker, no Redis required for V1. Queue uses database driver.

---

## 2. SYSTEM ARCHITECTURE

```
┌─────────────────────────────────────────────────────────────────┐
│                        Browser (Vue 3 SPA)                      │
│     Dashboard · Content Planner · Scheduler · Settings          │
└────────────────────────────┬────────────────────────────────────┘
                             │ Inertia.js (no REST, SSR-like DX)
┌────────────────────────────▼────────────────────────────────────┐
│                  Laravel 13 Application                         │
│                                                                 │
│  ┌─────────────┐  ┌──────────────┐  ┌──────────────────────┐   │
│  │ Controllers │  │   Services   │  │   Scheduled Jobs     │   │
│  │  (thin)     │  │  (business   │  │  (Laravel Scheduler) │   │
│  │             │  │   logic)     │  │  - DailyInsightJob   │   │
│  └──────┬──────┘  └──────┬───────┘  │  - PostReminderJob   │   │
│         │                │          └──────────────────────┘   │
│  ┌──────▼────────────────▼────────────────────────────────┐    │
│  │                    Models (Eloquent)                    │    │
│  └──────────────────────────┬───────────────────────────── ┘   │
└─────────────────────────────┼───────────────────────────────────┘
                              │
          ┌───────────────────┼───────────────────┐
          │                   │                   │
   ┌──────▼──────┐   ┌────────▼────────┐  ┌──────▼──────────┐
   │  SQLite/    │   │  TokenRouter AI  │  │  Threads Meta   │
   │  MySQL DB   │   │  (OpenAI-compat) │  │  API (future)   │
   │             │   │  api.tokenrouter │  │  + Manual mode  │
   └─────────────┘   │  .com/v1        │  └─────────────────┘
                     └─────────────────┘
```

### 2.1 Key Architectural Decisions

| Decision | Choice | Reason |
|---|---|---|
| Frontend-backend glue | Inertia.js | No separate API needed; full Vue 3 DX; cPanel friendly |
| Queue driver | Database (V1), switchable to Redis (V2) | cPanel has no Redis; database queue is stable |
| Auth | Laravel Breeze (Inertia+Vue variant) | Fast setup, multi-user ready from the start |
| AI abstraction | `AIService` wraps TokenRouter (OpenAI-compatible SDK) | Swap model per feature; add providers later |
| Media storage | Local `storage/app/public` with symlink | cPanel compatible, no S3 needed for V1 |
| Scheduler | Laravel `schedule:run` via cPanel cron | Standard pattern for shared hosting |

---

## 3. ENTITY RELATIONSHIP DIAGRAM (ERD)

### 3.1 Entity Overview

```
workspaces ──< users
workspaces ──< content_pillars
workspaces ──< posts ──< post_media
workspaces ──< hook_templates
workspaces ──< content_ideas ──< posts (optional link)
workspaces ──< ai_model_configs
workspaces ──< analytics_snapshots
users ──< activity_logs
```

### 3.2 Full ERD (Text Notation)

```
┌─────────────────────┐
│     workspaces      │
├─────────────────────┤
│ id (PK)             │
│ name                │
│ threads_handle      │
│ threads_account_id  │◄── nullable, filled when Threads API enabled
│ threads_token       │◄── nullable, encrypted
│ threads_api_mode    │◄── enum: manual | api
│ timezone            │
│ plan                │◄── enum: free | pro (V3 billing hook)
│ plan_expires_at     │◄── nullable
│ created_at          │
│ updated_at          │
└────────┬────────────┘
         │ 1
         │
         │ N
┌────────▼────────────┐         ┌──────────────────────┐
│       users         │         │    content_pillars   │
├─────────────────────┤         ├──────────────────────┤
│ id (PK)             │         │ id (PK)              │
│ workspace_id (FK)   │         │ workspace_id (FK)    │
│ name                │         │ name                 │
│ email               │         │ description          │
│ password            │         │ color_hex            │
│ role                │◄── enum │ icon                 │
│  owner|admin|member │         │ is_active            │
│ avatar_url          │         │ post_frequency       │◄── posts/week
│ email_verified_at   │         │ created_at           │
│ remember_token      │         │ updated_at           │
│ last_login_at       │         └──────────┬───────────┘
│ created_at          │                    │ 1
│ updated_at          │                    │
└─────────────────────┘                    │ N
                              ┌────────────▼──────────────┐
                              │       content_ideas       │
                              ├───────────────────────────┤
                              │ id (PK)                   │
                              │ workspace_id (FK)         │
                              │ content_pillar_id (FK)    │
                              │ title                     │
                              │ description               │
                              │ ai_generated              │◄── boolean
                              │ status                    │◄── enum: idea|in_progress|done|discarded
                              │ target_date               │◄── nullable
                              │ created_by (FK→users)     │
                              │ created_at                │
                              │ updated_at                │
                              └──────────┬────────────────┘
                                         │ 1 (optional)
                                         │
                                         │ N
┌─────────────────────────────┐          │
│           posts             │◄─────────┘
├─────────────────────────────┤
│ id (PK)                     │
│ workspace_id (FK)           │
│ content_idea_id (FK,null)   │
│ content_pillar_id (FK,null) │
│ body                        │◄── text, the actual post content
│ hook                        │◄── nullable, first line / hook text
│ status                      │◄── enum: draft|scheduled|published|failed|cancelled
│ scheduled_at                │◄── datetime, nullable
│ published_at                │◄── datetime, nullable
│ threads_post_id             │◄── nullable, filled after publish via API
│ publish_mode                │◄── enum: manual|auto
│ reminder_sent_at            │◄── nullable
│ notes                       │◄── internal editor notes
│ ai_model_used               │◄── nullable, log which model wrote this
│ created_by (FK→users)       │
│ created_at                  │
│ updated_at                  │
└──────────┬──────────────────┘
           │ 1
           │
           │ N
┌──────────▼──────────────────┐
│         post_media          │
├─────────────────────────────┤
│ id (PK)                     │
│ post_id (FK)                │
│ workspace_id (FK)           │
│ type                        │◄── enum: image|video
│ file_path                   │◄── local storage path
│ original_filename           │
│ ai_provider                 │◄── nullable: dalle3|stability|replicate|kling|gemini
│ ai_prompt                   │◄── nullable, the prompt used to generate
│ ai_model                    │◄── nullable, exact model string
│ token_cost                  │◄── nullable, decimal
│ sort_order                  │
│ created_at                  │
│ updated_at                  │
└─────────────────────────────┘

┌─────────────────────────────┐
│      hook_templates         │
├─────────────────────────────┤
│ id (PK)                     │
│ workspace_id (FK)           │
│ content_pillar_id (FK,null) │
│ text                        │◄── the hook template text
│ category                    │◄── enum: curiosity|fear|promise|story|question|data
│ score                       │◄── int 0-100, AI-evaluated
│ times_used                  │
│ is_favorite                 │
│ created_at                  │
│ updated_at                  │
└─────────────────────────────┘

┌─────────────────────────────┐
│      ai_model_configs       │
├─────────────────────────────┤
│ id (PK)                     │
│ workspace_id (FK)           │
│ feature                     │◄── enum: hook_gen|copywriting|insight|image|video
│ provider                    │◄── e.g. tokenrouter
│ model_id                    │◄── e.g. gpt-4o, claude-opus-4-5
│ temperature                 │◄── decimal
│ max_tokens                  │◄── int
│ system_prompt               │◄── text, overridable per feature
│ estimated_cost_per_1k       │◄── decimal, for display
│ is_active                   │
│ created_at                  │
│ updated_at                  │
└─────────────────────────────┘

┌─────────────────────────────┐
│    analytics_snapshots      │
├─────────────────────────────┤
│ id (PK)                     │
│ workspace_id (FK)           │
│ post_id (FK, nullable)      │◄── null = workspace-level snapshot
│ snapshot_date               │◄── date
│ followers_count             │
│ impressions                 │
│ likes                       │
│ replies                     │
│ reposts                     │
│ quotes                      │
│ engagement_rate             │◄── computed decimal
│ source                      │◄── enum: manual|api
│ created_at                  │
│ updated_at                  │
└─────────────────────────────┘

┌─────────────────────────────┐
│       activity_logs         │
├─────────────────────────────┤
│ id (PK)                     │
│ workspace_id (FK)           │
│ user_id (FK)                │
│ action                      │◄── e.g. post.created, post.scheduled
│ subject_type                │◄── morph
│ subject_id                  │◄── morph
│ meta                        │◄── JSON
│ created_at                  │
└─────────────────────────────┘
```

---

## 4. DATABASE SCHEMA — FULL MIGRATIONS

> **Order matters.** Run migrations in the order listed below.

### Migration 001 — workspaces
```php
Schema::create('workspaces', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('threads_handle')->nullable();
    $table->string('threads_account_id')->nullable();
    $table->text('threads_token')->nullable(); // encrypted via Laravel encrypt()
    $table->enum('threads_api_mode', ['manual', 'api'])->default('manual');
    $table->string('timezone')->default('Asia/Makassar');
    $table->enum('plan', ['free', 'pro'])->default('free');
    $table->timestamp('plan_expires_at')->nullable();
    $table->timestamps();
});
```

### Migration 002 — users (modify default Laravel users migration)
```php
Schema::table('users', function (Blueprint $table) {
    $table->foreignId('workspace_id')->constrained()->cascadeOnDelete()->after('id');
    $table->enum('role', ['owner', 'admin', 'member'])->default('member')->after('workspace_id');
    $table->string('avatar_url')->nullable();
    $table->timestamp('last_login_at')->nullable();
});
```

### Migration 003 — content_pillars
```php
Schema::create('content_pillars', function (Blueprint $table) {
    $table->id();
    $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->text('description')->nullable();
    $table->string('color_hex', 7)->default('#3B82F6');
    $table->string('icon')->default('ti-flag'); // Tabler icon class
    $table->boolean('is_active')->default(true);
    $table->unsignedTinyInteger('post_frequency')->default(2); // posts per week
    $table->timestamps();
});
```

### Migration 004 — content_ideas
```php
Schema::create('content_ideas', function (Blueprint $table) {
    $table->id();
    $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
    $table->foreignId('content_pillar_id')->nullable()->constrained()->nullOnDelete();
    $table->string('title');
    $table->text('description')->nullable();
    $table->boolean('ai_generated')->default(false);
    $table->enum('status', ['idea', 'in_progress', 'done', 'discarded'])->default('idea');
    $table->date('target_date')->nullable();
    $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
    $table->timestamps();
});
```

### Migration 005 — posts
```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
    $table->foreignId('content_idea_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('content_pillar_id')->nullable()->constrained()->nullOnDelete();
    $table->text('body');
    $table->string('hook')->nullable();
    $table->enum('status', ['draft', 'scheduled', 'published', 'failed', 'cancelled'])->default('draft');
    $table->dateTime('scheduled_at')->nullable();
    $table->dateTime('published_at')->nullable();
    $table->string('threads_post_id')->nullable();
    $table->enum('publish_mode', ['manual', 'auto'])->default('manual');
    $table->dateTime('reminder_sent_at')->nullable();
    $table->text('notes')->nullable();
    $table->string('ai_model_used')->nullable();
    $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
    $table->timestamps();
    
    $table->index(['workspace_id', 'status']);
    $table->index(['workspace_id', 'scheduled_at']);
});
```

### Migration 006 — post_media
```php
Schema::create('post_media', function (Blueprint $table) {
    $table->id();
    $table->foreignId('post_id')->constrained()->cascadeOnDelete();
    $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
    $table->enum('type', ['image', 'video'])->default('image');
    $table->string('file_path');
    $table->string('original_filename');
    $table->string('ai_provider')->nullable(); // dalle3 | stability | replicate | kling | gemini
    $table->text('ai_prompt')->nullable();
    $table->string('ai_model')->nullable();
    $table->decimal('token_cost', 10, 6)->nullable();
    $table->unsignedTinyInteger('sort_order')->default(0);
    $table->timestamps();
});
```

### Migration 007 — hook_templates
```php
Schema::create('hook_templates', function (Blueprint $table) {
    $table->id();
    $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
    $table->foreignId('content_pillar_id')->nullable()->constrained()->nullOnDelete();
    $table->text('text');
    $table->enum('category', ['curiosity', 'fear', 'promise', 'story', 'question', 'data'])->default('curiosity');
    $table->unsignedTinyInteger('score')->default(0); // 0-100
    $table->unsignedInteger('times_used')->default(0);
    $table->boolean('is_favorite')->default(false);
    $table->timestamps();
});
```

### Migration 008 — ai_model_configs
```php
Schema::create('ai_model_configs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
    $table->enum('feature', ['hook_gen', 'copywriting', 'insight', 'image', 'video']);
    $table->string('provider')->default('tokenrouter');
    $table->string('model_id'); // e.g. gpt-4o, claude-sonnet-4-20250514
    $table->decimal('temperature', 3, 2)->default(0.80);
    $table->unsignedInteger('max_tokens')->default(1000);
    $table->text('system_prompt')->nullable();
    $table->decimal('estimated_cost_per_1k', 8, 4)->default(0); // for display only
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    
    $table->unique(['workspace_id', 'feature']);
});
```

### Migration 009 — analytics_snapshots
```php
Schema::create('analytics_snapshots', function (Blueprint $table) {
    $table->id();
    $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
    $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
    $table->date('snapshot_date');
    $table->unsignedInteger('followers_count')->default(0);
    $table->unsignedInteger('impressions')->default(0);
    $table->unsignedInteger('likes')->default(0);
    $table->unsignedInteger('replies')->default(0);
    $table->unsignedInteger('reposts')->default(0);
    $table->unsignedInteger('quotes')->default(0);
    $table->decimal('engagement_rate', 5, 2)->default(0);
    $table->enum('source', ['manual', 'api'])->default('manual');
    $table->timestamps();
    
    $table->unique(['workspace_id', 'post_id', 'snapshot_date']);
    $table->index(['workspace_id', 'snapshot_date']);
});
```

### Migration 010 — activity_logs
```php
Schema::create('activity_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('action'); // post.created, post.scheduled, pillar.created, etc.
    $table->nullableMorphs('subject'); // subject_type, subject_id
    $table->json('meta')->nullable();
    $table->timestamp('created_at');
    
    $table->index(['workspace_id', 'created_at']);
});
```

### Seeders Required
```
DatabaseSeeder
├── WorkspaceSeeder       → 1 workspace: "Gimora Digital"
├── UserSeeder            → 1 owner user (from .env: SEED_EMAIL, SEED_PASSWORD)
├── ContentPillarSeeder   → 5 default pillars: Tech Tips, Case Study, Edukasi AI, Behind the Scenes, Promo
├── AiModelConfigSeeder   → default configs per feature pointing to tokenrouter
└── AnalyticsDummySeeder  → 30 days of dummy analytics_snapshots for dev/demo
```

---

## 5. PRODUCT REQUIREMENTS DOCUMENT (PRD)

### 5.1 Problem Statement

Gimora Digital needs to consistently post high-quality content on Threads to build brand authority for an IT agency targeting UMKM and government clients in Eastern Indonesia. Currently, content is written ad-hoc without a system — resulting in inconsistent posting, no analytics tracking, and wasted time on copywriting.

### 5.2 Goals

| Goal | Metric |
|---|---|
| Post consistently 5x/week | Scheduler fill rate ≥ 80% each week |
| Improve engagement | Avg engagement rate > 5% |
| Reduce content creation time | < 20 min from idea to scheduled post |
| Enable future SaaS | Architecture supports multi-workspace with zero DB migration |

### 5.3 Non-Goals (V1)

- No real-time Threads API sync (manual workflow first)
- No payment/billing module
- No mobile native app
- No multi-language UI (Indonesian only for V1)

### 5.4 User Personas

**V1 — Single User: Gimm (Owner)**
- Full access to all features
- Manages content strategy, approves and schedules posts
- Accesses from desktop browser (primary) and mobile (view-only)

**V3 — Multi-tenant: Client Business Owners**
- Each has their own workspace
- Limited to their own data
- Owner role per workspace

### 5.5 User Stories — V1 Core

**Dashboard**
- As Gimm, I want to see follower count, engagement rate, total impressions, and scheduled post count at a glance so I can assess my account health instantly.
- As Gimm, I want AI-generated daily insights telling me what's working and what to post next.
- As Gimm, I want to see the next 5 scheduled posts with their status and scheduled time.

**Content Planner**
- As Gimm, I want to define content pillars (topics) for my brand so my content stays strategic.
- As Gimm, I want to add content ideas under each pillar and track which ideas are in-progress or done.
- As Gimm, I want to see a calendar view of ideas and posts per month so I can spot gaps.

**Post Scheduler**
- As Gimm, I want to create a post with a body text, optional hook, assigned pillar, and scheduled datetime.
- As Gimm, I want to attach images to a post.
- As Gimm, I want to change a post's status (draft → scheduled → published) manually or via reminder.
- As Gimm, I want to receive an email/notification reminder X minutes before a scheduled post so I can copy-paste it to Threads manually (manual mode).
- As Gimm, I want to see a list view of all posts filterable by status and date range.

### 5.6 Acceptance Criteria Summary

| Feature | Criteria |
|---|---|
| Dashboard loads | All 4 metric cards show real data from DB within 2s |
| Content Planner | CRUD pillars and ideas; ideas link to posts |
| Calendar view | Monthly grid showing ideas and posts by scheduled date |
| Post create | Form validates body ≤ 500 chars (Threads limit), scheduled_at required if status=scheduled |
| Post list | Filterable by status; sortable by scheduled_at |
| Manual reminder | Email sent via Laravel Mail N minutes before scheduled_at |
| AI config | Superadmin can switch model per feature from settings page |

---

## 6. FEATURE SPECIFICATIONS — V1 CORE

### 6.1 Dashboard

**Route:** `GET /dashboard`  
**Controller:** `DashboardController@index`  
**Data resolved server-side via Inertia props:**

```php
return Inertia::render('Dashboard/Index', [
    'metrics'       => $this->analyticsService->getWorkspaceMetrics($workspace),
    'upcomingPosts' => Post::upcoming($workspace)->limit(5)->get(),
    'aiInsights'    => $this->insightService->getDailyInsights($workspace),
    'chartData'     => $this->analyticsService->getEngagementChart($workspace, days: 30),
    'recentActivity'=> ActivityLog::forWorkspace($workspace)->limit(10)->get(),
]);
```

**Metrics computed from:**
- `followers_count` → latest `analytics_snapshots` row where `post_id IS NULL`
- `engagement_rate` → avg of last 7 days snapshots
- `impressions` → sum last 30 days
- `scheduled_count` → `posts` where status=scheduled and scheduled_at > now()

**AI Insights generation:**
- Run once daily via `GenerateDailyInsightsJob` (Laravel Scheduler)
- Prompt: summarize last 7 days post performance, identify best time, suggest topic
- Store result in `workspace_meta` JSON column (add to workspaces table) key: `daily_insights`
- If fresh (< 24h old), serve from cache; else regenerate async

### 6.2 Content Planner

**Routes:**
```
GET  /content-pillars              → ContentPillarController@index
POST /content-pillars              → ContentPillarController@store
PUT  /content-pillars/{id}         → ContentPillarController@update
DELETE /content-pillars/{id}       → ContentPillarController@destroy

GET  /content-ideas                → ContentIdeaController@index
POST /content-ideas                → ContentIdeaController@store
PUT  /content-ideas/{id}           → ContentIdeaController@update
DELETE /content-ideas/{id}         → ContentIdeaController@destroy
POST /content-ideas/{id}/to-post   → ContentIdeaController@convertToPost
```

**Calendar view data:**
- `GET /content-planner/calendar?month=2025-05` returns ideas + posts grouped by date
- Returned as flat array: `[{ date, type: 'idea'|'post', title, status, color }]`

**Validation rules — ContentIdea:**
```php
'title'              => 'required|string|max:200',
'content_pillar_id'  => 'nullable|exists:content_pillars,id',
'description'        => 'nullable|string|max:1000',
'status'             => 'in:idea,in_progress,done,discarded',
'target_date'        => 'nullable|date|after_or_equal:today',
```

### 6.3 Post Scheduler

**Routes:**
```
GET    /posts                → PostController@index
GET    /posts/create         → PostController@create
POST   /posts                → PostController@store
GET    /posts/{id}/edit      → PostController@edit
PUT    /posts/{id}           → PostController@update
DELETE /posts/{id}           → PostController@destroy
POST   /posts/{id}/publish   → PostController@markPublished  (manual mode)
POST   /posts/{id}/cancel    → PostController@cancel
```

**Validation rules — Post:**
```php
'body'                → 'required|string|max:500',
'hook'                → 'nullable|string|max:150',
'content_pillar_id'   → 'nullable|exists:content_pillars,id',
'content_idea_id'     → 'nullable|exists:content_ideas,id',
'status'              => 'in:draft,scheduled,cancelled',
'scheduled_at'        => 'required_if:status,scheduled|date|after:now',
'publish_mode'        => 'in:manual,auto',
'media'               => 'nullable|array|max:4',
'media.*'             => 'file|mimes:jpg,jpeg,png,gif,mp4,mov|max:51200',
```

**Reminder system:**
- `SendPostReminderJob` dispatched at `scheduled_at - reminder_offset_minutes` (configurable per workspace, default 30 min)
- Sends email via `PostReminderMail` Mailable
- Email includes: post body, hook, scheduled time, link to edit
- Sets `reminder_sent_at` on post record

**Post List filters:**
```
?status=draft|scheduled|published|failed|cancelled
?pillar_id={id}
?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD
?search={keyword}
```

---

## 7. FILE & FOLDER STRUCTURE

```
threadsai-manager/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       └── GenerateInsightsCommand.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/              ← Breeze default
│   │   │   ├── DashboardController.php
│   │   │   ├── ContentPillarController.php
│   │   │   ├── ContentIdeaController.php
│   │   │   ├── PostController.php
│   │   │   ├── AnalyticsController.php
│   │   │   └── Settings/
│   │   │       └── AiModelConfigController.php
│   │   ├── Middleware/
│   │   │   └── EnsureWorkspaceAccess.php
│   │   └── Requests/
│   │       ├── StorePostRequest.php
│   │       ├── UpdatePostRequest.php
│   │       ├── StoreContentIdeaRequest.php
│   │       └── StoreContentPillarRequest.php
│   ├── Jobs/
│   │   ├── SendPostReminderJob.php
│   │   └── GenerateDailyInsightsJob.php
│   ├── Mail/
│   │   └── PostReminderMail.php
│   ├── Models/
│   │   ├── Workspace.php
│   │   ├── User.php
│   │   ├── ContentPillar.php
│   │   ├── ContentIdea.php
│   │   ├── Post.php
│   │   ├── PostMedia.php
│   │   ├── HookTemplate.php
│   │   ├── AiModelConfig.php
│   │   ├── AnalyticsSnapshot.php
│   │   └── ActivityLog.php
│   ├── Services/
│   │   ├── AIService.php
│   │   ├── InsightService.php
│   │   ├── AnalyticsService.php
│   │   ├── PostReminderService.php
│   │   └── MediaUploadService.php
│   └── Traits/
│       └── BelongsToWorkspace.php
├── database/
│   ├── migrations/
│   │   ├── 001_create_workspaces_table.php
│   │   ├── 002_add_workspace_to_users_table.php
│   │   ├── 003_create_content_pillars_table.php
│   │   ├── 004_create_content_ideas_table.php
│   │   ├── 005_create_posts_table.php
│   │   ├── 006_create_post_media_table.php
│   │   ├── 007_create_hook_templates_table.php
│   │   ├── 008_create_ai_model_configs_table.php
│   │   ├── 009_create_analytics_snapshots_table.php
│   │   └── 010_create_activity_logs_table.php
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── WorkspaceSeeder.php
│       ├── UserSeeder.php
│       ├── ContentPillarSeeder.php
│       ├── AiModelConfigSeeder.php
│       └── AnalyticsDummySeeder.php
├── resources/
│   ├── js/
│   │   ├── app.js
│   │   ├── Pages/
│   │   │   ├── Dashboard/
│   │   │   │   └── Index.vue
│   │   │   ├── ContentPlanner/
│   │   │   │   ├── Index.vue      ← pillars + ideas list
│   │   │   │   └── Calendar.vue   ← calendar view
│   │   │   ├── Posts/
│   │   │   │   ├── Index.vue      ← post list with filters
│   │   │   │   ├── Create.vue
│   │   │   │   └── Edit.vue
│   │   │   ├── Settings/
│   │   │   │   ├── General.vue
│   │   │   │   └── AiModels.vue   ← model config per feature
│   │   │   └── Auth/              ← Breeze default
│   │   ├── Components/
│   │   │   ├── Layout/
│   │   │   │   ├── AppLayout.vue  ← sidebar + topbar shell
│   │   │   │   ├── Sidebar.vue
│   │   │   │   └── Topbar.vue
│   │   │   ├── Dashboard/
│   │   │   │   ├── MetricCard.vue
│   │   │   │   ├── EngagementChart.vue  ← Chart.js
│   │   │   │   ├── InsightPanel.vue
│   │   │   │   └── UpcomingPosts.vue
│   │   │   ├── Posts/
│   │   │   │   ├── PostCard.vue
│   │   │   │   ├── PostStatusBadge.vue
│   │   │   │   ├── PostFilters.vue
│   │   │   │   └── MediaUploader.vue
│   │   │   ├── ContentPlanner/
│   │   │   │   ├── PillarCard.vue
│   │   │   │   ├── IdeaCard.vue
│   │   │   │   └── CalendarGrid.vue
│   │   │   └── UI/
│   │   │       ├── AppButton.vue
│   │   │       ├── AppModal.vue
│   │   │       ├── AppBadge.vue
│   │   │       ├── AppInput.vue
│   │   │       ├── AppSelect.vue
│   │   │       └── AppTextarea.vue
│   │   └── Composables/
│   │       ├── useFlash.js
│   │       └── useWorkspace.js
│   └── views/
│       ├── app.blade.php
│       └── mail/
│           └── post-reminder.blade.php
├── routes/
│   └── web.php
├── .env.example
├── composer.json
└── vite.config.js
```

---

## 8. API ROUTES & CONTROLLER MAP

All routes are Inertia web routes (not REST API). Protected by `auth` + `EnsureWorkspaceAccess` middleware.

```php
// routes/web.php

Route::middleware(['auth', 'workspace'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Content Pillars
    Route::resource('content-pillars', ContentPillarController::class)
        ->except(['show']);

    // Content Ideas
    Route::resource('content-ideas', ContentIdeaController::class)
        ->except(['show']);
    Route::post('content-ideas/{idea}/to-post', [ContentIdeaController::class, 'convertToPost'])
        ->name('content-ideas.to-post');

    // Content Planner Calendar
    Route::get('content-planner', [ContentPlannerController::class, 'index'])->name('content-planner');
    Route::get('content-planner/calendar-data', [ContentPlannerController::class, 'calendarData'])
        ->name('content-planner.calendar');

    // Posts
    Route::resource('posts', PostController::class);
    Route::post('posts/{post}/publish', [PostController::class, 'markPublished'])->name('posts.publish');
    Route::post('posts/{post}/cancel', [PostController::class, 'cancel'])->name('posts.cancel');

    // Analytics (V1: manual input, V2: API sync)
    Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics');
    Route::post('analytics/snapshot', [AnalyticsController::class, 'storeSnapshot'])->name('analytics.snapshot');

    // Settings
    Route::get('settings/general', [Settings\GeneralController::class, 'index'])->name('settings.general');
    Route::put('settings/general', [Settings\GeneralController::class, 'update']);
    Route::get('settings/ai-models', [Settings\AiModelConfigController::class, 'index'])->name('settings.ai-models');
    Route::put('settings/ai-models/{config}', [Settings\AiModelConfigController::class, 'update']);

});
```

---

## 9. SERVICE LAYER DESIGN

### 9.1 AIService.php

Central service wrapping TokenRouter (OpenAI-compatible endpoint).

```php
class AIService
{
    private string $baseUrl = 'https://api.tokenrouter.com/v1';
    private string $apiKey;  // from config('services.tokenrouter.key')

    public function complete(
        string $feature,           // e.g. 'hook_gen'
        string $userPrompt,
        ?string $systemPrompt = null,
        ?Workspace $workspace = null
    ): string {
        $config = $this->getConfig($feature, $workspace);
        
        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/chat/completions", [
                'model'       => $config->model_id,
                'temperature' => $config->temperature,
                'max_tokens'  => $config->max_tokens,
                'messages'    => [
                    ['role' => 'system', 'content' => $systemPrompt ?? $config->system_prompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
            ]);

        return $response->json('choices.0.message.content');
    }

    private function getConfig(string $feature, ?Workspace $workspace): AiModelConfig {
        return AiModelConfig::where('workspace_id', $workspace?->id ?? auth()->user()->workspace_id)
            ->where('feature', $feature)
            ->where('is_active', true)
            ->firstOrFail();
    }
}
```

### 9.2 InsightService.php

```php
class InsightService
{
    public function __construct(private AIService $ai) {}

    public function getDailyInsights(Workspace $workspace): array
    {
        $cached = json_decode($workspace->workspace_meta['daily_insights'] ?? '{}', true);
        
        if (!empty($cached['generated_at']) && Carbon::parse($cached['generated_at'])->isToday()) {
            return $cached['insights'];
        }

        // Dispatch async job; return stale or placeholder in the meantime
        GenerateDailyInsightsJob::dispatch($workspace);
        return $cached['insights'] ?? $this->getPlaceholderInsights();
    }
}
```

### 9.3 AnalyticsService.php

```php
class AnalyticsService
{
    public function getWorkspaceMetrics(Workspace $workspace): array
    {
        $latest = AnalyticsSnapshot::latestWorkspaceLevel($workspace)->first();
        $last7  = AnalyticsSnapshot::workspaceLevel($workspace)->last7Days()->get();
        $last30 = AnalyticsSnapshot::workspaceLevel($workspace)->last30Days()->get();

        return [
            'followers'        => $latest?->followers_count ?? 0,
            'followers_delta'  => $this->delta($last30, 'followers_count'),
            'engagement_rate'  => round($last7->avg('engagement_rate'), 1),
            'impressions'      => $last30->sum('impressions'),
            'scheduled_count'  => Post::forWorkspace($workspace)->scheduled()->count(),
        ];
    }

    public function getEngagementChart(Workspace $workspace, int $days = 30): array
    {
        return AnalyticsSnapshot::workspaceLevel($workspace)
            ->lastNDays($days)
            ->orderBy('snapshot_date')
            ->get()
            ->map(fn($s) => [
                'date'        => $s->snapshot_date->format('d M'),
                'impressions' => $s->impressions,
                'likes'       => $s->likes,
                'replies'     => $s->replies,
            ])
            ->toArray();
    }
}
```

### 9.4 MediaUploadService.php

```php
class MediaUploadService
{
    public function upload(UploadedFile $file, Post $post): PostMedia
    {
        $path = $file->store("workspaces/{$post->workspace_id}/posts/{$post->id}", 'public');

        return PostMedia::create([
            'post_id'           => $post->id,
            'workspace_id'      => $post->workspace_id,
            'type'              => Str::startsWith($file->getMimeType(), 'video') ? 'video' : 'image',
            'file_path'         => $path,
            'original_filename' => $file->getClientOriginalName(),
            'sort_order'        => $post->media()->count(),
        ]);
    }
}
```

---

## 10. FRONTEND COMPONENT TREE

```
AppLayout.vue
├── Sidebar.vue
│   ├── Logo + workspace name
│   ├── NavItem (Dashboard, Content Planner, Posts, Settings)
│   └── AccountRow (avatar, name, handle)
└── [slot: page content]

Pages/Dashboard/Index.vue
├── Topbar (page title, date range chip, Sync button, Create button)
├── MetricCard × 4 (followers, engagement, impressions, scheduled)
├── EngagementChart.vue (Chart.js line chart)
├── InsightPanel.vue (AI daily insights)
└── UpcomingPosts.vue (list of next 5 posts)

Pages/ContentPlanner/Index.vue
├── PillarCard × N (per content pillar)
│   └── IdeaCard × N (per idea under pillar)
├── Modal: CreatePillarForm
└── Modal: CreateIdeaForm

Pages/ContentPlanner/Calendar.vue
└── CalendarGrid.vue
    └── DayCell × 28-31 (ideas + posts per day, color coded by pillar)

Pages/Posts/Index.vue
├── PostFilters.vue (status, pillar, date range, search)
└── PostCard × N
    ├── PostStatusBadge.vue
    └── Action buttons (Edit, Cancel, Mark Published)

Pages/Posts/Create.vue / Edit.vue
├── AppTextarea (body — 500 char counter)
├── AppInput (hook — 150 char counter)
├── AppSelect (pillar, idea link, status)
├── DateTimePicker (scheduled_at)
├── MediaUploader.vue (drag-drop, 4 files max)
└── Submit buttons

Pages/Settings/AiModels.vue
└── AiModelConfigRow × 5 (per feature)
    ├── Model ID input (text — user types model string)
    ├── Temperature slider
    ├── Max tokens input
    ├── Estimated cost per 1k display
    └── System prompt textarea (expandable)
```

---

## 11. ENVIRONMENT CONFIGURATION

### .env.example
```env
APP_NAME="ThreadsAI Manager"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database — dev: SQLite, prod: MySQL
DB_CONNECTION=sqlite
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=threadsai
# DB_USERNAME=root
# DB_PASSWORD=

# Queue — use database for cPanel compatibility
QUEUE_CONNECTION=database

# Mail — for post reminders
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@gimora.my.id"
MAIL_FROM_NAME="ThreadsAI Manager"

# AI — TokenRouter (OpenAI-compatible)
TOKENROUTER_API_KEY=your_key_here
TOKENROUTER_BASE_URL=https://api.tokenrouter.com/v1

# Seeder defaults (change before first migrate:fresh --seed)
SEED_WORKSPACE_NAME="Gimora Digital"
SEED_THREADS_HANDLE="@gimoradigital.id"
SEED_EMAIL=gimm@gimora.my.id
SEED_PASSWORD=supersecret

# Post reminder offset (minutes before scheduled_at)
POST_REMINDER_OFFSET_MINUTES=30

# Threads API (V2 — leave empty for V1 manual mode)
THREADS_APP_ID=
THREADS_APP_SECRET=
THREADS_ACCESS_TOKEN=
```

### config/services.php additions
```php
'tokenrouter' => [
    'key'      => env('TOKENROUTER_API_KEY'),
    'base_url' => env('TOKENROUTER_BASE_URL', 'https://api.tokenrouter.com/v1'),
],
'threads' => [
    'app_id'       => env('THREADS_APP_ID'),
    'app_secret'   => env('THREADS_APP_SECRET'),
    'access_token' => env('THREADS_ACCESS_TOKEN'),
],
```

---

## 12. DEPLOYMENT GUIDE — CPANEL

### 12.1 Prerequisites
- PHP 8.3+ with extensions: pdo, pdo_mysql, mbstring, openssl, curl, fileinfo, gd
- MySQL 8.0+ database created in cPanel
- Node.js 20+ available via cPanel Terminal (or pre-build locally)
- Composer 2.x

### 12.2 Steps

```bash
# 1. Upload code to /home/[user]/threadsai (NOT public_html)
# 2. Set document root to /home/[user]/threadsai/public

# 3. SSH / cPanel Terminal:
cd ~/threadsai
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate

# 4. Edit .env: set DB_CONNECTION=mysql, fill DB_* credentials

# 5. Run migrations + seed
php artisan migrate --force
php artisan db:seed --force

# 6. Build frontend (if Node available on server)
npm ci
npm run build
# OR: build locally, upload /public/build folder

# 7. Storage symlink
php artisan storage:link

# 8. Set permissions
chmod -R 775 storage bootstrap/cache

# 9. cPanel Cron Job (every minute)
* * * * * cd ~/threadsai && php artisan schedule:run >> /dev/null 2>&1

# 10. Queue worker (cPanel background process or cron every 5 min)
*/5 * * * * cd ~/threadsai && php artisan queue:work --max-jobs=50 --stop-when-empty >> /dev/null 2>&1
```

### 12.3 Scheduled Tasks (Console/Kernel.php)
```php
$schedule->job(new GenerateDailyInsightsJob())->dailyAt('06:00');
$schedule->command('queue:prune-failed --hours=48')->daily();
```

---

## 13. FUTURE ROADMAP (V2+)

| Version | Features | Notes |
|---|---|---|
| V2 | Hook Generator (AI) | TokenRouter, scored 0-100, saved to hook_templates |
| V2 | Copywriting AI | Full post writer, tone selector, thread splitter |
| V2 | Image/Video Studio | Multi-provider: gemini-3-pro-image-preview, kling-v3, gemini-3.1-flash-image-preview, dreamina-seedance-2-0-fast | Cost token display per model from ai_model_configs |
| V2 | Threads API sync | OAuth flow, auto-publish, pull analytics |
| V2 | Deep Analytics | Per-post breakdown, heatmap by hour, best hashtags |
| V3 | Multi-tenant SaaS | Workspace isolation, invite system, role management |
| V3 | Billing | Midtrans/Xendit, per-workspace plan gating |
| V3 | White-label | Custom domain per workspace, logo customization |

### Image/Video Providers (Pre-configured for V2)

| Model ID | Provider | Type | Notes |
|---|---|---|---|
| `google/gemini-3-pro-image-preview` | TokenRouter | Image | High quality, text in image support |
| `google/gemini-3.1-flash-image-preview` | TokenRouter | Image | Faster, lower cost |
| `kling-v3` | TokenRouter | Video | Cinematic video generation |
| `dreamina-seedance-2-0-fast-260128` | TokenRouter | Video | Fast video, good for social |

All model configs stored in `ai_model_configs` table with `estimated_cost_per_1k` for display in superadmin Settings > AI Models page.

---

## 14. CODING AGENT INSTRUCTIONS

> **READ THIS SECTION FIRST before writing any code.**

### 14.1 Identity & Role

You are **Antigravity**, an autonomous coding agent powered by Claude Opus 4.6. You are building **ThreadsAI Manager** — a full-stack Laravel 13 + Vue 3 + Inertia.js application from scratch.

### 14.2 Stack & Versions — Exact

| Layer | Choice |
|---|---|
| PHP Framework | Laravel 13 (latest) |
| Frontend | Vue 3 Composition API + `<script setup>` syntax |
| Bridge | Inertia.js v2 |
| CSS | Tailwind CSS v4 (use `@import "tailwindcss"` in CSS, NOT config file) |
| Icons | Tabler Icons (via CDN or npm `@tabler/icons-vue`) |
| Charts | Chart.js + vue-chartjs |
| Database (dev) | SQLite |
| Database (prod) | MySQL 8.0 |
| Queue | Database driver |
| Auth | Laravel Breeze (Inertia + Vue 3 variant) |
| HTTP Client | Laravel `Http` facade (Guzzle) |

### 14.3 Build Order — Staged Execution

Execute in this exact order. Do NOT skip stages.

```
Stage 1: Project scaffold
  - laravel new threadsai-manager --breeze --stack=vue --inertia
  - Install: vue-chartjs, chart.js, @tabler/icons-vue
  - Configure Tailwind CSS v4
  - Set up .env from .env.example

Stage 2: Database
  - Create all 10 migrations in order
  - Create all models with fillable, casts, relationships, scopes
  - Create all seeders
  - Run: php artisan migrate:fresh --seed

Stage 3: Backend — Services & Jobs
  - AIService.php
  - AnalyticsService.php
  - InsightService.php
  - MediaUploadService.php
  - PostReminderService.php
  - SendPostReminderJob.php
  - GenerateDailyInsightsJob.php
  - PostReminderMail.php

Stage 4: Backend — Controllers & Routes
  - EnsureWorkspaceAccess middleware
  - All Form Request classes
  - DashboardController
  - ContentPillarController
  - ContentIdeaController
  - ContentPlannerController
  - PostController
  - AnalyticsController
  - Settings/AiModelConfigController
  - Register all routes in web.php

Stage 5: Frontend — Layout & UI primitives
  - AppLayout.vue (sidebar + topbar shell)
  - Sidebar.vue with navigation
  - UI components: AppButton, AppModal, AppInput, AppSelect, AppTextarea, AppBadge

Stage 6: Frontend — Dashboard page
  - MetricCard.vue
  - EngagementChart.vue (Chart.js)
  - InsightPanel.vue
  - UpcomingPosts.vue
  - Pages/Dashboard/Index.vue (assemble)

Stage 7: Frontend — Content Planner pages
  - PillarCard.vue + CreatePillarModal
  - IdeaCard.vue + CreateIdeaModal
  - CalendarGrid.vue
  - Pages/ContentPlanner/Index.vue
  - Pages/ContentPlanner/Calendar.vue

Stage 8: Frontend — Posts pages
  - PostCard.vue, PostStatusBadge.vue, PostFilters.vue
  - MediaUploader.vue (drag-drop)
  - Pages/Posts/Index.vue
  - Pages/Posts/Create.vue
  - Pages/Posts/Edit.vue

Stage 9: Settings pages
  - Pages/Settings/General.vue
  - Pages/Settings/AiModels.vue (model config per feature, with cost display)

Stage 10: Final
  - Configure Console/Kernel.php scheduled tasks
  - Storage symlink
  - Run full test: migrate:fresh --seed, serve, verify all pages load
```

### 14.4 Coding Standards

- **All Vue components:** `<script setup>` Composition API only. No Options API.
- **All PHP:** strict types, type hints on all method parameters and return types.
- **No hardcoded IDs or workspace_id in controllers** — always resolve from `auth()->user()->workspace_id`.
- **All queries scoped to workspace** — every Eloquent query MUST include `workspace_id` filter.
- **Models must have `BelongsToWorkspace` trait** — which adds a global scope for automatic workspace filtering.
- **Form Requests for all POST/PUT** — no inline validation in controllers.
- **Inertia responses only** — no `response()->json()` in web controllers.
- **Error handling:** wrap AI calls in try/catch; return graceful fallback if TokenRouter fails.
- **Character limits enforced both frontend (live counter) and backend (validation)**.

### 14.5 Design System

- **Color palette:** Neutral gray base. Blue (#3B82F6) as primary action. Green for success/published. Yellow-amber for scheduled. Red for failed. Gray for draft.
- **Typography:** system-ui / sans-serif. No decorative fonts.
- **Sidebar width:** 220px fixed on desktop, hidden on mobile (hamburger toggle).
- **Cards:** white background, 1px border (`border-gray-200`), rounded-xl, padding 16-20px.
- **Status badges:** pill shape, color-coded per status enum.
- **Post body textarea:** shows live character count `X / 500`.

### 14.6 AI Integration Notes

- **TokenRouter is OpenAI-compatible.** Use standard OpenAI message format: `{role, content}`.
- **Base URL:** `https://api.tokenrouter.com/v1` (set in `.env`).
- **Auth:** Bearer token in `Authorization` header.
- **Model switching:** Always read from `ai_model_configs` table, never hardcode model strings.
- **System prompts** are stored per-feature in `ai_model_configs.system_prompt` and overridable from Settings.
- **Default system prompts for V1 insight feature:**
  ```
  Kamu adalah AI content strategist untuk akun Threads bisnis Indonesia.
  Analisis data performa konten dan berikan 3 insight singkat dalam Bahasa Indonesia.
  Format: JSON array of {type: 'success'|'info'|'warning', text: string}
  ```

### 14.7 Do NOT Do

- Do NOT use Laravel API routes (`routes/api.php`) — everything goes through `routes/web.php` with Inertia.
- Do NOT use `WidthType.PERCENTAGE` in any document generation.
- Do NOT use Vue Options API.
- Do NOT hardcode any API keys in code — always via `env()` or `config()`.
- Do NOT install unnecessary packages — keep composer.json lean.
- Do NOT use `dd()` or `dump()` in final code.
- Do NOT use `*` in Eloquent selects — be explicit with columns.

### 14.8 After Each Stage

After completing each stage, output:
1. A summary of files created/modified.
2. Any migration commands to run.
3. Any errors encountered and how they were resolved.
4. Confirmation that the stage is complete before proceeding to the next.

---

*End of Implementation Plan — ThreadsAI Manager v1.0*  
*Generated for Antigravity Opus 4.6 · Gimora Digital · May 2025*
