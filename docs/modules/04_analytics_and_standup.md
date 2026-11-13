# Modul 04: Analytics Widgets, Activity Logs & Standup Report

Rujuk `AGENT.md` dan `docs/ARCHITECTURE.md`. Bina **Modul 04: Analytics Widgets, Activity Logs & Standup Report** dengan mematuhi 100% English Web UI, Strict Multi-Tenancy, dan Mandatori Full CRUD:

### 1. Data Layer (Migrations & Schema)

- `activity_logs`:
    - `id`, `user_id` (foreignId constrained cascade), `actor_name` (string), `action_type` (string: 'created', 'updated', 'moved', 'deleted', 'col_added', 'pic_added'), `task_title` (string), `meta_details` (string, nullable), `timestamps`.
    - Indeks kompaun: `['user_id', 'created_at']`.

### 2. Logic Layer (`PmoAnalyticsService.php` & `StandupGeneratorService.php`)

- **Strict Data Isolation:** Semua pengiraan wajib menapis `where('user_id', Auth::id())`.
- **5 Widget Analitik Masa Nyata:**
    1. `Task Summary`: Jumlah keseluruhan tugasan & pecahan mengikut keutamaan (High, Medium, Low).
    2. `Completion Rate`: Peratusan siap untuk carta donat SVG (`done / total`).
    3. `Tasks by Priority`: Bilangan dan peratusan bagi setiap tahap keutamaan termasuk Done.
    4. `Recent Activity`: 10 log aktiviti terkini daripada `activity_logs` berserta kiraan masa relatif (`diffForHumans()`).
    5. `Team Workload`: Bar kemajuan mendatar memaparkan beban tugasan aktif setiap PIC.
- **Standup Report Formatter:**
    - Menjana teks laporan harian berformat bersih (sedia disalin ke WhatsApp/Telegram/Email) mengikut pecahan setiap lajur berserta penanda `[OVERDUE]`.

### 3. Presentation Layer (`app/Livewire/Pmo/AnalyticsWidgets.php` & `StandupModal.php`)

- **UI Language:** 100% Professional English ("Task Summary", "Completion Rate", "Tasks by Priority", "Recent Activity", "Team Workload", "Daily Standup Report", "Copy to Clipboard").
- **Full Control & CRUD Operations:**
    1. **Create / Log:** Setiap tindakan pada papan Kanban secara automatik mencipta rekod `ActivityLog` dan pengguna boleh menambah catatan tambahan dalam modal laporan standup.
    2. **Read:** 5 kad widget metrik di bahagian bawah papan Kanban & Modal Daily Standup Report.
    3. **Update:** Kemas kini metrik secara langsung (real-time reactive) setiap kali kad digerakkan atau ditapis.
    4. **Delete:** Butang "Clear Activity Logs" berserta **Confirmation Modal** keselamatan untuk mengosongkan sejarah aktiviti lama.

### 4. Verification Layer (Database Seeder)

- `ActivityLogSeeder.php`: Jana 5 rekod aktiviti permulaan yang realistik untuk `user_id = 1`.
