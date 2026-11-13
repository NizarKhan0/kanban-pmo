# 🏛️ Kanban PMO — Master Architecture & System Blueprint

Dokumen ini adalah rujukan tunggal (Single Source of Truth) bagi seni bina teknikal, skema data, dan polisi kejuruteraan sistem Kanban PMO. Peraturan agen: `CLAUDE.md`. Spesifikasi modul: `docs/modules/`. Fail rujukan luaran: `docs/reference/`.

> Status: Modul 01–06 siap, ditambah Dashboard, Landing Page dan reka bentuk Auth. Dikemas kini selepas pelaksanaan — lihat juga "Nota Pelaksanaan" dalam setiap fail modul.

## 1. Technical Overview

- **Objektif Sistem:** Sistem pengurusan projek berpusat (PMO Command Center) berasaskan papan Kanban interaktif untuk memantau aliran kerja SDLC pelbagai projek, beban kerja pasukan (PIC), analitik penyelesaian, laporan standup harian, pembantu AI bersepadu, dan hab notifikasi pelbagai saluran (Omnichannel: Telegram, Gmail, WhatsApp).
- **Tech Stack Matrix:**
    - Framework: Laravel 13 (PHP 8.4+, `declare(strict_types=1)` pada semua fail PHP)
    - Frontend: Livewire v4 + Alpine.js (Drag-and-Drop `x-sort` & Optimistic UI) + Flux (dari Starter Kit: layout, sidebar, toast)
    - Styling: Tailwind CSS v4 (`@import "tailwindcss";`) dengan tema Neo Dark/Light; aksen Flux `#8b5cf6`
    - Database: MySQL 8.0+ (InnoDB, `utf8mb4_unicode_ci`); ujian menggunakan SQLite in-memory
    - Auth: Laravel Fortify (Starter Kit) — login, register, reset password, 2FA, passkeys, email verification
    - AI SDK: `anthropic-ai/sdk` (rasmi) untuk Claude; Laravel `Http` untuk OpenAI / Custom Endpoint
- **Multi-Tenancy:** Single Database Tenant Boundary (Semua rekod utama terikat kepada `user_id`; sub-entiti melalui induk).
- **Integrations (Simulation-First Ready):**
    1. **AI Engine:** Anthropic Claude (lalai `claude-sonnet-5-5`), OpenAI, Custom LLM Endpoint (OpenAI-compatible).
    2. **Telegram:** Telegram Bot API (Synchronous HTTP, mesej dipecah ≤ 4000 aksara).
    3. **Email / Gmail:** Laravel Mail (Gmail SMTP + App Password) untuk peringatan PIC & laporan PMO.
    4. **WhatsApp:** `wa.me` Deep-Link Formatter & Optional Webhook Gateway API (`POST {phone, message}` + Bearer token).

## 2. Entity Relationship Diagram (ASCII ERD)

```
[ User ]
├── hasMany ──► [ Projects ] (id, user_id, name, code, description, icon, color, status, sort_order)
├── hasMany ──► [ TeamMembers ] (id, user_id, name, role, email, phone_whatsapp, icon, color, is_me, is_active, sort_order)
├── hasMany ──► [ BoardColumns ] (id, user_id, name, icon, color, wip_limit, is_done_column, sort_order)
│     └── hasMany ──► [ Tasks ] (id, user_id, board_column_id, project_id?, team_member_id?, title,
│                       project_name_snapshot, pic_name_snapshot, priority, icon, color, due_date,
│                       tags(json), notes, sort_order, completed_at)
│           ├── hasMany ──► [ Subtasks ] (id, task_id, title, is_completed, sort_order)   ← tiada user_id; skop melalui task
│           ├── belongsTo (nullable, nullOnDelete) ──► [ Projects ]
│           └── belongsTo (nullable, nullOnDelete) ──► [ TeamMembers (PIC) ]
├── hasMany ──► [ ActivityLogs ] (id, user_id, actor_name, action_type, task_title, meta_details)
├── hasMany ──► [ SavedAiPrompts ] (id, user_id, title, action_type, custom_instruction, icon, color, sort_order)
├── hasMany ──► [ AutomationLogs ] (id, user_id, channel, command_name, recipient, message, response_payload, tokens_used, status, error_message)
└── hasMany ──► [ WorkspaceBackups ] (id, user_id, filename, file_size_kb, columns_count, tasks_count, payload_json, notes)
```

- `activity_logs.action_type`: `created`, `updated`, `moved`, `deleted`, `col_added`, `pic_added`, `note` (nota standup pengguna).
- Indeks kompaun mengikut spesifikasi modul; semua jadual milik pengguna mempunyai indeks bermula dengan `user_id`.

## 3. Core Audit Entity: `automation_logs`

Setiap panggilan API AI dan penghantaran notifikasi (Telegram, Gmail, WhatsApp) WAJIB direkodkan di sini:

- `id`, `user_id` (foreignId constrained cascade)
- `channel` (enum: `'ai_claude'`, `'ai_openai'`, `'ai_custom'`, `'telegram'`, `'gmail'`, `'whatsapp'`)
- `command_name` (string): `'ai:standup'`, `'ai:risk'`, `'ai:capture'`, `'ai:subtasks'`, `'ai:improve_task'`, `'notify:daily_standup'`, `'notify:overdue_alert'`, `'notify:test_alert'`, `'notify:manual'`
- `recipient` (string, nullable: email, chat_id, nombor telefon, atau nama model AI)
- `message` (text: prompt atau kandungan mesej yang dihantar; dipotong pada 60 000 aksara)
- `response_payload` (longText, nullable: jawapan AI atau respons API gateway)
- `tokens_used` (integer, default: 0)
- `status` (enum: `'sent'`, `'simulated'`, `'failed'`)
- `error_message` (text, nullable), `timestamps`.
- Indeks kompaun: `['user_id', 'channel', 'status', 'created_at']`.
- Log audit tidak boleh disunting; hanya "Clear Audit Logs" (julat >30 hari / >7 hari / semua) dengan Confirmation Modal.

## 4. Business Rules & PMO SOPs

1. **PMO PIC History Preservation (SOP Kekal Sejarah):** Apabila seorang `TeamMember` (PIC) dipadam, kad `Tasks` milik mereka DILARANG dipadam. `team_member_id` diset `NULL`, manakala `pic_name_snapshot` mengekalkan nama asal PIC sehingga tugasan ditugaskan semula. Sama untuk `project_id` / `project_name_snapshot`. Menamakan semula PIC/projek mengemas kini snapshot pada semua kadnya.
2. **WIP Limit Enforcement:** Setiap `BoardColumn` mempunyai `wip_limit` (0 = tiada had). Jika bilangan kad melebihi `wip_limit`, lajur memaparkan amaran visual merah (`Over WIP Limit`).
3. **Auto-Done Priority Sync:** Kad yang masuk ke lajur `is_done_column = true` → `priority = 'done'` dan `completed_at = now()`; kad yang keluar → `priority = 'medium'` dan `completed_at = null`. Kad dalam lajur Done sentiasa `done`.
4. **Simulation-First Integration Policy:** Selagi kunci luaran (`PMO_AI_API_KEY`, `PMO_TELEGRAM_BOT_TOKEN`, `PMO_WHATSAPP_GATEWAY_URL`, atau tetapan `MAIL_*`) kosong atau saluran dinyahaktifkan dalam `.env`, transaksi direkod sebagai `'simulated'` dan hasil simulasi pintar (berdasarkan data papan sebenar) dipulangkan tanpa ralat. Panggilan sebenar yang gagal → `'failed'` + jawapan simulasi sebagai sandaran.
5. **Drag-and-Drop Positioning:** `sort_order` dalam gandaan 10. Kad yang disembunyikan oleh penapis kekal di slot asalnya semasa kad lain disusun semula. Drag-and-drop hanya aktif dalam sort "Manual Drag".
6. **Minimum Board:** Papan sentiasa mempunyai sekurang-kurangnya 1 lajur; pengguna baharu menerima 5 lajur standard secara automatik. Memadam lajur yang berisi kad memerlukan lajur destinasi (kad tidak pernah dipadam).
7. **Data Replacement Safety:** Import JSON, Restore Snapshot dan Reset to Sample Data menggantikan seluruh papan, dan SENTIASA mencipta snapshot keselamatan automatik terlebih dahulu. Templat AI, log automasi dan snapshot tidak disentuh.
8. **Secrets Policy:** Kunci API dan token tidak pernah dieksport (JSON/CSV) atau ditulis ke log.

## 5. Jadual Automasi Konsol (`routes/console.php` & `config/pmo.php`)

- `pmo:send-daily-standup` -> `dailyAt(config('pmo.standup_time', '08:30'))` (`.env`: `PMO_STANDUP_TIME=08:30`)
- `pmo:check-overdue-tasks` -> `dailyAt(config('pmo.overdue_check_time', '09:00'))` (`.env`: `PMO_OVERDUE_CHECK_TIME=09:00`)
- Zon Masa: `Asia/Kuala_Lumpur` (`PMO_TIMEZONE`).
- Pilihan command: `--user=ID`, `--channel=telegram,email,whatsapp`, `--pmo-only` (overdue). Setiap tenant diproses berasingan (`RunsPerTenant`).
- Memerlukan `php artisan schedule:work` (lokal) atau cron `php artisan schedule:run` setiap minit (server).

## 6. Application Surfaces (Routes)

| Route | Nama | Komponen / View | Akses |
|---|---|---|---|
| `/` | `home` | `landing.blade.php` (landing page Neo; `welcome.blade.php` disimpan tetapi tidak dirouting) | Awam |
| `/dashboard` | `dashboard` | `Livewire\Pmo\Dashboard` | auth + verified |
| `/pmo/board` | `pmo.board` | `Livewire\Pmo\KanbanBoard` (+ `AnalyticsWidgets`, `StandupModal` di dalamnya); `?view=all|my|due|completed` | auth + verified |
| `/pmo/roster` | `pmo.roster` | `Livewire\Pmo\RosterProjectIndex` | auth + verified |
| `/pmo/hub` | `pmo.hub` | `Livewire\Pmo\AiOmnichannelHub` | auth + verified |
| `/settings/profile`, `/settings/security`, `/settings/appearance` | `profile.edit`, `security.edit`, `appearance.edit` | Starter Kit | auth |
| `/settings/data` | `data.edit` | `Livewire\Pmo\SettingsPortabilityModal` (halaman "Data & Backup") | auth + verified |
| `/login`, `/register`, … | Fortify | `livewire/auth/*` dalam layout `auth/simple` (split-screen Neo, saiz tetap tanpa scroll) | Tetamu |

Sidebar: Dashboard, Kanban Board, My Tasks, Overdue / Due, Completed, AI & Omnichannel Hub, Projects & Roster. Data & Backup berada di Settings (tab ke-4).

## 7. Service Layer Map

| Service | Tanggungjawab |
|---|---|
| `Pmo\ProjectService`, `Pmo\TeamRosterService` | CRUD projek & PIC, Set as Me, SOP snapshot nama |
| `Pmo\BoardColumnService` | CRUD lajur, `updateColumnOrder`, padam dengan pemindahan kad |
| `Pmo\TaskService` (+ `TaskFilters`) | CRUD tugas & subtask, penapis/views/sort, `syncCardMove`; projek/PIC hanya melalui ID roster (tiada auto-daftar) |
| `Pmo\WorkloadCalculator` | Kiraan open/done/overdue per PIC & projek (satu query berkumpulan) |
| `Pmo\ActivityLogger` | Log aktiviti automatik + nota standup + Clear Activity Logs |
| `Pmo\PmoAnalyticsService`, `Pmo\StandupGeneratorService` | 5 widget analitik, laporan standup (format chat / plain) |
| `Pmo\AiPmoService` (+ `Ai\AiProviderGateway`) | 5 tindakan AI, Smart Simulation, Copy Prompt / Paste JSON |
| `Pmo\OmnichannelNotifierService`, `Pmo\AutomationLogger` | Telegram / Gmail / WhatsApp, status saluran, log audit |
| `Pmo\SavedPromptService` | CRUD templat prompt AI |
| `PmoPortabilityService` | Eksport/import JSON (serasi `docs/reference/Kanban_PMO_Neo.html`), CSV, snapshot, restore, reset |

Peristiwa Livewire: `pmo-board-changed` (analitik dikira semula), `pmo-workspace-replaced` (halaman lain dimuat semula selepas import/restore/reset), `open-standup`, `pmo-apply-theme`.

## 8. Quality Gates

Ujian ciri bagi setiap modul (`tests/Feature/Pmo/*`), Pint, PHPStan (0 ralat, tanpa ignore/baseline), `migrate` + `db:seed` idempotent, dan `npm run build` — lihat `CLAUDE.md` §8.
