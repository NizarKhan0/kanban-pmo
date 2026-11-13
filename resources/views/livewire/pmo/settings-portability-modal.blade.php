@php
    $board = $this->board;
    $snapshots = $this->snapshots;
    $totalKb = (float) $snapshots->sum(fn ($s) => (float) $s->file_size_kb);

    $field = 'w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm text-zinc-900 placeholder-zinc-400 shadow-sm transition focus:border-[#8b5cf6] focus:outline-none focus:ring-2 focus:ring-[#8b5cf6]/30 dark:border-white/10 dark:bg-[#07040f] dark:text-white dark:placeholder-zinc-500';
    $label = 'mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-400';
    $error = 'mt-1.5 text-xs font-medium text-rose-600 dark:text-rose-400';
    $section = 'space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-white/10';
    $h3 = 'text-sm font-semibold text-zinc-900 dark:text-white';
    $btnPrimary = 'inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#ff2e9a] to-[#8b5cf6] px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-[#ff2e9a]/20 transition hover:brightness-110 disabled:opacity-60';
    $btnGhost = 'inline-flex items-center justify-center gap-2 rounded-xl border border-zinc-300 px-4 py-2.5 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-100 disabled:opacity-60 dark:border-white/15 dark:text-zinc-200 dark:hover:bg-white/5';
    $btnDanger = 'inline-flex items-center justify-center gap-2 rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-600/20 transition hover:bg-rose-500 disabled:cursor-not-allowed disabled:opacity-50';
    $rowBtn = 'rounded-lg px-2 py-1 text-xs font-semibold transition';
@endphp

<section class="w-full" x-data x-on:pmo-apply-theme.window="$flux.appearance = $event.detail.theme">

    {{-- ═══════════ Restore — confirmation (stacked) ═══════════ --}}
    <x-pmo.modal show="showRestoreModal" title="Restore Snapshot" max-width="max-w-md" close="closeConfirm" :stacked="true">
        <div class="space-y-4">
            <div class="flex items-start gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-zinc-700 dark:text-zinc-200">
                <span class="text-xl" aria-hidden="true">⚠️</span>
                <p>Your current board (projects, PICs, columns, cards, subtasks and activity) will be <strong>replaced</strong> by <strong class="text-zinc-900 dark:text-white">“{{ $targetLabel }}”</strong>.</p>
            </div>
            <p class="flex items-start gap-2 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-xs text-emerald-800 dark:text-emerald-300">
                <span aria-hidden="true">🛡️</span> A safety snapshot of your current board is created automatically first, so you can undo this.
            </p>
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="closeConfirm" class="{{ $btnGhost }}">Cancel</button>
                <button type="button" wire:click="restore" wire:loading.attr="disabled" wire:target="restore" class="{{ $btnPrimary }}">
                    <span wire:loading.remove wire:target="restore">Restore Snapshot</span>
                    <span wire:loading wire:target="restore">Restoring…</span>
                </button>
            </div>
        </div>
    </x-pmo.modal>

    {{-- ═══════════ Delete snapshot — confirmation (stacked) ═══════════ --}}
    <x-pmo.modal show="showDeleteModal" title="Delete Snapshot" max-width="max-w-md" close="closeConfirm" :stacked="true">
        <div class="space-y-4">
            <div class="flex items-start gap-3 rounded-xl border border-rose-500/30 bg-rose-500/10 p-4">
                <span class="text-xl" aria-hidden="true">⚠️</span>
                <p class="text-sm text-zinc-700 dark:text-zinc-200">Delete the snapshot <strong class="text-zinc-900 dark:text-white">“{{ $targetLabel }}”</strong>? Your current board is not affected, but this backup cannot be recovered.</p>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="closeConfirm" class="{{ $btnGhost }}">Cancel</button>
                <button type="button" wire:click="destroy" class="{{ $btnDanger }}">Delete Snapshot</button>
            </div>
        </div>
    </x-pmo.modal>

    {{-- ═══════════ Reset to sample — two-step confirmation (stacked) ═══════════ --}}
    <x-pmo.modal show="showResetModal" title="Reset Workspace to Sample Data" max-width="max-w-md" close="closeConfirm" :stacked="true">
        <div class="space-y-4">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                <span @class(['rounded-full px-2 py-0.5', 'bg-rose-600 text-white' => $resetStep === 1, 'bg-zinc-200 dark:bg-white/10' => $resetStep !== 1])>Step 1</span>
                <span aria-hidden="true">→</span>
                <span @class(['rounded-full px-2 py-0.5', 'bg-rose-600 text-white' => $resetStep === 2, 'bg-zinc-200 dark:bg-white/10' => $resetStep !== 2])>Step 2</span>
            </div>

            @if ($resetStep === 1)
                <div class="space-y-2 rounded-xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-zinc-700 dark:text-zinc-200">
                    <p class="font-semibold text-rose-700 dark:text-rose-300">This permanently replaces your workspace data:</p>
                    <ul class="list-disc space-y-0.5 pl-5 text-xs">
                        <li>{{ $board['projects'] }} projects and {{ $board['people'] }} team members</li>
                        <li>{{ $board['columns'] }} columns and {{ $board['tasks'] }} cards (with subtasks)</li>
                        <li>The activity history</li>
                    </ul>
                    <p class="text-xs">Snapshots, AI prompt templates and automation audit logs are kept. A safety snapshot is created first.</p>
                </div>
                <label class="flex cursor-pointer items-start gap-2 text-sm text-zinc-700 dark:text-zinc-200">
                    <input type="checkbox" wire:model="resetAcknowledge" class="mt-0.5 size-4 rounded accent-rose-600">
                    I understand my current board will be replaced by the default sample data.
                </label>
                @error('resetAcknowledge') <p class="{{ $error }}">{{ $message }}</p> @enderror
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeConfirm" class="{{ $btnGhost }}">Cancel</button>
                    <button type="button" wire:click="resetNext" class="{{ $btnDanger }}">Continue</button>
                </div>
            @else
                <div>
                    <label for="reset-phrase" class="{{ $label }}">Type {{ $resetPhraseRequired }} to confirm</label>
                    <input id="reset-phrase" type="text" wire:model="resetPhrase" class="{{ $field }} font-mono uppercase" autocomplete="off" placeholder="{{ $resetPhraseRequired }}" x-init="$nextTick(() => $el.focus())">
                    @error('resetPhrase') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeConfirm" class="{{ $btnGhost }}">Cancel</button>
                    <button type="button" wire:click="resetWorkspace" wire:loading.attr="disabled" wire:target="resetWorkspace" class="{{ $btnDanger }}">
                        <span wire:loading.remove wire:target="resetWorkspace">Reset All Data to Default Sample</span>
                        <span wire:loading wire:target="resetWorkspace">Resetting…</span>
                    </button>
                </div>
            @endif
        </div>
    </x-pmo.modal>

    {{-- ═══════════ Page body (Settings › Data & Backup) ═══════════ --}}
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">Settings &amp; Data Portability</flux:heading>

    <x-settings.layout heading="Data & Backup" subheading="Back up, export, import and restore your workspace." :wide="true">
        <div class="space-y-5">

            {{-- KPI strip --}}
            <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ([
                    ['Current Board', $board['columns'].' cols · '.$board['tasks'].' cards'],
                    ['Team & Projects', $board['people'].' PICs · '.$board['projects'].' projects'],
                    ['Snapshots', $snapshots->count().' · '.($totalKb >= 1024 ? number_format($totalKb / 1024, 2).' MB' : number_format($totalKb, 2).' KB')],
                    ['Latest Snapshot', $snapshots->first()?->created_at?->diffForHumans() ?? 'None yet'],
                ] as [$k, $v])
                    <div class="rounded-xl border border-zinc-200 px-3 py-2 dark:border-white/10">
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $k }}</dt>
                        <dd class="mt-0.5 text-sm font-bold tabular-nums text-zinc-900 dark:text-white">{{ $v }}</dd>
                    </div>
                @endforeach
            </dl>

            <div class="grid gap-4 md:grid-cols-2">
                {{-- Export --}}
                <div class="{{ $section }} md:col-span-2">
                    <h3 class="{{ $h3 }}">Export</h3>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" x-on:click="$wire.exportJson($flux.dark ? 'dark' : 'light')" class="{{ $btnGhost }}">⬇️ Export JSON</button>
                        <button type="button" wire:click="exportCsv" class="{{ $btnGhost }}">⬇️ Export CSV</button>
                    </div>
                    <p class="text-[11px] text-zinc-500 dark:text-zinc-400">JSON is compatible with the original Kanban PMO Neo HTML app. API keys are never included.</p>
                </div>

                {{-- Create snapshot --}}
                <form wire:submit="createSnapshot" class="{{ $section }}">
                    <h3 class="{{ $h3 }}">Create Snapshot Backup</h3>
                    <div>
                        <label for="snapshot-note" class="sr-only">Snapshot label</label>
                        <input id="snapshot-note" type="text" wire:model="snapshotNote" maxlength="250" class="{{ $field }}" placeholder="Label (optional), e.g. Before sprint planning">
                        @error('snapshotNote') <p class="{{ $error }}">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" wire:loading.attr="disabled" wire:target="createSnapshot" class="{{ $btnPrimary }}">📸 Create Snapshot Backup</button>
                </form>

                {{-- Import --}}
                <form wire:submit="importJson" class="{{ $section }}">
                    <h3 class="{{ $h3 }}">Import JSON</h3>
                    <div>
                        <label for="import-file" class="sr-only">Backup file</label>
                        <input id="import-file" type="file" wire:model="importFile" accept=".json,application/json"
                            class="block w-full text-xs text-zinc-600 file:mr-3 file:rounded-lg file:border-0 file:bg-[#8b5cf6]/15 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-violet-700 hover:file:bg-[#8b5cf6]/25 dark:text-zinc-300 dark:file:text-violet-200">
                        <p wire:loading wire:target="importFile" class="mt-1 text-xs text-zinc-500">Uploading…</p>
                        @error('importFile') <p class="{{ $error }}">{{ $message }}</p> @enderror
                        <p class="mt-1 text-[11px] text-zinc-500 dark:text-zinc-400">Original HTML or Laravel backup, max {{ $maxUploadMb }} MB.</p>
                    </div>
                    <label class="flex cursor-pointer items-center gap-2 text-xs text-zinc-700 dark:text-zinc-200">
                        <input type="checkbox" wire:model.live="importApply" class="size-4 rounded accent-[#8b5cf6]"> Apply to my board immediately
                    </label>
                    @if ($importApply)
                        <label class="flex cursor-pointer items-start gap-2 text-xs text-rose-700 dark:text-rose-300">
                            <input type="checkbox" wire:model="importConfirm" class="mt-0.5 size-4 rounded accent-rose-600">
                            I understand this replaces my current board (a safety snapshot is created first).
                        </label>
                        @error('importConfirm') <p class="{{ $error }}">{{ $message }}</p> @enderror
                    @endif
                    <button type="submit" wire:loading.attr="disabled" wire:target="importJson,importFile" class="{{ $btnGhost }}">⬆️ Import JSON</button>
                </form>
            </div>

            {{-- Snapshot history --}}
            <div class="{{ $section }}">
                <h3 class="{{ $h3 }}">Snapshot History</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-zinc-200 text-[11px] uppercase tracking-wide text-zinc-500 dark:border-white/10 dark:text-zinc-400">
                            <tr>
                                <th class="py-2 pr-3">Snapshot</th>
                                <th class="py-2 pr-3">Created</th>
                                <th class="py-2 pr-3 text-right">Size</th>
                                <th class="py-2 pr-3 text-right">Columns</th>
                                <th class="py-2 pr-3 text-right">Cards</th>
                                <th class="py-2 text-right"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-white/5">
                            @forelse ($snapshots as $snapshot)
                                <tr wire:key="snapshot-{{ $snapshot->id }}" class="align-top">
                                    <td class="py-2 pr-3">
                                        @if ($editingId === $snapshot->id)
                                            <form wire:submit="saveNote" class="flex items-center gap-1">
                                                <input type="text" wire:model="editingNote" maxlength="250" class="{{ $field }} !py-1 text-xs" aria-label="Snapshot label" x-init="$el.focus()" x-on:keydown.escape.stop.prevent="$wire.cancelEdit()">
                                                <button type="submit" class="{{ $rowBtn }} bg-[#8b5cf6] text-white">Save</button>
                                                <button type="button" wire:click="cancelEdit" class="{{ $rowBtn }} text-zinc-500 hover:bg-zinc-100 dark:hover:bg-white/5">Cancel</button>
                                            </form>
                                            @error('editingNote') <p class="{{ $error }}">{{ $message }}</p> @enderror
                                        @else
                                            <p class="font-medium text-zinc-900 dark:text-white">{{ $snapshot->notes ?: 'Untitled snapshot' }}</p>
                                        @endif
                                        <p class="font-mono text-[11px] text-zinc-500 dark:text-zinc-400">{{ $snapshot->filename }}</p>
                                    </td>
                                    <td class="whitespace-nowrap py-2 pr-3 text-xs text-zinc-600 dark:text-zinc-300" title="{{ $snapshot->created_at?->setTimezone(config('pmo.timezone'))->format('j M Y, H:i') }}">{{ $snapshot->created_at?->diffForHumans() }}</td>
                                    <td class="whitespace-nowrap py-2 pr-3 text-right tabular-nums text-xs text-zinc-600 dark:text-zinc-300">{{ $snapshot->sizeLabel() }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums text-xs text-zinc-600 dark:text-zinc-300">{{ $snapshot->columns_count }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums text-xs text-zinc-600 dark:text-zinc-300">{{ $snapshot->tasks_count }}</td>
                                    <td class="py-2 text-right">
                                        <div class="flex flex-wrap justify-end gap-1">
                                            <button type="button" wire:click="confirmRestore({{ $snapshot->id }})" class="{{ $rowBtn }} text-[#ff2e9a] hover:bg-[#ff2e9a]/10">↺ Restore Snapshot</button>
                                            <button type="button" wire:click="downloadSnapshot({{ $snapshot->id }})" class="{{ $rowBtn }} text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/5">⬇️ Download</button>
                                            <button type="button" wire:click="editNote({{ $snapshot->id }})" class="{{ $rowBtn }} text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/5">✏️ Edit Label</button>
                                            <button type="button" wire:click="confirmDelete({{ $snapshot->id }})" class="{{ $rowBtn }} text-rose-600 hover:bg-rose-500/10 dark:text-rose-400">🗑 Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">No snapshots yet. Create one before making big changes.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Danger zone --}}
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-rose-500/30 bg-rose-500/5 p-4">
                <div>
                    <h3 class="text-sm font-semibold text-rose-700 dark:text-rose-300">Danger Zone</h3>
                    <p class="text-xs text-zinc-600 dark:text-zinc-300">Replace the whole board with the default sample workspace.</p>
                </div>
                <button type="button" wire:click="startReset" class="{{ $btnDanger }}">Reset Workspace to Sample Data</button>
            </div>

        </div>
    </x-settings.layout>
</section>
