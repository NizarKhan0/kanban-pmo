# Modul 03: Tasks, Subtasks & Advanced Filtering

Rujuk `CLAUDE.md` dan `docs/ARCHITECTURE.md`. Bina **Modul 03: Tasks, Subtasks & Advanced Filtering** dengan mematuhi 100% English Web UI, Strict Multi-Tenancy, dan Mandatori Full CRUD:

### 1. Data Layer (Migrations & Schema — Lengkap Medan UX)

- `tasks`:
    - `id`, `user_id` (foreignId constrained cascade), `board_column_id` (foreignId constrained cascade), `project_id` (foreignId nullable constrained nullOnDelete), `team_member_id` (foreignId nullable constrained nullOnDelete)
    - `title` (string), `project_name_snapshot` (string, nullable), `pic_name_snapshot` (string, nullable)
    - `priority` (enum: 'high', 'medium', 'low', 'done', default: 'medium')
    - `icon` (string 10, default: '📌'), `color` (string 20, default: 'violet')
    - `due_date` (date, nullable), `tags` (json, nullable), `notes` (text, nullable)
    - `sort_order` (integer, default: 0), `completed_at` (dateTime, nullable), `timestamps`.
    - Indeks kompaun: `['user_id', 'board_column_id', 'sort_order']` dan `['user_id', 'priority', 'due_date']`.
- `subtasks`:
    - `id`, `task_id` (foreignId constrained cascade), `title` (string), `is_completed` (boolean, default: false), `sort_order` (integer, default: 0), `timestamps`.
    - Indeks kompaun: `['task_id', 'is_completed', 'sort_order']`.

### 2. Logic Layer (`Task.php`, `Subtask.php` & Drag-and-Drop Sync)

- **Strict Data Isolation:** Semua query wajib menapis `where('user_id', Auth::id())`.
- **Business Logic & Helpers:**
    - `progressPercentage(): int`: Kira peratusan `subtasks` yang `is_completed = true`.
    - `isOverdue(): bool`: Pulangkan `true` jika `due_date < today()` dan `priority !== 'done'` serta lajur semasa bukan `is_done_column`.
    - `syncCardMove(int $taskId, int $targetColumnId, array $orderedTaskIds)`:
        - Pindahkan kad ke lajur sasaran dan kemas kini `sort_order` kad-kad dalam lajur tersebut.
        - Kad yang sedang disembunyikan oleh penapis (filter) mesti kekal di kedudukan asalnya.
        - Jika lajur sasaran ialah `is_done_column = true`, tukar `priority = 'done'` dan set `completed_at = now()`. Jika ditarik keluar dari lajur Done, tukar `priority = 'medium'` dan `completed_at = null`.
        - Auto-daftar `project_name_snapshot` atau `pic_name_snapshot` jika pengguna menaip nama baharu.

### 3. Presentation Layer (Integrasi dalam `KanbanBoard.php` & Task Modal)

- **UI Language:** 100% Professional English ("Add Task", "My Tasks", "Overdue / Due", "Reset Filters", "Subtasks Progress", "Blocker / Notes").
- **Bar Penapisan & Paparan Pantas (Top Filter Bar & Sidebar Views):**
    - Sidebar Views: `Kanban Board` (All), `My Tasks` (ditapis mengikut PIC `is_me`), `Overdue / Due`, dan `Completed`.
    - Penapis Reaktif (`wire:model.live`): Carian kata kunci (`Ctrl+K`), Project, Assignee (PIC), Priority, Due Date (`Anytime`, `Overdue Only`, `Due Today`, `Next 7 Days`, `Next 30 Days`, `No Due Date`), dan Sort (`Manual Drag`, `Due Soonest`, `Priority`, `Newest`, `A -> Z`, `% Done`).
- **MANDATORY Full CRUD (Tasks & Sub-Entity Subtasks):**
    1. **Create:** Butang "+ Add Task" pada bar atas atau di bawah setiap lajur membuka Task Modal lengkap dengan input Subtasks dinamik.
    2. **Read:** Kad Kanban memaparkan tajuk, bulatan singkatan PIC (`initials`), nama projek, progress bar subtasks (`2/5`), lencana tag, pill keutamaan berwarna, dan tarikh akhir (merah jika lewat).
    3. **Update:** Klik kad untuk buka Edit Task Modal tanpa reload, toggle selesai Subtask secara terus, atau tarik kad merentas lajur (Drag-and-Drop).
    4. **Delete:** Butang Delete Task dan Delete Subtask berserta **Confirmation Modal** keselamatan.

### 4. Verification Layer (Database Seeder)

- `TaskSeeder.php`: Jana 14 kad tugasan realistik merentasi 5 lajur (seperti _Prepare SRS document for application module_, _FPX payment integration_, _Monthly report module UAT_) lengkap dengan subtasks dan tags untuk `user_id = 1`.

### 5. Nota Pelaksanaan (dikemas kini selepas pembinaan)

- `subtasks` tidak mempunyai `user_id` (mengikut skema di atas) — semua query ditapis melalui `task.user_id` (lihat `CLAUDE.md` §5).
- Task Modal: **Project** dan **Assignee (PIC)** ialah dropdown daripada Modul 01 (tiada teks bebas); ID disahkan milik pengguna semasa. Kad yang PIC/projeknya telah dipadam memaparkan nama lama ("Former PIC") dan snapshot itu kekal sehingga pengguna memilih yang baharu.
- Tiada "auto-daftar": projek dan PIC tidak pernah dicipta secara automatik daripada teks. Ia mesti didaftarkan di Projects & Roster (Modul 01).
- Drag-and-drop kad hanya aktif dalam sort "Manual Drag" (sort lain akan menimpa susunan manual).
- View "Overdue / Due" = tugasan terbuka yang lewat atau tamat tempoh dalam 7 hari.
- Lajur Done sentiasa memaksa `priority = 'done'`; `due_date` sentiasa disimpan sebagai `Y-m-d`.
- Delete Subtask: baris baharu yang belum disimpan dibuang terus; subtask tersimpan melalui Confirmation Modal (bertindih di atas Task Modal).
