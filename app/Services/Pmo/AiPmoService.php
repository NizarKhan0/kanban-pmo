<?php

declare(strict_types=1);

namespace App\Services\Pmo;

use App\Models\AutomationLog;
use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Task;
use App\Models\TeamMember;
use App\Services\Pmo\Ai\AiProviderException;
use App\Services\Pmo\Ai\AiProviderGateway;
use App\Services\Pmo\Ai\AiResult;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * AI PMO Assistant: five board-aware actions with Smart Simulation fallback.
 *
 * Every run is written to automation_logs as "sent" (live provider answer),
 * "simulated" (no credentials in .env — answer generated locally from the
 * board) or "failed" (provider error — a simulated answer is returned instead,
 * so the user is never left without a result). All data is scoped to Auth::id().
 */
final class AiPmoService
{
    public const ACTIONS = ['standup', 'risk', 'capture', 'subtasks', 'improve_task'];

    private const SYSTEM = <<<'TXT'
        You are an experienced PMO (project management office) assistant for a software delivery team.
        You receive a snapshot of the team's Kanban board and help the PMO run the day.
        Write in clear, professional English. Be specific: name the actual tasks, people and dates from the snapshot, and never invent tasks, people or projects that are not in it.
        Plain-text answers are pasted into WhatsApp, Telegram or email, so use short headings, simple bullet points ("•") and no tables or Markdown links.
        TXT;

    private const MAX_CONTEXT_TASKS = 300;

    public function __construct(
        private readonly AiProviderGateway $gateway,
        private readonly AutomationLogger $logs,
        private readonly TaskService $tasks,
        private readonly BoardColumnService $columns,
    ) {}

    // ──────────────────────────────────────────────────────────────────
    // The five actions
    // ──────────────────────────────────────────────────────────────────

    public function generateStandupSummary(?string $customInstruction = null): AiResult
    {
        return $this->run('standup', customInstruction: $customInstruction);
    }

    public function analyzeRisksAndBlockers(?string $customInstruction = null): AiResult
    {
        return $this->run('risk', customInstruction: $customInstruction);
    }

    public function captureTasksFromNotes(string $rawNotes, ?string $customInstruction = null): AiResult
    {
        return $this->run('capture', notes: $rawNotes, customInstruction: $customInstruction);
    }

    public function breakIntoSubtasks(Task $task, ?string $customInstruction = null): AiResult
    {
        return $this->run('subtasks', task: $task, customInstruction: $customInstruction);
    }

    public function improveTaskDefinition(Task $task, ?string $customInstruction = null): AiResult
    {
        return $this->run('improve_task', task: $task, customInstruction: $customInstruction);
    }

    /**
     * Dispatcher used by the hub and saved prompt templates.
     */
    public function run(string $action, ?string $notes = null, ?Task $task = null, ?string $customInstruction = null): AiResult
    {
        $action = in_array($action, self::ACTIONS, true) ? $action : 'standup';
        $task = $this->ownedTask($task);
        [$user, $schema] = $this->buildPrompt($action, $notes, $task, $customInstruction);
        $command = 'ai:'.$action;
        $channel = $this->gateway->channel();
        $model = $this->gateway->model();

        if (! $this->gateway->isConfigured()) {
            [$text, $data] = $this->simulate($action, (string) $notes, $task);
            $log = $this->logs->record($channel, $command, $user, AutomationLog::STATUS_SIMULATED, $model, $this->payloadFor($text, $data));

            return new AiResult($action, AutomationLog::STATUS_SIMULATED, $text, $data, $this->manualPrompt($user, $schema), 'simulation', null, $log->id);
        }

        try {
            $completion = $this->gateway->complete(self::SYSTEM, $user, $schema);
            [$text, $data] = $this->parseAnswer($action, $completion->text, $task);
        } catch (AiProviderException $e) {
            [$text, $data] = $this->simulate($action, (string) $notes, $task);
            $log = $this->logs->record($channel, $command, $user, AutomationLog::STATUS_FAILED, $model, null, 0, $e->getMessage());

            return new AiResult($action, AutomationLog::STATUS_FAILED, $text, $data, $this->manualPrompt($user, $schema), 'simulation', $e->getMessage(), $log->id);
        }

        $log = $this->logs->record($channel, $command, $user, AutomationLog::STATUS_SENT, $completion->model, $completion->text, $completion->tokens);

        return new AiResult($action, AutomationLog::STATUS_SENT, $text, $data, $this->manualPrompt($user, $schema), 'live', null, $log->id);
    }

    /**
     * "Paste JSON Answer": parse an answer the user obtained manually (e.g. from a chat assistant).
     */
    public function parseManualAnswer(string $action, string $answer, ?Task $task = null): AiResult
    {
        $action = in_array($action, self::ACTIONS, true) ? $action : 'standup';
        $task = $this->ownedTask($task);

        try {
            [$text, $data] = $this->parseAnswer($action, $answer, $task);
        } catch (AiProviderException $e) {
            return new AiResult($action, AutomationLog::STATUS_FAILED, '', [], '', 'manual', $e->getMessage());
        }

        return new AiResult($action, AutomationLog::STATUS_SENT, $text, $data, '', 'manual');
    }

    /**
     * Copyable prompt for the manual "Copy Prompt" flow.
     */
    public function promptFor(string $action, ?string $notes = null, ?Task $task = null, ?string $customInstruction = null): string
    {
        $action = in_array($action, self::ACTIONS, true) ? $action : 'standup';
        [$user, $schema] = $this->buildPrompt($action, $notes, $this->ownedTask($task), $customInstruction);

        return $this->manualPrompt($user, $schema);
    }

    // ──────────────────────────────────────────────────────────────────
    // Prompt building
    // ──────────────────────────────────────────────────────────────────

    /**
     * @return array{0: string, 1: array<string, mixed>|null} [user prompt, JSON schema]
     */
    private function buildPrompt(string $action, ?string $notes, ?Task $task, ?string $custom): array
    {
        $context = $this->boardContext();
        $extra = trim((string) $custom) === '' ? '' : "\n\nAdditional instructions from the PMO:\n".trim((string) $custom);

        return match ($action) {
            'standup' => [
                $context."\n\nWrite today's daily standup summary with four short sections: Achievements (recently completed work), Work in Progress, Blockers & Overdue, and Today's Top 3 Focus (each with a one-line reason).".$extra,
                null,
            ],
            'risk' => [
                $context."\n\nAnalyse delivery risk on this board: overdue cards, WIP limit breaches, workload imbalance between PICs and any blockers in the notes. Start with an overall risk level (Low / Medium / High), then the findings, then exactly 3 concrete corrective actions naming who should do what.".$extra,
                null,
            ],
            'capture' => [
                $this->peopleAndProjects()."\n\nToday is ".Task::today().".\n\nExtract every actionable task from the meeting notes / email below. Reuse the exact names of known projects and PICs when they are mentioned. Use YYYY-MM-DD for due dates (resolve relative dates such as \"Friday\" from today) or an empty string when no date is given; use an empty string for unknown project or PIC. Priority is high, medium or low.\n\n--- NOTES START ---\n".trim((string) $notes)."\n--- NOTES END ---".$extra,
                $this->captureSchema(),
            ],
            'subtasks' => [
                $context."\n\nBreak the following task into 3 to 7 concrete, sequential subtasks that a team member can tick off. Do not repeat existing subtasks.\n\n".$this->taskDetail($task).$extra,
                $this->subtaskSchema(),
            ],
            default => [
                $context."\n\nImprove the definition of the following card: make the title a clear, action-oriented outcome (max 120 characters), choose the right priority (high, medium or low) and propose a realistic due date (YYYY-MM-DD, not in the past). Explain the reasoning briefly.\n\n".$this->taskDetail($task).$extra,
                $this->improveSchema(),
            ],
        };
    }

    /**
     * @param  array<string, mixed>|null  $schema
     */
    private function manualPrompt(string $user, ?array $schema): string
    {
        $prompt = self::SYSTEM."\n\n".$user;

        if ($schema !== null) {
            $prompt .= "\n\nRespond with JSON only (no commentary), matching this JSON Schema:\n"
                .json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return $prompt;
    }

    private function boardContext(): string
    {
        $tz = (string) config('pmo.timezone', 'Asia/Kuala_Lumpur');
        $columns = $this->columns->list();
        $counts = $this->columns->cardCounts($columns);
        $board = $this->tasks->board(new TaskFilters);

        $lines = ['Today: '.Carbon::now($tz)->format('Y-m-d (l)')." · timezone {$tz}", '', 'Board columns (in workflow order):'];
        foreach ($columns as $column) {
            $count = $counts[$column->id] ?? 0;
            $wip = $column->wip_limit > 0 ? "WIP limit {$column->wip_limit}".($count > $column->wip_limit ? ' — OVER LIMIT' : '') : 'no WIP limit';
            $lines[] = "- {$column->name}: {$count} cards, {$wip}".($column->is_done_column ? ', done column' : '');
        }

        $lines[] = '';
        $lines[] = 'Tasks:';
        $listed = 0;
        $total = 0;
        foreach ($columns as $column) {
            foreach ($board[$column->id] ?? [] as $task) {
                $total++;
                if ($listed >= self::MAX_CONTEXT_TASKS) {
                    continue;
                }
                $lines[] = '- '.$this->taskLine($task);
                $listed++;
            }
        }
        if ($total === 0) {
            $lines[] = '(no tasks on the board)';
        } elseif ($listed < $total) {
            $lines[] = '('.($total - $listed).' more tasks not listed)';
        }

        $lines[] = '';
        $lines[] = 'Open tasks per PIC: '.$this->workloadLine($board);

        return implode("\n", $lines);
    }

    private function taskLine(Task $task): string
    {
        [$done, $total] = $task->subtaskCounts();
        $parts = ["[{$task->column->name}] {$task->title}", 'PIC: '.($task->picLabel() ?? 'unassigned')];

        if ($task->projectLabel()) {
            $parts[] = 'Project: '.$task->projectLabel();
        }
        $parts[] = 'Priority: '.$task->priority;
        if ($task->due_date) {
            $parts[] = 'Due: '.$task->due_date->toDateString().($task->isOverdue() ? ' (OVERDUE)' : '');
        }
        if ($total > 0) {
            $parts[] = "Subtasks {$done}/{$total}";
        }
        if ($task->notes) {
            $parts[] = 'Notes: '.Str::limit(Str::squish($task->notes), 200);
        }

        return implode(' | ', $parts);
    }

    /**
     * @param  array<int, Collection<int, Task>>  $board
     */
    private function workloadLine(array $board): string
    {
        $open = [];
        foreach ($board as $cards) {
            foreach ($cards as $task) {
                if (! $task->isCompleted()) {
                    $name = $task->picLabel() ?? 'Unassigned';
                    $open[$name] = ($open[$name] ?? 0) + 1;
                }
            }
        }

        foreach ($this->members() as $member) {
            if ($member->is_active) {
                $open[$member->name] ??= 0;
            }
        }

        if ($open === []) {
            return 'none';
        }

        arsort($open);

        return implode(', ', array_map(fn (string $n, int $c): string => "{$n} {$c}", array_keys($open), $open));
    }

    private function taskDetail(?Task $task): string
    {
        if ($task === null) {
            return 'Task: (none selected)';
        }

        $lines = ['Task: '.$this->taskLine($task)];
        $existing = $task->subtasks->pluck('title')->all();
        $lines[] = 'Existing subtasks: '.($existing === [] ? 'none' : implode('; ', $existing));

        return implode("\n", $lines);
    }

    private function peopleAndProjects(): string
    {
        $projects = Project::query()->where('user_id', $this->ownerId())->orderBy('name')->pluck('name')->all();
        $people = $this->members()->map(fn (TeamMember $m): string => $m->name.($m->role ? " ({$m->role})" : ''))->all();

        return 'Known projects: '.($projects === [] ? 'none' : implode(', ', $projects))
            ."\nKnown PICs: ".($people === [] ? 'none' : implode(', ', $people));
    }

    // ──────────────────────────────────────────────────────────────────
    // Structured output schemas (strict: every field required, no extras)
    // ──────────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function captureSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'tasks' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'project' => ['type' => 'string'],
                            'pic' => ['type' => 'string'],
                            'priority' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                            'due_date' => ['type' => 'string'],
                            'notes' => ['type' => 'string'],
                        ],
                        'required' => ['title', 'project', 'pic', 'priority', 'due_date', 'notes'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['tasks'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function subtaskSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['subtasks' => ['type' => 'array', 'items' => ['type' => 'string']]],
            'required' => ['subtasks'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function improveSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'priority' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                'due_date' => ['type' => 'string'],
                'rationale' => ['type' => 'string'],
            ],
            'required' => ['title', 'priority', 'due_date', 'rationale'],
            'additionalProperties' => false,
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // Answer parsing (live + manual)
    // ──────────────────────────────────────────────────────────────────

    /**
     * @return array{0: string, 1: array<string, mixed>}
     *
     * @throws AiProviderException
     */
    private function parseAnswer(string $action, string $answer, ?Task $task): array
    {
        if (in_array($action, ['standup', 'risk'], true)) {
            $text = trim($answer);
            if ($text === '') {
                throw new AiProviderException('The answer is empty.');
            }

            return [$text, []];
        }

        $json = $this->decodeJson($answer);

        return match ($action) {
            'capture' => $this->captureResult($this->normaliseCaptured($json['tasks'] ?? (array_is_list($json) ? $json : []))),
            'subtasks' => $this->subtaskResult($task, $this->normaliseSubtasks($json['subtasks'] ?? (array_is_list($json) ? $json : []), $task)),
            default => $this->improveResult($task, $json),
        };
    }

    /**
     * @return array<mixed>
     *
     * @throws AiProviderException
     */
    private function decodeJson(string $answer): array
    {
        $candidates = [trim($answer)];
        if (preg_match('/```(?:json)?\s*(.+?)```/s', $answer, $m)) {
            $candidates[] = trim($m[1]);
        }
        foreach ([['{', '}'], ['[', ']']] as [$open, $close]) {
            $start = strpos($answer, $open);
            $end = strrpos($answer, $close);
            if ($start !== false && $end !== false && $end > $start) {
                $candidates[] = substr($answer, $start, $end - $start + 1);
            }
        }

        foreach ($candidates as $candidate) {
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new AiProviderException('The answer is not valid JSON. Paste the JSON object exactly as returned.');
    }

    /**
     * @return list<array{title: string, project: string, pic: string, priority: string, due_date: string, notes: string}>
     */
    private function normaliseCaptured(mixed $items): array
    {
        $tasks = [];
        foreach (is_array($items) ? $items : [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $title = Str::limit(trim((string) ($item['title'] ?? '')), 250, '');
            if ($title === '') {
                continue;
            }
            $priority = strtolower(trim((string) ($item['priority'] ?? 'medium')));
            $tasks[] = [
                'title' => $title,
                'project' => Str::limit(trim((string) ($item['project'] ?? '')), 250, ''),
                'pic' => Str::limit(trim((string) ($item['pic'] ?? '')), 250, ''),
                'priority' => in_array($priority, ['high', 'medium', 'low'], true) ? $priority : 'medium',
                'due_date' => $this->validDate((string) ($item['due_date'] ?? '')) ?? '',
                'notes' => Str::limit(trim((string) ($item['notes'] ?? '')), 2000, ''),
            ];
            if (count($tasks) >= 25) {
                break;
            }
        }

        return $tasks;
    }

    /**
     * @return list<string>
     */
    private function normaliseSubtasks(mixed $items, ?Task $task): array
    {
        $existing = array_map(mb_strtolower(...), $task?->subtasks->pluck('title')->all() ?? []);
        $titles = [];

        foreach (is_array($items) ? $items : [] as $item) {
            $title = Str::limit(trim(is_array($item) ? (string) ($item['title'] ?? '') : (string) $item), 250, '');
            $key = mb_strtolower($title);
            if ($title !== '' && ! in_array($key, $existing, true) && ! in_array($key, array_map(mb_strtolower(...), $titles), true)) {
                $titles[] = $title;
            }
        }

        return array_slice($titles, 0, 7);
    }

    /**
     * @param  list<array{title: string, project: string, pic: string, priority: string, due_date: string, notes: string}>  $tasks
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function captureResult(array $tasks): array
    {
        $text = $tasks === []
            ? 'No actionable tasks were found in the notes.'
            : count($tasks).' '.Str::plural('task', count($tasks)).' found. Review the checklist, then add the selected ones to your board.';

        return [$text, ['tasks' => $tasks]];
    }

    /**
     * @param  list<string>  $subtasks
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function subtaskResult(?Task $task, array $subtasks): array
    {
        $text = $subtasks === []
            ? 'No new subtasks were suggested.'
            : count($subtasks).' '.Str::plural('subtask', count($subtasks)).' suggested for “'.($task->title ?? 'task').'”.';

        return [$text, ['task_id' => $task?->id, 'subtasks' => $subtasks]];
    }

    /**
     * @param  array<mixed>  $json
     * @return array{0: string, 1: array<string, mixed>}
     *
     * @throws AiProviderException
     */
    private function improveResult(?Task $task, array $json): array
    {
        if ($task === null) {
            throw new AiProviderException('Select a task to improve first.');
        }

        $title = Str::limit(trim((string) ($json['title'] ?? '')), 250, '');
        $priority = strtolower(trim((string) ($json['priority'] ?? $task->priority)));

        $proposed = [
            'title' => $title !== '' ? $title : $task->title,
            'priority' => in_array($priority, ['high', 'medium', 'low'], true) ? $priority : ($task->priority === 'done' ? 'medium' : $task->priority),
            'due_date' => $this->validDate((string) ($json['due_date'] ?? '')) ?? $task->due_date?->toDateString() ?? '',
        ];

        return [
            trim((string) ($json['rationale'] ?? '')) ?: 'Suggested improvements are ready to review.',
            [
                'task_id' => $task->id,
                'current' => ['title' => $task->title, 'priority' => $task->priority, 'due_date' => $task->due_date?->toDateString() ?? ''],
                'proposed' => $proposed,
            ],
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // Smart Simulation (board-aware, deterministic, free)
    // ──────────────────────────────────────────────────────────────────

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function simulate(string $action, string $notes, ?Task $task): array
    {
        return match ($action) {
            'standup' => [$this->simulatedStandup(), []],
            'risk' => [$this->simulatedRisk(), []],
            'capture' => $this->captureResult($this->simulatedCapture($notes)),
            'subtasks' => $this->subtaskResult($task, $this->simulatedSubtasks($task)),
            default => $task === null
                ? ['Select a task to improve first.', []]
                : $this->improveResult($task, $this->simulatedImprovement($task)),
        };
    }

    /**
     * @return Collection<int, Task>
     */
    private function allTasks(): Collection
    {
        $all = new Collection;
        foreach ($this->tasks->board(new TaskFilters) as $cards) {
            foreach ($cards as $task) {
                $all->push($task);
            }
        }

        return $all;
    }

    private function simulatedStandup(): string
    {
        $tasks = $this->allTasks();
        $open = $tasks->reject(fn (Task $t): bool => $t->isCompleted());
        $first = $this->columns->list()->first();
        $notStarted = fn (Task $t): bool => ($first !== null && $t->board_column_id === $first->id)
            || (bool) preg_match('/\b(backlog|to ?do|todo|ready|planned|queue)\b/i', $t->column->name);

        $done = $tasks->filter(fn (Task $t): bool => $t->isCompleted())
            ->sortByDesc(fn (Task $t): int => $t->completed_at?->getTimestamp() ?? 0)->take(5);
        $wip = $open->reject($notStarted)->take(8);
        $blocked = $open->filter(fn (Task $t): bool => $t->isOverdue() || Str::contains(Str::lower((string) $t->notes), ['block', 'waiting', 'awaiting', 'pending']));

        $lines = ['⚙️ Simulation Mode — generated from your live board (no AI key configured).', '', 'DAILY STANDUP SUMMARY — '.Carbon::parse(Task::today())->format('l, j F Y'), ''];

        $lines[] = '✅ Achievements';
        $lines = [...$lines, ...($done->isEmpty() ? ['• No cards completed yet.'] : $done->map(fn (Task $t): string => '• '.$t->title.$this->who($t))->all())];

        $lines[] = '';
        $lines[] = '⚡ Work in Progress';
        $lines = [...$lines, ...($wip->isEmpty() ? ['• Nothing in progress.'] : $wip->map(fn (Task $t): string => "• {$t->title}{$this->who($t)} — {$t->column->name}".$this->progress($t))->all())];

        $lines[] = '';
        $lines[] = '🚧 Blockers & Overdue';
        $lines = [...$lines, ...($blocked->isEmpty() ? ['• No blockers or overdue cards.'] : $blocked->map(fn (Task $t): string => '• '.($t->isOverdue() ? '[OVERDUE] ' : '').$t->title.$this->who($t).($t->notes ? ' — '.Str::limit(Str::squish($t->notes), 90) : ''))->all())];

        $lines[] = '';
        $lines[] = '🎯 Today’s Top 3 Focus';
        $focus = $this->rankFocus($open)->take(3)->values();
        $lines = [...$lines, ...($focus->isEmpty() ? ['• Pull the next card from the backlog.'] : $focus->map(fn (Task $t, int $i): string => ($i + 1).'. '.$t->title.$this->who($t).' — '.$this->focusReason($t))->all())];

        return implode("\n", $lines);
    }

    private function simulatedRisk(): string
    {
        $tasks = $this->allTasks();
        $open = $tasks->reject(fn (Task $t): bool => $t->isCompleted());
        $overdue = $open->filter(fn (Task $t): bool => $t->isOverdue())->sortBy(fn (Task $t): string => (string) $t->due_date?->toDateString());

        $columns = $this->columns->list();
        $counts = $this->columns->cardCounts($columns);
        $breaches = $columns->filter(fn (BoardColumn $c): bool => $c->isOverWip($counts[$c->id] ?? 0));

        $threshold = max(1, (int) config('pmo.roster.overload_threshold', 5));
        $load = [];
        foreach ($this->members()->where('is_active', true) as $member) {
            $load[$member->name] = $open->where('team_member_id', $member->id)->count();
        }
        arsort($load);
        $overloaded = array_filter($load, fn (int $n): bool => $n >= $threshold);
        $names = array_keys($load);
        $busiest = $names[0] ?? '';
        $quietest = $names[count($names) - 1] ?? '';
        $imbalanced = count($names) > 1 && $load[$busiest] - $load[$quietest] >= 3;
        $unassigned = $open->whereNull('team_member_id')->count();

        $level = $overdue->count() >= 3 || $breaches->isNotEmpty() || $overloaded !== [] ? 'High' : ($overdue->isNotEmpty() || $imbalanced ? 'Medium' : 'Low');
        $today = Carbon::parse(Task::today());

        $lines = ['⚙️ Simulation Mode — generated from your live board (no AI key configured).', '', "RISK & BLOCKER ANALYSIS — Overall risk: {$level}", ''];

        $lines[] = '⏰ Overdue cards ('.$overdue->count().')';
        $lines = [...$lines, ...($overdue->isEmpty() ? ['• None.'] : $overdue->map(fn (Task $t): string => "• {$t->title}{$this->who($t)} — ".(int) $t->due_date?->diffInDays($today).' day(s) late')->all())];

        $lines[] = '';
        $lines[] = '🚦 WIP limit breaches ('.$breaches->count().')';
        $lines = [...$lines, ...($breaches->isEmpty() ? ['• All columns are within their WIP limits.'] : $breaches->map(fn (BoardColumn $c): string => "• {$c->name}: ".($counts[$c->id] ?? 0)." cards vs limit {$c->wip_limit}")->values()->all())];

        $lines[] = '';
        $lines[] = '⚖️ Workload balance (open tasks, capacity '.$threshold.')';
        $lines = [...$lines, ...($load === [] ? ['• No active PICs on the roster.'] : array_map(fn (string $n, int $c): string => "• {$n}: {$c}".($c >= $threshold ? ' — OVERLOADED' : ''), array_keys($load), $load))];
        if ($unassigned > 0) {
            $lines[] = "• Unassigned: {$unassigned}";
        }

        $recommendations = [];
        if ($overdue->isNotEmpty()) {
            $recommendations[] = 'Re-plan overdue work today: agree new dates or scope cuts for '.$overdue->take(2)->map(fn (Task $t): string => '“'.$t->title.'”')->implode(' and ').'.';
        }
        if ($breaches->isNotEmpty()) {
            $c = $breaches->first();
            $recommendations[] = "Stop starting, start finishing in {$c->name}: move or complete ".(($counts[$c->id] ?? 0) - $c->wip_limit).' card(s) before pulling new work.';
        }
        if ($imbalanced || $overloaded !== []) {
            $recommendations[] = "Rebalance workload: move 1–2 cards from {$busiest} ({$load[$busiest]}) to {$quietest} ({$load[$quietest]}).";
        }
        if ($unassigned > 0) {
            $recommendations[] = "Assign an owner to the {$unassigned} unassigned open ".Str::plural('card', $unassigned).'.';
        }
        $recommendations[] = 'Break large cards without subtasks into checklists so progress is visible.';
        $recommendations[] = 'Review blockers in the daily standup and escalate anything older than 2 days.';

        $lines[] = '';
        $lines[] = '🛠️ Recommended actions';
        foreach (array_slice($recommendations, 0, 3) as $i => $rec) {
            $lines[] = ($i + 1).'. '.$rec;
        }

        return implode("\n", $lines);
    }

    /**
     * Heuristic extraction: one task per actionable line.
     *
     * @return list<array{title: string, project: string, pic: string, priority: string, due_date: string, notes: string}>
     */
    private function simulatedCapture(string $notes): array
    {
        $projects = Project::query()->where('user_id', $this->ownerId())->pluck('name')->all();
        $members = array_values(array_map(fn (TeamMember $m): string => $m->name, $this->members()->all()));
        $items = [];

        foreach (preg_split('/\R/u', $notes) ?: [] as $raw) {
            $line = trim((string) preg_replace('/^\s*(?:[-*•▪◦]|\d+[.)]|\[\s?\])\s*/u', '', $raw));
            $line = trim((string) preg_replace('/^(?:action(?: item)?|todo|to-do|task)\s*[:\-]\s*/i', '', $line));

            if (mb_strlen($line) < 8 || str_ends_with($line, ':') || str_word_count($line) < 2) {
                continue;
            }

            $lower = Str::lower($line);
            $pic = '';
            if (preg_match('/@([\p{L}][\p{L}.\-]*(?:\s[\p{Lu}][\p{L}.]*)?)/u', $line, $m)) {
                $pic = $this->matchName($m[1], $members) ?? rtrim($m[1], '.');
                $line = trim(str_replace($m[0], '', $line));
            } else {
                foreach ($members as $name) {
                    $first = Str::lower(Str::before($name, ' '));
                    if ($first !== '' && preg_match('/\b'.preg_quote($first, '/').'\b/u', $lower)) {
                        $pic = $name;
                        break;
                    }
                }
            }

            $project = '';
            foreach ($projects as $name) {
                if (Str::contains($lower, Str::lower($name))) {
                    $project = $name;
                    break;
                }
            }

            $priority = match (true) {
                (bool) preg_match('/\b(urgent|asap|critical|high priority|important|blocker)\b/i', $line) => 'high',
                (bool) preg_match('/\b(low priority|nice to have|when possible|someday)\b/i', $line) => 'low',
                default => 'medium',
            };

            $title = $this->cleanCapturedTitle($line, $pic);

            $items[] = [
                'title' => $title,
                'project' => $project,
                'pic' => $pic,
                'priority' => $priority,
                'due_date' => $this->extractDate($line) ?? '',
                'notes' => 'Captured from notes: '.Str::limit(trim($raw), 300),
            ];

            if (count($items) >= 25) {
                break;
            }
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    private function simulatedSubtasks(?Task $task): array
    {
        if ($task === null) {
            return [];
        }

        $text = Str::lower($task->title.' '.$task->notes.' '.implode(' ', $task->tags ?? []));
        $templates = [
            'integration|api|payment|fpx|gateway|sso|interface' => ['Confirm API specification and credentials', 'Build the integration in the sandbox environment', 'Handle callbacks, timeouts and error cases', 'Write automated integration tests', 'Run a security review', 'Deploy and verify in production'],
            'uat|test|qa|regression' => ['Prepare test scenarios and test data', 'Execute the test cycle', 'Log and triage defects', 'Retest fixed defects', 'Obtain test sign-off'],
            'document|srs|spec|report|policy|plan' => ['Gather inputs from stakeholders', 'Draft the outline', 'Write the first draft', 'Run an internal review', 'Incorporate feedback', 'Obtain final sign-off'],
            'deploy|go-live|release|launch|migration' => ['Prepare the release checklist', 'Freeze scope and tag the release', 'Deploy to staging and smoke test', 'Deploy to production', 'Monitor after release'],
            'meeting|workshop|kick-off|presentation|slides|briefing' => ['Confirm agenda and attendees', 'Prepare the materials', 'Run the session', 'Circulate minutes and action items'],
        ];

        $steps = ['Clarify scope and acceptance criteria', 'Agree the approach with the team', 'Complete the core work', 'Review the result with stakeholders', 'Finalise and close the card'];
        foreach ($templates as $pattern => $template) {
            if (preg_match('/\b('.$pattern.')\b/', $text)) {
                $steps = $template;
                break;
            }
        }

        $suggested = $this->normaliseSubtasks($steps, $task);
        foreach (['Clarify scope and acceptance criteria', 'Review the result with stakeholders', 'Finalise and close the card'] as $fallback) {
            if (count($suggested) >= 3) {
                break;
            }
            $suggested = $this->normaliseSubtasks([...$suggested, $fallback], $task);
        }

        return $suggested;
    }

    /**
     * @return array{title: string, priority: string, due_date: string, rationale: string}
     */
    private function simulatedImprovement(Task $task): array
    {
        $verbs = ['prepare', 'build', 'review', 'implement', 'define', 'set', 'update', 'fix', 'deliver', 'complete', 'conduct', 'draft', 'test', 'deploy', 'integrate', 'research', 'design', 'migrate', 'document', 'analyse', 'analyze', 'create', 'plan', 'run', 'finalise', 'finalize', 'configure', 'investigate', 'launch', 'obtain', 'hold', 'organise', 'organize', 'resolve', 'write'];
        $reasons = [];

        $title = Str::ucfirst(rtrim((string) preg_replace('/\s+/', ' ', trim($task->title)), ' .!'));
        if (! in_array(Str::lower(Str::before($title, ' ')), $verbs, true)) {
            $title = 'Complete '.(preg_match('/^\p{Lu}{2,}/u', $title) ? $title : Str::lcfirst($title));
            $reasons[] = 'Title rewritten as an action so the expected outcome is explicit.';
        }
        $title = Str::limit($title, 120, '');

        $today = Carbon::parse(Task::today());
        $due = $task->due_date;
        $blocked = Str::contains(Str::lower((string) $task->notes), ['block', 'waiting', 'awaiting']);
        $priority = $task->priority === 'done' ? 'medium' : $task->priority;

        if ($task->isOverdue() || ($due !== null && $due->diffInDays($today, true) <= 3 && $due->gte($today)) || $blocked) {
            if ($priority !== 'high') {
                $reasons[] = 'Priority raised to High: the card is '.($task->isOverdue() ? 'overdue' : ($blocked ? 'blocked' : 'due within 3 days')).'.';
            }
            $priority = 'high';
        }

        if ($due === null) {
            $days = ['high' => 3, 'medium' => 7, 'low' => 14][$priority] ?? 7;
            $newDue = $today->copy()->addDays($days)->toDateString();
            $reasons[] = "Due date added ({$newDue}) based on {$priority} priority.";
        } elseif ($task->isOverdue()) {
            $newDue = $today->copy()->addDays(2)->toDateString();
            $reasons[] = "Overdue date replaced with a realistic new target ({$newDue}).";
        } else {
            $newDue = $due->toDateString();
        }

        if ($reasons === []) {
            $reasons[] = 'The card is already well defined — no major changes needed.';
        }

        return [
            'title' => $title,
            'priority' => $priority,
            'due_date' => $newDue,
            'rationale' => "⚙️ Simulation Mode — rule-based suggestions.\n• ".implode("\n• ", $reasons),
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────

    /**
     * @param  Collection<int, Task>  $open
     * @return Collection<int, Task>
     */
    private function rankFocus(Collection $open): Collection
    {
        $today = Carbon::parse(Task::today());

        return $open->sortByDesc(function (Task $t) use ($today): int {
            $score = $t->isOverdue() ? 300 : 0;
            $score += ['high' => 200, 'medium' => 100, 'low' => 0][$t->priority] ?? 0;
            if ($t->due_date !== null) {
                $score += max(0, 60 - (int) $today->diffInDays($t->due_date, false));
            }

            return $score;
        });
    }

    private function focusReason(Task $t): string
    {
        return match (true) {
            $t->isOverdue() => 'overdue since '.$t->due_date?->format('j M'),
            $t->isDueToday() => 'due today',
            $t->due_date !== null => ucfirst($t->priority).' priority, due '.$t->due_date->format('j M'),
            default => ucfirst($t->priority).' priority',
        };
    }

    private function who(Task $t): string
    {
        return $t->picLabel() ? ' ('.$t->picLabel().')' : '';
    }

    private function progress(Task $t): string
    {
        [$done, $total] = $t->subtaskCounts();

        return $total > 0 ? " · {$done}/{$total} subtasks" : '';
    }

    /**
     * @param  list<string>  $names
     */
    private function matchName(string $token, array $names): ?string
    {
        $token = Str::lower(rtrim($token, '.'));
        foreach ($names as $name) {
            if (Str::lower($name) === $token || Str::lower(Str::before($name, ' ')) === Str::before($token, ' ')) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Strip the owner ("Afqah to …", "@Haziq …") and date / urgency phrases that
     * are already captured in their own fields.
     */
    private function cleanCapturedTitle(string $line, string $pic): string
    {
        $title = $line;

        if ($pic !== '') {
            $first = preg_quote(Str::before($pic, ' '), '/');
            $title = (string) preg_replace('/^'.$first.'(?:\s+\p{Lu}\.)?\s*(?:[:\-–]\s*|\s+(?:to|will|should|must|needs? to|is to)\s+)/iu', '', $title);
        }

        $patterns = [
            '/\s*\((?:urgent|asap|high priority|low priority|blocker)\)/i',
            '/\s+(?:by|before|on|due)\s+(?:\d{4}-\d{2}-\d{2}|\d{1,2}[\/.]\d{1,2}[\/.]\d{2,4}|\d{1,2}\s+[a-z]{3,9}|(?:this\s+|next\s+)?(?:monday|tuesday|wednesday|thursday|friday|saturday|sunday)|tomorrow|today|eod|end of (?:the )?month)\b/i',
            '/\s+(?:next week|tomorrow|today|eod|end of month|in \d{1,2} days?)\b/i',
            '/\s*[-–,]\s*(?:urgent|asap)\b/i',
        ];
        $title = (string) preg_replace($patterns, '', $title);
        $title = trim($title, ' 	.;,-–');

        return Str::ucfirst(Str::limit($title !== '' ? $title : $line, 120, ''));
    }

    private function extractDate(string $line): ?string
    {
        $today = Carbon::parse(Task::today());
        $lower = Str::lower($line);

        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $line, $m)) {
            return $this->validDate($m[1]);
        }
        if (preg_match('/\b(\d{1,2})[\/.](\d{1,2})[\/.](\d{2,4})\b/', $line, $m)) {
            $year = strlen($m[3]) === 2 ? '20'.$m[3] : $m[3];

            return $this->validDate(sprintf('%04d-%02d-%02d', (int) $year, (int) $m[2], (int) $m[1]));
        }
        if (preg_match('/\b(\d{1,2})\s+(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\b/i', $line, $m)) {
            $date = Carbon::createFromFormat('j M Y', $m[1].' '.ucfirst(strtolower(substr($m[2], 0, 3))).' '.$today->year);
            if ($date !== null) {
                return ($date->lt($today) ? $date->addYear() : $date)->toDateString();
            }
        }
        if (str_contains($lower, 'today') || str_contains($lower, 'eod')) {
            return $today->toDateString();
        }
        if (str_contains($lower, 'tomorrow')) {
            return $today->copy()->addDay()->toDateString();
        }
        if (preg_match('/\bin (\d{1,2}) days?\b/', $lower, $m)) {
            return $today->copy()->addDays((int) $m[1])->toDateString();
        }
        if (str_contains($lower, 'next week')) {
            return $today->copy()->addWeek()->startOfWeek()->toDateString();
        }
        if (str_contains($lower, 'end of month') || str_contains($lower, 'month end')) {
            return $today->copy()->endOfMonth()->toDateString();
        }
        if (preg_match('/\b(monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/', $lower, $m)) {
            return $today->copy()->next(ucfirst($m[1]))->toDateString();
        }

        return null;
    }

    private function validDate(string $value): ?string
    {
        $value = trim($value);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        [$y, $m, $d] = array_map(intval(...), explode('-', $value));

        return checkdate($m, $d, $y) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function payloadFor(string $text, array $data): string
    {
        return $data === [] ? $text : (string) json_encode(['summary' => $text, ...$data], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Re-load a task through the owner scope (never trust a passed-in model's tenant).
     */
    private function ownedTask(?Task $task): ?Task
    {
        return $task === null ? null : $this->tasks->find($task->id);
    }

    /**
     * @return Collection<int, TeamMember>
     */
    private function members(): Collection
    {
        return TeamMember::query()
            ->where('user_id', $this->ownerId())
            ->orderByDesc('is_me')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function ownerId(): int
    {
        $id = Auth::id();

        if ($id === null) {
            throw new AuthenticationException;
        }

        return (int) $id;
    }
}
