# Modul 06: Data Portability (JSON/CSV Backup) & Workspace Settings

Rujuk `CLAUDE.md` dan `docs/ARCHITECTURE.md`. Bina **Modul 06: Data Portability & Workspace Settings** dengan mematuhi 100% English Web UI, Strict Multi-Tenancy, dan Mandatori Full CRUD:

### 1. Data Layer (Migrations & Schema)

- `workspace_backups`:
    - `id`, `user_id` (foreignId constrained cascade), `filename` (string), `file_size_kb` (decimal 8,2), `columns_count` (integer), `tasks_count` (integer), `payload_json` (longText), `notes` (string, nullable), `timestamps`.
    - Indeks kompaun: `['user_id', 'created_at']`.

### 2. Logic Layer (`app/Services/PmoPortabilityService.php`)

- **Strict Data Isolation:** Eksport, import, dan sandaran hanya melibatkan data milik `Auth::id()`.
- **Full Compatibility dengan Format JSON Asal (`Kanban_PMO_Neo.html`):**
    - `exportToJson()`: Mengeksport keseluruhan papan (`people`, `columns` berserta `cards`, `activity`) tanpa menyertakan sebarang kunci API.
    - `importFromJson(array $jsonPayload)`: Menyokong struktur JSON daripada fail HTML asal mahupun versi Laravel. Melakukan normalisasi data, memetakan semula lajur, projek, PIC, dan kad ke dalam MySQL secara transaksi selamat (`DB::transaction`).
    - `exportToCsv()`: Muat turun fail CSV dengan pengekodan UTF-8 BOM (`Column, Title, Project, Assignee, Priority, Due Date, Subtasks Done, Subtasks Total, Tags, Notes`).

### 3. Presentation Layer (`app/Livewire/Pmo/SettingsPortabilityModal.php`)

- **UI Language:** 100% Professional English ("Settings & Data Portability", "Create Snapshot Backup", "Export JSON", "Import JSON", "Export CSV", "Restore Snapshot", "Reset Workspace to Sample Data").
- **MANDATORY Full CRUD pada Workspace Snapshots & Settings:**
    1. **Create:** Cipta titik sandaran dalaman (`WorkspaceBackup` snapshot) dengan satu klik atau muat naik fail `.json` luaran.
    2. **Read:** Senarai sejarah titik sandaran dalaman lengkap dengan saiz data (KB), jumlah lajur, dan jumlah kad.
    3. **Update / Restore:** Pulihkan (Restore) papan kepada mana-mana snapshot terdahulu, tukar tema Dark/Light, atau kemas kini nota label snapshot tanpa muat semula halaman.
    4. **Delete:** Padam snapshot sandaran atau jalankan "Reset All Data to Default Sample" berserta **Confirmation Modal** keselamatan berlapis.

### 4. Verification Layer (Database Seeder)

- `WorkspaceBackupSeeder.php`: Jana 1 rekod snapshot permulaan dalam `workspace_backups` untuk `user_id = 1` dan daftar semua Seeder Modul 01 hingga 06 ke dalam `DatabaseSeeder.php`.

### 5. Nota Pelaksanaan (dikemas kini selepas pembinaan)

- Lokasi: tab **Settings › Data & Backup** (`/settings/data`, `data.edit`) — bukan modal global. Kelas `SettingsPortabilityModal` dikekalkan namanya. Tema Dark/Light berada di Settings › Appearance.
- Fail rujukan format asal: `docs/reference/Kanban_PMO_Neo.html` (JSON: `v, me, people[], columns[{cards[]}], activity[]`; tiada entiti projek; lajur Done dikesan melalui nama `done|siap|selesai|complete`; subtask hanya kiraan `subD/subT`).
- Eksport ialah superset serasi (HTML asal boleh mengimportnya). Subtask daripada HTML asal dicipta sebagai "Checklist item 1..N".
- Import / Restore / Reset menggantikan seluruh papan dan SENTIASA mencipta snapshot keselamatan dahulu. Reset memerlukan 2 langkah (tanda kotak + taip `RESET`).
- CSV: UTF-8 BOM; sel bermula `= + - @` diberi awalan `'` (perlindungan formula injection).
