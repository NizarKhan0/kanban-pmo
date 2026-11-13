# 🏛️ Kanban PMO — Master Architecture & System Blueprint

Dokumen ini adalah rujukan tunggal (Single Source of Truth) bagi seni bina teknikal, skema data, dan polisi kejuruteraan sistem Kanban PMO.

## 1. Technical Overview

- **Objektif Sistem:** Sistem pengurusan projek berpusat (PMO Command Center) berasaskan papan Kanban interaktif untuk memantau aliran kerja SDLC pelbagai projek, beban kerja pasukan (PIC), analitik penyelesaian, laporan standup harian, pembantu AI bersepadu, dan hab notifikasi pelbagai saluran (Omnichannel: Telegram, Gmail, WhatsApp).
- **Tech Stack Matrix:**
    - Framework: Laravel 13 (PHP 8.3+ Strict Types)
    - Frontend: Pure Livewire v4 + Alpine.js (Drag-and-Drop & Optimistic UI)
    - Styling: Tailwind CSS v4 (`@import "tailwindcss";`) dengan sokongan tema Neo Dark/Light
    - Database: MySQL 8.0+ (InnoDB, `utf8mb4_unicode_ci`)
- **Multi-Tenancy:** Single Database Tenant Boundary (Semua rekod utama terikat kepada `user_id`).
- **Integrations (Simulation-First Ready):**
    1. **AI Engine:** Anthropic Claude API, OpenAI API, Custom LLM Endpoint.
    2. **Telegram:** Telegram Bot API (Synchronous HTTP).
    3. **Email / Gmail:** Laravel Mail (Gmail SMTP) untuk peringatan PIC & laporan PMO.
    4. **WhatsApp:** Direct `wa.me` Deep-Link Formatter & Optional Webhook Gateway API.

## 2. Entity Relationship Diagram (ASCII ERD)

[ User ]
├── hasMany ──► [ Projects ] (id, user_id, name, code, icon, color, status, sort_order)
├── hasMany ──► [ TeamMembers ] (id, user_id, name, role, email, phone_whatsapp, icon, color, is_me, sort_order)
├── hasMany ──► [ BoardColumns ] (id, user_id, name, icon, color, wip_limit, is_done_column, sort_order)
│ └── hasMany ──► [ Tasks ] ── hasMany ──► [ Subtasks ]
│ ├── belongsTo (nullable, nullOnDelete) ──► [ Projects ]
│ └── belongsTo (nullable, nullOnDelete) ──► [ TeamMembers (PIC) ]
├── hasMany ──► [ ActivityLogs ] (id, user_id, actor_name, action_type, task_title, meta_details)
├── hasMany ──► [ SavedAiPrompts ] (id, user_id, title, action_type, custom_instruction, icon, color)
├── hasMany ──► [ AutomationLogs ] (id, user_id, channel, command_name, recipient, status, response_payload)
└── hasMany ──► [ WorkspaceBackups ] (id, user_id, filename, file_size_kb, payload_json)

## 3. Core Audit Entity: `automation_logs`

Setiap panggilan API AI dan penghantaran notifikasi (Telegram, Gmail, WhatsApp) WAJIB direkodkan di sini:

- `id`, `user_id` (foreignId constrained cascade)
- `channel` (enum: `'ai_claude'`, `'ai_openai'`, `'ai_custom'`, `'telegram'`, `'gmail'`, `'whatsapp'`)
- `command_name` (string: cth `'ai:standup'`, `'ai:risk'`, `'ai:capture'`, `'notify:overdue_alert'`, `'notify:daily_standup'`)
- `recipient` (string, nullable: email, chat_id, nombor telefon, atau nama model AI)
- `message` (text: prompt atau kandungan mesej yang dihantar)
- `response_payload` (longText, nullable: jawapan AI atau respons API gateway)
- `tokens_used` (integer, default: 0)
- `status` (enum: `'sent'`, `'simulated'`, `'failed'`)
- `error_message` (text, nullable), `timestamps`.
- Indeks kompaun: `['user_id', 'channel', 'status', 'created_at']`.

## 4. Business Rules & PMO SOPs

1. **PMO PIC History Preservation (SOP Kekal Sejarah):** Apabila seorang `TeamMember` (PIC) dipadam daripada senarai pasukan, kad `Tasks` milik mereka DILARANG dipadam. Lajur `team_member_id` diset kepada `NULL`, manakala lajur `pic_name_snapshot` pada jadual `tasks` mengekalkan nama asal PIC tersebut sehingga tugasan ditugaskan semula. Begitu juga untuk `project_id` dan `project_name_snapshot`.
2. **WIP Limit Enforcement:** Setiap `BoardColumn` mempunyai `wip_limit` (0 = tiada had). Jika bilangan kad melebihi `wip_limit`, lajur memaparkan amaran visual merah (`Over WIP Limit`).
3. **Auto-Done Priority Sync:** Apabila kad ditarik masuk ke dalam lajur `is_done_column = true`, keutamaan (`priority`) kad ditukar kepada `'done'` secara automatik, dan dikembalikan kepada `'medium'` jika ditarik keluar ke lajur kerja lain.
4. **Simulation-First Integration Policy:** Selagi kunci API luaran (`PMO_AI_API_KEY`, `PMO_TELEGRAM_BOT_TOKEN`, `PMO_WHATSAPP_GATEWAY_URL`, atau `MAIL_PASSWORD`) kosong atau ditetapkan kepada mod simulasi dalam `.env`, sistem wajib merekodkan transaksi sebagai `'simulated'` di dalam `automation_logs` dan memulangkan hasil simulasi pintar tanpa melontarkan sebarang ralat.

## 5. Jadual Automasi Konsol (`routes/console.php` & `config/pmo.php`)

- `pmo:send-daily-standup` -> `dailyAt(config('pmo.standup_time', '08:30'))` (`.env`: `PMO_STANDUP_TIME=08:30`)
- `pmo:check-overdue-tasks` -> `dailyAt(config('pmo.overdue_check_time', '09:00'))` (`.env`: `PMO_OVERDUE_CHECK_TIME=09:00`)
- Zon Masa: `Asia/Kuala_Lumpur`.
