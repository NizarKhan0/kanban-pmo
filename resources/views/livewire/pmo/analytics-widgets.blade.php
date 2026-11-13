@php
    $w = $this->widgets;
    $summary = $w['summary'];
    $completion = $w['completion'];
    $workload = $w['workload'];

    $panel = 'rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-[#0d0722]';
    $heading = 'text-sm font-semibold text-zinc-900 dark:text-white';
    $sub = 'text-xs text-zinc-500 dark:text-zinc-400';
    $badge = 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset';
    $btnGhost = 'inline-flex items-center justify-center gap-2 rounded-xl border border-zinc-300 px-4 py-2.5 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-100 dark:border-white/15 dark:text-zinc-200 dark:hover:bg-white/5';
    $field = 'w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm text-zinc-900 shadow-sm focus:border-[#8b5cf6] focus:outline-none focus:ring-2 focus:ring-[#8b5cf6]/30 dark:border-white/10 dark:bg-[#07040f] dark:text-white';

    // Validated palette (dataviz validator): 500 steps on light, 600 steps on the #0d0722 dark surface.
    $priorityBar = [
        'high' => 'bg-rose-500 dark:bg-rose-600',
        'medium' => 'bg-amber-500 dark:bg-amber-600',
        'low' => 'bg-sky-500 dark:bg-sky-600',
        'done' => 'bg-emerald-500 dark:bg-emerald-600',
    ];
@endphp

<section class="space-y-4" aria-labelledby="analytics-heading">
    <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
            <h2 id="analytics-heading" class="text-lg font-semibold text-zinc-900 dark:text-white">Board Analytics</h2>
            <p class="{{ $sub }}">Live metrics that update as cards move or filters change.</p>
        </div>
        @if ($narrowed)
            <span class="{{ $badge }} bg-[#8b5cf6]/10 text-violet-700 ring-[#8b5cf6]/30 dark:text-violet-300">Filtered view</span>
        @endif
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {{-- ═══════ 1. Task Summary ═══════ --}}
        <article class="{{ $panel }}" aria-labelledby="w-summary">
            <div class="flex items-center justify-between">
                <h3 id="w-summary" class="{{ $heading }}">Task Summary</h3>
                <span aria-hidden="true">🗂️</span>
            </div>
            <p class="mt-3 text-4xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ $summary['total'] }}</p>
            <p class="{{ $sub }}">{{ str('task')->plural($summary['total']) }} in view · {{ $summary['open'] }} open · {{ $summary['done'] }} done</p>

            <dl class="mt-4 grid grid-cols-3 gap-2">
                @foreach (['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'] as $key => $label)
                    <div class="rounded-xl border border-zinc-200 px-3 py-2 dark:border-white/10" title="{{ $summary[$key] }} open {{ strtolower($label) }}-priority {{ str('task')->plural($summary[$key]) }}">
                        <dt class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            <span class="size-2 rounded-full {{ $priorityBar[$key] }}" aria-hidden="true"></span>{{ $label }}
                        </dt>
                        <dd class="mt-0.5 text-lg font-bold tabular-nums text-zinc-900 dark:text-white">{{ $summary[$key] }}</dd>
                    </div>
                @endforeach
            </dl>
        </article>

        {{-- ═══════ 2. Completion Rate (SVG donut) ═══════ --}}
        <article class="{{ $panel }}" aria-labelledby="w-completion">
            <div class="flex items-center justify-between">
                <h3 id="w-completion" class="{{ $heading }}">Completion Rate</h3>
                <span aria-hidden="true">🎯</span>
            </div>
            <div class="mt-3 flex items-center gap-5">
                <div class="relative size-32 shrink-0" title="{{ $completion['done'] }} of {{ $completion['total'] }} {{ str('task')->plural($completion['total']) }} completed">
                    <svg viewBox="0 0 36 36" class="size-full -rotate-90" role="img" aria-label="Completion rate {{ $completion['rate'] }} percent">
                        <title>{{ $completion['done'] }} of {{ $completion['total'] }} completed ({{ $completion['rate'] }}%)</title>
                        <circle cx="18" cy="18" r="15.915" fill="none" stroke-width="3.2" class="stroke-zinc-200 dark:stroke-white/10"></circle>
                        @if ($completion['rate'] > 0)
                            <circle cx="18" cy="18" r="15.915" fill="none" stroke-width="3.2"
                                stroke-dasharray="{{ $completion['rate'] }} {{ 100 - $completion['rate'] }}"
                                class="stroke-emerald-500 transition-[stroke-dasharray] duration-500 dark:stroke-emerald-600"></circle>
                        @endif
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-2xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ $completion['rate'] }}%</span>
                        <span class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Done</span>
                    </div>
                </div>
                <dl class="space-y-2 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="size-2.5 rounded-full bg-emerald-500 dark:bg-emerald-600" aria-hidden="true"></span>
                        <dt class="text-zinc-600 dark:text-zinc-300">Completed</dt>
                        <dd class="ml-auto pl-3 font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $completion['done'] }}</dd>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="size-2.5 rounded-full bg-zinc-300 dark:bg-white/20" aria-hidden="true"></span>
                        <dt class="text-zinc-600 dark:text-zinc-300">Remaining</dt>
                        <dd class="ml-auto pl-3 font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $completion['total'] - $completion['done'] }}</dd>
                    </div>
                </dl>
            </div>
        </article>

        {{-- ═══════ 3. Tasks by Priority ═══════ --}}
        <article class="{{ $panel }} md:col-span-2 xl:col-span-1" aria-labelledby="w-priority">
            <div class="flex items-center justify-between">
                <h3 id="w-priority" class="{{ $heading }}">Tasks by Priority</h3>
                <span aria-hidden="true">🚦</span>
            </div>
            <ul class="mt-4 space-y-3">
                @foreach ($w['priorities'] as $row)
                    <li wire:key="prio-{{ $row['key'] }}" title="{{ $row['label'] }}: {{ $row['count'] }} {{ str('task')->plural($row['count']) }} ({{ $row['pct'] }}%)">
                        <div class="mb-1 flex items-center justify-between text-xs">
                            <span class="font-semibold text-zinc-700 dark:text-zinc-200">{{ $row['label'] }}</span>
                            <span class="tabular-nums text-zinc-500 dark:text-zinc-400"><strong class="text-zinc-900 dark:text-white">{{ $row['count'] }}</strong> · {{ $row['pct'] }}%</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-white/5">
                            <div class="h-full rounded-full transition-all duration-500 {{ $priorityBar[$row['key']] }}" style="width: {{ $row['pct'] }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </article>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- ═══════ 4. Recent Activity ═══════ --}}
        <article class="{{ $panel }}" aria-labelledby="w-activity">
            <div class="flex items-center justify-between gap-2">
                <h3 id="w-activity" class="{{ $heading }}">Recent Activity</h3>
                <button type="button" wire:click="confirmClear" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-600 transition hover:bg-rose-500/10 dark:text-rose-400">🗑 Clear Activity Logs</button>
            </div>

            <ol class="mt-3 divide-y divide-zinc-100 dark:divide-white/5">
                @forelse ($w['recent'] as $log)
                    <li wire:key="log-{{ $log->id }}" class="flex items-start gap-3 py-2.5">
                        <span class="mt-0.5 text-base" aria-hidden="true">{{ $log->actionIcon() }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-zinc-700 dark:text-zinc-200">
                                <span class="font-semibold text-zinc-900 dark:text-white">{{ $log->actor_name }}</span>
                                <span class="{{ $badge }} {{ $log->actionBadge() }} mx-1">{{ $log->actionLabel() }}</span>
                                <span class="break-words">{{ $log->task_title }}</span>
                            </p>
                            @if ($log->meta_details)
                                <p class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400" title="{{ $log->meta_details }}">{{ $log->meta_details }}</p>
                            @endif
                        </div>
                        <time class="shrink-0 text-[11px] text-zinc-400" datetime="{{ $log->created_at?->toIso8601String() }}" title="{{ $log->created_at?->setTimezone(config('pmo.timezone'))->format('j M Y, H:i') }}">
                            {{ $log->created_at?->diffForHumans() }}
                        </time>
                    </li>
                @empty
                    <li class="py-8 text-center">
                        <p class="text-2xl" aria-hidden="true">🕊️</p>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">No activity yet. Actions on the board will appear here.</p>
                    </li>
                @endforelse
            </ol>
        </article>

        {{-- ═══════ 5. Team Workload ═══════ --}}
        <article class="{{ $panel }}" aria-labelledby="w-workload">
            <div class="flex items-center justify-between gap-2">
                <h3 id="w-workload" class="{{ $heading }}">Team Workload</h3>
                <span class="{{ $sub }}">Active tasks · capacity {{ $workload['threshold'] }}</span>
            </div>

            <ul class="mt-4 space-y-3.5">
                @forelse ($workload['rows'] as $row)
                    @php $capacityAt = (int) round($workload['threshold'] / $workload['max'] * 100); @endphp
                    <li wire:key="wl-{{ $row['id'] ?? 'none' }}" title="{{ $row['name'] }}: {{ $row['active'] }} active, {{ $row['overdue'] }} overdue">
                        <div class="mb-1.5 flex items-center gap-2">
                            <span class="flex size-6 shrink-0 items-center justify-center rounded-full text-[10px] font-bold text-white {{ $row['swatch'] }}" aria-hidden="true">{{ $row['initials'] }}</span>
                            <span class="truncate text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $row['name'] }}</span>
                            @if ($row['is_me'])
                                <span class="{{ $badge }} bg-emerald-500/15 text-emerald-700 ring-emerald-500/40 dark:text-emerald-300">Me</span>
                            @endif
                            @if ($row['overloaded'])
                                <span class="{{ $badge }} bg-rose-500/15 text-rose-700 ring-rose-500/40 dark:text-rose-300">⚠ Overloaded</span>
                            @endif
                            @if ($row['former'])
                                <span class="{{ $badge }} bg-zinc-500/10 text-zinc-600 ring-zinc-500/20 dark:text-zinc-300" title="Includes cards whose PIC was removed (name preserved)">incl. former PICs</span>
                            @endif
                            <span class="ml-auto shrink-0 text-xs tabular-nums text-zinc-500 dark:text-zinc-400">
                                <strong class="text-zinc-900 dark:text-white">{{ $row['active'] }}</strong> active
                                @if ($row['overdue'] > 0)
                                    · <span class="font-semibold text-rose-600 dark:text-rose-400">{{ $row['overdue'] }} overdue</span>
                                @endif
                            </span>
                        </div>
                        <div class="relative h-2 rounded-full bg-zinc-100 dark:bg-white/5">
                            <div @class([
                                'h-full rounded-full transition-all duration-500',
                                'bg-[#8b5cf6]' => ! $row['overloaded'],
                                'bg-rose-500 dark:bg-rose-600' => $row['overloaded'],
                            ]) style="width: {{ $row['pct'] }}%"></div>
                            <span class="absolute -top-0.5 h-3 w-px bg-zinc-400 dark:bg-zinc-500" style="left: {{ $capacityAt }}%" aria-hidden="true"></span>
                        </div>
                    </li>
                @empty
                    <li class="py-8 text-center">
                        <p class="text-2xl" aria-hidden="true">👥</p>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">No team members yet. <a href="{{ route('pmo.roster') }}" wire:navigate class="font-semibold text-[#8b5cf6] underline">Add PICs</a> to track workload.</p>
                    </li>
                @endforelse
            </ul>
            @if ($workload['rows'] !== [])
                <p class="mt-3 flex items-center gap-1.5 text-[11px] text-zinc-500 dark:text-zinc-400">
                    <span class="inline-block h-3 w-px bg-zinc-400" aria-hidden="true"></span> Capacity marker ({{ $workload['threshold'] }} open tasks)
                </p>
            @endif
        </article>
    </div>

    {{-- ═══════ Clear Activity Logs — Confirmation ═══════ --}}
    <x-pmo.modal show="showClearModal" title="Clear Activity Logs" max-width="max-w-md">
        <div class="space-y-4">
            <div class="flex items-start gap-3 rounded-xl border border-rose-500/30 bg-rose-500/10 p-4">
                <span class="text-xl" aria-hidden="true">⚠️</span>
                <p class="text-sm text-zinc-700 dark:text-zinc-200">Activity history (including standup notes) in the selected range will be permanently deleted. Tasks, columns and team members are not affected.</p>
            </div>

            <div>
                <label for="clear-range" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-400">Remove</label>
                <select id="clear-range" wire:model.live="clearRange" class="{{ $field }}">
                    @foreach ($ranges as $key => $text)
                        <option value="{{ $key }}">{{ $text }}</option>
                    @endforeach
                </select>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                    <strong class="tabular-nums text-zinc-900 dark:text-white">{{ $clearCount }}</strong> of {{ $totalLogs }} {{ str('entry')->plural($totalLogs) }} will be removed.
                </p>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" wire:click="closeModals" class="{{ $btnGhost }}">Cancel</button>
                <button type="button" wire:click="clearLogs" wire:loading.attr="disabled" wire:target="clearLogs" @disabled($clearCount === 0)
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-600/20 transition hover:bg-rose-500 disabled:cursor-not-allowed disabled:opacity-50">
                    Clear Activity Logs
                </button>
            </div>
        </div>
    </x-pmo.modal>
</section>
