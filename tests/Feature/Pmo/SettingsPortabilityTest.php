<?php

declare(strict_types=1);

namespace Tests\Feature\Pmo;

use App\Exceptions\Pmo\PortabilityException;
use App\Livewire\Pmo\KanbanBoard;
use App\Livewire\Pmo\SettingsPortabilityModal;
use App\Models\ActivityLog;
use App\Models\AutomationLog;
use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\SavedAiPrompt;
use App\Models\Task;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\WorkspaceBackup;
use App\Services\Pmo\TaskService;
use App\Services\PmoPortabilityService;
use App\Support\TaskTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsPortabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        TaskTable::flush();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        Livewire::test(KanbanBoard::class); // default 5 columns
    }

    public function test_data_and_backup_lives_in_settings(): void
    {
        $this->get(route('data.edit'))
            ->assertOk()
            ->assertSeeLivewire(SettingsPortabilityModal::class)
            ->assertSee('Data &amp; Backup', false)
            ->assertSee('Create Snapshot Backup')
            ->assertSee('Export JSON')
            ->assertSee('Import JSON')
            ->assertSee('Export CSV')
            ->assertSee('Reset Workspace to Sample Data')
            ->assertDontSee('System</button>', false); // theme lives on Settings › Appearance

        // Reachable from every settings tab; no longer a global sidebar modal.
        $this->get(route('profile.edit'))->assertOk()->assertSee(route('data.edit'), false);
        $this->get(route('pmo.board'))->assertOk()->assertDontSeeLivewire(SettingsPortabilityModal::class);
    }

    public function test_guests_cannot_open_data_and_backup(): void
    {
        auth()->logout();

        $this->get(route('data.edit'))->assertRedirect(route('login'));
    }

    // ── Original Kanban_PMO_Neo.html compatibility ───────────────────

    public function test_imports_the_original_html_format(): void
    {
        $stats = app(PmoPortabilityService::class)->importFromJson($this->originalHtmlPayload());

        $this->assertSame(['columns' => 3, 'tasks' => 4, 'people' => 3, 'projects' => 3, 'activity' => 2, 'theme' => 'light'], $stats);

        $columns = BoardColumn::query()->where('user_id', $this->user->id)->orderBy('sort_order')->get();
        $this->assertSame(['Backlog', 'Dalam Proses', 'Selesai'], $columns->pluck('name')->all());
        $this->assertSame([false, false, true], $columns->pluck('is_done_column')->all(), '"Selesai" is detected as a Done column like the original app');
        $this->assertSame(7, $columns[1]->wip_limit);
        $this->assertSame([10, 20, 30], $columns->pluck('sort_order')->all());

        // Projects did not exist as an entity in the HTML app — derived from card names.
        $this->assertSame(['eMyHaji 2.0', 'MyResearch', 'ePJS'], Project::query()->where('user_id', $this->user->id)->orderBy('sort_order')->pluck('name')->all());

        $me = TeamMember::query()->where('user_id', $this->user->id)->where('is_me', true)->sole();
        $this->assertSame('Ikhwan A.', $me->name);

        $fpx = Task::query()->where('user_id', $this->user->id)->where('title', 'Integrasi pembayaran FPX')->with('subtasks', 'teamMember', 'project')->sole();
        $this->assertSame('Afqah N.', $fpx->teamMember?->name);
        $this->assertSame('eMyHaji 2.0', $fpx->project?->name);
        $this->assertSame(['high', '2026-12-01'], [$fpx->priority, $fpx->due_date?->toDateString()]);
        $this->assertSame(['UAT', 'FPX'], $fpx->tags);
        $this->assertSame([1, 4], $fpx->subtaskCounts(), 'subD/subT counts become checklist items');

        // A PIC missing from the roster keeps the name (PIC History SOP).
        $orphan = Task::query()->where('title', 'Semakan kod')->sole();
        $this->assertNull($orphan->team_member_id);
        $this->assertSame('Bekas Staf', $orphan->pic_name_snapshot);

        // Cards in a Done column are done; activity verbs are mapped.
        $doneCard = Task::query()->where('title', 'Sesi latihan pengguna')->sole();
        $this->assertSame('done', $doneCard->priority);
        $this->assertNotNull($doneCard->completed_at);
        $this->assertSame(['created', 'pic_added'], ActivityLog::query()->where('user_id', $this->user->id)->orderBy('created_at')->pluck('action_type')->all());
    }

    public function test_original_format_without_people_derives_the_roster(): void
    {
        $payload = $this->originalHtmlPayload();
        unset($payload['people']);

        app(PmoPortabilityService::class)->importFromJson($payload);

        // Like the original app: "me" first, then PICs in card order.
        $this->assertSame(
            ['Ikhwan A.', 'Afqah N.', 'Bekas Staf', 'Haziq M.'],
            TeamMember::query()->where('user_id', $this->user->id)->orderBy('sort_order')->pluck('name')->all(),
        );
    }

    public function test_export_is_a_lossless_round_trip_and_has_no_api_keys(): void
    {
        config(['pmo.ai.api_key' => 'sk-ant-SECRET-KEY', 'pmo.telegram.bot_token' => 'BOT-SECRET']);
        $service = app(PmoPortabilityService::class);
        $service->importFromJson($this->originalHtmlPayload());

        $member = TeamMember::query()->where('user_id', $this->user->id)->where('name', 'Afqah N.')->sole();
        $member->update(['email' => 'afqah@example.com', 'phone_whatsapp' => '+60 12-345 6702']);
        Task::query()->where('title', 'Integrasi pembayaran FPX')->sole()->subtasks()->first()?->update(['title' => 'Register merchant']);

        $first = $service->exportToJson('light');
        $json = $service->exportJsonString();

        $this->assertStringNotContainsString('SECRET', $json, 'API keys and tokens are never exported');
        $this->assertSame(['prov' => 'claude', 'model' => (string) config('pmo.ai.model'), 'ep' => ''], $first['ai']);
        $this->assertSame('light', $first['theme']);
        $this->assertSame(1, $first['v']);
        $this->assertSame(['id', 'name', 'color', 'wip', 'icon', 'is_done', 'cards'], array_keys($first['columns'][0]));
        $card = $first['columns'][1]['cards'][0];
        foreach (['title', 'project', 'pic', 'prio', 'due', 'subD', 'subT', 'tags', 'notes', 'created', 'updated'] as $key) {
            $this->assertArrayHasKey($key, $card, "Original HTML card key {$key}");
        }

        $service->importFromJson($first);
        $second = $service->exportToJson('light');

        $strip = fn (array $e): array => [
            'people' => $e['people'],
            'projects' => $e['projects'],
            'columns' => array_map(fn (array $c): array => [...array_diff_key($c, ['id' => 1]), 'cards' => array_map(fn (array $k): array => array_diff_key($k, ['id' => 1]), $c['cards'])], $e['columns']),
            'activity' => $e['activity'],
        ];
        $this->assertSame($strip($first), $strip($second));
        $this->assertSame('Register merchant', $second['columns'][1]['cards'][0]['subtasks'][0]['title']);
    }

    public function test_csv_export_has_bom_headers_and_neutralises_formulas(): void
    {
        $column = BoardColumn::query()->where('user_id', $this->user->id)->where('name', 'To Do')->sole();
        $project = Project::query()->create(['user_id' => $this->user->id, 'name' => 'ePJS']);
        $member = TeamMember::query()->create(['user_id' => $this->user->id, 'name' => 'Farah L.']);
        app(TaskService::class)->create([
            'board_column_id' => $column->id, 'title' => '=HYPERLINK("http://evil")', 'project_id' => $project->id, 'team_member_id' => $member->id,
            'priority' => 'high', 'icon' => '📌', 'color' => 'violet', 'due_date' => '2026-12-01', 'tags' => ['uat', 'docs'], 'notes' => "Line 1\nLine \"2\"",
        ], [['id' => null, 'title' => 'a', 'is_completed' => true], ['id' => null, 'title' => 'b', 'is_completed' => false]]);

        $csv = app(PmoPortabilityService::class)->exportToCsv();
        $lines = explode("\r\n", $csv);

        $this->assertStringStartsWith("\u{FEFF}", $csv);
        $this->assertSame("\u{FEFF}".'"Column","Title","Project","Assignee","Priority","Due Date","Subtasks Done","Subtasks Total","Tags","Notes"', $lines[0]);
        $this->assertSame('"To Do","\'=HYPERLINK(""http://evil"")","ePJS","Farah L.","High","2026-12-01","1","2","uat|docs","Line 1 Line ""2"""', $lines[1]);
    }

    public function test_invalid_files_are_rejected_without_touching_the_board(): void
    {
        $before = Task::query()->count() + BoardColumn::query()->count();
        $service = app(PmoPortabilityService::class);

        foreach (['not json', '{"hello": "world"}', '{"columns": []}', '[1,2,3]'] as $contents) {
            try {
                $service->importFromJson($service->decode($contents));
                $this->fail("Accepted invalid payload: {$contents}");
            } catch (PortabilityException) {
                // expected
            }
        }

        $this->assertSame($before, Task::query()->count() + BoardColumn::query()->count());
    }

    // ── Snapshots CRUD + restore ─────────────────────────────────────

    public function test_snapshot_full_crud_and_restore(): void
    {
        $modal = Livewire::test(SettingsPortabilityModal::class)
            ->set('snapshotNote', 'Before sprint planning')
            ->call('createSnapshot')
            ->assertHasNoErrors();

        $snapshot = WorkspaceBackup::query()->where('user_id', $this->user->id)->sole();
        $this->assertSame([5, 0, 'Before sprint planning'], [$snapshot->columns_count, $snapshot->tasks_count, $snapshot->notes]);
        $this->assertGreaterThan(0, (float) $snapshot->file_size_kb);
        $modal->assertSee('Before sprint planning')->assertSee($snapshot->sizeLabel());

        // Change the board, then restore.
        BoardColumn::query()->where('user_id', $this->user->id)->where('name', 'Review')->delete();

        $modal->call('editNote', $snapshot->id)
            ->set('editingNote', 'Clean board')
            ->call('saveNote')
            ->assertSet('editingId', null);
        $this->assertSame('Clean board', $snapshot->fresh()?->notes);

        $modal->call('confirmRestore', $snapshot->id)
            ->assertSet('showRestoreModal', true)
            ->assertSee('safety snapshot')
            ->call('restore')
            ->assertSet('showRestoreModal', false)
            ->assertDispatched('pmo-workspace-replaced')
            ->assertDispatched('pmo-board-changed');

        $this->assertSame(5, BoardColumn::query()->where('user_id', $this->user->id)->count());
        $this->assertSame(2, WorkspaceBackup::query()->where('user_id', $this->user->id)->count(), 'Safety snapshot taken');
        $this->assertSame(4, WorkspaceBackup::query()->where('notes', 'like', 'Auto-backup before restoring%')->value('columns_count'));

        $download = $modal->call('downloadSnapshot', $snapshot->id);
        $download->assertFileDownloaded($snapshot->filename);

        $modal->call('confirmDelete', $snapshot->id)
            ->assertSet('showDeleteModal', true)
            ->call('destroy');
        $this->assertModelMissing($snapshot);
    }

    public function test_import_json_upload_requires_confirmation_and_creates_safety_snapshot(): void
    {
        $file = UploadedFile::fake()->createWithContent('Kanban_PMO_Neo_2026-11-01.json', (string) json_encode($this->originalHtmlPayload()));

        $modal = Livewire::test(SettingsPortabilityModal::class)
            ->set('importFile', $file)
            ->call('importJson')
            ->assertHasErrors('importConfirm');
        $this->assertSame(0, WorkspaceBackup::query()->count());

        $modal->set('importConfirm', true)
            ->call('importJson')
            ->assertHasNoErrors()
            ->assertDispatched('pmo-workspace-replaced')
            ->assertDispatched('pmo-apply-theme', theme: 'light');

        $this->assertSame(['Backlog', 'Dalam Proses', 'Selesai'], BoardColumn::query()->where('user_id', $this->user->id)->orderBy('sort_order')->pluck('name')->all());
        $this->assertSame(
            ['Imported from Kanban_PMO_Neo_2026-11-01.json', 'Auto-backup before restoring “Imported from Kanban_PMO_Neo_2026-11-01.json”'],
            WorkspaceBackup::query()->orderBy('id')->pluck('notes')->all(),
        );
    }

    public function test_upload_without_applying_only_stores_a_snapshot(): void
    {
        $file = UploadedFile::fake()->createWithContent('backup.json', (string) json_encode($this->originalHtmlPayload()));

        Livewire::test(SettingsPortabilityModal::class)
            ->set('importApply', false)
            ->set('importFile', $file)
            ->call('importJson')
            ->assertHasNoErrors();

        $this->assertSame(['Backlog', 'To Do', 'In Progress', 'Review', 'Done'], BoardColumn::query()->where('user_id', $this->user->id)->orderBy('sort_order')->pluck('name')->all());
        $this->assertSame(4, WorkspaceBackup::query()->sole()->tasks_count);

        Livewire::test(SettingsPortabilityModal::class)
            ->set('importApply', false)
            ->set('importFile', UploadedFile::fake()->createWithContent('bad.json', '{"columns": []}'))
            ->call('importJson')
            ->assertHasErrors('importFile');
    }

    public function test_export_downloads(): void
    {
        Livewire::test(SettingsPortabilityModal::class)->call('exportJson', 'dark')->assertFileDownloaded();
        Livewire::test(SettingsPortabilityModal::class)->call('exportCsv')->assertFileDownloaded();
    }

    // ── Reset to sample (layered confirmation) ───────────────────────

    public function test_reset_to_sample_requires_two_steps_and_keeps_other_data(): void
    {
        SavedAiPrompt::query()->create(['user_id' => $this->user->id, 'title' => 'Keep me', 'action_type' => 'risk', 'custom_instruction' => 'x']);
        AutomationLog::query()->create(['user_id' => $this->user->id, 'channel' => 'telegram', 'command_name' => 'keep', 'message' => 'm']);
        Project::query()->create(['user_id' => $this->user->id, 'name' => 'Old project']);

        $modal = Livewire::test(SettingsPortabilityModal::class)
            ->call('startReset')
            ->assertSet('showResetModal', true)
            ->call('resetNext')
            ->assertHasErrors('resetAcknowledge')
            ->assertSet('resetStep', 1)
            ->set('resetAcknowledge', true)
            ->call('resetNext')
            ->assertSet('resetStep', 2)
            ->set('resetPhrase', 'reset please')
            ->call('resetWorkspace')
            ->assertHasErrors('resetPhrase');

        $this->assertSame(1, Project::query()->where('user_id', $this->user->id)->count(), 'Nothing changed yet');

        $modal->set('resetPhrase', 'RESET')->call('resetWorkspace')->assertSet('showResetModal', false);

        $this->assertSame(6, Project::query()->where('user_id', $this->user->id)->count());
        $this->assertFalse(Project::query()->where('name', 'Old project')->exists());
        $this->assertSame(14, Task::query()->where('user_id', $this->user->id)->count());
        $this->assertSame(5, BoardColumn::query()->where('user_id', $this->user->id)->count());
        $this->assertSame('Ikhwan A.', TeamMember::query()->where('user_id', $this->user->id)->where('is_me', true)->value('name'));
        $this->assertSame(1, SavedAiPrompt::query()->where('user_id', $this->user->id)->count(), 'AI templates are kept');
        $this->assertSame(1, AutomationLog::query()->where('user_id', $this->user->id)->count(), 'Audit logs are kept');
        $this->assertSame('Auto-backup before reset to sample data', WorkspaceBackup::query()->where('user_id', $this->user->id)->sole()->notes);
    }

    // ── Multi-tenancy ────────────────────────────────────────────────

    public function test_tenant_isolation(): void
    {
        $other = User::factory()->create();
        $otherColumn = BoardColumn::query()->create(['user_id' => $other->id, 'name' => 'Other column']);
        Task::query()->create(['user_id' => $other->id, 'board_column_id' => $otherColumn->id, 'title' => 'Other secret task']);
        $otherSnapshot = WorkspaceBackup::query()->create(['user_id' => $other->id, 'filename' => 'x.json', 'file_size_kb' => 1, 'columns_count' => 1, 'tasks_count' => 1, 'payload_json' => '{}', 'notes' => 'Other snapshot']);

        $service = app(PmoPortabilityService::class);
        $this->assertStringNotContainsString('Other secret task', $service->exportJsonString());
        $this->assertStringNotContainsString('Other secret task', $service->exportToCsv());

        $service->importFromJson($this->originalHtmlPayload());
        $this->assertTrue(Task::query()->where('title', 'Other secret task')->exists(), 'Import never touches other workspaces');
        $this->assertModelExists($otherColumn);

        Livewire::test(SettingsPortabilityModal::class)->assertDontSee('Other snapshot');
        Livewire::test(SettingsPortabilityModal::class)->call('confirmRestore', $otherSnapshot->id)->assertStatus(404);
        Livewire::test(SettingsPortabilityModal::class)->call('downloadSnapshot', $otherSnapshot->id)->assertStatus(404);
        Livewire::test(SettingsPortabilityModal::class)->call('confirmDelete', $otherSnapshot->id)->assertStatus(404);
        $this->assertModelExists($otherSnapshot);
    }

    /**
     * A payload shaped exactly like the original Kanban_PMO_Neo.html export.
     *
     * @return array<string, mixed>
     */
    private function originalHtmlPayload(): array
    {
        $ms = 1762000000000;

        return [
            'v' => 1, 'lang' => 'ms', 'theme' => 'light', 'me' => 'Ikhwan A.', 'sideW' => 236,
            'ai' => ['prov' => 'claude', 'model' => 'claude-opus-5', 'ep' => ''],
            'people' => [
                ['name' => 'Ikhwan A.', 'role' => 'PMO Officer'],
                ['name' => 'Afqah N.', 'role' => 'Developer'],
                ['name' => 'Haziq M.', 'role' => 'QA / Tester'],
            ],
            'columns' => [
                ['id' => 'k1a', 'name' => 'Backlog', 'color' => '#a78bfa', 'wip' => 0, 'cards' => [
                    ['id' => 'x1', 'title' => 'Sediakan dokumen SRS', 'project' => 'eMyHaji 2.0', 'pic' => 'Ikhwan A.', 'prio' => 'medium', 'due' => '2026-12-10', 'subD' => 0, 'subT' => 0, 'tags' => [], 'notes' => '', 'created' => $ms, 'updated' => $ms],
                ]],
                ['id' => 'k2b', 'name' => 'Dalam Proses', 'color' => '#38BDF8', 'wip' => 7, 'cards' => [
                    ['id' => 'x2', 'title' => 'Integrasi pembayaran FPX', 'project' => 'eMyHaji 2.0', 'pic' => 'Afqah N.', 'prio' => 'high', 'due' => '2026-12-01', 'subD' => 1, 'subT' => 4, 'tags' => ['UAT', 'FPX', 'uat'], 'notes' => 'Menunggu kelayakan', 'created' => $ms, 'updated' => $ms + 1000],
                    ['id' => 'x3', 'title' => 'Semakan kod', 'project' => 'MyResearch', 'pic' => 'Bekas Staf', 'prio' => 'urgent', 'due' => 'tomorrow', 'subD' => 9, 'subT' => 3, 'created' => $ms, 'updated' => $ms],
                ]],
                ['id' => 'k3c', 'name' => 'Selesai', 'color' => 'not-a-colour', 'wip' => '0', 'cards' => [
                    ['id' => 'x4', 'title' => 'Sesi latihan pengguna', 'project' => 'ePJS', 'pic' => 'Haziq M.', 'prio' => 'medium', 'due' => '2026-11-01', 'subD' => 0, 'subT' => 0, 'tags' => [], 'notes' => '', 'created' => $ms, 'updated' => $ms],
                    ['id' => 'x5', 'title' => '   ', 'pic' => 'Nobody'],
                ]],
            ],
            'activity' => [
                ['who' => 'Ikhwan A.', 'what' => 'picAdded', 'task' => 'Haziq M.', 'ts' => $ms + 60000],
                ['who' => 'Ikhwan A.', 'what' => 'created', 'task' => 'Integrasi pembayaran FPX', 'ts' => $ms],
            ],
            'filters' => ['project' => '', 'pic' => '', 'prio' => '', 'due' => '', 'sort' => 'manual', 'q' => ''],
            'view' => 'board',
        ];
    }
}
