@php
    use App\Models\BoardColumn;
    use App\Models\Task;
    use App\Support\NeoPalette;

    $kpis = $this->kpis;
    $filters = $this->filters;
    $manual = $filters->isManualSort();
    $narrowed = $filters->narrows();
    $filterBarActive = $search !== '' || $project !== '' || $assignee !== '' || $priority !== '' || $due !== 'any' || $sort !== 'manual';

    $field = 'w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm text-zinc-900 placeholder-zinc-400 shadow-sm transition focus:border-[#8b5cf6] focus:outline-none focus:ring-2 focus:ring-[#8b5cf6]/30 dark:border-white/10 dark:bg-[#07040f] dark:text-white dark:placeholder-zinc-500';
    $select = 'rounded-xl border border-zinc-300 bg-white py-2 pl-3 pr-8 text-sm text-zinc-800 shadow-sm focus:border-[#8b5cf6] focus:outline-none focus:ring-2 focus:ring-[#8b5cf6]/30 dark:border-white/10 dark:bg-[#07040f] dark:text-zinc-100';
    $label = 'mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-400';
    $error = 'mt-1.5 text-xs font-medium text-rose-600 dark:text-rose-400';
    $panel = 'rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-white/10 dark:bg-[#0d0722]';
    $btnPrimary = 'inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#ff2e9a] to-[#8b5cf6] px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-[#ff2e9a]/20 transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-[#ff2e9a]/50 disabled:opacity-60';
    $btnGhost = 'inline-flex items-center justify-center gap-2 rounded-xl border border-zinc-300 px-4 py-2.5 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-100 dark:border-white/15 dark:text-zinc-200 dark:hover:bg-white/5';
    $btnDanger = 'inline-flex items-center justify-center gap-2 rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-600/20 transition hover:bg-rose-500 disabled:opacity-60';
    $badge = 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset';
    $menuItem = 'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-zinc-700 transition hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-40 dark:text-zinc-200 dark:hover:bg-white/5';
    $lastIndex = $this->columns->count() - 1;
@endphp

<div class="flex h-full w-full flex-col gap-6"
    x-data
    x-on:keydown.window.ctrl.k.prevent="$refs.taskSearch.focus(); $refs.taskSearch.select()"
    x-on:keydown.window.meta.k.prevent="$refs.taskSearch.focus(); $refs.taskSearch.select()">

    {{-- ═══════════════ Header ═══════════════ --}}
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#ff2e9a]">PMO Command Center</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $views[$filters->view] }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                @switch($filters->view)
                    @case('my') Tasks assigned to the PIC marked as “Me” on your team roster. @break
                    @case('due') Open tasks that are overdue or due within the next 7 days. @break
                    @case('completed') Tasks marked as Done or sitting in a Done column. @break
                    @default Shape your delivery workflow. Drag cards between columns, or drag a column by its handle.
                @endswitch
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="$dispatch('open-standup')" class="{{ $btnGhost }}"><span aria-hidden="true">📋</span> Daily Standup Report</button>
            <button type="button" wire:click="createColumn" class="{{ $btnGhost }}"><span aria-hidden="true">🧱</span> Add Column</button>
            <button type="button" wire:click="createTask" class="{{ $btnPrimary }}"><span aria-hidden="true">＋</span> Add Task</button>
        </div>
    </header>

    {{-- ═══════════════ KPI Cards ═══════════════ --}}
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5" aria-label="Board metrics">
        @foreach ([
            ['label' => 'Open Tasks', 'value' => $kpis['open'], 'hint' => 'Not yet done', 'icon' => '🗂️', 'accent' => 'from-[#8b5cf6]'],
            ['label' => 'Overdue', 'value' => $kpis['overdue'], 'hint' => $kpis['overdue'] > 0 ? 'Past their due date' : 'Nothing overdue', 'icon' => '⏰', 'accent' => $kpis['overdue'] > 0 ? 'from-rose-500' : 'from-emerald-500'],
            ['label' => 'Due This Week', 'value' => $kpis['due_week'], 'hint' => 'Next 7 days', 'icon' => '📅', 'accent' => 'from-amber-500'],
            ['label' => 'Completed', 'value' => $kpis['completed'], 'hint' => 'Marked as Done', 'icon' => '✅', 'accent' => 'from-emerald-500'],
            ['label' => 'Over WIP Limit', 'value' => $kpis['over_wip'], 'hint' => $kpis['over_wip'] > 0 ? 'Columns need attention' : 'All within limits', 'icon' => '🔥', 'accent' => $kpis['over_wip'] > 0 ? 'from-rose-500' : 'from-sky-500'],
        ] as $card)
            <div class="{{ $panel }} relative overflow-hidden p-5">
                <div class="pointer-events-none absolute -right-8 -top-8 size-28 rounded-full bg-gradient-to-br {{ $card['accent'] }} to-transparent opacity-20 blur-xl"></div>
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $card['label'] }}</p>
                    <span class="text-xl" aria-hidden="true">{{ $card['icon'] }}</span>
                </div>
                <p class="mt-3 text-3xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ $card['value'] }}</p>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $card['hint'] }}</p>
            </div>
        @endforeach
    </section>

    {{-- ═══════════════ Views + Filter Bar ═══════════════ --}}
    <section class="{{ $panel }} space-y-3 p-4" aria-label="Views and filters">
        <nav class="flex flex-wrap gap-1" aria-label="Board views">
            @foreach ($views as $key => $text)
                <button type="button" wire:key="view-{{ $key }}" wire:click="setView('{{ $key }}')"
                    @class([
                        'rounded-lg px-3 py-1.5 text-sm font-semibold transition',
                        'bg-gradient-to-r from-[#ff2e9a] to-[#8b5cf6] text-white shadow' => $filters->view === $key,
                        'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/5' => $filters->view !== $key,
                    ])>{{ $text }}</button>
            @endforeach
        </nav>

        @if ($filters->view === 'my' && $this->meId === null)
            <p class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs text-amber-800 dark:text-amber-300">
                No team member is marked as “Me” yet. Use <a href="{{ route('pmo.roster') }}" wire:navigate class="font-semibold underline">Set as Me</a> on the Team Roster to enable this view.
            </p>
        @endif

        <div class="flex flex-wrap items-center gap-2">
            <div class="relative min-w-56 flex-1">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-zinc-400" aria-hidden="true">🔍</span>
                <input type="search" x-ref="taskSearch" wire:model.live.debounce.300ms="search" class="{{ $field }} !py-2 pl-9 pr-16" placeholder="Search tasks, tags, notes…" aria-label="Search tasks">
                <kbd class="pointer-events-none absolute inset-y-0 right-3 my-auto h-fit rounded-md border border-zinc-300 px-1.5 py-0.5 font-mono text-[10px] text-zinc-500 dark:border-white/15 dark:text-zinc-400">Ctrl K</kbd>
            </div>

            <select wire:model.live="project" class="{{ $select }}" aria-label="Filter by project">
                <option value="">All Projects</option>
                <option value="none">No Project</option>
                @foreach ($this->projectOptions as $option)
                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="assignee" class="{{ $select }}" aria-label="Filter by assignee">
                <option value="">All Assignees</option>
                <option value="none">Unassigned</option>
                @foreach ($this->memberOptions as $option)
                    <option value="{{ $option->id }}">{{ $option->name }}{{ $option->is_me ? ' (Me)' : '' }}</option>
                @endforeach
            </select>

            <select wire:model.live="priority" class="{{ $select }}" aria-label="Filter by priority">
                <option value="">All Priorities</option>
                @foreach ($priorities as $key => $text)
                    <option value="{{ $key }}">{{ $text }}</option>
                @endforeach
            </select>

            <select wire:model.live="due" class="{{ $select }}" aria-label="Filter by due date">
                @foreach ($dueOptions as $key => $text)
                    <option value="{{ $key }}">{{ $key === 'any' ? 'Due: Anytime' : $text }}</option>
                @endforeach
            </select>

            <select wire:model.live="sort" class="{{ $select }}" aria-label="Sort cards">
                @foreach ($sortOptions as $key => $text)
                    <option value="{{ $key }}">Sort: {{ $text }}</option>
                @endforeach
            </select>

            @if ($filterBarActive)
                <button type="button" wire:click="resetFilters" class="rounded-xl px-3 py-2 text-sm font-semibold text-[#ff2e9a] transition hover:bg-[#ff2e9a]/10">✕ Reset Filters</button>
            @endif

            <span wire:loading.delay class="text-xs text-zinc-400">Updating…</span>
        </div>

        @unless ($manual)
            <p class="text-xs text-zinc-500 dark:text-zinc-400">ℹ️ Cards are sorted by <strong>{{ $sortOptions[$filters->sort] }}</strong>. Switch to <button type="button" wire:click="$set('sort', 'manual')" class="font-semibold text-[#8b5cf6] underline">Manual Drag</button> to move cards by drag-and-drop.</p>
        @endunless
    </section>

    {{-- ═══════════════ Board ═══════════════ --}}
    <section class="-mx-1 flex-1 overflow-x-auto pb-4" aria-label="Kanban board" wire:key="board-{{ $filters->sort }}">
        <div class="flex min-h-[30rem] items-stretch gap-4 px-1">
            <div class="flex items-stretch gap-4" x-sort="$wire.moveColumn($key, $position)" x-sort:config="{ animation: 180, direction: 'horizontal' }">
                @foreach ($this->columns as $column)
                    @php
                        $count = $this->cardCounts[$column->id] ?? 0;
                        $over = $column->isOverWip($count);
                        $cards = $this->board[$column->id] ?? collect();
                    @endphp
                    <div
                        wire:key="column-{{ $column->id }}"
                        x-sort:item="{{ $column->id }}"
                        style="--col: {{ $column->color }}"
                        @class([
                            'flex w-80 shrink-0 flex-col overflow-hidden rounded-2xl border bg-zinc-50 shadow-sm transition dark:bg-[#0d0722]',
                            'border-zinc-200 dark:border-white/10' => ! $over,
                            'border-rose-500 ring-2 ring-rose-500/40 dark:border-rose-500' => $over,
                        ])
                    >
                        <div class="h-1.5 w-full bg-(--col)"></div>

                        {{-- Column header --}}
                        <div @class(['flex items-center gap-2 px-3 py-3', 'bg-rose-500/10' => $over])>
                            <button type="button" x-sort:handle class="cursor-grab rounded-md p-1 text-zinc-400 hover:bg-zinc-200/70 hover:text-zinc-700 active:cursor-grabbing dark:hover:bg-white/10 dark:hover:text-white" title="Drag to reorder" aria-label="Drag to reorder {{ $column->name }}">
                                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor"><path d="M7 4a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm0 6a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm-1.5 7.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3ZM16 4a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm-1.5 7.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3ZM16 16a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z"/></svg>
                            </button>

                            <span class="text-base" aria-hidden="true">{{ $column->icon }}</span>
                            <h2 class="min-w-0 flex-1 truncate text-sm font-semibold text-zinc-900 dark:text-white" title="{{ $column->name }}">{{ $column->name }}</h2>

                            <span class="{{ $badge }} bg-(--col)/15 text-zinc-700 ring-(--col)/40 dark:text-white" title="{{ $narrowed ? 'Visible / total cards' : 'Cards in column' }}">
                                {{ $narrowed ? $cards->count().'/'.$count : $count }}
                            </span>

                            {{-- ⋮ menu --}}
                            <div class="relative" x-data="{ open: false }" x-on:keydown.escape="open = false">
                                <button type="button" x-on:click="open = !open" class="rounded-md px-1.5 py-0.5 text-lg leading-none text-zinc-500 hover:bg-zinc-200/70 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-white" aria-label="Column options for {{ $column->name }}" x-bind:aria-expanded="open">⋮</button>
                                <div x-cloak x-show="open" x-transition.origin.top.right x-on:click.outside="open = false"
                                    class="absolute right-0 z-30 mt-1 w-44 rounded-xl border border-zinc-200 bg-white p-1 shadow-xl dark:border-white/10 dark:bg-[#130a2e]">
                                    <button type="button" class="{{ $menuItem }}" x-on:click="open = false" wire:click="createTask({{ $column->id }})">＋ Add Task</button>
                                    <button type="button" class="{{ $menuItem }}" x-on:click="open = false" wire:click="editColumn({{ $column->id }})">✏️ Edit Column</button>
                                    <button type="button" class="{{ $menuItem }}" x-on:click="open = false" wire:click="shiftColumn({{ $column->id }}, -1)" @disabled($loop->first)>⬅️ Move Left</button>
                                    <button type="button" class="{{ $menuItem }}" x-on:click="open = false" wire:click="shiftColumn({{ $column->id }}, 1)" @disabled($loop->index === $lastIndex)>➡️ Move Right</button>
                                    <div class="my-1 border-t border-zinc-100 dark:border-white/10"></div>
                                    <button type="button" class="{{ $menuItem }} !text-rose-600 dark:!text-rose-400" x-on:click="open = false" wire:click="confirmDeleteColumn({{ $column->id }})">🗑 Delete Column</button>
                                </div>
                            </div>
                        </div>

                        {{-- WIP / status strip --}}
                        <div class="flex flex-wrap items-center gap-1.5 px-3 pb-3">
                            @if ($column->hasWipLimit())
                                <span @class([
                                    $badge,
                                    'bg-rose-500/15 text-rose-700 ring-rose-500/40 dark:text-rose-300' => $over,
                                    'bg-zinc-500/10 text-zinc-600 ring-zinc-500/20 dark:text-zinc-300' => ! $over,
                                ])>WIP {{ $count }}/{{ $column->wip_limit }}</span>
                            @else
                                <span class="{{ $badge }} bg-zinc-500/10 text-zinc-500 ring-zinc-500/20 dark:text-zinc-400">No WIP Limit</span>
                            @endif
                            @if ($over)
                                <span class="{{ $badge }} animate-pulse bg-rose-600 text-white ring-rose-600">Over WIP Limit</span>
                            @endif
                            @if ($column->is_done_column)
                                <span class="{{ $badge }} bg-emerald-500/15 text-emerald-700 ring-emerald-500/40 dark:text-emerald-300">✓ Done Column</span>
                            @endif
                        </div>

                        {{-- Card lane --}}
                        <div class="relative flex-1 px-3">
                            @if ($cards->isEmpty())
                                <div class="pointer-events-none absolute inset-x-3 top-0 flex h-24 flex-col items-center justify-center rounded-xl border-2 border-dashed border-zinc-200 text-center dark:border-white/10">
                                    <p class="text-xs font-medium text-zinc-400 dark:text-zinc-500">{{ $narrowed && $count > 0 ? 'No matching cards' : 'Drop cards here' }}</p>
                                </div>
                            @endif

                            <div class="relative flex min-h-24 flex-col gap-2 pb-2"
                                @if ($manual)
                                    x-sort="$wire.moveCard($key, {{ $column->id }}, $position)"
                                    x-sort:group="cards"
                                    x-sort:config="{ animation: 160, ghostClass: 'opacity-40' }"
                                @endif
                            >
                                @foreach ($cards as $task)
                                    @php
                                        [$doneSubs, $totalSubs] = $task->subtaskCounts();
                                        $overdue = $task->isOverdue();
                                        $dueToday = $task->isDueToday();
                                        $completed = $task->isCompleted();
                                        $picFormer = $task->teamMember === null && $task->pic_name_snapshot !== null;
                                        $projectFormer = $task->project === null && $task->project_name_snapshot !== null;
                                    @endphp
                                    <article
                                        wire:key="task-{{ $task->id }}"
                                        x-sort:item="{{ $task->id }}"
                                        wire:click="editTask({{ $task->id }})"
                                        x-data="{ checklist: false }"
                                        @class([
                                            'group relative cursor-pointer select-none overflow-hidden rounded-xl border bg-white p-3 pl-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:bg-[#130a2e]',
                                            'border-zinc-200 dark:border-white/10' => ! $overdue,
                                            'border-rose-400 dark:border-rose-500/60' => $overdue,
                                            'opacity-75' => $completed,
                                            'active:cursor-grabbing' => $manual,
                                        ])
                                    >
                                        <span class="absolute inset-y-0 left-0 w-1 {{ NeoPalette::swatch($task->color) }}" aria-hidden="true"></span>

                                        <div class="flex items-start gap-2">
                                            <span class="text-base leading-5" aria-hidden="true">{{ $task->icon }}</span>
                                            <h3 @class(['line-clamp-2 flex-1 text-sm font-semibold leading-5 text-zinc-900 dark:text-white', 'line-through decoration-emerald-500/70' => $completed])>{{ $task->title }}</h3>
                                            @if ($task->picLabel())
                                                <span @class([
                                                    'flex size-7 shrink-0 items-center justify-center rounded-full text-[10px] font-bold',
                                                    'text-white '.NeoPalette::swatch($task->teamMember?->color) => ! $picFormer,
                                                    'bg-zinc-200 text-zinc-600 ring-1 ring-dashed ring-zinc-400 dark:bg-white/10 dark:text-zinc-300' => $picFormer,
                                                ]) title="{{ $picFormer ? 'Former PIC: '.$task->picLabel() : 'PIC: '.$task->picLabel() }}">{{ $task->picInitials() }}</span>
                                            @endif
                                        </div>

                                        @if ($task->projectLabel())
                                            <p class="mt-1.5 truncate text-xs text-zinc-500 dark:text-zinc-400" title="{{ $projectFormer ? 'Project removed — name preserved' : $task->projectLabel() }}">
                                                📁 {{ $task->projectLabel() }}@if ($projectFormer) <span class="italic">(archived)</span>@endif
                                            </p>
                                        @endif

                                        @if (! empty($task->tags))
                                            <div class="mt-2 flex flex-wrap gap-1">
                                                @foreach ($task->tags as $tag)
                                                    <span class="rounded-md bg-[#8b5cf6]/10 px-1.5 py-0.5 text-[10px] font-semibold text-violet-700 dark:text-violet-300">#{{ $tag }}</span>
                                                @endforeach
                                            </div>
                                        @endif

                                        @if ($totalSubs > 0)
                                            <div class="mt-2.5">
                                                <button type="button" x-on:click.stop="checklist = !checklist" class="flex w-full items-center gap-2 text-left" x-bind:aria-expanded="checklist" aria-label="Toggle subtasks">
                                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-zinc-200 dark:bg-white/10">
                                                        <div class="h-full rounded-full bg-gradient-to-r from-[#8b5cf6] to-[#ff2e9a] transition-all" style="width: {{ $task->progressPercentage() }}%"></div>
                                                    </div>
                                                    <span class="text-[11px] font-semibold tabular-nums text-zinc-600 dark:text-zinc-300">{{ $doneSubs }}/{{ $totalSubs }}</span>
                                                    <span class="text-[10px] text-zinc-400 transition" x-bind:class="checklist && 'rotate-180'">▾</span>
                                                </button>
                                                <ul x-cloak x-show="checklist" x-collapse class="mt-2 space-y-1" x-on:click.stop>
                                                    @foreach ($task->subtasks as $subtask)
                                                        <li wire:key="card-sub-{{ $subtask->id }}">
                                                            <label class="flex cursor-pointer items-start gap-2 rounded-md px-1 py-0.5 text-xs hover:bg-zinc-100 dark:hover:bg-white/5">
                                                                <input type="checkbox" class="mt-0.5 size-3.5 rounded accent-[#ff2e9a]" @checked($subtask->is_completed) wire:click.stop="toggleSubtask({{ $subtask->id }})">
                                                                <span @class(['text-zinc-700 dark:text-zinc-200', 'line-through opacity-60' => $subtask->is_completed])>{{ $subtask->title }}</span>
                                                            </label>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
                                            <span class="{{ $badge }} {{ $task->priorityBadge() }}">{{ $task->priorityLabel() }}</span>
                                            @if ($task->due_date)
                                                <span @class([
                                                    $badge,
                                                    'bg-rose-600 text-white ring-rose-600' => $overdue,
                                                    'bg-amber-500/15 text-amber-700 ring-amber-500/40 dark:text-amber-300' => $dueToday,
                                                    'bg-zinc-500/10 text-zinc-600 ring-zinc-500/20 dark:text-zinc-300' => ! $overdue && ! $dueToday,
                                                ]) title="Due {{ $task->due_date->format('j M Y') }}">
                                                    📅 {{ $overdue ? 'Overdue · ' : ($dueToday ? 'Today · ' : '') }}{{ $task->due_date->format('j M') }}
                                                </span>
                                            @endif
                                            @if ($task->notes)
                                                <span class="ml-auto text-xs" title="Has blocker / notes" aria-label="Has blocker / notes">📝</span>
                                            @endif
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>

                        <button type="button" wire:click="createTask({{ $column->id }})" class="mx-3 mb-3 mt-1 rounded-xl px-3 py-2 text-left text-sm font-semibold text-zinc-500 transition hover:bg-zinc-200/60 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/5 dark:hover:text-white">
                            ＋ Add Task
                        </button>
                    </div>
                @endforeach
            </div>

            {{-- Add column tile (outside the sortable list) --}}
            <button type="button" wire:click="createColumn"
                class="flex w-72 shrink-0 flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-zinc-300 text-sm font-semibold text-zinc-500 transition hover:border-[#ff2e9a] hover:bg-[#ff2e9a]/5 hover:text-[#ff2e9a] dark:border-white/15 dark:text-zinc-400">
                <span class="text-2xl" aria-hidden="true">＋</span>
                Add Column
            </button>
        </div>
    </section>

    {{-- ═══════════════ Analytics Widgets (reactive to filters + board events) ═══════════════ --}}
    <livewire:pmo.analytics-widgets :filters="$this->filterState" wire:key="pmo-analytics" />

    <livewire:pmo.standup-modal :filters="$this->filterState" wire:key="pmo-standup" />

    {{-- ═══════════════ Delete Subtask (stacked; declared before the Task Modal so Escape closes it first) ═══════════════ --}}
    <x-pmo.modal show="showSubtaskDeleteModal" title="Delete Subtask" max-width="max-w-sm" close="cancelSubtaskDelete" :stacked="true">
        <div class="space-y-4">
            <p class="text-sm text-zinc-700 dark:text-zinc-200">Delete the subtask <strong class="text-zinc-900 dark:text-white">“{{ $deleteSubtaskTitle }}”</strong>? This cannot be undone.</p>
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="cancelSubtaskDelete" class="{{ $btnGhost }}">Cancel</button>
                <button type="button" wire:click="destroySubtask" wire:loading.attr="disabled" wire:target="destroySubtask" class="{{ $btnDanger }}">Delete Subtask</button>
            </div>
        </div>
    </x-pmo.modal>

    {{-- ═══════════════ Task Modal (Create / Edit) ═══════════════ --}}
    <x-pmo.modal
        show="showTaskModal"
        :title="$taskForm->id ? 'Edit Task' : 'Add Task'"
        :subtitle="$taskForm->id ? 'Update the card details, subtasks and blockers.' : 'Create a new card on your board.'"
        max-width="max-w-2xl"
    >
        <form wire:submit="saveTask" class="space-y-4">
            <div>
                <label for="task-title" class="{{ $label }}">Task Title</label>
                <input id="task-title" type="text" wire:model="taskForm.title" class="{{ $field }}" placeholder="e.g. FPX payment integration" maxlength="255">
                @error('taskForm.title') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="task-column" class="{{ $label }}">Column</label>
                    <select id="task-column" wire:model="taskForm.board_column_id" class="{{ $field }}">
                        @foreach ($this->columns as $column)
                            <option value="{{ $column->id }}">{{ $column->icon }} {{ $column->name }}{{ $column->is_done_column ? ' (Done)' : '' }}</option>
                        @endforeach
                    </select>
                    @error('taskForm.board_column_id') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="task-priority" class="{{ $label }}">Priority</label>
                    <select id="task-priority" wire:model="taskForm.priority" class="{{ $field }}">
                        @foreach ($priorities as $key => $text)
                            <option value="{{ $key }}">{{ $text }}</option>
                        @endforeach
                    </select>
                    @error('taskForm.priority') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="task-project" class="{{ $label }}">Project</label>
                    <select id="task-project" wire:model="taskForm.project_id" class="{{ $field }}">
                        <option value="">No project</option>
                        @foreach ($this->projectOptions as $option)
                            <option value="{{ $option->id }}">{{ $option->name }}{{ $option->status !== 'active' ? ' — '.(\App\Models\Project::STATUSES[$option->status] ?? $option->status) : '' }}</option>
                        @endforeach
                    </select>
                    @if ($taskForm->former_project !== '')
                        <p class="mt-1.5 text-xs text-zinc-500 dark:text-zinc-400">Previously: <strong>{{ $taskForm->former_project }}</strong> (project removed — name kept on this card until you choose another).</p>
                    @endif
                    @error('taskForm.project_id') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="task-pic" class="{{ $label }}">Assignee (PIC)</label>
                    <select id="task-pic" wire:model="taskForm.team_member_id" class="{{ $field }}">
                        <option value="">Unassigned</option>
                        @foreach ($this->memberOptions as $option)
                            <option value="{{ $option->id }}" @disabled(! $option->is_active && (int) $taskForm->team_member_id !== $option->id)>
                                {{ $option->name }}{{ $option->is_me ? ' (Me)' : '' }}{{ $option->is_active ? '' : ' — Inactive' }}
                            </option>
                        @endforeach
                    </select>
                    @if ($taskForm->former_pic !== '')
                        <p class="mt-1.5 text-xs text-zinc-500 dark:text-zinc-400">Former PIC: <strong>{{ $taskForm->former_pic }}</strong> (removed from the roster — name kept on this card until you assign someone).</p>
                    @endif
                    @error('taskForm.team_member_id') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
                @if ($this->projectOptions->isEmpty() || $this->memberOptions->isEmpty())
                    <p class="-mt-2 text-xs text-zinc-500 sm:col-span-2 dark:text-zinc-400">
                        {{ $this->projectOptions->isEmpty() ? 'No projects yet.' : '' }} {{ $this->memberOptions->isEmpty() ? 'No team members yet.' : '' }}
                        Add them on <a href="{{ route('pmo.roster') }}" wire:navigate class="font-semibold text-[#8b5cf6] underline">Projects &amp; Roster</a>.
                    </p>
                @endif

                <div>
                    <label for="task-due" class="{{ $label }}">Due Date</label>
                    <input id="task-due" type="date" wire:model="taskForm.due_date" class="{{ $field }}">
                    @error('taskForm.due_date') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="task-tags" class="{{ $label }}">Tags</label>
                    <input id="task-tags" type="text" wire:model="taskForm.tags" class="{{ $field }}" placeholder="e.g. payment, integration, uat" maxlength="500">
                    <p class="mt-1.5 text-xs text-zinc-500 dark:text-zinc-400">Separate tags with commas (max 10).</p>
                    @error('taskForm.tags') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <span class="{{ $label }}">Icon</span>
                    <div class="flex flex-wrap items-center gap-1">
                        @foreach (Task::ICONS as $icon)
                            <button type="button" wire:key="t-icon-{{ $loop->index }}" x-on:click="$wire.set('taskForm.icon', @js($icon), false)"
                                class="flex size-8 items-center justify-center rounded-lg text-base ring-1 transition"
                                x-bind:class="$wire.taskForm.icon === @js($icon) ? 'ring-2 ring-[#ff2e9a] bg-[#ff2e9a]/10' : 'ring-zinc-200 hover:bg-zinc-100 dark:ring-white/10 dark:hover:bg-white/5'">{{ $icon }}</button>
                        @endforeach
                    </div>
                    @error('taskForm.icon') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <span class="{{ $label }}">Colour</span>
                    <div class="flex flex-wrap gap-2">
                        @foreach (NeoPalette::COLORS as $key => $text)
                            <button type="button" wire:key="t-color-{{ $key }}" x-on:click="$wire.set('taskForm.color', @js($key), false)" title="{{ $text }}" aria-label="{{ $text }}"
                                class="size-7 rounded-full transition {{ NeoPalette::swatch($key) }}"
                                x-bind:class="$wire.taskForm.color === @js($key) ? 'ring-2 ring-offset-2 ring-zinc-900 dark:ring-white dark:ring-offset-[#0d0722]' : 'opacity-70 hover:opacity-100'"></button>
                        @endforeach
                    </div>
                    @error('taskForm.color') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="task-notes" class="{{ $label }}">Blocker / Notes</label>
                <textarea id="task-notes" wire:model="taskForm.notes" rows="3" class="{{ $field }}" placeholder="Blockers, dependencies or context for the team"></textarea>
                @error('taskForm.notes') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>

            {{-- Subtasks --}}
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-white/10"
                x-data="{
                    get total() { return $wire.taskForm.subtasks.length },
                    get done() { return $wire.taskForm.subtasks.filter(s => s.is_completed).length },
                }">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <span class="{{ $label }} !mb-0">Subtasks Progress</span>
                    <span class="text-xs font-semibold tabular-nums text-zinc-600 dark:text-zinc-300" x-text="total ? `${done}/${total} · ${Math.round(done / total * 100)}%` : 'No subtasks'"></span>
                </div>
                <div class="mb-3 h-1.5 overflow-hidden rounded-full bg-zinc-200 dark:bg-white/10">
                    <div class="h-full rounded-full bg-gradient-to-r from-[#8b5cf6] to-[#ff2e9a] transition-all" x-bind:style="`width: ${total ? Math.round(done / total * 100) : 0}%`"></div>
                </div>

                <ul class="space-y-2">
                    @foreach ($taskForm->subtasks as $index => $row)
                        <li wire:key="form-sub-{{ $row['key'] }}" class="flex items-center gap-2">
                            <input type="checkbox" wire:model="taskForm.subtasks.{{ $index }}.is_completed" class="size-4 shrink-0 rounded accent-[#ff2e9a]" aria-label="Mark subtask complete">
                            <input type="text" wire:model="taskForm.subtasks.{{ $index }}.title" class="{{ $field }} !py-1.5" placeholder="Subtask title" maxlength="255">
                            @if ($row['id'] !== null)
                                <button type="button" wire:click="confirmDeleteSubtask({{ $row['id'] }})" class="shrink-0 rounded-lg px-2 py-1 text-sm text-rose-600 hover:bg-rose-500/10 dark:text-rose-400" title="Delete subtask" aria-label="Delete subtask">🗑</button>
                            @else
                                <button type="button" wire:click="removeSubtaskRow({{ $index }})" class="shrink-0 rounded-lg px-2 py-1 text-sm text-zinc-500 hover:bg-zinc-100 dark:hover:bg-white/5" title="Remove unsaved row" aria-label="Remove unsaved row">✕</button>
                            @endif
                        </li>
                        @error('taskForm.subtasks.'.$index.'.title') <li class="{{ $error }} !mt-0 pl-6">{{ $message }}</li> @enderror
                    @endforeach
                </ul>

                <div class="mt-3 flex gap-2">
                    <input type="text" wire:model="taskForm.newSubtask" wire:keydown.enter.prevent="addSubtaskRow" class="{{ $field }} !py-1.5" placeholder="Add a subtask and press Enter" maxlength="255">
                    <button type="button" wire:click="addSubtaskRow" class="{{ $btnGhost }} !py-1.5 shrink-0">＋ Add</button>
                </div>
                @error('taskForm.subtasks') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 pt-2">
                <div>
                    @if ($taskForm->id)
                        <button type="button" wire:click="confirmDeleteTask({{ $taskForm->id }})" class="text-sm font-semibold text-rose-600 hover:underline dark:text-rose-400">Delete Task</button>
                    @endif
                </div>
                <div class="flex gap-2">
                    <button type="button" wire:click="closeModals" class="{{ $btnGhost }}">Cancel</button>
                    <button type="submit" class="{{ $btnPrimary }}" wire:loading.attr="disabled" wire:target="saveTask">
                        <span wire:loading.remove wire:target="saveTask">{{ $taskForm->id ? 'Save Changes' : 'Create Task' }}</span>
                        <span wire:loading wire:target="saveTask">Saving…</span>
                    </button>
                </div>
            </div>
        </form>
    </x-pmo.modal>

    {{-- ═══════════════ Delete Task Confirmation ═══════════════ --}}
    <x-pmo.modal show="showTaskDeleteModal" title="Delete Task" max-width="max-w-md">
        <div class="space-y-4">
            <div class="flex items-start gap-3 rounded-xl border border-rose-500/30 bg-rose-500/10 p-4">
                <span class="text-xl" aria-hidden="true">⚠️</span>
                <p class="text-sm text-zinc-700 dark:text-zinc-200">
                    You are about to delete <strong class="text-zinc-900 dark:text-white">“{{ $deleteTaskTitle }}”</strong>
                    @if ($deleteTaskSubtasks > 0)
                        together with its <strong>{{ $deleteTaskSubtasks }}</strong> {{ str('subtask')->plural($deleteTaskSubtasks) }}
                    @endif
                    . This action cannot be undone.
                </p>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="closeModals" class="{{ $btnGhost }}">Cancel</button>
                <button type="button" wire:click="destroyTask" wire:loading.attr="disabled" wire:target="destroyTask" class="{{ $btnDanger }}">
                    <span wire:loading.remove wire:target="destroyTask">Delete Task</span>
                    <span wire:loading wire:target="destroyTask">Deleting…</span>
                </button>
            </div>
        </div>
    </x-pmo.modal>

    {{-- ═══════════════ Column Modal (Create / Edit) ═══════════════ --}}
    <x-pmo.modal
        show="showColumnModal"
        :title="$columnForm->id ? 'Edit Column' : 'Add Column'"
        :subtitle="$columnForm->id ? 'Update this workflow stage.' : 'Add a new workflow stage to your board.'"
    >
        <form wire:submit="saveColumn" class="space-y-4">
            <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-white/10" aria-hidden="true">
                <div class="h-1.5" x-bind:style="{ background: $wire.columnForm.color }"></div>
                <div class="flex items-center gap-2 px-3 py-2.5">
                    <span x-text="$wire.columnForm.icon"></span>
                    <span class="flex-1 truncate text-sm font-semibold text-zinc-900 dark:text-white" x-text="$wire.columnForm.name || 'Column name'"></span>
                    <span class="rounded-full bg-zinc-500/10 px-2 py-0.5 text-[11px] font-semibold text-zinc-600 dark:text-zinc-300"
                        x-text="Number($wire.columnForm.wip_limit) > 0 ? 'WIP 0/' + $wire.columnForm.wip_limit : 'No WIP Limit'"></span>
                </div>
            </div>

            <div>
                <label for="column-name" class="{{ $label }}">Column Name</label>
                <input id="column-name" type="text" wire:model="columnForm.name" class="{{ $field }}" placeholder="e.g. In Progress" maxlength="100">
                @error('columnForm.name') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>

            <div>
                <span class="{{ $label }}">Colour</span>
                <div class="flex flex-wrap items-center gap-2">
                    @foreach (BoardColumn::COLOR_PRESETS as $hex)
                        <button type="button" wire:key="c-color-{{ $loop->index }}" x-on:click="$wire.set('columnForm.color', @js($hex), false)" title="{{ $hex }}" aria-label="Colour {{ $hex }}"
                            class="size-8 rounded-full transition" style="background: {{ $hex }}"
                            x-bind:class="$wire.columnForm.color === @js($hex) ? 'ring-2 ring-offset-2 ring-zinc-900 dark:ring-white dark:ring-offset-[#0d0722]' : 'opacity-70 hover:opacity-100'"></button>
                    @endforeach
                    <label class="relative size-8 cursor-pointer overflow-hidden rounded-full ring-1 ring-zinc-300 dark:ring-white/20" title="Custom colour">
                        <input type="color" wire:model="columnForm.color" class="absolute -inset-2 size-12 cursor-pointer" aria-label="Custom colour">
                    </label>
                    <input type="text" wire:model.blur="columnForm.color" class="{{ $field }} !w-28 font-mono" maxlength="7" aria-label="Hex colour">
                </div>
                @error('columnForm.color') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>

            <div>
                <span class="{{ $label }}">Icon</span>
                <div class="flex flex-wrap items-center gap-1.5">
                    @foreach (BoardColumn::ICONS as $icon)
                        <button type="button" wire:key="c-icon-{{ $loop->index }}" x-on:click="$wire.set('columnForm.icon', @js($icon), false)"
                            class="flex size-9 items-center justify-center rounded-lg text-lg ring-1 transition"
                            x-bind:class="$wire.columnForm.icon === @js($icon) ? 'ring-2 ring-[#ff2e9a] bg-[#ff2e9a]/10' : 'ring-zinc-200 hover:bg-zinc-100 dark:ring-white/10 dark:hover:bg-white/5'">{{ $icon }}</button>
                    @endforeach
                    <input type="text" wire:model="columnForm.icon" class="{{ $field }} !w-16 text-center" maxlength="10" aria-label="Custom icon">
                </div>
                @error('columnForm.icon') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="column-wip" class="{{ $label }}">WIP Limit</label>
                    <input id="column-wip" type="number" min="0" max="999" wire:model="columnForm.wip_limit" class="{{ $field }}">
                    <p class="mt-1.5 text-xs text-zinc-500 dark:text-zinc-400">Set 0 for no limit.</p>
                    @error('columnForm.wip_limit') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>

                <label class="flex cursor-pointer items-start gap-3 self-start rounded-xl border border-zinc-200 p-3 sm:mt-6 dark:border-white/10">
                    <input type="checkbox" wire:model="columnForm.is_done_column" class="mt-0.5 size-4 rounded accent-emerald-500">
                    <span>
                        <span class="block text-sm font-semibold text-zinc-900 dark:text-white">Is Done Column</span>
                        <span class="block text-xs text-zinc-500 dark:text-zinc-400">Cards moved here are marked as Done automatically.</span>
                    </span>
                </label>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 pt-2">
                <div>
                    @if ($columnForm->id)
                        <button type="button" wire:click="confirmDeleteColumn({{ $columnForm->id }})" class="text-sm font-semibold text-rose-600 hover:underline dark:text-rose-400">Delete Column</button>
                    @endif
                </div>
                <div class="flex gap-2">
                    <button type="button" wire:click="closeModals" class="{{ $btnGhost }}">Cancel</button>
                    <button type="submit" class="{{ $btnPrimary }}" wire:loading.attr="disabled" wire:target="saveColumn">
                        <span wire:loading.remove wire:target="saveColumn">{{ $columnForm->id ? 'Save Changes' : 'Add Column' }}</span>
                        <span wire:loading wire:target="saveColumn">Saving…</span>
                    </button>
                </div>
            </div>
        </form>
    </x-pmo.modal>

    {{-- ═══════════════ Delete Column Confirmation ═══════════════ --}}
    <x-pmo.modal show="showDeleteModal" title="Delete Column" max-width="max-w-md">
        <div class="space-y-4">
            @if (! $deleteAllowed)
                <div class="flex items-start gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-zinc-700 dark:text-zinc-200">
                    <span class="text-xl" aria-hidden="true">🔒</span>
                    <p><strong class="text-zinc-900 dark:text-white">{{ $deleteName }}</strong> is the only column on your board. Your board must keep at least one column, so it cannot be deleted. Add another column first.</p>
                </div>
                <div class="flex justify-end">
                    <button type="button" wire:click="closeModals" class="{{ $btnGhost }}">Close</button>
                </div>
            @else
                <div class="flex items-start gap-3 rounded-xl border border-rose-500/30 bg-rose-500/10 p-4">
                    <span class="text-xl" aria-hidden="true">⚠️</span>
                    <p class="text-sm text-zinc-700 dark:text-zinc-200">
                        You are about to delete the column <strong class="text-zinc-900 dark:text-white">{{ $deleteName }}</strong>. This action cannot be undone.
                    </p>
                </div>

                @if ($deleteCards > 0)
                    <div class="space-y-2 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3">
                        <p class="flex items-start gap-2 text-xs text-emerald-800 dark:text-emerald-300">
                            <span aria-hidden="true">🛡️</span>
                            <span>This column holds <strong>{{ $deleteCards }}</strong> task {{ str('card')->plural($deleteCards) }}. They will <strong>not</strong> be deleted. Choose where to move them:</span>
                        </p>
                        <select wire:model="deleteTargetId" class="{{ $field }}" aria-label="Move cards to">
                            @foreach ($deleteTargets as $targetId => $targetName)
                                <option value="{{ $targetId }}">{{ $targetName }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">This column is empty. No task cards will be affected.</p>
                @endif

                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" wire:click="closeModals" class="{{ $btnGhost }}">Cancel</button>
                    <button type="button" wire:click="destroyColumn" wire:loading.attr="disabled" wire:target="destroyColumn" class="{{ $btnDanger }}">
                        <span wire:loading.remove wire:target="destroyColumn">Delete Column</span>
                        <span wire:loading wire:target="destroyColumn">Deleting…</span>
                    </button>
                </div>
            @endif
        </div>
    </x-pmo.modal>
</div>
