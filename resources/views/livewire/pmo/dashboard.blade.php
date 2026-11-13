@php
    use App\Support\NeoPalette;

    $kpis = $this->kpis;
    $a = $this->analytics;
    $completion = $a['completion'];
    $workload = $a['workload'];
    $portfolio = $this->portfolio;
    $activeProjects = $this->activeProjects;

    $panel = 'rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-[#0d0722]';
    $h2 = 'text-sm font-semibold text-zinc-900 dark:text-white';
    $sub = 'text-xs text-zinc-500 dark:text-zinc-400';
    $badge = 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset';
    $link = 'text-xs font-semibold text-[#8b5cf6] hover:underline';
    $btnPrimary = 'inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#ff2e9a] to-[#8b5cf6] px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-[#ff2e9a]/20 transition hover:brightness-110';
    $btnGhost = 'inline-flex items-center justify-center gap-2 rounded-xl border border-zinc-300 px-4 py-2.5 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-100 dark:border-white/15 dark:text-zinc-200 dark:hover:bg-white/5';
@endphp

<div class="mx-auto w-full max-w-7xl space-y-6">

    {{-- ═══════════════ Header ═══════════════ --}}
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-[#0d0722]">
        <div class="pointer-events-none absolute -right-16 -top-24 size-72 rounded-full bg-gradient-to-br from-[#ff2e9a]/25 to-[#8b5cf6]/0 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-24 left-1/3 size-72 rounded-full bg-gradient-to-br from-[#8b5cf6]/20 to-transparent blur-3xl"></div>
        <div class="relative flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#ff2e9a]">PMO Command Center</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $greeting }}{{ $name !== '' ? ', '.$name : '' }} 👋</h1>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $today }} ·
                    @if ($kpis['overdue'] > 0)
                        <span class="font-semibold text-rose-600 dark:text-rose-400">{{ $kpis['overdue'] }} overdue {{ str('card')->plural($kpis['overdue']) }}</span> need attention.
                    @elseif ($kpis['open'] > 0)
                        Everything is on schedule.
                    @else
                        Your board is clear.
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('pmo.board') }}" wire:navigate class="{{ $btnPrimary }}">🗂️ Open Kanban Board</a>
                <a href="{{ route('pmo.hub') }}" wire:navigate class="{{ $btnGhost }}">🤖 AI &amp; Omnichannel Hub</a>
                <a href="{{ route('data.edit') }}" wire:navigate class="{{ $btnGhost }}">💾 Data &amp; Backup</a>
            </div>
        </div>
    </header>

    {{-- ═══════════════ KPI tiles ═══════════════ --}}
    <section class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5" aria-label="Key metrics">
        @foreach ([
            ['Open Tasks', $kpis['open'], 'Across all projects', '🗂️', 'from-[#8b5cf6]', route('pmo.board')],
            ['Overdue', $kpis['overdue'], $kpis['overdue'] > 0 ? 'Past their due date' : 'Nothing overdue', '⏰', $kpis['overdue'] > 0 ? 'from-rose-500' : 'from-emerald-500', route('pmo.board', ['view' => 'due'])],
            ['Due This Week', $kpis['due_week'], 'Next 7 days', '📅', 'from-amber-500', route('pmo.board', ['view' => 'due'])],
            ['Completion Rate', $completion['rate'].'%', $completion['done'].' of '.$completion['total'].' done', '🎯', 'from-emerald-500', route('pmo.board', ['view' => 'completed'])],
            ['Active Projects', $activeProjects, $workload['rows'] === [] ? 'No PICs yet' : count($workload['rows']).' people on the roster', '📁', 'from-[#ff2e9a]', route('pmo.roster')],
        ] as [$label, $value, $hint, $icon, $accent, $href])
            <a href="{{ $href }}" wire:navigate class="{{ $panel }} group relative overflow-hidden transition hover:border-[#8b5cf6]/40">
                <div class="pointer-events-none absolute -right-8 -top-8 size-28 rounded-full bg-gradient-to-br {{ $accent }} to-transparent opacity-20 blur-xl transition group-hover:opacity-30"></div>
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $label }}</p>
                    <span class="text-xl" aria-hidden="true">{{ $icon }}</span>
                </div>
                <p class="mt-3 text-3xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ $value }}</p>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $hint }}</p>
            </a>
        @endforeach
    </section>

    {{-- ═══════════════ Focus + completion ═══════════════ --}}
    <div class="grid gap-6 xl:grid-cols-3">
        <section class="{{ $panel }} xl:col-span-2" aria-labelledby="focus-heading">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <h2 id="focus-heading" class="{{ $h2 }}">🎯 {{ $hasMe ? 'My Focus' : 'Team Focus' }}</h2>
                    <p class="{{ $sub }}">{{ $hasMe ? 'Your open cards, most urgent first.' : 'Most urgent open cards. Use “Set as Me” on the roster to see only yours.' }}</p>
                </div>
                <a href="{{ route('pmo.board', $hasMe ? ['view' => 'my'] : []) }}" wire:navigate class="{{ $link }}">View all →</a>
            </div>
            <ul class="mt-4 divide-y divide-zinc-100 dark:divide-white/5">
                @forelse ($this->focus as $task)
                    @php [$doneSubs, $totalSubs] = $task->subtaskCounts(); @endphp
                    <li wire:key="focus-{{ $task->id }}" class="flex items-start gap-3 py-3">
                        <span class="mt-0.5 w-1 self-stretch rounded-full {{ NeoPalette::swatch($task->color) }}" aria-hidden="true"></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $task->icon }} {{ $task->title }}</p>
                            <p class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $task->column->name }}{{ $task->projectLabel() ? ' · '.$task->projectLabel() : '' }}{{ ! $hasMe && $task->picLabel() ? ' · '.$task->picLabel() : '' }}{{ $totalSubs > 0 ? " · {$doneSubs}/{$totalSubs} subtasks" : '' }}
                            </p>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-1">
                            <span class="{{ $badge }} {{ $task->priorityBadge() }}">{{ $task->priorityLabel() }}</span>
                            @if ($task->due_date)
                                <span @class([
                                    'text-[11px] font-semibold tabular-nums',
                                    'text-rose-600 dark:text-rose-400' => $task->isOverdue(),
                                    'text-amber-600 dark:text-amber-400' => $task->isDueToday(),
                                    'text-zinc-500 dark:text-zinc-400' => ! $task->isOverdue() && ! $task->isDueToday(),
                                ])>{{ $task->isOverdue() ? 'Overdue · ' : ($task->isDueToday() ? 'Today · ' : '') }}{{ $task->due_date->format('j M') }}</span>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="py-10 text-center">
                        <p class="text-2xl" aria-hidden="true">🎉</p>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">No open cards. Enjoy the calm — or add the next task on the board.</p>
                    </li>
                @endforelse
            </ul>
        </section>

        <section class="{{ $panel }}" aria-labelledby="completion-heading">
            <h2 id="completion-heading" class="{{ $h2 }}">Completion Rate</h2>
            <div class="mt-4 flex items-center gap-5">
                <div class="relative size-32 shrink-0" title="{{ $completion['done'] }} of {{ $completion['total'] }} cards completed">
                    <svg viewBox="0 0 36 36" class="size-full -rotate-90" role="img" aria-label="Completion rate {{ $completion['rate'] }} percent">
                        <circle cx="18" cy="18" r="15.915" fill="none" stroke-width="3.2" class="stroke-zinc-200 dark:stroke-white/10"></circle>
                        @if ($completion['rate'] > 0)
                            <circle cx="18" cy="18" r="15.915" fill="none" stroke-width="3.2" stroke-dasharray="{{ $completion['rate'] }} {{ 100 - $completion['rate'] }}" class="stroke-emerald-500 dark:stroke-emerald-600"></circle>
                        @endif
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-2xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ $completion['rate'] }}%</span>
                        <span class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Done</span>
                    </div>
                </div>
                <dl class="w-full space-y-2 text-sm">
                    @foreach ([['High', $a['summary']['high'], 'bg-rose-500 dark:bg-rose-600'], ['Medium', $a['summary']['medium'], 'bg-amber-500 dark:bg-amber-600'], ['Low', $a['summary']['low'], 'bg-sky-500 dark:bg-sky-600'], ['Done', $a['summary']['done'], 'bg-emerald-500 dark:bg-emerald-600']] as [$k, $v, $dot])
                        <div class="flex items-center gap-2">
                            <span class="size-2.5 rounded-full {{ $dot }}" aria-hidden="true"></span>
                            <dt class="text-zinc-600 dark:text-zinc-300">{{ $k }}</dt>
                            <dd class="ml-auto font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $v }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
            <p class="mt-4 {{ $sub }}">Open cards by priority, plus completed cards.</p>
        </section>
    </div>

    {{-- ═══════════════ Deadlines + workflow ═══════════════ --}}
    <div class="grid gap-6 lg:grid-cols-2">
        <section class="{{ $panel }}" aria-labelledby="deadlines-heading">
            <div class="flex items-center justify-between">
                <h2 id="deadlines-heading" class="{{ $h2 }}">📅 Upcoming Deadlines</h2>
                <a href="{{ route('pmo.board', ['view' => 'due']) }}" wire:navigate class="{{ $link }}">Overdue / Due →</a>
            </div>
            <ul class="mt-3 space-y-2">
                @forelse ($this->deadlines as $task)
                    @php $overdue = $task->isOverdue(); @endphp
                    <li wire:key="deadline-{{ $task->id }}" class="flex items-center gap-3 rounded-xl px-3 py-2 {{ $overdue ? 'bg-rose-500/10' : 'bg-zinc-50 dark:bg-white/[0.03]' }}">
                        <div @class([
                            'flex size-11 shrink-0 flex-col items-center justify-center rounded-lg text-center leading-none',
                            'bg-rose-600 text-white' => $overdue,
                            'bg-white text-zinc-800 ring-1 ring-zinc-200 dark:bg-[#130a2e] dark:text-zinc-100 dark:ring-white/10' => ! $overdue,
                        ])>
                            <span class="text-[10px] font-semibold uppercase">{{ $task->due_date?->format('M') }}</span>
                            <span class="text-base font-bold tabular-nums">{{ $task->due_date?->format('j') }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $task->title }}</p>
                            <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $task->picLabel() ?? 'Unassigned' }} · {{ $task->column->name }}</p>
                        </div>
                        @if ($overdue)
                            <span class="{{ $badge }} bg-rose-600 text-white ring-rose-600">Overdue</span>
                        @elseif ($task->isDueToday())
                            <span class="{{ $badge }} bg-amber-500/15 text-amber-700 ring-amber-500/40 dark:text-amber-300">Today</span>
                        @endif
                    </li>
                @empty
                    <li class="py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">No deadlines in the next 7 days.</li>
                @endforelse
            </ul>
        </section>

        <section class="{{ $panel }}" aria-labelledby="flow-heading">
            <div class="flex items-center justify-between">
                <h2 id="flow-heading" class="{{ $h2 }}">🧱 Workflow</h2>
                <a href="{{ route('pmo.board') }}" wire:navigate class="{{ $link }}">Open board →</a>
            </div>
            <p class="{{ $sub }}">Cards in each stage, with WIP limits.</p>
            <ul class="mt-4 space-y-3">
                @forelse ($this->flow as $row)
                    <li wire:key="flow-{{ $row['column']->id }}" title="{{ $row['column']->name }}: {{ $row['count'] }} {{ str('card')->plural($row['count']) }}{{ $row['column']->wip_limit > 0 ? ' (WIP limit '.$row['column']->wip_limit.')' : '' }}">
                        <div class="mb-1 flex items-center gap-2 text-xs">
                            <span class="size-2.5 rounded-full" style="background: {{ $row['column']->color }}" aria-hidden="true"></span>
                            <span class="font-semibold text-zinc-700 dark:text-zinc-200">{{ $row['column']->icon }} {{ $row['column']->name }}</span>
                            @if ($row['over'])
                                <span class="{{ $badge }} bg-rose-600 text-white ring-rose-600">Over WIP Limit</span>
                            @endif
                            <span class="ml-auto tabular-nums text-zinc-500 dark:text-zinc-400">
                                <strong class="text-zinc-900 dark:text-white">{{ $row['count'] }}</strong>{{ $row['column']->wip_limit > 0 ? ' / '.$row['column']->wip_limit : '' }}
                            </span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-white/5">
                            <div @class(['h-full rounded-full transition-all duration-500', 'bg-rose-500' => $row['over']]) style="width: {{ $row['pct'] }}%; {{ $row['over'] ? '' : 'background: '.$row['column']->color }}"></div>
                        </div>
                    </li>
                @empty
                    <li class="py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">Open the Kanban board to set up your workflow.</li>
                @endforelse
            </ul>
        </section>
    </div>

    {{-- ═══════════════ Portfolio + workload ═══════════════ --}}
    <div class="grid gap-6 lg:grid-cols-2">
        <section class="{{ $panel }}" aria-labelledby="portfolio-heading">
            <div class="flex items-center justify-between">
                <h2 id="portfolio-heading" class="{{ $h2 }}">📁 Project Portfolio</h2>
                <a href="{{ route('pmo.roster') }}" wire:navigate class="{{ $link }}">Manage →</a>
            </div>
            <ul class="mt-4 space-y-3.5">
                @forelse ($portfolio as $row)
                    @php $p = $row['project']; @endphp
                    <li wire:key="project-{{ $p->id }}" title="{{ $p->name }}: {{ $row['done'] }} done, {{ $row['open'] }} open, {{ $row['overdue'] }} overdue">
                        <div class="mb-1.5 flex items-center gap-2">
                            <span class="flex size-7 shrink-0 items-center justify-center rounded-lg text-sm ring-1 ring-inset {{ NeoPalette::badge($p->color) }}" aria-hidden="true">{{ $p->icon }}</span>
                            <span class="truncate text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $p->name }}</span>
                            @if ($p->status === 'on_hold')
                                <span class="{{ $badge }} {{ $p->statusBadge() }}">{{ $p->statusLabel() }}</span>
                            @endif
                            @if ($row['overdue'] > 0)
                                <span class="{{ $badge }} bg-rose-500/15 text-rose-700 ring-rose-500/40 dark:text-rose-300">{{ $row['overdue'] }} overdue</span>
                            @endif
                            <span class="ml-auto shrink-0 text-xs tabular-nums text-zinc-500 dark:text-zinc-400"><strong class="text-zinc-900 dark:text-white">{{ $row['pct'] }}%</strong> · {{ $row['done'] }}/{{ $row['open'] + $row['done'] }}</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-white/5">
                            <div class="h-full rounded-full bg-gradient-to-r from-[#8b5cf6] to-[#ff2e9a] transition-all duration-500" style="width: {{ $row['pct'] }}%"></div>
                        </div>
                    </li>
                @empty
                    <li class="py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">No active projects. <a href="{{ route('pmo.roster') }}" wire:navigate class="font-semibold text-[#8b5cf6] underline">Create one</a>.</li>
                @endforelse
            </ul>
        </section>

        <section class="{{ $panel }}" aria-labelledby="team-heading">
            <div class="flex items-center justify-between">
                <h2 id="team-heading" class="{{ $h2 }}">👥 Team Workload</h2>
                <span class="{{ $sub }}">Capacity {{ $workload['threshold'] }}</span>
            </div>
            <ul class="mt-4 space-y-3.5">
                @forelse ($workload['rows'] as $row)
                    <li wire:key="team-{{ $row['id'] ?? 'none' }}" title="{{ $row['name'] }}: {{ $row['active'] }} active, {{ $row['overdue'] }} overdue">
                        <div class="mb-1.5 flex items-center gap-2">
                            <span class="flex size-6 shrink-0 items-center justify-center rounded-full text-[10px] font-bold text-white {{ $row['swatch'] }}" aria-hidden="true">{{ $row['initials'] }}</span>
                            <span class="truncate text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $row['name'] }}</span>
                            @if ($row['is_me'])
                                <span class="{{ $badge }} bg-emerald-500/15 text-emerald-700 ring-emerald-500/40 dark:text-emerald-300">Me</span>
                            @endif
                            @if ($row['overloaded'])
                                <span class="{{ $badge }} bg-rose-500/15 text-rose-700 ring-rose-500/40 dark:text-rose-300">⚠ Overloaded</span>
                            @endif
                            <span class="ml-auto shrink-0 text-xs tabular-nums text-zinc-500 dark:text-zinc-400"><strong class="text-zinc-900 dark:text-white">{{ $row['active'] }}</strong> active</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-white/5">
                            <div @class(['h-full rounded-full transition-all duration-500', 'bg-[#8b5cf6]' => ! $row['overloaded'], 'bg-rose-500 dark:bg-rose-600' => $row['overloaded']]) style="width: {{ $row['pct'] }}%"></div>
                        </div>
                    </li>
                @empty
                    <li class="py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">No team members yet. <a href="{{ route('pmo.roster') }}" wire:navigate class="font-semibold text-[#8b5cf6] underline">Add PICs</a>.</li>
                @endforelse
            </ul>
        </section>
    </div>

    {{-- ═══════════════ Activity + channels ═══════════════ --}}
    <div class="grid gap-6 lg:grid-cols-3">
        <section class="{{ $panel }} lg:col-span-2" aria-labelledby="activity-heading">
            <div class="flex items-center justify-between">
                <h2 id="activity-heading" class="{{ $h2 }}">🕒 Recent Activity</h2>
                <a href="{{ route('pmo.board') }}" wire:navigate class="{{ $link }}">Board analytics →</a>
            </div>
            <ol class="mt-3 divide-y divide-zinc-100 dark:divide-white/5">
                @forelse ($a['recent']->take(6) as $log)
                    <li wire:key="log-{{ $log->id }}" class="flex items-start gap-3 py-2.5">
                        <span class="mt-0.5" aria-hidden="true">{{ $log->actionIcon() }}</span>
                        <p class="min-w-0 flex-1 text-sm text-zinc-700 dark:text-zinc-200">
                            <span class="font-semibold text-zinc-900 dark:text-white">{{ $log->actor_name }}</span>
                            <span class="{{ $badge }} {{ $log->actionBadge() }} mx-1">{{ $log->actionLabel() }}</span>
                            <span class="break-words">{{ $log->task_title }}</span>
                        </p>
                        <time class="shrink-0 text-[11px] text-zinc-400" datetime="{{ $log->created_at?->toIso8601String() }}">{{ $log->created_at?->diffForHumans() }}</time>
                    </li>
                @empty
                    <li class="py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">No activity yet.</li>
                @endforelse
            </ol>
        </section>

        <section class="{{ $panel }}" aria-labelledby="channels-heading">
            <div class="flex items-center justify-between">
                <h2 id="channels-heading" class="{{ $h2 }}">📡 Automation Channels</h2>
                <a href="{{ route('pmo.hub') }}" wire:navigate class="{{ $link }}">Hub →</a>
            </div>
            <ul class="mt-4 space-y-2.5">
                @foreach ($this->channels as $key => $channel)
                    <li wire:key="channel-{{ $key }}" class="flex items-center gap-3 rounded-xl bg-zinc-50 px-3 py-2.5 dark:bg-white/[0.03]">
                        <span class="text-lg" aria-hidden="true">{{ $channel['icon'] }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $channel['label'] }}</p>
                            <p class="truncate text-[11px] text-zinc-500 dark:text-zinc-400">{{ $channel['detail'] }}</p>
                        </div>
                        @if ($channel['connected'])
                            <span class="{{ $badge }} bg-emerald-500/15 text-emerald-700 ring-emerald-500/40 dark:text-emerald-300">● Connected</span>
                        @else
                            <span class="{{ $badge }} bg-amber-500/15 text-amber-700 ring-amber-500/40 dark:text-amber-300">◐ Simulation</span>
                        @endif
                    </li>
                @endforeach
            </ul>
            <p class="mt-3 {{ $sub }}">Daily standup at {{ config('pmo.standup_time') }} · overdue check at {{ config('pmo.overdue_check_time') }}.</p>
        </section>
    </div>
</div>
