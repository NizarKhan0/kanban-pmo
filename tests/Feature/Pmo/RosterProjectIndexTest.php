<?php

declare(strict_types=1);

namespace Tests\Feature\Pmo;

use App\Livewire\Pmo\RosterProjectIndex;
use App\Models\Project;
use App\Models\TeamMember;
use App\Models\User;
use App\Support\TaskTable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class RosterProjectIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TaskTable::flush();
    }

    public function test_guests_are_redirected(): void
    {
        $this->get(route('pmo.roster'))->assertRedirect(route('login'));
    }

    public function test_page_renders_in_english(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('pmo.roster'))
            ->assertOk()
            ->assertSee('Team Roster &amp; PICs', false)
            ->assertSee('Project Portfolio')
            ->assertSee('Overloaded PICs');
    }

    public function test_project_full_crud(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Livewire::test(RosterProjectIndex::class)
            ->call('createProject')
            ->assertSet('showProjectModal', true)
            ->set('projectForm.name', 'eMyHaji 2.0')
            ->set('projectForm.code', 'emh2')
            ->set('projectForm.color', 'emerald')
            ->call('saveProject')
            ->assertHasNoErrors()
            ->assertSet('showProjectModal', false)
            ->assertSee('eMyHaji 2.0');

        $project = Project::query()->where('user_id', $user->id)->sole();
        $this->assertSame('EMH2', $project->code);

        $component->call('editProject', $project->id)
            ->assertSet('projectForm.name', 'eMyHaji 2.0')
            ->set('projectForm.name', 'eMyHaji 3.0')
            ->set('projectForm.status', 'on_hold')
            ->call('saveProject')
            ->assertHasNoErrors();

        $this->assertSame('on_hold', $project->fresh()?->status);

        $component->call('confirmDeleteProject', $project->id)
            ->assertSet('showDeleteModal', true)
            ->assertSet('deleteName', 'eMyHaji 3.0')
            ->call('destroy');

        $this->assertModelMissing($project);
    }

    public function test_project_validation_and_unique_code_per_user(): void
    {
        $user = User::factory()->create();
        Project::query()->create(['user_id' => $user->id, 'name' => 'A', 'code' => 'DUP']);
        Project::query()->create(['user_id' => User::factory()->create()->id, 'name' => 'B', 'code' => 'OTHER']);
        $this->actingAs($user);

        Livewire::test(RosterProjectIndex::class)
            ->call('createProject')
            ->set('projectForm.name', '')
            ->set('projectForm.code', 'dup')
            ->call('saveProject')
            ->assertHasErrors(['projectForm.name' => 'required']);

        Livewire::test(RosterProjectIndex::class)
            ->call('createProject')
            ->set('projectForm.name', 'C')
            ->set('projectForm.code', 'OTHER')
            ->call('saveProject')
            ->assertHasNoErrors();
    }

    public function test_member_full_crud_and_set_as_me(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Livewire::test(RosterProjectIndex::class)
            ->call('createMember')
            ->set('memberForm.name', 'Ikhwan A.')
            ->set('memberForm.role', 'PMO Officer')
            ->set('memberForm.email', 'Ikhwan@Gmail.com')
            ->set('memberForm.phone_whatsapp', '+60 12-345 6789')
            ->set('memberForm.is_me', true)
            ->call('saveMember')
            ->assertHasNoErrors();

        $component->call('createMember')
            ->set('memberForm.name', 'Afqah N.')
            ->call('saveMember')
            ->assertHasNoErrors();

        $ikhwan = TeamMember::query()->where('user_id', $user->id)->where('name', 'Ikhwan A.')->sole();
        $afqah = TeamMember::query()->where('user_id', $user->id)->where('name', 'Afqah N.')->sole();

        $this->assertTrue($ikhwan->is_me);
        $this->assertSame('ikhwan@gmail.com', $ikhwan->email);
        $this->assertSame('IA', $ikhwan->initials());
        $this->assertSame('https://wa.me/60123456789', $ikhwan->whatsappUrl());

        $component->call('setAsMe', $afqah->id);
        $this->assertFalse($ikhwan->fresh()?->is_me);
        $this->assertTrue($afqah->fresh()?->is_me);

        $component->call('editMember', $afqah->id)
            ->set('memberForm.role', 'Developer')
            ->set('memberForm.phone_whatsapp', 'not-a-phone')
            ->call('saveMember')
            ->assertHasErrors(['memberForm.phone_whatsapp'])
            ->set('memberForm.phone_whatsapp', '')
            ->call('saveMember')
            ->assertHasNoErrors();

        $this->assertSame('Developer', $afqah->fresh()?->role);

        $component->call('confirmDeleteMember', $afqah->id)
            ->assertSee('Confirm Removal')
            ->call('destroy');

        $this->assertModelMissing($afqah);
    }

    public function test_tenant_isolation(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::query()->create(['user_id' => $owner->id, 'name' => 'Secret Project']);
        $member = TeamMember::query()->create(['user_id' => $owner->id, 'name' => 'Secret PIC']);

        $this->actingAs($intruder);

        Livewire::test(RosterProjectIndex::class)
            ->assertDontSee('Secret Project')
            ->assertDontSee('Secret PIC');

        Livewire::test(RosterProjectIndex::class)
            ->call('confirmDeleteMember', $member->id)
            ->assertStatus(404)
            ->assertSet('deleteId', null)
            ->assertSet('showDeleteModal', false);

        Livewire::test(RosterProjectIndex::class)
            ->call('editProject', $project->id)
            ->assertStatus(404)
            ->assertSet('showProjectModal', false);

        $this->assertModelExists($project);
        $this->assertModelExists($member);
    }

    public function test_pic_history_preservation_sop(): void
    {
        $this->createStandInTasksTable();

        $user = User::factory()->create();
        $this->actingAs($user);

        $member = TeamMember::query()->create(['user_id' => $user->id, 'name' => 'Haziq M.']);
        $project = Project::query()->create(['user_id' => $user->id, 'name' => 'ePJS']);

        $base = ['user_id' => $user->id, 'team_member_id' => $member->id, 'project_id' => $project->id,
            'pic_name_snapshot' => 'Haziq M.', 'project_name_snapshot' => 'ePJS'];
        DB::table('tasks')->insert([
            [...$base, 'title' => 'Open', 'priority' => 'high', 'due_date' => null],
            [...$base, 'title' => 'Overdue', 'priority' => 'medium', 'due_date' => '2000-01-01'],
            [...$base, 'title' => 'Done', 'priority' => 'done', 'due_date' => '2000-01-01'],
        ]);

        $this->assertSame(['open' => 2, 'done' => 1, 'overdue' => 1], $member->workloadStats());

        // Rename syncs snapshot.
        Livewire::test(RosterProjectIndex::class)
            ->call('editMember', $member->id)
            ->set('memberForm.name', 'Haziq Mohd.')
            ->call('saveMember')
            ->assertHasNoErrors()
            ->call('confirmDeleteMember', $member->id)
            ->assertSet('deleteOpenTasks', 2)
            ->assertSet('deleteTotalTasks', 3)
            ->call('destroy');

        // Delete keeps tasks, nulls FK, keeps snapshot.
        $this->assertSame(3, DB::table('tasks')->count());
        $this->assertSame(3, DB::table('tasks')->whereNull('team_member_id')->where('pic_name_snapshot', 'Haziq Mohd.')->count());

        Livewire::test(RosterProjectIndex::class)
            ->call('confirmDeleteProject', $project->id)
            ->call('destroy');

        $this->assertSame(3, DB::table('tasks')->whereNull('project_id')->where('project_name_snapshot', 'ePJS')->count());
    }

    private function createStandInTasksTable(): void
    {
        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('priority')->default('medium');
            $table->date('due_date')->nullable();
            $table->string('pic_name_snapshot')->nullable();
            $table->string('project_name_snapshot')->nullable();
            $table->timestamps();
        });

        TaskTable::flush();
    }
}
