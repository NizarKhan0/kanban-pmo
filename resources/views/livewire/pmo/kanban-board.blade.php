@php
    use App\Models\BoardColumn;

    $kpis = $this->kpis;
    $field = 'w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm text-zinc-900 placeholder-zinc-400 shadow-sm transition focus:border-[#8b5cf6] focus:outline-none focus:ring-2 focus:ring-[#8b5cf6]/30 dark:border-white/10 dark:bg-[#07040f] dark:text-white dark:placeholder-zinc-500';
    $label = 'mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-400';
    $error = 'mt-1.5 text-xs font-medium text-rose-600 dark:text-rose-400';
    $panel = 'rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-white/10 dark:bg-[#0d0722]';
    $btnPrimary = 'inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#ff2e9a] to-[#8b5cf6] px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-[#ff2e9a]/20 transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-[#ff2e9a]/50 disabled:opacity-60';
    $btnGhost = 'inline-flex items-center justify-center gap-2 rounded-xl border border-zinc-300 px-4 py-2.5 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-100 dark:border-white/15 dark:text-zinc-200 dark:hover:bg-white/5';
    $badge = 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset';
    $menuItem = 'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-zinc-700 transition hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-40 dark:text-zinc-200 dark:hover:bg-white/5';
    $lastIndex = $this->columns->count() - 1;
@endphp

<div class="flex h-full w-full flex-col gap-6">

    {{-- ═══════════════ Header ═══════════════ --}}
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#ff2e9a]">PMO Command Center</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">Kanban Board</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Shape your delivery workflow. Drag a column by its handle to reorder it.</p>
        </div>
        <button type="button" wire:click="createColumn" class="{{ $btnPrimary }}">
            <span aria-hidden="true">＋</span> Add Column
        </button>
    </header>

    {{-- ═══════════════ KPI Cards ═══════════════ --}}
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Board metrics">
        @foreach ([
            ['label' => 'Board Columns', 'value' => $kpis['columns'], 'hint' => 'Workflow stages', 'icon' => '🧱', 'accent' => 'from-[#8b5cf6]'],
            ['label' => 'Cards on Board', 'value' => $kpis['cards'], 'hint' => 'Across all columns', 'icon' => '🗃️', 'accent' => 'from-[#ff2e9a]'],
            ['label' => 'WIP-Limited Columns', 'value' => $kpis['wip_limited'], 'hint' => 'Columns with a WIP limit', 'icon' => '🚦', 'accent' => 'from-sky-500'],
            ['label' => 'Over WIP Limit', 'value' => $kpis['over_wip'], 'hint' => $kpis['over_wip'] > 0 ? 'Needs attention' : 'All within limits', 'icon' => '🔥', 'accent' => $kpis['over_wip'] > 0 ? 'from-rose-500' : 'from-emerald-500'],
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

    {{-- ═══════════════ Board ═══════════════ --}}
    <section class="-mx-1 flex-1 overflow-x-auto pb-4" aria-label="Kanban board">
        <div class="flex min-h-[28rem] items-stretch gap-4 px-1">
            <div class="flex items-stretch gap-4" x-sort="$wire.moveColumn($key, $position)" x-sort:config="{ animation: 180, direction: 'horizontal' }">
                @foreach ($this->columns as $column)
                    @php
                        $count = $this->cardCounts[$column->id] ?? 0;
                        $over = $column->isOverWip($count);
                    @endphp
                    <div
                        wire:key="column-{{ $column->id }}"
                        x-sort:item="{{ $column->id }}"
                        style="--col: {{ $column->color }}"
                        @class([
                            'flex w-72 shrink-0 flex-col overflow-hidden rounded-2xl border bg-zinc-50 shadow-sm transition dark:bg-[#0d0722]',
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

                            <span class="{{ $badge }} bg-(--col)/15 text-zinc-700 ring-(--col)/40 dark:text-white" title="Cards in column">{{ $count }}</span>

                            {{-- ⋮ menu --}}
                            <div class="relative" x-data="{ open: false }" x-on:keydown.escape="open = false">
                                <button type="button" x-on:click="open = !open" class="rounded-md px-1.5 py-0.5 text-lg leading-none text-zinc-500 hover:bg-zinc-200/70 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-white" aria-label="Column options for {{ $column->name }}" x-bind:aria-expanded="open">⋮</button>
                                <div x-cloak x-show="open" x-transition.origin.top.right x-on:click.outside="open = false"
                                    class="absolute right-0 z-30 mt-1 w-44 rounded-xl border border-zinc-200 bg-white p-1 shadow-xl dark:border-white/10 dark:bg-[#130a2e]">
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

                        {{-- Card lane (task cards arrive with the Tasks module) --}}
                        <div class="mx-3 mb-3 flex flex-1 flex-col items-center justify-center rounded-xl border-2 border-dashed border-zinc-200 px-4 py-10 text-center dark:border-white/10">
                            <p class="text-2xl opacity-60" aria-hidden="true">{{ $count > 0 ? '🗃️' : '🫙' }}</p>
                            <p class="mt-2 text-xs font-medium text-zinc-500 dark:text-zinc-400">
                                {{ $count > 0 ? $count.' '.str('card')->plural($count) : 'No cards yet' }}
                            </p>
                        </div>
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

    {{-- ═══════════════ Column Modal (Create / Edit) ═══════════════ --}}
    <x-pmo.modal
        show="showColumnModal"
        :title="$columnForm->id ? 'Edit Column' : 'Add Column'"
        :subtitle="$columnForm->id ? 'Update this workflow stage.' : 'Add a new workflow stage to your board.'"
    >
        <form wire:submit="saveColumn" class="space-y-4">
            {{-- Live preview --}}
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

    {{-- ═══════════════ Delete Confirmation Modal ═══════════════ --}}
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
                    <button type="button" wire:click="destroyColumn" wire:loading.attr="disabled" wire:target="destroyColumn"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-600/20 transition hover:bg-rose-500 disabled:opacity-60">
                        <span wire:loading.remove wire:target="destroyColumn">Delete Column</span>
                        <span wire:loading wire:target="destroyColumn">Deleting…</span>
                    </button>
                </div>
            @endif
        </div>
    </x-pmo.modal>
</div>
