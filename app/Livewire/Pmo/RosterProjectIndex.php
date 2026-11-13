<?php

declare(strict_types=1);

namespace App\Livewire\Pmo;

use App\Livewire\Forms\Pmo\ProjectForm;
use App\Livewire\Forms\Pmo\TeamMemberForm;
use App\Models\Project;
use App\Models\TeamMember;
use App\Services\Pmo\ProjectService;
use App\Services\Pmo\TeamRosterService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * @property-read Collection<int, Project> $projects
 * @property-read array<string, int> $projectCounts
 * @property-read Collection<int, TeamMember> $members
 * @property-read array<int, array{open: int, done: int, overdue: int}> $workload
 * @property-read array{total_projects: int, active_projects: int, active_pics: int, total_pics: int, overloaded_pics: int, threshold: int} $kpis
 */
#[Title('Projects & Team Roster')]
class RosterProjectIndex extends Component
{
    public ProjectForm $projectForm;

    public TeamMemberForm $memberForm;

    public bool $showProjectModal = false;

    public bool $showMemberModal = false;

    public bool $showDeleteModal = false;

    #[Url(as: 'status', except: 'all')]
    public string $projectStatus = 'all';

    #[Locked]
    public ?string $deleteType = null;

    #[Locked]
    public ?int $deleteId = null;

    #[Locked]
    public string $deleteName = '';

    #[Locked]
    public int $deleteOpenTasks = 0;

    #[Locked]
    public int $deleteTotalTasks = 0;

    /**
     * Re-render after Settings & Data Portability replaced the workspace.
     */
    #[On('pmo-workspace-replaced')]
    public function refreshWorkspace(): void
    {
        // Computed properties are rebuilt on the next render.
    }

    // ──────────────────────────────────────────────────────────────────
    // Read
    // ──────────────────────────────────────────────────────────────────

    /**
     * @return Collection<int, Project>
     */
    #[Computed]
    public function projects(): Collection
    {
        $status = $this->projectStatus === 'all' ? null : $this->projectStatus;

        return app(ProjectService::class)->list($status);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function projectCounts(): array
    {
        return app(ProjectService::class)->statusCounts();
    }

    /**
     * @return Collection<int, TeamMember>
     */
    #[Computed]
    public function members(): Collection
    {
        return app(TeamRosterService::class)->list();
    }

    /**
     * @return array<int, array{open: int, done: int, overdue: int}>
     */
    #[Computed]
    public function workload(): array
    {
        return app(TeamRosterService::class)->workloadFor($this->members);
    }

    /**
     * @return array{total_projects: int, active_projects: int, active_pics: int, total_pics: int, overloaded_pics: int, threshold: int}
     */
    #[Computed]
    public function kpis(): array
    {
        $roster = app(TeamRosterService::class);
        $counts = $this->projectCounts;

        $overloaded = $this->members->filter(
            fn (TeamMember $member): bool => $roster->isOverloaded($member, $this->workload[$member->id]),
        )->count();

        return [
            'total_projects' => $counts['all'],
            'active_projects' => $counts['active'],
            'active_pics' => $this->members->where('is_active', true)->count(),
            'total_pics' => $this->members->count(),
            'overloaded_pics' => $overloaded,
            'threshold' => $roster->overloadThreshold(),
        ];
    }

    public function setProjectStatus(string $status): void
    {
        $this->projectStatus = $status === 'all' || array_key_exists($status, Project::STATUSES) ? $status : 'all';
    }

    // ──────────────────────────────────────────────────────────────────
    // Projects: Create / Update
    // ──────────────────────────────────────────────────────────────────

    public function createProject(): void
    {
        $this->projectForm->reset();
        $this->projectForm->resetErrorBag();
        $this->showProjectModal = true;
    }

    public function editProject(int $id): void
    {
        $this->projectForm->fillFrom(app(ProjectService::class)->find($id));
        $this->showProjectModal = true;
    }

    public function saveProject(): void
    {
        $data = $this->projectForm->payload();
        $service = app(ProjectService::class);

        if ($this->projectForm->id !== null) {
            $project = $service->update($this->projectForm->id, $data);
            Flux::toast(variant: 'success', heading: 'Project updated', text: "\"{$project->name}\" has been saved.");
        } else {
            $project = $service->create($data);
            Flux::toast(variant: 'success', heading: 'Project created', text: "\"{$project->name}\" was added to your portfolio.");
        }

        $this->closeModals();
    }

    // ──────────────────────────────────────────────────────────────────
    // Team Members: Create / Update / Set as Me
    // ──────────────────────────────────────────────────────────────────

    public function createMember(): void
    {
        $this->memberForm->reset();
        $this->memberForm->resetErrorBag();
        $this->showMemberModal = true;
    }

    public function editMember(int $id): void
    {
        $this->memberForm->fillFrom(app(TeamRosterService::class)->find($id));
        $this->showMemberModal = true;
    }

    public function saveMember(): void
    {
        $data = $this->memberForm->payload();
        $service = app(TeamRosterService::class);

        if ($this->memberForm->id !== null) {
            $member = $service->update($this->memberForm->id, $data);
            Flux::toast(variant: 'success', heading: 'Team member updated', text: "{$member->name}'s profile has been saved.");
        } else {
            $member = $service->create($data);
            Flux::toast(variant: 'success', heading: 'Team member added', text: "{$member->name} has joined the roster.");
        }

        $this->closeModals();
    }

    public function setAsMe(int $id): void
    {
        $member = app(TeamRosterService::class)->setAsMe($id);

        Flux::toast(variant: 'success', heading: 'Profile linked', text: "You are now identified as {$member->name}.");
    }

    // ──────────────────────────────────────────────────────────────────
    // Delete (with Confirmation Modal)
    // ──────────────────────────────────────────────────────────────────

    public function confirmDeleteProject(int $id): void
    {
        $this->openDeleteModal('project', $id, app(ProjectService::class)->deletePreview($id));
    }

    public function confirmDeleteMember(int $id): void
    {
        $this->openDeleteModal('member', $id, app(TeamRosterService::class)->deletePreview($id));
    }

    public function destroy(): void
    {
        if ($this->deleteId === null || $this->deleteType === null) {
            $this->closeModals();

            return;
        }

        $name = $this->deleteName;

        if ($this->deleteType === 'project') {
            $retained = app(ProjectService::class)->delete($this->deleteId);
            $heading = 'Project deleted';
        } else {
            $retained = app(TeamRosterService::class)->delete($this->deleteId);
            $heading = 'Team member removed';
        }

        $text = $retained > 0
            ? "\"{$name}\" was removed. {$retained} task card(s) were preserved."
            : "\"{$name}\" was removed.";

        Flux::toast(variant: 'success', heading: $heading, text: $text);

        $this->closeModals();
    }

    public function closeModals(): void
    {
        $this->showProjectModal = false;
        $this->showMemberModal = false;
        $this->showDeleteModal = false;
        $this->deleteType = null;
        $this->deleteId = null;
        $this->deleteName = '';
        $this->deleteOpenTasks = 0;
        $this->deleteTotalTasks = 0;
        $this->projectForm->resetErrorBag();
        $this->memberForm->resetErrorBag();
    }

    public function render(): View
    {
        return view('livewire.pmo.roster-project-index', [
            'statuses' => Project::STATUSES,
        ]);
    }

    /**
     * @param  array{name: string, open: int, total: int}  $preview
     */
    private function openDeleteModal(string $type, int $id, array $preview): void
    {
        $this->deleteType = $type;
        $this->deleteId = $id;
        $this->deleteName = $preview['name'];
        $this->deleteOpenTasks = $preview['open'];
        $this->deleteTotalTasks = $preview['total'];
        $this->showDeleteModal = true;
    }
}
