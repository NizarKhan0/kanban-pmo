<?php

declare(strict_types=1);

namespace App\Livewire\Pmo;

use App\Exceptions\Pmo\PortabilityException;
use App\Models\WorkspaceBackup;
use App\Services\PmoPortabilityService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Settings & Data Portability: snapshots (CRUD + restore), JSON/CSV export,
 * JSON import, theme switch and reset-to-sample with layered confirmation.
 *
 * @property-read Collection<int, WorkspaceBackup> $snapshots
 * @property-read array{columns: int, tasks: int, people: int, projects: int} $board
 */
class SettingsPortabilityModal extends Component
{
    use WithFileUploads;

    public const RESET_PHRASE = 'RESET';

    public bool $showModal = false;

    public string $snapshotNote = '';

    // ── Import ───────────────────────────────────────────────────────
    /** @var TemporaryUploadedFile|null */
    public $importFile = null;

    public bool $importApply = true;

    public bool $importConfirm = false;

    // ── Inline note editing ──────────────────────────────────────────
    #[Locked]
    public ?int $editingId = null;

    public string $editingNote = '';

    // ── Confirmations (stacked over the main modal) ──────────────────
    public bool $showRestoreModal = false;

    public bool $showDeleteModal = false;

    public bool $showResetModal = false;

    #[Locked]
    public ?int $targetId = null;

    #[Locked]
    public string $targetLabel = '';

    #[Locked]
    public int $resetStep = 1;

    public bool $resetAcknowledge = false;

    public string $resetPhrase = '';

    #[On('open-settings-portability')]
    public function open(): void
    {
        $this->resetErrorBag();
        $this->snapshotNote = '';
        $this->cancelEdit();
        $this->showModal = true;
    }

    // ──────────────────────────────────────────────────────────────────
    // Read
    // ──────────────────────────────────────────────────────────────────

    /**
     * @return Collection<int, WorkspaceBackup>
     */
    #[Computed]
    public function snapshots(): Collection
    {
        return app(PmoPortabilityService::class)->snapshots();
    }

    /**
     * @return array{columns: int, tasks: int, people: int, projects: int}
     */
    #[Computed]
    public function board(): array
    {
        return app(PmoPortabilityService::class)->currentStats();
    }

    // ──────────────────────────────────────────────────────────────────
    // Create
    // ──────────────────────────────────────────────────────────────────

    public function createSnapshot(): void
    {
        $this->validate(['snapshotNote' => ['nullable', 'string', 'max:250']], attributes: ['snapshotNote' => 'label']);

        $snapshot = app(PmoPortabilityService::class)->createSnapshot($this->snapshotNote);
        $this->snapshotNote = '';
        unset($this->snapshots);

        Flux::toast(variant: 'success', heading: 'Snapshot created', text: "{$snapshot->columns_count} columns and {$snapshot->tasks_count} cards saved ({$snapshot->sizeLabel()}).");
    }

    public function importJson(): void
    {
        $this->validate([
            'importFile' => ['required', 'file', 'max:'.PmoPortabilityService::MAX_UPLOAD_KB, 'extensions:json'],
            'importConfirm' => $this->importApply ? ['accepted'] : ['nullable'],
        ], [
            'importConfirm.accepted' => 'Please confirm that importing will replace your current board.',
            'importFile.extensions' => 'Choose a .json backup file.',
        ], ['importFile' => 'backup file']);

        $service = app(PmoPortabilityService::class);

        try {
            $snapshot = $service->storeUpload((string) $this->importFile->get(), $this->importFile->getClientOriginalName());
            $result = $this->importApply ? $service->restoreSnapshot($snapshot->id) : null;
        } catch (PortabilityException $e) {
            $this->addError('importFile', $e->getMessage());

            return;
        }

        $this->reset('importFile', 'importConfirm');
        unset($this->snapshots, $this->board);

        if ($result === null) {
            Flux::toast(variant: 'success', heading: 'Backup uploaded', text: "Saved as a snapshot ({$snapshot->tasks_count} cards). Restore it whenever you are ready.");

            return;
        }

        $this->afterReplace($result['theme']);
        Flux::toast(variant: 'success', heading: 'Import complete', text: "Board replaced: {$result['columns']} columns, {$result['tasks']} cards, {$result['people']} PICs. Your previous board was saved as a snapshot.");
    }

    // ──────────────────────────────────────────────────────────────────
    // Export / download
    // ──────────────────────────────────────────────────────────────────

    public function exportJson(string $theme = 'dark'): StreamedResponse
    {
        $service = app(PmoPortabilityService::class);
        $json = $service->exportJsonString($theme);

        return response()->streamDownload(fn () => print ($json), $service->exportFilename('json'), ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public function exportCsv(): StreamedResponse
    {
        $service = app(PmoPortabilityService::class);
        $csv = $service->exportToCsv();

        return response()->streamDownload(fn () => print ($csv), $service->exportFilename('csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function downloadSnapshot(int $id): StreamedResponse
    {
        $service = app(PmoPortabilityService::class);
        $snapshot = $service->findSnapshot($id);
        $json = $snapshot->payload_json;

        return response()->streamDownload(fn () => print ($json), $snapshot->filename, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    // ──────────────────────────────────────────────────────────────────
    // Update: snapshot label
    // ──────────────────────────────────────────────────────────────────

    public function editNote(int $id): void
    {
        $snapshot = app(PmoPortabilityService::class)->findSnapshot($id);
        $this->resetErrorBag('editingNote');
        $this->editingId = $snapshot->id;
        $this->editingNote = (string) $snapshot->notes;
    }

    public function saveNote(): void
    {
        if ($this->editingId === null) {
            return;
        }

        $this->validate(['editingNote' => ['nullable', 'string', 'max:250']], attributes: ['editingNote' => 'label']);

        app(PmoPortabilityService::class)->updateSnapshotNotes($this->editingId, $this->editingNote);
        $this->cancelEdit();
        unset($this->snapshots);

        Flux::toast(variant: 'success', heading: 'Snapshot updated', text: 'The snapshot label has been saved.');
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editingNote = '';
    }

    // ──────────────────────────────────────────────────────────────────
    // Update: restore (confirmation)
    // ──────────────────────────────────────────────────────────────────

    public function confirmRestore(int $id): void
    {
        $snapshot = app(PmoPortabilityService::class)->findSnapshot($id);
        $this->targetId = $snapshot->id;
        $this->targetLabel = $this->label($snapshot);
        $this->showRestoreModal = true;
    }

    public function restore(): void
    {
        if ($this->targetId === null) {
            $this->closeConfirm();

            return;
        }

        try {
            $result = app(PmoPortabilityService::class)->restoreSnapshot($this->targetId);
        } catch (PortabilityException $e) {
            Flux::toast(variant: 'danger', heading: 'Restore failed', text: $e->getMessage());
            $this->closeConfirm();

            return;
        }

        $this->closeConfirm();
        unset($this->snapshots, $this->board);
        $this->afterReplace($result['theme']);

        Flux::toast(variant: 'success', heading: 'Snapshot restored', text: "Board restored: {$result['columns']} columns, {$result['tasks']} cards. Your previous board was saved as a snapshot.");
    }

    // ──────────────────────────────────────────────────────────────────
    // Delete: snapshot (confirmation)
    // ──────────────────────────────────────────────────────────────────

    public function confirmDelete(int $id): void
    {
        $snapshot = app(PmoPortabilityService::class)->findSnapshot($id);
        $this->targetId = $snapshot->id;
        $this->targetLabel = $this->label($snapshot);
        $this->showDeleteModal = true;
    }

    public function destroy(): void
    {
        if ($this->targetId !== null) {
            app(PmoPortabilityService::class)->deleteSnapshot($this->targetId);
            unset($this->snapshots);
            Flux::toast(variant: 'success', heading: 'Snapshot deleted', text: "“{$this->targetLabel}” was removed.");
        }

        $this->closeConfirm();
    }

    // ──────────────────────────────────────────────────────────────────
    // Delete: reset to sample data (two-step confirmation)
    // ──────────────────────────────────────────────────────────────────

    public function startReset(): void
    {
        $this->resetErrorBag(['resetAcknowledge', 'resetPhrase']);
        $this->resetStep = 1;
        $this->resetAcknowledge = false;
        $this->resetPhrase = '';
        $this->showResetModal = true;
    }

    public function resetNext(): void
    {
        $this->validate(['resetAcknowledge' => ['accepted']], ['resetAcknowledge.accepted' => 'Tick the box to confirm you understand.']);
        $this->resetStep = 2;
    }

    public function resetWorkspace(): void
    {
        if ($this->resetStep !== 2 || ! $this->resetAcknowledge) {
            $this->resetStep = 1;

            return;
        }

        if (trim($this->resetPhrase) !== self::RESET_PHRASE) {
            $this->addError('resetPhrase', 'Type '.self::RESET_PHRASE.' exactly to confirm.');

            return;
        }

        $result = app(PmoPortabilityService::class)->resetToSample();

        $this->closeConfirm();
        unset($this->snapshots, $this->board);
        $this->afterReplace(null);

        Flux::toast(variant: 'success', heading: 'Workspace reset', text: "Sample data loaded: {$result['columns']} columns, {$result['tasks']} cards. Your previous board was saved as a snapshot.");
    }

    // ──────────────────────────────────────────────────────────────────

    public function closeConfirm(): void
    {
        $this->showRestoreModal = false;
        $this->showDeleteModal = false;
        $this->showResetModal = false;
        $this->targetId = null;
        $this->targetLabel = '';
        $this->resetStep = 1;
        $this->resetAcknowledge = false;
        $this->resetPhrase = '';
        $this->resetErrorBag(['resetAcknowledge', 'resetPhrase']);
    }

    public function closeModals(): void
    {
        $this->closeConfirm();
        $this->cancelEdit();
        $this->reset('importFile', 'importConfirm');
        $this->resetErrorBag();
        $this->showModal = false;
    }

    public function render(): View
    {
        return view('livewire.pmo.settings-portability-modal', [
            'resetPhraseRequired' => self::RESET_PHRASE,
            'maxUploadMb' => PmoPortabilityService::MAX_UPLOAD_KB / 1024,
        ]);
    }

    /**
     * Refresh every page component that shows board data, and apply an imported theme.
     */
    private function afterReplace(?string $theme): void
    {
        $this->dispatch('pmo-workspace-replaced');
        $this->dispatch('pmo-board-changed');

        if ($theme !== null) {
            $this->dispatch('pmo-apply-theme', theme: $theme);
        }
    }

    private function label(WorkspaceBackup $snapshot): string
    {
        return Str::limit($snapshot->notes ?: $snapshot->filename, 120);
    }
}
