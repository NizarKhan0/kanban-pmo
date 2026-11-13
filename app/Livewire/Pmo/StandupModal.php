<?php

declare(strict_types=1);

namespace App\Livewire\Pmo;

use App\Models\ActivityLog;
use App\Services\Pmo\ActivityLogger;
use App\Services\Pmo\StandupGeneratorService;
use App\Services\Pmo\TaskFilters;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;

/**
 * Daily Standup Report modal with today's standup notes (full CRUD).
 *
 * @property-read string $report
 * @property-read Collection<int, ActivityLog> $notes
 */
class StandupModal extends Component
{
    /** @var array<string, string> Board view + filter state (from KanbanBoard). */
    #[Reactive]
    public array $filters = [];

    public bool $showStandupModal = false;

    public string $format = StandupGeneratorService::FORMAT_CHAT;

    public bool $useFilters = false;

    public bool $includeNotes = true;

    // ── Notes ────────────────────────────────────────────────────────
    public string $noteText = '';

    public ?int $editingNoteId = null;

    public string $editingText = '';

    public bool $showNoteDeleteModal = false;

    #[Locked]
    public ?int $deleteNoteId = null;

    #[Locked]
    public string $deleteNoteText = '';

    #[On('open-standup')]
    public function open(): void
    {
        $this->resetErrorBag();
        $this->noteText = '';
        $this->cancelEdit();
        $this->useFilters = TaskFilters::fromArray($this->filters)->narrows();
        $this->showStandupModal = true;
    }

    #[Computed]
    public function report(): string
    {
        $format = array_key_exists($this->format, StandupGeneratorService::FORMATS) ? $this->format : StandupGeneratorService::FORMAT_CHAT;

        return app(StandupGeneratorService::class)->generate(
            $this->useFilters ? TaskFilters::fromArray($this->filters) : null,
            $format,
            $this->includeNotes,
        );
    }

    /**
     * @return Collection<int, ActivityLog>
     */
    #[Computed]
    public function notes(): Collection
    {
        return app(ActivityLogger::class)->todaysNotes();
    }

    // ── Create ───────────────────────────────────────────────────────

    public function addNote(): void
    {
        $this->validate(
            ['noteText' => ['required', 'string', 'max:250']],
            attributes: ['noteText' => 'note'],
        );

        app(ActivityLogger::class)->addNote($this->noteText);
        $this->noteText = '';

        Flux::toast(variant: 'success', heading: 'Note added', text: 'Your note was added to today’s standup report.');
        $this->dispatch('pmo-board-changed');
    }

    // ── Update ───────────────────────────────────────────────────────

    public function editNote(int $id): void
    {
        $note = app(ActivityLogger::class)->findNote($id);
        $this->resetErrorBag();
        $this->editingNoteId = $note->id;
        $this->editingText = $note->task_title;
    }

    public function saveNote(): void
    {
        if ($this->editingNoteId === null) {
            return;
        }

        $this->validate(
            ['editingText' => ['required', 'string', 'max:250']],
            attributes: ['editingText' => 'note'],
        );

        app(ActivityLogger::class)->updateNote($this->editingNoteId, $this->editingText);
        $this->cancelEdit();

        Flux::toast(variant: 'success', heading: 'Note updated', text: 'Your standup note has been saved.');
        $this->dispatch('pmo-board-changed');
    }

    public function cancelEdit(): void
    {
        $this->editingNoteId = null;
        $this->editingText = '';
    }

    // ── Delete (stacked confirmation) ────────────────────────────────

    public function confirmDeleteNote(int $id): void
    {
        $note = app(ActivityLogger::class)->findNote($id);
        $this->deleteNoteId = $note->id;
        $this->deleteNoteText = $note->task_title;
        $this->showNoteDeleteModal = true;
    }

    public function destroyNote(): void
    {
        if ($this->deleteNoteId !== null) {
            app(ActivityLogger::class)->deleteNote($this->deleteNoteId);

            if ($this->editingNoteId === $this->deleteNoteId) {
                $this->cancelEdit();
            }

            Flux::toast(variant: 'success', heading: 'Note deleted', text: 'The note was removed from today’s report.');
            $this->dispatch('pmo-board-changed');
        }

        $this->cancelNoteDelete();
    }

    public function cancelNoteDelete(): void
    {
        $this->showNoteDeleteModal = false;
        $this->deleteNoteId = null;
        $this->deleteNoteText = '';
    }

    public function closeModals(): void
    {
        $this->showStandupModal = false;
        $this->cancelEdit();
        $this->cancelNoteDelete();
        $this->resetErrorBag();
    }

    public function render(): View
    {
        return view('livewire.pmo.standup-modal', [
            'formats' => StandupGeneratorService::FORMATS,
            'subject' => 'Daily Standup Report — '.now((string) config('pmo.timezone', 'Asia/Kuala_Lumpur'))->format('j M Y'),
        ]);
    }
}
