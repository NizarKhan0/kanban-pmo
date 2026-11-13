# Modul 02: Kanban Board & Columns Engine

Rujuk `AGENT.md` dan `docs/ARCHITECTURE.md`. Bina **Modul 02: Kanban Board & Columns Engine** dengan mematuhi 100% English Web UI, Strict Multi-Tenancy, dan Mandatori Full CRUD:

### 1. Data Layer (Migrations & Schema — Lengkap Medan UX)

- `board_columns`:
    - `id`, `user_id` (foreignId constrained cascade), `name` (string, cth: 'In Progress'), `icon` (string 10, default: '📋'), `color` (string 20, default: '#38bdf8'), `wip_limit` (integer, default: 0), `is_done_column` (boolean, default: false), `sort_order` (integer, default: 0), `timestamps`.
    - Indeks kompaun: `['user_id', 'sort_order']`.

### 2. Logic Layer (`BoardColumn.php` & Positioning Service)

- **Strict Data Isolation:** Semua query wajib menapis `where('user_id', Auth::id())`.
- **WIP Limit Logic:**
    - `isOverWip(): bool`: Pulangkan `true` jika `wip_limit > 0` dan bilangan kad dalam lajur `> wip_limit`.
- **Column Reordering (`updateColumnOrder(array $orderedIds)`):**
    - Kemas kini `sort_order` (gandaan 10: 10, 20, 30...) bagi setiap lajur milik `user_id` semasa secara reaktif.

### 3. Presentation Layer (`app/Livewire/Pmo/KanbanBoard.php`)

- **UI Language:** 100% Professional English ("Add Column", "WIP Limit", "Over WIP Limit", "Edit Column", "Delete Column").
- **MANDATORY Full CRUD pada Board Columns:**
    1. **Create:** Butang "+ Add Column" membuka modal cipta lajur baharu (nama, pilihan warna hex/badge, had WIP, dan toggle `Is Done Column`).
    2. **Read:** Paparan papan Kanban mendatar dengan kepala lajur berwarna, lencana jumlah kad, penunjuk WIP (`WIP 3/7`), dan sorotan merah jika melebihi had WIP.
    3. **Update:** Menu tiga titik (`⋮`) pada kepala lajur membuka Edit Column Modal tanpa reload, serta sokongan Drag-and-Drop menggunakan Alpine.js untuk menyusun semula turutan lajur.
    4. **Delete:** Butang Delete Column berserta **Confirmation Modal** keselamatan (pastikan sekurang-kurangnya 1 lajur lalai kekal wujud).

### 4. Verification Layer (Database Seeder)

- `BoardColumnSeeder.php`: Jana 5 lajur standard PMO untuk `user_id = 1`:
    1. `Backlog` (`#a78bfa`, WIP: 0)
    2. `To Do` (`#60a5fa`, WIP: 0)
    3. `In Progress` (`#38bdf8`, WIP: 7)
    4. `Review` (`#c084fc`, WIP: 0)
    5. `Done` (`#34d399`, WIP: 0, `is_done_column = true`)
