<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Pmo;

use App\Models\Task;
use App\Support\NeoPalette;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

class TaskForm extends Form
{
    public ?int $id = null;

    public int|string|null $board_column_id = null;

    public string $title = '';

    public string $project_name = '';

    public string $pic_name = '';

    public string $priority = 'medium';

    public string $icon = '📌';

    public string $color = 'violet';

    public string $due_date = '';

    public string $tags = '';

    public string $notes = '';

    /** @var list<array{key: string, id: ?int, title: string, is_completed: bool}> */
    public array $subtasks = [];

    public string $newSubtask = '';

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'board_column_id' => [
                'required', 'integer',
                Rule::exists('board_columns', 'id')->where('user_id', Auth::id()),
            ],
            'title' => ['required', 'string', 'max:255'],
            'project_name' => ['nullable', 'string', 'max:255'],
            'pic_name' => ['nullable', 'string', 'max:255'],
            'priority' => ['required', Rule::in(array_keys(Task::PRIORITIES))],
            'icon' => ['required', 'string', 'max:10'],
            'color' => ['required', Rule::in(NeoPalette::keys())],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'tags' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'subtasks' => ['array', 'max:50'],
            'subtasks.*.id' => ['nullable', 'integer'],
            'subtasks.*.title' => ['required', 'string', 'max:255'],
            'subtasks.*.is_completed' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'board_column_id' => 'column',
            'title' => 'task title',
            'project_name' => 'project',
            'pic_name' => 'assignee',
            'due_date' => 'due date',
            'notes' => 'blocker / notes',
            'subtasks.*.title' => 'subtask title',
        ];
    }

    public function startFor(?int $columnId): void
    {
        $this->reset();
        $this->resetErrorBag();
        $this->board_column_id = $columnId;
    }

    public function fillFrom(Task $task): void
    {
        $this->resetErrorBag();
        $this->id = $task->id;
        $this->board_column_id = $task->board_column_id;
        $this->title = $task->title;
        $this->project_name = (string) $task->projectLabel();
        $this->pic_name = (string) $task->picLabel();
        $this->priority = $task->priority;
        $this->icon = $task->icon;
        $this->color = $task->color;
        $this->due_date = (string) $task->due_date?->toDateString();
        $this->tags = implode(', ', $task->tags ?? []);
        $this->notes = (string) $task->notes;
        $this->newSubtask = '';
        $this->subtasks = array_values($task->subtasks->map(fn ($subtask): array => [
            'key' => 's'.$subtask->id,
            'id' => $subtask->id,
            'title' => $subtask->title,
            'is_completed' => $subtask->is_completed,
        ])->all());
    }

    public function addSubtask(): void
    {
        $this->subtasks[] = [
            'key' => 'n'.Str::random(8),
            'id' => null,
            'title' => trim($this->newSubtask),
            'is_completed' => false,
        ];
        $this->newSubtask = '';
    }

    public function removeRow(int $index): void
    {
        if (isset($this->subtasks[$index]) && $this->subtasks[$index]['id'] === null) {
            array_splice($this->subtasks, $index, 1);
        }
    }

    public function forgetSubtask(int $subtaskId): void
    {
        $this->subtasks = array_values(array_filter(
            $this->subtasks,
            fn (array $row): bool => $row['id'] !== $subtaskId,
        ));
    }

    public function markSubtask(int $subtaskId, bool $completed): void
    {
        foreach ($this->subtasks as $index => $row) {
            if ($row['id'] === $subtaskId) {
                $this->subtasks[$index]['is_completed'] = $completed;
            }
        }
    }

    /**
     * @return array{0: array{board_column_id: int, title: string, project_name: ?string, pic_name: ?string, priority: string, icon: string, color: string, due_date: ?string, tags: list<string>, notes: ?string}, 1: list<array{id: ?int, title: string, is_completed: bool}>}
     */
    public function payload(): array
    {
        $this->title = trim($this->title);
        $this->project_name = trim($this->project_name);
        $this->pic_name = trim($this->pic_name);
        $this->notes = trim($this->notes);

        // Unsaved blank rows are simply dropped; saved rows must keep a title.
        $this->subtasks = array_values(array_filter(
            array_map(fn (array $row): array => [...$row, 'title' => trim((string) $row['title'])], $this->subtasks),
            fn (array $row): bool => $row['id'] !== null || $row['title'] !== '',
        ));

        $this->validate();

        $nullable = static fn (string $value): ?string => $value === '' ? null : $value;

        return [
            [
                'board_column_id' => (int) $this->board_column_id,
                'title' => $this->title,
                'project_name' => $nullable($this->project_name),
                'pic_name' => $nullable($this->pic_name),
                'priority' => $this->priority,
                'icon' => trim($this->icon),
                'color' => $this->color,
                'due_date' => $nullable($this->due_date),
                'tags' => $this->parsedTags(),
                'notes' => $nullable($this->notes),
            ],
            array_map(fn (array $row): array => [
                'id' => $row['id'] === null ? null : (int) $row['id'],
                'title' => $row['title'],
                'is_completed' => (bool) $row['is_completed'],
            ], $this->subtasks),
        ];
    }

    /**
     * @return list<string>
     */
    private function parsedTags(): array
    {
        $tags = [];

        foreach (explode(',', $this->tags) as $tag) {
            $tag = Str::limit(trim(ltrim(trim($tag), '#')), 30, '');

            if ($tag !== '' && ! in_array(mb_strtolower($tag), array_map(mb_strtolower(...), $tags), true)) {
                $tags[] = $tag;
            }
        }

        return array_slice($tags, 0, 10);
    }
}
