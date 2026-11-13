<?php

declare(strict_types=1);

namespace App\Livewire\Pmo;

use App\Livewire\Forms\Pmo\SavedAiPromptForm;
use App\Models\AutomationLog;
use App\Models\BoardColumn;
use App\Models\SavedAiPrompt;
use App\Models\Task;
use App\Services\Pmo\AiPmoService;
use App\Services\Pmo\AutomationLogger;
use App\Services\Pmo\BoardColumnService;
use App\Services\Pmo\OmnichannelNotifierService;
use App\Services\Pmo\SavedPromptService;
use App\Services\Pmo\StandupGeneratorService;
use App\Services\Pmo\TaskService;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * @property-read array<string, array{label: string, icon: string, connected: bool, detail: string, missing: list<string>}> $channels
 * @property-read array{total: int, sent: int, simulated: int, failed: int, tokens: int} $stats
 * @property-read LengthAwarePaginator<int, AutomationLog> $logs
 * @property-read Collection<int, SavedAiPrompt> $prompts
 * @property-read Collection<int, Task> $taskOptions
 * @property-read Collection<int, BoardColumn> $columnOptions
 */
#[Title('AI & Omnichannel Hub')]
class AiOmnichannelHub extends Component
{
    use WithPagination;

    public SavedAiPromptForm $promptForm;

    // ── AI assistant ─────────────────────────────────────────────────
    public string $aiAction = 'standup';

    public string $aiNotes = '';

    public ?int $aiTaskId = null;

    #[Locked]
    public ?int $aiTemplateId = null;

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $aiResult = null;

    public bool $showManual = false;

    #[Locked]
    public string $manualPrompt = '';

    public string $manualAnswer = '';

    /** @var array<int, bool> */
    public array $captureSelected = [];

    public ?int $captureColumnId = null;

    // ── Dispatch ─────────────────────────────────────────────────────
    public string $telegramChatId = '';

    public string $emailTo = '';

    public string $whatsappPhone = '';

    /** @var list<array<string, mixed>> */
    #[Locked]
    public array $dispatchResults = [];

    // ── Audit log filters ────────────────────────────────────────────
    #[Url(as: 'channel', except: 'all')]
    public string $logChannel = 'all';

    #[Url(as: 'status', except: 'all')]
    public string $logStatus = 'all';

    // ── Modals ───────────────────────────────────────────────────────
    public bool $showPromptModal = false;

    public bool $showPromptDeleteModal = false;

    public bool $showClearModal = false;

    #[Locked]
    public ?int $deletePromptId = null;

    #[Locked]
    public string $deletePromptTitle = '';

    public string $clearRange = '30';

    #[Locked]
    public int $clearCount = 0;

    // ──────────────────────────────────────────────────────────────────
    // Read
    // ──────────────────────────────────────────────────────────────────

    /**
     * @return array<string, array{label: string, icon: string, connected: bool, detail: string, missing: list<string>}>
     */
    #[Computed]
    public function channels(): array
    {
        return app(OmnichannelNotifierService::class)->channelStatus();
    }

    /**
     * @return array{total: int, sent: int, simulated: int, failed: int, tokens: int}
     */
    #[Computed]
    public function stats(): array
    {
        return app(AutomationLogger::class)->stats();
    }

    /**
     * @return LengthAwarePaginator<int, AutomationLog>
     */
    #[Computed]
    public function logs(): LengthAwarePaginator
    {
        return app(AutomationLogger::class)->paginate($this->logChannel, $this->logStatus);
    }

    /**
     * @return Collection<int, SavedAiPrompt>
     */
    #[Computed]
    public function prompts(): Collection
    {
        return app(SavedPromptService::class)->list();
    }

    /**
     * @return Collection<int, Task>
     */
    #[Computed]
    public function taskOptions(): Collection
    {
        return Task::query()
            ->where('user_id', Auth::id())
            ->with('column')
            ->orderBy('board_column_id')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return Collection<int, BoardColumn>
     */
    #[Computed]
    public function columnOptions(): Collection
    {
        return app(BoardColumnService::class)->list();
    }

    // ──────────────────────────────────────────────────────────────────
    // AI assistant
    // ──────────────────────────────────────────────────────────────────

    public function setAction(string $action): void
    {
        $this->aiAction = array_key_exists($action, SavedAiPrompt::ACTIONS) ? $action : 'standup';
        $this->aiTemplateId = null;
        $this->resetAiOutput();
    }

    public function runAi(): void
    {
        if (! $this->inputsReady()) {
            return;
        }

        $instruction = $this->aiTemplateId === null ? null : app(SavedPromptService::class)->find($this->aiTemplateId)->custom_instruction;

        $result = app(AiPmoService::class)->run($this->aiAction, $this->aiNotes, $this->selectedTask(), $instruction);

        $this->resetAiOutput();
        $this->aiResult = $result->toArray();
        $this->manualPrompt = $result->prompt;
        $this->primeCaptureSelection();
        $this->refreshAudit();

        match ($result->status) {
            AutomationLog::STATUS_SENT => Flux::toast(variant: 'success', heading: 'AI answer ready', text: SavedAiPrompt::ACTIONS[$this->aiAction]['label'].' generated by the live AI engine.'),
            AutomationLog::STATUS_SIMULATED => Flux::toast(variant: 'warning', heading: 'Simulation Mode', text: 'No AI key in .env — a smart simulated answer was generated from your board.'),
            default => Flux::toast(variant: 'danger', heading: 'AI call failed', text: ($result->error ?? 'Unknown error').' A simulated answer is shown instead.'),
        };
    }

    public function runTemplate(int $id): void
    {
        $template = app(SavedPromptService::class)->find($id);

        $this->aiAction = $template->action_type;
        $this->resetAiOutput();
        $this->aiTemplateId = $template->id;

        $this->runAi();
    }

    public function clearTemplate(): void
    {
        $this->aiTemplateId = null;
    }

    public function openManual(): void
    {
        if (! $this->inputsReady()) {
            return;
        }

        $instruction = $this->aiTemplateId === null ? null : app(SavedPromptService::class)->find($this->aiTemplateId)->custom_instruction;
        $this->manualPrompt = app(AiPmoService::class)->promptFor($this->aiAction, $this->aiNotes, $this->selectedTask(), $instruction);
        $this->manualAnswer = '';
        $this->showManual = true;
    }

    public function useManualAnswer(): void
    {
        $this->validate(['manualAnswer' => ['required', 'string', 'max:100000']], attributes: ['manualAnswer' => 'answer']);

        $result = app(AiPmoService::class)->parseManualAnswer($this->aiAction, $this->manualAnswer, $this->selectedTask());

        if ($result->status === AutomationLog::STATUS_FAILED) {
            $this->addError('manualAnswer', (string) $result->error);

            return;
        }

        $this->aiResult = $result->toArray();
        $this->primeCaptureSelection();
        $this->showManual = false;
        Flux::toast(variant: 'success', heading: 'Answer imported', text: 'Your pasted answer is ready to review.');
    }

    public function applyCapture(): void
    {
        $items = $this->aiResult['data']['tasks'] ?? [];
        $selected = array_keys(array_filter($this->captureSelected));
        $column = $this->captureColumnId === null ? null : $this->columnOptions->firstWhere('id', $this->captureColumnId);

        if ($column === null) {
            $this->addError('captureColumnId', 'Choose a column for the new cards.');

            return;
        }
        if ($selected === []) {
            Flux::toast(variant: 'warning', heading: 'Nothing selected', text: 'Tick at least one task to add.');

            return;
        }

        $service = app(TaskService::class);
        $created = 0;
        $registered = [];
        foreach ($selected as $index) {
            $item = $items[$index] ?? null;
            if (! is_array($item) || trim((string) ($item['title'] ?? '')) === '') {
                continue;
            }

            $service->create([
                'board_column_id' => $column->id,
                'title' => Str::limit((string) $item['title'], 250, ''),
                'project_name' => ($item['project'] ?? '') !== '' ? (string) $item['project'] : null,
                'pic_name' => ($item['pic'] ?? '') !== '' ? (string) $item['pic'] : null,
                'priority' => in_array($item['priority'] ?? '', ['high', 'medium', 'low'], true) ? (string) $item['priority'] : 'medium',
                'icon' => '🧲',
                'color' => 'violet',
                'due_date' => ($item['due_date'] ?? '') !== '' ? (string) $item['due_date'] : null,
                'tags' => ['ai-capture'],
                'notes' => ($item['notes'] ?? '') !== '' ? (string) $item['notes'] : null,
            ]);
            $registered = [...$registered, ...$service->registered()];
            $created++;
        }

        Flux::toast(
            variant: 'success',
            heading: 'Tasks added to board',
            text: "{$created} ".Str::plural('card', $created)." added to “{$column->name}”.".($registered === [] ? '' : ' Newly registered — '.implode(', ', array_unique($registered)).'.'),
        );
        $this->resetAiOutput();
    }

    public function applySubtasks(): void
    {
        $taskId = (int) ($this->aiResult['data']['task_id'] ?? 0);
        $subtasks = array_values(array_filter((array) ($this->aiResult['data']['subtasks'] ?? []), 'is_string'));

        $added = app(TaskService::class)->appendSubtasks($taskId, $subtasks);

        Flux::toast(variant: 'success', heading: 'Subtasks added', text: "{$added} ".Str::plural('subtask', $added).' added to the task.');
        $this->resetAiOutput();
    }

    public function applyImprovement(): void
    {
        $taskId = (int) ($this->aiResult['data']['task_id'] ?? 0);
        $proposed = (array) ($this->aiResult['data']['proposed'] ?? []);

        $task = app(TaskService::class)->applyImprovement(
            $taskId,
            (string) ($proposed['title'] ?? ''),
            (string) ($proposed['priority'] ?? ''),
            ($proposed['due_date'] ?? '') === '' ? null : (string) $proposed['due_date'],
        );

        Flux::toast(variant: 'success', heading: 'Task improved', text: "“{$task->title}” has been updated.");
        $this->resetAiOutput();
        unset($this->taskOptions);
    }

    // ──────────────────────────────────────────────────────────────────
    // Omnichannel dispatch (manual trigger + test alerts)
    // ──────────────────────────────────────────────────────────────────

    public function dispatchTelegram(): void
    {
        $this->validate(['telegramChatId' => ['nullable', 'string', 'max:64', 'regex:/^[-@\w]+$/']], attributes: ['telegramChatId' => 'chat ID']);

        $result = app(OmnichannelNotifierService::class)->sendTelegram(
            app(StandupGeneratorService::class)->generate(),
            $this->telegramChatId !== '' ? $this->telegramChatId : null,
            'notify:daily_standup',
        );

        $this->afterDispatch('Dispatch to Telegram', $result->toArray());
    }

    public function dispatchEmail(): void
    {
        $this->validate(['emailTo' => ['nullable', 'email', 'max:255']], attributes: ['emailTo' => 'email address']);

        $result = app(OmnichannelNotifierService::class)->sendEmail(
            'Daily Standup Report — '.Carbon::now((string) config('pmo.timezone'))->format('j M Y'),
            app(StandupGeneratorService::class)->generate(null, StandupGeneratorService::FORMAT_PLAIN),
            $this->emailTo !== '' ? $this->emailTo : null,
            'notify:daily_standup',
        );

        $this->afterDispatch('Email Standup Report', $result->toArray());
    }

    public function dispatchWhatsApp(): void
    {
        $this->validate(['whatsappPhone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s\-]{6,28}$/']], attributes: ['whatsappPhone' => 'WhatsApp number']);

        $result = app(OmnichannelNotifierService::class)->sendWhatsApp(
            app(StandupGeneratorService::class)->generate(),
            $this->whatsappPhone !== '' ? $this->whatsappPhone : null,
            'notify:daily_standup',
        );

        $this->afterDispatch('Share via WhatsApp', $result->toArray());
    }

    public function sendTestAlert(string $channel): void
    {
        $notifier = app(OmnichannelNotifierService::class);
        $message = '✅ Test alert from Kanban PMO — '.Carbon::now((string) config('pmo.timezone'))->format('j M Y, H:i').'. If you can read this, the channel is working.';

        $result = match ($channel) {
            'telegram' => $notifier->sendTelegram($message, null, 'notify:test_alert'),
            'gmail' => $notifier->sendEmail('Kanban PMO — Test Alert', $message, null, 'notify:test_alert'),
            'whatsapp' => $notifier->sendWhatsApp($message, null, 'notify:test_alert'),
            default => null,
        };

        if ($result !== null) {
            $this->afterDispatch('Send Test Alert', $result->toArray());
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Saved prompt templates (CRUD)
    // ──────────────────────────────────────────────────────────────────

    public function createPrompt(): void
    {
        $this->promptForm->reset();
        $this->promptForm->resetErrorBag();
        $this->promptForm->action_type = $this->aiAction;
        $this->showPromptModal = true;
    }

    public function editPrompt(int $id): void
    {
        $this->promptForm->fillFrom(app(SavedPromptService::class)->find($id));
        $this->showPromptModal = true;
    }

    public function savePrompt(): void
    {
        $data = $this->promptForm->payload();
        $service = app(SavedPromptService::class);

        if ($this->promptForm->id !== null) {
            $prompt = $service->update($this->promptForm->id, $data);
            Flux::toast(variant: 'success', heading: 'Template updated', text: "“{$prompt->title}” has been saved.");
        } else {
            $prompt = $service->create($data);
            Flux::toast(variant: 'success', heading: 'Template created', text: "“{$prompt->title}” is ready to run.");
        }

        $this->closeModals();
    }

    public function confirmDeletePrompt(int $id): void
    {
        $prompt = app(SavedPromptService::class)->find($id);
        $this->deletePromptId = $prompt->id;
        $this->deletePromptTitle = $prompt->title;
        $this->showPromptModal = false;
        $this->showPromptDeleteModal = true;
    }

    public function destroyPrompt(): void
    {
        if ($this->deletePromptId !== null) {
            app(SavedPromptService::class)->delete($this->deletePromptId);

            if ($this->aiTemplateId === $this->deletePromptId) {
                $this->aiTemplateId = null;
            }

            Flux::toast(variant: 'success', heading: 'Template deleted', text: "“{$this->deletePromptTitle}” was removed.");
        }

        $this->closeModals();
    }

    // ──────────────────────────────────────────────────────────────────
    // Execution audit logs
    // ──────────────────────────────────────────────────────────────────

    public function setLogChannel(string $group): void
    {
        $this->logChannel = array_key_exists($group, AutomationLog::CHANNEL_GROUPS) ? $group : 'all';
        $this->resetPage();
    }

    public function setLogStatus(string $status): void
    {
        $this->logStatus = $status === 'all' || array_key_exists($status, AutomationLog::STATUSES) ? $status : 'all';
        $this->resetPage();
    }

    public function confirmClearLogs(): void
    {
        $this->clearRange = '30';
        $this->clearCount = app(AutomationLogger::class)->clearPreview($this->clearRange);
        $this->showClearModal = true;
    }

    public function updatedClearRange(): void
    {
        if (! array_key_exists($this->clearRange, AutomationLogger::CLEAR_RANGES)) {
            $this->clearRange = '30';
        }

        $this->clearCount = app(AutomationLogger::class)->clearPreview($this->clearRange);
    }

    public function clearLogs(): void
    {
        $removed = app(AutomationLogger::class)->clear($this->clearRange);

        Flux::toast(
            variant: 'success',
            heading: 'Audit logs cleared',
            text: $removed > 0 ? "{$removed} ".Str::plural('record', $removed).' removed.' : 'There was nothing to remove.',
        );

        $this->closeModals();
        $this->resetPage();
        $this->refreshAudit();
    }

    public function closeModals(): void
    {
        $this->showPromptModal = false;
        $this->showPromptDeleteModal = false;
        $this->showClearModal = false;
        $this->deletePromptId = null;
        $this->deletePromptTitle = '';
        $this->clearCount = 0;
        $this->promptForm->resetErrorBag();
    }

    public function render(): View
    {
        return view('livewire.pmo.ai-omnichannel-hub', [
            'actions' => SavedAiPrompt::ACTIONS,
            'channelGroups' => AutomationLog::CHANNEL_GROUPS,
            'statuses' => AutomationLog::STATUSES,
            'clearRanges' => AutomationLogger::CLEAR_RANGES,
            'aiProvider' => config('pmo.ai.provider'),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────

    private function inputsReady(): bool
    {
        $needs = SavedAiPrompt::ACTIONS[$this->aiAction]['needs'] ?? null;

        if ($needs === 'notes' && trim($this->aiNotes) === '') {
            $this->addError('aiNotes', 'Paste meeting notes or an email to capture tasks from.');

            return false;
        }
        if ($needs === 'notes' && mb_strlen($this->aiNotes) > 20000) {
            $this->addError('aiNotes', 'Notes are too long (max 20,000 characters). Split them into smaller parts.');

            return false;
        }
        if ($needs === 'task' && $this->selectedTask() === null) {
            $this->addError('aiTaskId', 'Choose a task first.');

            return false;
        }

        $this->resetErrorBag(['aiNotes', 'aiTaskId']);

        return true;
    }

    private function selectedTask(): ?Task
    {
        $needs = SavedAiPrompt::ACTIONS[$this->aiAction]['needs'] ?? null;

        if ($needs !== 'task' || $this->aiTaskId === null) {
            return null;
        }

        return app(TaskService::class)->find($this->aiTaskId);
    }

    private function resetAiOutput(): void
    {
        $this->aiResult = null;
        $this->showManual = false;
        $this->manualAnswer = '';
        $this->captureSelected = [];
        $this->resetErrorBag(['manualAnswer', 'captureColumnId']);
    }

    private function primeCaptureSelection(): void
    {
        $count = count((array) ($this->aiResult['data']['tasks'] ?? []));
        $this->captureSelected = $count === 0 ? [] : array_fill(0, $count, true);

        if ($this->captureColumnId === null) {
            $this->captureColumnId = $this->columnOptions->first(fn (BoardColumn $c): bool => ! $c->is_done_column)->id
                ?? $this->columnOptions->first()->id ?? null;
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function afterDispatch(string $label, array $result): void
    {
        array_unshift($this->dispatchResults, ['label' => $label, 'at' => now()->toIso8601String(), ...$result]);
        $this->dispatchResults = array_slice($this->dispatchResults, 0, 5);
        $this->refreshAudit();

        match ($result['status']) {
            AutomationLog::STATUS_SENT => Flux::toast(variant: 'success', heading: $label, text: (string) $result['summary']),
            AutomationLog::STATUS_SIMULATED => Flux::toast(variant: 'warning', heading: $label.' — Simulation Mode', text: (string) $result['summary']),
            default => Flux::toast(variant: 'danger', heading: $label.' failed', text: (string) $result['summary']),
        };
    }

    private function refreshAudit(): void
    {
        unset($this->logs, $this->stats);
    }
}
