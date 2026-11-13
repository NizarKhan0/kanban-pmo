# Modul 05: AI PMO Assistant & Omnichannel Automation Hub (Telegram, Gmail, WhatsApp)

Rujuk `AGENT.md` dan `docs/ARCHITECTURE.md`. Bina **Modul 05: AI PMO Assistant & Omnichannel Automation Hub** dengan mematuhi 100% English Web UI, Strict Multi-Tenancy, Environment-Driven Config, dan Smart Simulation:

### 1. Data Layer (Migrations & Schema — Lengkap Medan UX)

- `automation_logs`:
    - `id`, `user_id` (foreignId constrained cascade)
    - `channel` (enum: 'ai_claude', 'ai_openai', 'ai_custom', 'telegram', 'gmail', 'whatsapp')
    - `command_name` (string), `recipient` (string, nullable), `message` (text), `response_payload` (longText, nullable), `tokens_used` (integer, default: 0)
    - `status` (enum: 'sent', 'simulated', 'failed', default: 'simulated')
    - `error_message` (text, nullable), `timestamps`.
    - Indeks kompaun: `['user_id', 'channel', 'status', 'created_at']`.
- `saved_ai_prompts`:
    - `id`, `user_id` (foreignId constrained cascade), `title` (string), `action_type` (enum: 'standup', 'risk', 'capture', 'subtasks', 'improve_task'), `custom_instruction` (text), `icon` (string 10, default: '✨'), `color` (string 20, default: 'pink'), `sort_order` (integer, default: 0), `timestamps`.

### 2. Logic Layer (Services, Artisan Commands & `config/pmo.php`)

- **Fail Konfigurasi `config/pmo.php` (Dibaca daripada `.env`):**
    - AI: `PMO_AI_PROVIDER` ('claude'), `PMO_AI_MODEL` ('claude-sonnet-4-20250514'), `PMO_AI_API_KEY`, `PMO_AI_CUSTOM_ENDPOINT`.
    - Telegram: `PMO_TELEGRAM_ENABLED` (false), `PMO_TELEGRAM_BOT_TOKEN`, `PMO_TELEGRAM_CHAT_ID`.
    - Gmail / Email: `PMO_EMAIL_ENABLED` (false), `PMO_EMAIL_RECIPIENT` (emel ketua/PMO), menggunakan konfigurasi `MAIL_*` Laravel.
    - WhatsApp: `PMO_WHATSAPP_ENABLED` (false), `PMO_WHATSAPP_GATEWAY_URL`, `PMO_WHATSAPP_API_TOKEN`, `PMO_WHATSAPP_DEFAULT_PHONE`.
- **Service 1: `AiPmoService.php` (5 Fungsi AI + Simulation Fallback):**
    1. `generateStandupSummary()`: Jana ringkasan pencapaian, kerja semasa, blocker/lewat, dan 3 fokus utama hari ini.
    2. `analyzeRisksAndBlockers()`: Analisis kad lewat, pelanggaran WIP, ketidakseimbangan beban PIC, dan 3 cadangan pembetulan.
    3. `captureTasksFromNotes(string $rawNotes)`: Ekstrak nota mesyuarat/emel kepada tatasusunan JSON (`title`, `project`, `pic`, `priority`, `due_date`, `notes`) untuk pratonton senarai semak.
    4. `breakIntoSubtasks(Task $task)`: Pecahkan tugasan kepada 3-7 item jadual `subtasks`.
    5. `improveTaskDefinition(Task $task)`: Perbaiki tajuk, keutamaan, dan tarikh akhir kad.
    - _Smart Simulation:_ Jika `PMO_AI_API_KEY` kosong dalam `.env`, jangan lontarkan ralat! Jana respons simulasi pintar berdasarkan data sebenar papan semasa dan rekod status `'simulated'` dalam `automation_logs`. Sediakan juga butang "Copy Prompt" & "Paste JSON Answer" manual.
- **Service 2: `OmnichannelNotifierService.php` (Telegram, Gmail, WhatsApp):**
    - `sendTelegram(string $message, ?string $chatId = null)`: Hantar via HTTP ke Telegram Bot API. Jika token kosong atau `PMO_TELEGRAM_ENABLED=false`, log sebagai `'simulated'` dalam `automation_logs`.
    - `sendEmail(string $subject, string $body, ?string $toEmail = null)`: Hantar via Laravel Mail (Gmail SMTP). Jika `PMO_EMAIL_ENABLED=false` atau tiada konfigurasi SMTP, log sebagai `'simulated'` dalam `automation_logs`.
    - `sendWhatsApp(string $message, ?string $phone = null)`: Jika `PMO_WHATSAPP_GATEWAY_URL` wujud dan aktif, hantar POST ke webhook gateway. Jika tidak, log sebagai `'simulated'` dalam `automation_logs` dan sediakan pautan pantas `https://wa.me/{phone}?text={urlEncodedMessage}` untuk dibuka terus di pelayar.
- **Artisan Commands (`routes/console.php`):**
    - `pmo:send-daily-standup`: Jana laporan standup dan hantar ke saluran yang aktif (atau simulasi) mengikut masa `PMO_STANDUP_TIME`.
    - `pmo:check-overdue-tasks`: Kenal pasti kad yang telah lewat (`due_date < today()`) dan hantar peringatan kepada PMO / PIC melalui saluran pilihan.

### 3. Presentation Layer (`app/Livewire/Pmo/AiOmnichannelHub.php`)

- **UI Language:** 100% Professional English ("AI & Omnichannel Hub", "Channel Status", "Simulation Mode", "Send Test Alert", "Share via WhatsApp", "Dispatch to Telegram", "Email Standup Report", "Execution Audit Logs").
- **MANDATORY Full CRUD & Omnichannel Controls:**
    1. **Create:** Cipta templat `SavedAiPrompt` baharu, tangkap tugasan daripada nota mesyuarat/emel untuk ditambah ke papan Kanban, atau cetuskan penghantaran laporan segera (_Manual Trigger_) ke Telegram, Gmail, dan WhatsApp.
    2. **Read:**
        - **Kad Status 4 Saluran:** Memaparkan lencana status bagi _AI Engine_, _Telegram Bot_, _Gmail SMTP_, dan _WhatsApp Gateway_ (`Connected` dalam warna hijau atau `Simulation Mode (.env Not Set)` dalam warna amber).
        - **Jadual Audit `automation_logs`:** Senarai penuh sejarah automasi dengan penapis mengikut saluran (`All`, `AI`, `Telegram`, `Gmail`, `WhatsApp`) dan status (`Sent`, `Simulated`, `Failed`).
    3. **Update:** Kemas kini templat `SavedAiPrompt` melalui modal tanpa muat semula halaman (zero page reload).
    4. **Delete:** Padam templat `SavedAiPrompt` atau bersihkan rekod `automation_logs` ("Clear Audit Logs") berserta **Confirmation Modal** keselamatan.

### 4. Verification Layer (Database Seeder)

- `AiOmnichannelSeeder.php`: Jana 3 templat `saved_ai_prompts` dan 4 rekod contoh `automation_logs` (1 rekod bagi setiap saluran: `ai_claude`, `telegram`, `gmail`, dan `whatsapp` dengan status `'simulated'`) untuk `user_id = 1`.
