<?php

declare(strict_types=1);

namespace App\Livewire\Pmo;

use App\Models\ActivityLog;
use App\Services\Pmo\ActivityLogger;
use App\Services\Pmo\PmoAnalyticsService;
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
 * Five real-time analytics widgets rendered under the Kanban board.
 *
 * @property-read array{
 *     summary: array{total: int, open: int, high: int, medium: int, low: int, done: int},
 *     completion: array{done: int, total: int, rate: int},
 *     priorities: list<array{key: string, label: string, count: int, pct: int}>,
 *     workload: array{rows: list<array{id: ?int, name: string, initials: string, swatch: string, active: int, overdue: int, pct: int, is_me: bool, overloaded: bool, former: bool}>, threshold: int, max: int},
 *     recent: Collection<int, ActivityLog>,
 * } $widgets
 */
class AnalyticsWidgets extends Component
{
    /** @var array<string, string> Board view + filter state (from KanbanBoard). */
    #[Reactive]
    public array $filters = [];

    public bool $showClearModal = false;

    public string $clearRange = '30';

    #[Locked]
    public int $clearCount = 0;

    #[Locked]
    public int $totalLogs = 0;

    #[On('pmo-board-changed')]
    public function refreshWidgets(): void
    {
        unset($this->widgets);
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function widgets(): array
    {
        return app(PmoAnalyticsService::class)->widgets(TaskFilters::fromArray($this->filters));
    }

    // ── Delete: Clear Activity Logs ──────────────────────────────────

    public function confirmClear(): void
    {
        $this->clearRange = '30';
        $this->refreshClearCount();
        $this->showClearModal = true;
    }

    public function updatedClearRange(): void
    {
        if (! array_key_exists($this->clearRange, ActivityLogger::CLEAR_RANGES)) {
            $this->clearRange = '30';
        }

        $this->refreshClearCount();
    }

    public function clearLogs(): void
    {
        $removed = app(ActivityLogger::class)->clear($this->clearRange);

        Flux::toast(
            variant: 'success',
            heading: 'Activity logs cleared',
            text: $removed > 0 ? "{$removed} ".str('entry')->plural($removed).' removed.' : 'There was nothing to remove.',
        );

        $this->closeModals();
        unset($this->widgets);
    }

    public function closeModals(): void
    {
        $this->showClearModal = false;
        $this->clearCount = 0;
    }

    public function render(): View
    {
        return view('livewire.pmo.analytics-widgets', [
            'narrowed' => TaskFilters::fromArray($this->filters)->narrows(),
            'ranges' => ActivityLogger::CLEAR_RANGES,
        ]);
    }

    private function refreshClearCount(): void
    {
        $logger = app(ActivityLogger::class);
        $this->clearCount = $logger->clearPreview($this->clearRange);
        $this->totalLogs = $logger->total();
    }
}
