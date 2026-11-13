# Modul 01: Projects & Team Roster (PIC Management)

Rujuk `CLAUDE.md` dan `docs/ARCHITECTURE.md`. Bina **Modul 01: Projects & Team Roster** dengan mematuhi 100% English Web UI, Strict Multi-Tenancy, dan Mandatori Full CRUD:

### 1. Data Layer (Migrations & Schema — Lengkap Medan UX)

- `projects`:
    - `id`, `user_id` (foreignId constrained cascade), `name` (string, cth: 'eMyHaji 2.0'), `code` (string 20, nullable), `description` (text, nullable), `icon` (string 10, default: '📁'), `color` (string 20, default: 'violet'), `status` (enum: 'active', 'on_hold', 'completed', 'archived', default: 'active'), `sort_order` (integer, default: 0), `timestamps`.
    - Indeks kompaun: `['user_id', 'status', 'sort_order']`.
- `team_members`:
    - `id`, `user_id` (foreignId constrained cascade), `name` (string, cth: 'Ikhwan A.'), `role` (string, nullable, cth: 'PMO Officer'), `email` (string, nullable), `phone_whatsapp` (string 30, nullable), `icon` (string 10, default: '👤'), `color` (string 20, default: 'pink'), `is_me` (boolean, default: false), `is_active` (boolean, default: true), `sort_order` (integer, default: 0), `timestamps`.
    - Indeks kompaun: `['user_id', 'is_active', 'sort_order']`.

### 2. Logic Layer (Models & PMO History Preservation SOP)

- **Strict Data Isolation:** Semua query wajib menapis `where('user_id', Auth::id())`.
- **Helper Methods (`TeamMember.php`):**
    - `initials(): string`: Jana 2 huruf singkatan nama (cth: "Ikhwan A." -> "IA").
    - `workloadStats(): array`: Pulangkan kiraan `['open' => int, 'done' => int, 'overdue' => int]` berdasarkan tugasan berkaitan.
- **SOP Rename & Delete PIC:**
    - Apabila nama `TeamMember` dikemas kini, kemas kini juga `pic_name_snapshot` pada semua rekod `tasks` miliknya.
    - Apabila `TeamMember` dipadam, pastikan semua `tasks` miliknya TIDAK terpadam (`nullOnDelete` pada `team_member_id`, manakala `pic_name_snapshot` kekal menyimpan nama tersebut).
    - Fungsi `setAsMe()`: Menetapkan `is_me = true` pada ahli yang dipilih dan `false` pada ahli lain bagi `user_id` tersebut.

### 3. Presentation Layer (`app/Livewire/Pmo/RosterProjectIndex.php`)

- **UI Language:** 100% Professional English ("Team Roster & PICs", "Project Portfolio", "Set as Me", "Active Workload", "Confirm Removal").
- **MANDATORY Full CRUD (Projects & Team Members):**
    1. **Create:** Modal/Borang reaktif tambah Project baharu (dengan icon & warna) dan tambah Team Member (PIC) baharu (lengkap nama, peranan, emel Gmail, dan nombor WhatsApp).
    2. **Read:** Kad metrik KPI (Total Projects, Active PICs, Overloaded PICs) & senarai PIC lengkap dengan lencana beban kerja (`Active`, `Completed`, `Overdue`) dan penanda lencana hijau `Me`.
    3. **Update:** Butang Edit pada setiap Project & Team Member tanpa reload, serta butang satu-klik "Set as Me".
    4. **Delete:** Butang Delete berserta **Confirmation Modal** yang memaparkan amaran berapa jumlah tugasan aktif PIC tersebut (dengan jaminan bahawa kad tugasan tidak akan dipadam).

### 4. Verification Layer (Database Seeder)

- `PmoRosterProjectSeeder.php`: Jana 6 projek contoh (`eMyHaji 2.0`, `MyResearch`, `ePJS`, `MyWarga`, `SPK-KuliM`, `eBantuanJKM 2.0`) dan 4 ahli pasukan (`Ikhwan A. - PMO Officer [is_me=true]`, `Afqah N. - Developer`, `Haziq M. - QA / Tester`, `Farah L. - Business Analyst`) untuk `user_id = 1`.

### 5. Nota Pelaksanaan (dikemas kini selepas pembinaan)

- Halaman: `/pmo/roster` (`pmo.roster`). Komponen: `RosterProjectIndex`; service: `ProjectService`, `TeamRosterService`, `WorkloadCalculator`.
- "Overloaded PIC" = PIC aktif dengan tugasan terbuka ≥ `PMO_PIC_OVERLOAD_THRESHOLD` (lalai 5).
- `workloadStats()` dan kemas kini `pic_name_snapshot` bergantung pada jadual `tasks` (Modul 03). Guard `App\Support\TaskTable` memastikan ciri ini berfungsi secara automatik apabila jadual itu wujud.
- SOP Kekal Sejarah turut digunakan pada projek (`project_name_snapshot`).
- Kod projek ditukar ke huruf besar dan unik bagi setiap pengguna.
- Seeder menyokong `->forOwner($userId)` (digunakan oleh Reset Workspace, Modul 06).
