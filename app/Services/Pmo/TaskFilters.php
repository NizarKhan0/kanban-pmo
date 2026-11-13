<?php

declare(strict_types=1);

namespace App\Services\Pmo;

/**
 * Immutable board filter state (view + filter bar + sort).
 */
final readonly class TaskFilters
{
    /** @var array<string, string> */
    public const VIEWS = [
        'all' => 'Kanban Board',
        'my' => 'My Tasks',
        'due' => 'Overdue / Due',
        'completed' => 'Completed',
    ];

    /** @var array<string, string> */
    public const DUE = [
        'any' => 'Anytime',
        'overdue' => 'Overdue Only',
        'today' => 'Due Today',
        'week' => 'Next 7 Days',
        'month' => 'Next 30 Days',
        'none' => 'No Due Date',
    ];

    /** @var array<string, string> */
    public const SORTS = [
        'manual' => 'Manual Drag',
        'due' => 'Due Soonest',
        'priority' => 'Priority',
        'newest' => 'Newest',
        'az' => 'A → Z',
        'progress' => '% Done',
    ];

    /** Project / assignee filter value meaning "none assigned". */
    public const NONE = 'none';

    public function __construct(
        public string $view = 'all',
        public string $search = '',
        public string $project = '',
        public string $assignee = '',
        public string $priority = '',
        public string $due = 'any',
        public string $sort = 'manual',
    ) {}

    /**
     * @param  array<string, mixed>  $state
     */
    public static function fromArray(array $state): self
    {
        $value = static fn (string $key, string $default): string => is_string($state[$key] ?? null) ? $state[$key] : $default;

        $view = $value('view', 'all');
        $due = $value('due', 'any');
        $sort = $value('sort', 'manual');

        return new self(
            view: array_key_exists($view, self::VIEWS) ? $view : 'all',
            search: mb_substr($value('search', ''), 0, 100),
            project: $value('project', ''),
            assignee: $value('assignee', ''),
            priority: $value('priority', ''),
            due: array_key_exists($due, self::DUE) ? $due : 'any',
            sort: array_key_exists($sort, self::SORTS) ? $sort : 'manual',
        );
    }

    /**
     * @return array{view: string, search: string, project: string, assignee: string, priority: string, due: string, sort: string}
     */
    public function toArray(): array
    {
        return [
            'view' => $this->view,
            'search' => $this->search,
            'project' => $this->project,
            'assignee' => $this->assignee,
            'priority' => $this->priority,
            'due' => $this->due,
            'sort' => $this->sort,
        ];
    }

    public function isManualSort(): bool
    {
        return $this->sort === 'manual';
    }

    /** True when anything narrows the set of visible cards. */
    public function narrows(): bool
    {
        return $this->view !== 'all'
            || trim($this->search) !== ''
            || $this->project !== ''
            || $this->assignee !== ''
            || $this->priority !== ''
            || $this->due !== 'any';
    }
}
