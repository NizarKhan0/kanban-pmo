@php
    $report = $this->report;
    $field = 'w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm text-zinc-900 placeholder-zinc-400 shadow-sm transition focus:border-[#8b5cf6] focus:outline-none focus:ring-2 focus:ring-[#8b5cf6]/30 dark:border-white/10 dark:bg-[#07040f] dark:text-white dark:placeholder-zinc-500';
    $label = 'mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-400';
    $error = 'mt-1.5 text-xs font-medium text-rose-600 dark:text-rose-400';
    $btnPrimary = 'inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#ff2e9a] to-[#8b5cf6] px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-[#ff2e9a]/20 transition hover:brightness-110 disabled:opacity-60';
    $btnGhost = 'inline-flex items-center justify-center gap-2 rounded-xl border border-zinc-300 px-4 py-2.5 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-100 dark:border-white/15 dark:text-zinc-200 dark:hover:bg-white/5';
    $chip = 'inline-flex items-center gap-1.5 rounded-xl border border-zinc-300 px-3 py-2 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-100 dark:border-white/15 dark:text-zinc-200 dark:hover:bg-white/5';
@endphp

<div>
    {{-- Delete Note — stacked, declared first so Escape closes it before the report modal --}}
    <x-pmo.modal show="showNoteDeleteModal" title="Delete Note" max-width="max-w-sm" close="cancelNoteDelete" :stacked="true">
        <div class="space-y-4">
            <p class="text-sm text-zinc-700 dark:text-zinc-200">Delete the note <strong class="text-zinc-900 dark:text-white">“{{ \Illuminate\Support\Str::limit($deleteNoteText, 80) }}”</strong>? It will also be removed from today’s report.</p>
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="cancelNoteDelete" class="{{ $btnGhost }}">Cancel</button>
                <button type="button" wire:click="destroyNote" class="inline-flex items-center justify-center rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-500">Delete Note</button>
            </div>
        </div>
    </x-pmo.modal>

    <x-pmo.modal show="showStandupModal" title="Daily Standup Report" subtitle="Review, add notes, then copy or share with your team." max-width="max-w-3xl">
        <div class="space-y-5"
            x-data="{
                copied: false,
                async copy() {
                    const text = $refs.report.value;
                    try {
                        await navigator.clipboard.writeText(text);
                    } catch (e) {
                        $refs.report.select();
                        document.execCommand('copy');
                    }
                    this.copied = true;
                    setTimeout(() => this.copied = false, 2000);
                    $flux.toast({ heading: 'Copied to clipboard', text: 'Paste it into WhatsApp, Telegram or email.', variant: 'success' });
                },
            }">

            {{-- Options --}}
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                <div class="flex rounded-xl border border-zinc-200 bg-zinc-50 p-1 dark:border-white/10 dark:bg-[#07040f]" role="radiogroup" aria-label="Report format">
                    @foreach ($formats as $key => $text)
                        <button type="button" role="radio" aria-checked="{{ $format === $key ? 'true' : 'false' }}" wire:click="$set('format', '{{ $key }}')"
                            @class([
                                'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                                'bg-white text-zinc-900 shadow-sm dark:bg-[#8b5cf6]/25 dark:text-white' => $format === $key,
                                'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' => $format !== $key,
                            ])>{{ $text }}</button>
                    @endforeach
                </div>
                <label class="flex cursor-pointer items-center gap-2 text-sm text-zinc-700 dark:text-zinc-200">
                    <input type="checkbox" wire:model.live="useFilters" class="size-4 rounded accent-[#8b5cf6]">
                    Only tasks matching current board filters
                </label>
                <label class="flex cursor-pointer items-center gap-2 text-sm text-zinc-700 dark:text-zinc-200">
                    <input type="checkbox" wire:model.live="includeNotes" class="size-4 rounded accent-[#ff2e9a]">
                    Include notes
                </label>
            </div>

            {{-- Report --}}
            <div class="relative">
                <textarea x-ref="report" readonly rows="16" aria-label="Standup report text"
                    class="{{ $field }} resize-y font-mono text-xs leading-relaxed">{{ $report }}</textarea>
                <span wire:loading.delay class="absolute right-3 top-3 text-xs text-zinc-400">Refreshing…</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="button" x-on:click="copy()" class="{{ $btnPrimary }}">
                    <span x-show="! copied">📋 Copy to Clipboard</span>
                    <span x-show="copied" x-cloak>✓ Copied</span>
                </button>
                <a href="https://wa.me/?text={{ rawurlencode($report) }}" target="_blank" rel="noopener" class="{{ $chip }}">💬 Share via WhatsApp</a>
                <a href="https://t.me/share/url?url=&text={{ rawurlencode($report) }}" target="_blank" rel="noopener" class="{{ $chip }}">✈️ Share via Telegram</a>
                <a href="mailto:?subject={{ rawurlencode($subject) }}&body={{ rawurlencode($report) }}" class="{{ $chip }}">✉️ Send by Email</a>
            </div>

            {{-- Notes (Create / Read / Update / Delete) --}}
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-white/10">
                <div class="mb-3 flex items-center justify-between">
                    <span class="{{ $label }} !mb-0">Today’s Standup Notes</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $this->notes->count() }} {{ str('note')->plural($this->notes->count()) }}</span>
                </div>

                <ul class="space-y-2">
                    @forelse ($this->notes as $note)
                        <li wire:key="note-{{ $note->id }}" class="rounded-lg bg-zinc-50 px-3 py-2 dark:bg-white/[0.03]">
                            @if ($editingNoteId === $note->id)
                                <form wire:submit="saveNote" class="flex flex-col gap-2 sm:flex-row sm:items-start">
                                    <div class="flex-1">
                                        <input type="text" wire:model="editingText" maxlength="250" class="{{ $field }} !py-1.5" aria-label="Edit note" x-init="$el.focus()" x-on:keydown.escape.stop.prevent="$wire.cancelEdit()">
                                        @error('editingText') <p class="{{ $error }}">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="flex gap-1">
                                        <button type="submit" class="rounded-lg bg-[#8b5cf6] px-3 py-1.5 text-xs font-semibold text-white hover:brightness-110">Save</button>
                                        <button type="button" wire:click="cancelEdit" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-zinc-600 hover:bg-zinc-200 dark:text-zinc-300 dark:hover:bg-white/10">Cancel</button>
                                    </div>
                                </form>
                            @else
                                <div class="flex items-start gap-2">
                                    <p class="flex-1 text-sm text-zinc-800 dark:text-zinc-100">{{ $note->task_title }}
                                        <span class="block text-[11px] text-zinc-500 dark:text-zinc-400">{{ $note->actor_name }} · {{ $note->created_at?->diffForHumans() }}</span>
                                    </p>
                                    <button type="button" wire:click="editNote({{ $note->id }})" class="rounded-lg px-2 py-1 text-xs font-semibold text-zinc-600 hover:bg-zinc-200 dark:text-zinc-300 dark:hover:bg-white/10">✏️ Edit</button>
                                    <button type="button" wire:click="confirmDeleteNote({{ $note->id }})" class="rounded-lg px-2 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-500/10 dark:text-rose-400">🗑 Delete</button>
                                </div>
                            @endif
                        </li>
                    @empty
                        <li class="text-sm text-zinc-500 dark:text-zinc-400">No notes yet today. Add blockers, decisions or announcements for the team.</li>
                    @endforelse
                </ul>

                <form wire:submit="addNote" class="mt-3">
                    <div class="flex gap-2">
                        <input type="text" wire:model="noteText" maxlength="250" class="{{ $field }} !py-2" placeholder="e.g. UAT session moved to Thursday 10:00 AM" aria-label="New note">
                        <button type="submit" class="{{ $btnGhost }} !py-2 shrink-0" wire:loading.attr="disabled" wire:target="addNote">＋ Add Note</button>
                    </div>
                    @error('noteText') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </form>
            </div>

            <div class="flex justify-end">
                <button type="button" wire:click="closeModals" class="{{ $btnGhost }}">Close</button>
            </div>
        </div>
    </x-pmo.modal>
</div>
