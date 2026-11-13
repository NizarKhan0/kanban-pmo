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
