<?php

declare(strict_types=1);

namespace Tests\Feature\Pmo;

use App\Livewire\Pmo\KanbanBoard;
use App\Models\BoardColumn;
use App\Models\User;
use App\Services\Pmo\BoardColumnService;
use App\Support\TaskTable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class KanbanBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TaskTable::flush();
    }

    public function test_guests_are_redirected(): void
    {
        $this->get(route('pmo.board'))->assertRedirect(route('login'));
    }

    public function test_first_visit_provisions_standard_columns(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('pmo.board'))
            ->assertOk()
            ->assertSee('Kanban Board')
            ->assertSee('Add Column')
            ->assertSee('WIP 0/7')
            ->assertSee('Done Column');

        $this->assertSame(
            ['Backlog', 'To Do', 'In Progress', 'Review', 'Done'],
            BoardColumn::query()->where('user_id', $user->id)->orderBy('sort_order')->pluck('name')->all(),
        );
        $this->assertSame([10, 20, 30, 40, 50], BoardColumn::query()->where('user_id', $user->id)->orderBy('sort_order')->pluck('sort_order')->all());

        // Second visit does not duplicate.
        $this->get(route('pmo.board'))->assertOk();
        $this->assertSame(5, BoardColumn::query()->where('user_id', $user->id)->count());
    }

    public function test_column_full_crud(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Livewire::test(KanbanBoard::class)
            ->call('createColumn')
            ->assertSet('showColumnModal', true)
            ->set('columnForm.name', 'UAT')
            ->set('columnForm.color', '#FF2E9A')
            ->set('columnForm.wip_limit', '3')
            ->call('saveColumn')
            ->assertHasNoErrors()
            ->assertSet('showColumnModal', false)
            ->assertSee('UAT');

        $column = BoardColumn::query()->where('user_id', $user->id)->where('name', 'UAT')->sole();
        $this->assertSame('#ff2e9a', $column->color);
        $this->assertSame(3, $column->wip_limit);
        $this->assertSame(60, $column->sort_order);

        $component->call('editColumn', $column->id)
            ->assertSet('columnForm.name', 'UAT')
            ->set('columnForm.name', 'User Acceptance')
            ->set('columnForm.is_done_column', true)
            ->call('saveColumn')
            ->assertHasNoErrors();

        $this->assertTrue($column->fresh()?->is_done_column);

        $component->call('confirmDeleteColumn', $column->id)
            ->assertSet('showDeleteModal', true)
            ->assertSet('deleteName', 'User Acceptance')
            ->call('destroyColumn');

        $this->assertModelMissing($column);
    }

    public function test_validation(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(KanbanBoard::class)
            ->call('createColumn')
            ->set('columnForm.name', '')
            ->set('columnForm.color', 'blue')
            ->set('columnForm.wip_limit', -1)
            ->call('saveColumn')
            ->assertHasErrors([
                'columnForm.name' => 'required',
                'columnForm.color' => 'regex',
                'columnForm.wip_limit' => 'min',
            ]);
    }

    public function test_reorder_uses_steps_of_ten_and_ignores_foreign_ids(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(KanbanBoard::class);
        $foreign = BoardColumn::query()->create(['user_id' => $other->id, 'name' => 'Foreign', 'sort_order' => 999]);

        $ids = BoardColumn::query()->where('user_id', $user->id)->orderBy('sort_order')->pluck('id')->all();
        [$backlog, $todo, $progress, $review, $done] = $ids;

        // Drag "Done" to the front.
        Livewire::test(KanbanBoard::class)->call('moveColumn', $done, 0);
        $this->assertSame([$done, $backlog, $todo, $progress, $review], $this->order($user));

        // Service API with a foreign ID mixed in.
        app(BoardColumnService::class)->updateColumnOrder([$review, $foreign->id, $progress]);
        $this->assertSame([$review, $progress, $done, $backlog, $todo], $this->order($user));
        $this->assertSame([10, 20, 30, 40, 50], BoardColumn::query()->where('user_id', $user->id)->orderBy('sort_order')->pluck('sort_order')->all());
        $this->assertSame(999, $foreign->fresh()?->sort_order);

        // Menu "Move Right".
        Livewire::test(KanbanBoard::class)->call('shiftColumn', $review, 1);
        $this->assertSame([$progress, $review, $done, $backlog, $todo], $this->order($user));
    }

    public function test_cannot_delete_last_column(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Livewire::test(KanbanBoard::class);

        $columns = BoardColumn::query()->where('user_id', $user->id)->get();
        $columns->slice(1)->each->delete();
        $last = $columns->first();

        Livewire::test(KanbanBoard::class)
            ->call('confirmDeleteColumn', $last->id)
            ->assertSet('deleteAllowed', false)
            ->assertSee('must keep at least one column')
            ->call('destroyColumn');

        $this->assertModelExists($last);
    }

    public function test_tenant_isolation(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        Livewire::test(KanbanBoard::class);
        $ownerColumn = BoardColumn::query()->where('user_id', $owner->id)->firstOrFail();
        $ownerColumn->update(['name' => 'Owner Secret Stage']);

        $this->actingAs(User::factory()->create());

        Livewire::test(KanbanBoard::class)
            ->assertDontSee('Owner Secret Stage')
            ->call('editColumn', $ownerColumn->id)
            ->assertStatus(404);

        Livewire::test(KanbanBoard::class)
            ->call('confirmDeleteColumn', $ownerColumn->id)
            ->assertStatus(404);

        $this->assertModelExists($ownerColumn);
    }

    public function test_wip_limit_and_delete_moves_cards_with_priority_sync(): void
    {
        $this->createStandInTasksTable();

        $user = User::factory()->create();
        $this->actingAs($user);
        Livewire::test(KanbanBoard::class);

        $progress = BoardColumn::query()->where('user_id', $user->id)->where('name', 'In Progress')->sole();
        $done = BoardColumn::query()->where('user_id', $user->id)->where('name', 'Done')->sole();
        $progress->update(['wip_limit' => 2]);

        foreach (['A', 'B', 'C'] as $i => $title) {
            DB::table('tasks')->insert(['user_id' => $user->id, 'board_column_id' => $progress->id, 'title' => $title, 'priority' => 'high', 'sort_order' => $i + 1]);
        }
        DB::table('tasks')->insert(['user_id' => $user->id, 'board_column_id' => $done->id, 'title' => 'Old', 'priority' => 'done', 'sort_order' => 5]);

        $this->assertTrue($progress->fresh()?->isOverWip());
        $this->assertFalse($done->isOverWip());

        Livewire::test(KanbanBoard::class)
            ->assertSee('WIP 3/2')
            ->assertSee('Over WIP Limit')
            ->call('confirmDeleteColumn', $progress->id)
            ->assertSet('deleteCards', 3)
            ->set('deleteTargetId', $done->id)
            ->call('destroyColumn');

        $this->assertModelMissing($progress);
        $this->assertSame(4, DB::table('tasks')->where('board_column_id', $done->id)->where('priority', 'done')->count());
        $this->assertSame([5, 6, 7, 8], DB::table('tasks')->orderBy('sort_order')->pluck('sort_order')->map(fn ($v): int => (int) $v)->all());
    }

    public function test_delete_with_cards_requires_valid_target(): void
    {
        $this->createStandInTasksTable();

        $user = User::factory()->create();
        $this->actingAs($user);
        Livewire::test(KanbanBoard::class);

        $backlog = BoardColumn::query()->where('user_id', $user->id)->where('name', 'Backlog')->sole();
        $foreign = BoardColumn::query()->create(['user_id' => User::factory()->create()->id, 'name' => 'Foreign']);
        DB::table('tasks')->insert(['user_id' => $user->id, 'board_column_id' => $backlog->id, 'title' => 'X', 'priority' => 'low', 'sort_order' => 1]);

        Livewire::test(KanbanBoard::class)
            ->call('confirmDeleteColumn', $backlog->id)
            ->set('deleteTargetId', $foreign->id)
            ->call('destroyColumn');

        $this->assertModelExists($backlog);
        $this->assertSame(1, DB::table('tasks')->where('board_column_id', $backlog->id)->count());
    }

    /**
     * @return list<int>
     */
    private function order(User $user): array
    {
        return BoardColumn::query()->where('user_id', $user->id)->orderBy('sort_order')->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
    }

    private function createStandInTasksTable(): void
    {
        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('board_column_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('priority')->default('medium');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        TaskTable::flush();
    }
}
