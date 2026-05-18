# Threadly Hosting Changes

This file lists the code and deployment-sensitive changes required when updating the existing cPanel shared-hosting installation at `threadly.gimmhost.my.id`.

## Changed Code Areas

- Auth routing:
  - Public registration is disabled.
  - Breeze profile routes are restored: `GET /profile`, `PATCH /profile`, `DELETE /profile`.
- Scheduler:
  - Auto-publish now uses `publish_mode=auto`.
  - Reminder emails are limited to manual scheduled posts.
- Threads token handling:
  - `threads_access_token` is read backward-compatibly from old plaintext values.
  - New token writes are encrypted with Laravel `APP_KEY`.
  - A migration expands the token column so long encrypted/API token values fit.
- External HTTPS calls:
  - TokenRouter and Threads API SSL verification default to enabled.
  - Emergency cPanel overrides are available through `TOKENROUTER_VERIFY_SSL=false` and `THREADS_VERIFY_SSL=false`.
- Post media:
  - Create/edit post forms now send selected media files through multipart form data.
  - UI enforces the 4-media limit before submit.
- AI optimization:
  - AI prompts now live in versioned templates in `config/ai-prompts.php`.
  - TokenRouter models now include capability metadata, endpoint routing, JSON-mode support flags, and estimated pricing in `config/ai-models.php`.
  - Text workflows use deterministic caching, smaller workflow-specific token caps, structured JSON parsing, and one cheap repair attempt when JSON is invalid.
  - Existing default helper/insight model configs that still use `anthropic/claude-sonnet-4.5` are moved to `deepseek/deepseek-v4-flash`.
  - Existing default copywriting configs are moved to `deepseek/deepseek-v4-pro` for better final post/thread quality at lower cost than Sonnet.
  - New endpoints are available for one-call content assistance and dedicated hashtag generation: `POST /ai/content-assist` and `POST /ai/generate-hashtags`.
  - AI usage cost logging now uses model catalog estimates instead of a flat token multiplier.
- Image Studio AI:
  - New poster workflow builds a detailed visual prompt with brand/audience/composition/safe-area/negative constraints.
  - Poster metadata is stored with original prompt, enhanced prompt, negative prompt, model params, overlay config, template version, status, estimated cost, and errors.
  - Image endpoint selection follows model capability metadata: image generation, edits, or multimodal chat where appropriate.
  - Optional cPanel-safe AI image queue is available through `AI_IMAGE_QUEUE_ENABLED=true`.
- Analytics and hooks:
  - Analytics snapshots now include Threads metrics used by the existing Analytics UI: likes, replies, reposts, quotes, and source.
  - Hook template categories are relaxed from a fixed enum to a string so the current free-text UI does not fail database constraints.
- Environment and docs:
  - `.env.example` is now Threadly-specific.
  - `README.md` documents the actual project.

## Files Changed For Hosting Awareness

```text
.env.example
README.md
HOSTING_CHANGES.md
routes/auth.php
routes/console.php
config/services.php
config/ai-models.php
config/ai-prompts.php
database/migrations/2026_05_18_000001_expand_workspace_threads_token_column.php
database/migrations/2026_05_18_000002_add_threads_metrics_to_analytics_snapshots_table.php
database/migrations/2026_05_18_000003_relax_hook_template_category_column.php
database/migrations/2026_05_18_000004_add_ai_optimization_metadata.php
database/migrations/2026_05_18_000005_move_default_ai_text_models_to_hemat.php
database/migrations/2026_05_18_000006_set_copywriting_default_model.php
app/Jobs/GeneratePosterImageJob.php
app/Models/Workspace.php
app/Models/AnalyticsSnapshot.php
app/Models/AiUsageLog.php
app/Models/GeneratedMedia.php
app/Services/AI/*
app/Http/Controllers/AnalyticsController.php
app/Http/Controllers/AiAssistController.php
app/Http/Controllers/CopywritingController.php
app/Http/Controllers/HookTemplateController.php
app/Http/Controllers/ImageStudioController.php
app/Http/Controllers/PostController.php
app/Services/PostReminderService.php
app/Services/AIService.php
app/Services/ThreadsApiService.php
app/Services/InsightService.php
resources/js/Pages/ImageStudio/Index.vue
resources/js/Pages/Hooks/Index.vue
resources/js/Pages/Posts/Create.vue
resources/js/Pages/Posts/Edit.vue
public/build/*
```

## cPanel Deploy Commands

Run from the project folder on cPanel:

```bash
cd ~/threadly.gimmhost.my.id
git pull origin main
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
```

Confirm `public/build/manifest.json` exists after pull. This project currently commits built Vite assets for cPanel.

## Production `.env` Checklist

Do not commit `.env`. Edit it manually on cPanel.

Required production values:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://threadly.gimmhost.my.id
SESSION_SECURE_COOKIE=true

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

QUEUE_CONNECTION=database
CACHE_STORE=database

TOKENROUTER_API_KEY=...
TOKENROUTER_BASE_URL=https://api.tokenrouter.com/v1
TOKENROUTER_VERIFY_SSL=true

AI_CACHE_TTL_DAYS=14
AI_DAILY_COST_LIMIT_USD=0
AI_DEFAULT_COST_MODE=hemat
AI_IMAGE_QUEUE_ENABLED=false

THREADS_APP_ID=...
THREADS_APP_SECRET=...
THREADS_REDIRECT_URI=https://threadly.gimmhost.my.id/auth/threads/callback
THREADS_VERIFY_SSL=true
```

Only set `TOKENROUTER_VERIFY_SSL=false` or `THREADS_VERIFY_SSL=false` temporarily if the shared host has a CA certificate problem and external HTTPS requests fail.

## Migration Notes

- The migration only changes `workspaces.threads_access_token` to a larger text column.
- Existing production rows are preserved.
- Existing plaintext tokens continue to work.
- Any token saved after this deploy is encrypted.
- AI migration only adds metadata columns to `ai_usage_logs` and `generated_media`; it does not delete existing generated media or usage logs.
- AI model-cost migrations only update old default TokenRouter text configs from Sonnet or Flash to the new helper/copywriting defaults. Custom model rows using other model IDs are preserved.

## Cron

Keep the scheduler cron active:

```cron
* * * * * cd /home/username/threadly.gimmhost.my.id && php artisan schedule:run >> /dev/null 2>&1
```

If queue workers are not persistent on the shared host, keep the stop-when-empty worker cron:

```cron
* * * * * cd /home/username/threadly.gimmhost.my.id && php artisan queue:work database --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

If `AI_IMAGE_QUEUE_ENABLED=true`, the scheduler also runs a short AI queue worker every minute:

```cron
* * * * * cd /home/username/threadly.gimmhost.my.id && php artisan schedule:run >> /dev/null 2>&1
```

Do not add a second long-running AI worker on shared hosting unless the host explicitly supports persistent processes.

## Rollback Notes

- Prefer reverting the Git commit and running `php artisan optimize:clear`.
- Do not roll back the token migration unless there is a hard requirement; shrinking token columns can truncate encrypted values.
- Do not roll back AI metadata columns unless necessary; old code can ignore the extra nullable columns.
- If the frontend looks stale, confirm the latest `public/build` files were pulled and run `php artisan optimize:clear`.
