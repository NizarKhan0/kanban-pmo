@php
    use App\Models\SavedAiPrompt;
    use App\Models\Task;
    use App\Support\NeoPalette;

    $stats = $this->stats;
    $result = $aiResult;
    $data = $result['data'] ?? [];
    $actionMeta = $actions[$aiAction];
    $activeTemplate = $aiTemplateId ? $this->prompts->firstWhere('id', $aiTemplateId) : null;

    $field = 'w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm text-zinc-900 placeholder-zinc-400 shadow-sm transition focus:border-[#8b5cf6] focus:outline-none focus:ring-2 focus:ring-[#8b5cf6]/30 dark:border-white/10 dark:bg-[#07040f] dark:text-white dark:placeholder-zinc-500';
    $label = 'mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-400';
    $error = 'mt-1.5 text-xs font-medium text-rose-600 dark:text-rose-400';
    $panel = 'rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-white/10 dark:bg-[#0d0722]';
    $btnPrimary = 'inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#ff2e9a] to-[#8b5cf6] px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-[#ff2e9a]/20 transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-[#ff2e9a]/50 disabled:opacity-60';
    $btnGhost = 'inline-flex items-center justify-center gap-2 rounded-xl border border-zinc-300 px-4 py-2.5 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-100 disabled:opacity-60 dark:border-white/15 dark:text-zinc-200 dark:hover:bg-white/5';
    $btnDanger = 'inline-flex items-center justify-center gap-2 rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-600/20 transition hover:bg-rose-500 disabled:cursor-not-allowed disabled:opacity-50';
    $badge = 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset';
    $statusBadge = fn (string $s): string => \App\Models\AutomationLog::STATUSES[$s]['badge'] ?? '';
    $statusLabel = fn (string $s): string => \App\Models\AutomationLog::STATUSES[$s]['label'] ?? ucfirst($s);
@endphp

<div class="mx-auto w-full max-w-7xl space-y-8"
    x-data="{
        async copyText(text, what) {
            try { await navigator.clipboard.writeText(text) } catch (e) {
                const t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove();
            }
            $flux.toast({ heading: what + ' copied', text: 'Paste it wherever you need it.', variant: 'success' });
        },
    }">

    {{-- ═══════════════ Header ═══════════════ --}}
    <header>
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#ff2e9a]">PMO Command Center</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">AI &amp; Omnichannel Hub</h1>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Run the AI PMO assistant on your board and dispatch reports to Telegram, Gmail and WhatsApp. Every run is recorded in the execution audit log.</p>
    </header>

    {{-- ═══════════════ Channel Status ═══════════════ --}}
    <section aria-labelledby="channel-status-heading" class="space-y-3">
        <h2 id="channel-status-heading" class="text-lg font-semibold text-zinc-900 dark:text-white">Channel Status</h2>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($this->channels as $key => $channel)
                <article wire:key="channel-{{ $key }}" class="{{ $panel }} flex flex-col p-5">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="flex items-center gap-2 text-sm font-semibold text-zinc-900 dark:text-white"><span aria-hidden="true">{{ $channel['icon'] }}</span>{{ $channel['label'] }}</h3>
                        <span @class([
                            'size-2.5 rounded-full',
                            'bg-emerald-500 shadow-[0_0_8px] shadow-emerald-500' => $channel['connected'],
                            'bg-amber-500' => ! $channel['connected'],
                        ]) aria-hidden="true"></span>
                    </div>
                    <div class="mt-3">
                        @if ($channel['connected'])
                            <span class="{{ $badge }} bg-emerald-500/15 text-emerald-700 ring-emerald-500/40 dark:text-emerald-300">● Connected</span>
                        @else
                            <span class="{{ $badge }} bg-amber-500/15 text-amber-700 ring-amber-500/40 dark:text-amber-300">◐ Simulation Mode (.env Not Set)</span>
                        @endif
                    </div>
                    <p class="mt-2 truncate text-xs text-zinc-500 dark:text-zinc-400" title="{{ $channel['detail'] }}">{{ $channel['detail'] }}</p>
                    @if ($channel['missing'] !== [])
                        <p class="mt-1 text-[11px] text-zinc-500 dark:text-zinc-400">Set in .env: <span class="font-mono text-zinc-700 dark:text-zinc-300">{{ implode(', ', $channel['missing']) }}</span></p>
                    @endif
                    <div class="mt-auto pt-3">
                        @if ($key !== 'ai')
                            <button type="button" wire:click="sendTestAlert('{{ $key }}')" wire:loading.attr="disabled" wire:target="sendTestAlert('{{ $key }}')" class="text-xs font-semibold text-[#8b5cf6] hover:underline">↗ Send Test Alert</button>
                        @else
                            <span class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ $channel['connected'] ? 'Live calls are billed by your AI provider.' : 'Free: answers are simulated from your board.' }}</span>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        <dl class="grid grid-cols-2 gap-3 sm:grid-cols-5">
            @foreach ([['Total Runs', $stats['total'], 'text-zinc-900 dark:text-white'], ['Sent', $stats['sent'], 'text-emerald-600 dark:text-emerald-400'], ['Simulated', $stats['simulated'], 'text-amber-600 dark:text-amber-400'], ['Failed', $stats['failed'], 'text-rose-600 dark:text-rose-400'], ['Tokens Used', number_format($stats['tokens']), 'text-zinc-900 dark:text-white']] as [$k, $v, $tone])
                <div class="{{ $panel }} px-4 py-3">
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $k }}</dt>
                    <dd class="mt-0.5 text-xl font-bold tabular-nums {{ $tone }}">{{ $v }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- ═══════════════ AI Assistant + Templates ═══════════════ --}}
    <div class="grid gap-6 xl:grid-cols-3">
        <section class="{{ $panel }} space-y-5 p-5 xl:col-span-2" aria-labelledby="ai-heading">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="ai-heading" class="text-lg font-semibold text-zinc-900 dark:text-white">🤖 AI PMO Assistant</h2>
                @unless ($this->channels['ai']['connected'])
                    <span class="{{ $badge }} bg-amber-500/15 text-amber-700 ring-amber-500/40 dark:text-amber-300">Simulation Mode</span>
                @endunless
            </div>

            <nav class="flex flex-wrap gap-1.5" aria-label="AI actions">
                @foreach ($actions as $key => $meta)
                    <button type="button" wire:key="action-{{ $key }}" wire:click="setAction('{{ $key }}')"
                        @class([
                            'rounded-xl px-3 py-2 text-xs font-semibold transition',
                            'bg-gradient-to-r from-[#ff2e9a] to-[#8b5cf6] text-white shadow' => $aiAction === $key,
                            'border border-zinc-200 text-zinc-600 hover:bg-zinc-100 dark:border-white/10 dark:text-zinc-300 dark:hover:bg-white/5' => $aiAction !== $key,
                        ])>{{ $meta['icon'] }} {{ $meta['label'] }}</button>
                @endforeach
            </nav>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $actionMeta['hint'] }}</p>

            @if ($activeTemplate)
                <div class="flex items-start gap-2 rounded-xl border border-[#8b5cf6]/30 bg-[#8b5cf6]/10 px-3 py-2 text-xs text-violet-800 dark:text-violet-200">
                    <span aria-hidden="true">{{ $activeTemplate->icon }}</span>
                    <p class="flex-1">Using template <strong>{{ $activeTemplate->title }}</strong>: {{ \Illuminate\Support\Str::limit($activeTemplate->custom_instruction, 140) }}</p>
                    <button type="button" wire:click="clearTemplate" class="font-semibold hover:underline">Clear</button>
                </div>
            @endif

            {{-- Inputs --}}
            @if ($actionMeta['needs'] === 'notes')
                <div>
                    <label for="ai-notes" class="{{ $label }}">Meeting Notes / Email</label>
                    <textarea id="ai-notes" wire:model="aiNotes" rows="6" class="{{ $field }}" placeholder="- Afqah to finish FPX callback handling by Friday (urgent)&#10;- Haziq: prepare UAT scripts for MyResearch next week&#10;- Farah to update the SRS for eMyHaji 2.0"></textarea>
                    @error('aiNotes') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
            @elseif ($actionMeta['needs'] === 'task')
                <div>
                    <label for="ai-task" class="{{ $label }}">Task</label>
                    <select id="ai-task" wire:model="aiTaskId" class="{{ $field }}">
                        <option value="">Select a task…</option>
                        @foreach ($this->taskOptions->groupBy(fn ($t) => $t->column->name) as $columnName => $group)
                            <optgroup label="{{ $columnName }}">
                                @foreach ($group as $task)
                                    <option value="{{ $task->id }}">{{ $task->title }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('aiTaskId') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
            @endif

            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="runAi" wire:loading.attr="disabled" wire:target="runAi,runTemplate" class="{{ $btnPrimary }}">
                    <span wire:loading.remove wire:target="runAi,runTemplate">✨ Run {{ $actionMeta['label'] }}</span>
                    <span wire:loading wire:target="runAi,runTemplate">Thinking…</span>
                </button>
                <button type="button" wire:click="openManual" class="{{ $btnGhost }}">📋 Copy Prompt / Paste JSON Answer</button>
            </div>

            {{-- Manual flow --}}
            @if ($showManual)
                <div class="space-y-3 rounded-xl border border-dashed border-zinc-300 p-4 dark:border-white/15">
                    <p class="text-xs text-zinc-600 dark:text-zinc-300">No API key? Copy this prompt into any AI chat assistant, then paste its answer below{{ in_array($aiAction, ['capture', 'subtasks', 'improve_task'], true) ? ' (JSON only)' : '' }}.</p>
                    <div class="relative">
                        <textarea readonly rows="6" class="{{ $field }} font-mono text-[11px]" aria-label="Prompt">{{ $manualPrompt }}</textarea>
                        <button type="button" x-on:click="copyText(@js($manualPrompt), 'Prompt')" class="absolute right-2 top-2 rounded-lg bg-white/90 px-2 py-1 text-xs font-semibold text-zinc-700 shadow ring-1 ring-zinc-200 hover:bg-white dark:bg-[#130a2e] dark:text-zinc-200 dark:ring-white/10">Copy Prompt</button>
                    </div>
                    <div>
                        <label for="manual-answer" class="{{ $label }}">Paste JSON Answer</label>
                        <textarea id="manual-answer" wire:model="manualAnswer" rows="5" class="{{ $field }} font-mono text-xs" placeholder="{{ in_array($aiAction, ['capture', 'subtasks', 'improve_task'], true) ? '{ ... }' : 'Paste the answer text here' }}"></textarea>
                        @error('manualAnswer') <p class="{{ $error }}">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex gap-2">
                        <button type="button" wire:click="useManualAnswer" class="{{ $btnGhost }}">Use Pasted Answer</button>
                        <button type="button" wire:click="$set('showManual', false)" class="text-sm font-semibold text-zinc-500 hover:underline">Close</button>
                    </div>
                </div>
            @endif

            {{-- Result --}}
            @if ($result)
                <div class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-white/10" wire:key="ai-result-{{ $result['log_id'] ?? 'manual' }}">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="{{ $badge }} {{ $statusBadge($result['status']) }}">{{ $result['source'] === 'manual' ? 'Pasted Answer' : ($result['source'] === 'live' ? 'Sent · Live AI' : $statusLabel($result['status'])) }}</span>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $actions[$result['action']]['label'] }}</span>
                        @if (in_array($result['action'], ['standup', 'risk'], true))
                            <button type="button" x-on:click="copyText(@js($result['text']), 'Answer')" class="ml-auto text-xs font-semibold text-[#8b5cf6] hover:underline">Copy to Clipboard</button>
                        @endif
                    </div>

                    @if ($result['error'])
                        <p class="rounded-lg border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-xs text-rose-700 dark:text-rose-300">{{ $result['error'] }} — a simulated answer is shown below.</p>
                    @endif

                    @switch($result['action'])
                        @case('capture')
                            <p class="text-sm text-zinc-700 dark:text-zinc-200">{{ $result['text'] }}</p>
                            @if (! empty($data['tasks']))
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-xs">
                                        <thead class="text-[11px] uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                            <tr><th class="py-2 pr-2"><span class="sr-only">Include</span></th><th class="py-2 pr-3">Title</th><th class="py-2 pr-3">Project</th><th class="py-2 pr-3">PIC</th><th class="py-2 pr-3">Priority</th><th class="py-2">Due</th></tr>
                                        </thead>
                                        <tbody class="divide-y divide-zinc-100 dark:divide-white/5">
                                            @foreach ($data['tasks'] as $i => $item)
                                                <tr wire:key="cap-{{ $i }}" class="align-top">
                                                    <td class="py-2 pr-2"><input type="checkbox" wire:model="captureSelected.{{ $i }}" class="size-4 rounded accent-[#ff2e9a]" aria-label="Include {{ $item['title'] }}"></td>
                                                    <td class="py-2 pr-3 font-medium text-zinc-900 dark:text-white">{{ $item['title'] }}</td>
                                                    <td class="py-2 pr-3 text-zinc-600 dark:text-zinc-300">{{ $item['project'] ?: '—' }}</td>
                                                    <td class="py-2 pr-3 text-zinc-600 dark:text-zinc-300">{{ $item['pic'] ?: '—' }}</td>
                                                    <td class="py-2 pr-3"><span class="{{ $badge }} {{ Task::PRIORITY_BADGES[$item['priority']] ?? '' }}">{{ Task::PRIORITIES[$item['priority']] ?? $item['priority'] }}</span></td>
                                                    <td class="py-2 text-zinc-600 dark:text-zinc-300">{{ $item['due_date'] ?: '—' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div class="flex flex-wrap items-end gap-2">
                                    <div class="min-w-48">
                                        <label for="capture-column" class="{{ $label }}">Add to Column</label>
                                        <select id="capture-column" wire:model="captureColumnId" class="{{ $field }} !py-2">
                                            @foreach ($this->columnOptions as $column)
                                                <option value="{{ $column->id }}">{{ $column->icon }} {{ $column->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('captureColumnId') <p class="{{ $error }}">{{ $message }}</p> @enderror
                                    </div>
                                    <button type="button" wire:click="applyCapture" wire:loading.attr="disabled" class="{{ $btnPrimary }}">＋ Add Selected to Board</button>
                                </div>
                                <p class="text-[11px] text-zinc-500 dark:text-zinc-400">New project or PIC names are registered automatically.</p>
                            @endif
                            @break

                        @case('subtasks')
                            <p class="text-sm text-zinc-700 dark:text-zinc-200">{{ $result['text'] }}</p>
                            @if (! empty($data['subtasks']))
                                <ol class="list-decimal space-y-1 pl-5 text-sm text-zinc-800 dark:text-zinc-100">
                                    @foreach ($data['subtasks'] as $subtask)
                                        <li>{{ $subtask }}</li>
                                    @endforeach
                                </ol>
                                <button type="button" wire:click="applySubtasks" wire:loading.attr="disabled" class="{{ $btnPrimary }}">＋ Add {{ count($data['subtasks']) }} Subtasks to Task</button>
                            @endif
                            @break

                        @case('improve_task')
                            @if (! empty($data['proposed']))
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-sm">
                                        <thead class="text-[11px] uppercase tracking-wide text-zinc-500 dark:text-zinc-400"><tr><th class="py-2 pr-3">Field</th><th class="py-2 pr-3">Current</th><th class="py-2">Proposed</th></tr></thead>
                                        <tbody class="divide-y divide-zinc-100 dark:divide-white/5">
                                            @foreach (['title' => 'Title', 'priority' => 'Priority', 'due_date' => 'Due Date'] as $f => $fLabel)
                                                @php $changed = ($data['current'][$f] ?? '') !== ($data['proposed'][$f] ?? ''); @endphp
                                                <tr>
                                                    <td class="py-2 pr-3 text-xs font-semibold text-zinc-500 dark:text-zinc-400">{{ $fLabel }}</td>
                                                    <td class="py-2 pr-3 text-zinc-600 dark:text-zinc-300">{{ $data['current'][$f] ?: '—' }}</td>
                                                    <td @class(['py-2', 'font-semibold text-emerald-700 dark:text-emerald-300' => $changed, 'text-zinc-600 dark:text-zinc-300' => ! $changed])>{{ $data['proposed'][$f] ?: '—' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                            <p class="whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-200">{{ $result['text'] }}</p>
                            @if (! empty($data['proposed']))
                                <button type="button" wire:click="applyImprovement" wire:loading.attr="disabled" class="{{ $btnPrimary }}">✓ Apply Improvements</button>
                            @endif
                            @break

                        @default
                            <pre class="max-h-[28rem] overflow-auto whitespace-pre-wrap rounded-lg bg-zinc-50 p-3 font-sans text-sm leading-relaxed text-zinc-800 dark:bg-[#07040f] dark:text-zinc-100">{{ $result['text'] }}</pre>
                    @endswitch
                </div>
            @endif
        </section>

        {{-- Saved templates --}}
        <section class="{{ $panel }} space-y-3 p-5" aria-labelledby="templates-heading">
            <div class="flex items-center justify-between">
                <h2 id="templates-heading" class="text-lg font-semibold text-zinc-900 dark:text-white">Saved Prompt Templates</h2>
                <button type="button" wire:click="createPrompt" class="text-sm font-semibold text-[#8b5cf6] hover:underline">＋ New</button>
            </div>
            <ul class="space-y-2">
                @forelse ($this->prompts as $prompt)
                    <li wire:key="prompt-{{ $prompt->id }}" class="rounded-xl border border-zinc-200 p-3 dark:border-white/10">
                        <div class="flex items-start gap-2">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg ring-1 ring-inset {{ NeoPalette::badge($prompt->color) }}" aria-hidden="true">{{ $prompt->icon }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $prompt->title }}</p>
                                <p class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ $prompt->actionLabel() }}</p>
                            </div>
                        </div>
                        <p class="mt-2 line-clamp-2 text-xs text-zinc-600 dark:text-zinc-300">{{ $prompt->custom_instruction }}</p>
                        <div class="mt-2 flex justify-end gap-1">
                            <button type="button" wire:click="runTemplate({{ $prompt->id }})" class="rounded-lg px-2 py-1 text-xs font-semibold text-[#ff2e9a] hover:bg-[#ff2e9a]/10">▶ Run</button>
                            <button type="button" wire:click="editPrompt({{ $prompt->id }})" class="rounded-lg px-2 py-1 text-xs font-semibold text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/5">✏️ Edit</button>
                            <button type="button" wire:click="confirmDeletePrompt({{ $prompt->id }})" class="rounded-lg px-2 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-500/10 dark:text-rose-400">🗑 Delete</button>
                        </div>
                    </li>
                @empty
                    <li class="py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">No templates yet. Save an instruction you use often.</li>
                @endforelse
            </ul>
            <p class="text-[11px] text-zinc-500 dark:text-zinc-400">Templates that need notes or a task use the inputs in the assistant panel.</p>
        </section>
    </div>

    {{-- ═══════════════ Omnichannel Dispatch ═══════════════ --}}
    <section class="{{ $panel }} space-y-4 p-5" aria-labelledby="dispatch-heading">
        <div>
            <h2 id="dispatch-heading" class="text-lg font-semibold text-zinc-900 dark:text-white">📣 Manual Trigger — Daily Standup Report</h2>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Send today’s standup report now. Leave a recipient empty to use the default from .env.</p>
        </div>
        <div class="grid gap-4 md:grid-cols-3">
            <div class="space-y-2">
                <label for="tg-chat" class="{{ $label }}">Telegram Chat ID</label>
                <input id="tg-chat" type="text" wire:model="telegramChatId" class="{{ $field }}" placeholder="Default chat" maxlength="64">
                @error('telegramChatId') <p class="{{ $error }}">{{ $message }}</p> @enderror
                <button type="button" wire:click="dispatchTelegram" wire:loading.attr="disabled" wire:target="dispatchTelegram" class="{{ $btnGhost }} w-full">✈️ Dispatch to Telegram</button>
            </div>
            <div class="space-y-2">
                <label for="email-to" class="{{ $label }}">Email Recipient</label>
                <input id="email-to" type="email" wire:model="emailTo" class="{{ $field }}" placeholder="Default PMO recipient" maxlength="255">
                @error('emailTo') <p class="{{ $error }}">{{ $message }}</p> @enderror
                <button type="button" wire:click="dispatchEmail" wire:loading.attr="disabled" wire:target="dispatchEmail" class="{{ $btnGhost }} w-full">✉️ Email Standup Report</button>
            </div>
            <div class="space-y-2">
                <label for="wa-phone" class="{{ $label }}">WhatsApp Number</label>
                <input id="wa-phone" type="tel" wire:model="whatsappPhone" class="{{ $field }}" placeholder="+60 12-345 6789" maxlength="30">
                @error('whatsappPhone') <p class="{{ $error }}">{{ $message }}</p> @enderror
                <button type="button" wire:click="dispatchWhatsApp" wire:loading.attr="disabled" wire:target="dispatchWhatsApp" class="{{ $btnGhost }} w-full">💬 Share via WhatsApp</button>
            </div>
        </div>

        @if ($dispatchResults !== [])
            <ul class="space-y-2">
                @foreach ($dispatchResults as $i => $dispatch)
                    <li wire:key="dispatch-{{ $dispatch['log_id'] ?? $i }}" class="flex flex-wrap items-center gap-2 rounded-xl bg-zinc-50 px-3 py-2 text-xs dark:bg-white/[0.03]">
                        <span class="{{ $badge }} {{ $statusBadge($dispatch['status']) }}">{{ $statusLabel($dispatch['status']) }}</span>
                        <span class="font-semibold text-zinc-800 dark:text-zinc-100">{{ $dispatch['label'] }}</span>
                        <span class="flex-1 text-zinc-600 dark:text-zinc-300">{{ $dispatch['summary'] }}</span>
                        @if ($dispatch['link'])
                            <a href="{{ $dispatch['link'] }}" target="_blank" rel="noopener" class="rounded-lg bg-emerald-600 px-2.5 py-1 font-semibold text-white hover:bg-emerald-500">Open WhatsApp ↗</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- ═══════════════ Execution Audit Logs ═══════════════ --}}
    <section class="{{ $panel }} space-y-4 p-5" aria-labelledby="audit-heading">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="audit-heading" class="text-lg font-semibold text-zinc-900 dark:text-white">Execution Audit Logs</h2>
            <button type="button" wire:click="confirmClearLogs" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-500/10 dark:text-rose-400">🗑 Clear Audit Logs</button>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <nav class="flex flex-wrap gap-1 rounded-xl border border-zinc-200 bg-zinc-50 p-1 dark:border-white/10 dark:bg-[#07040f]" aria-label="Filter by channel">
                @foreach ($channelGroups as $key => $text)
                    <button type="button" wire:key="lc-{{ $key }}" wire:click="setLogChannel('{{ $key }}')" @class(['rounded-lg px-2.5 py-1 text-xs font-semibold transition', 'bg-white text-zinc-900 shadow-sm dark:bg-[#8b5cf6]/25 dark:text-white' => $logChannel === $key, 'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' => $logChannel !== $key])>{{ $text }}</button>
                @endforeach
            </nav>
            <nav class="flex flex-wrap gap-1 rounded-xl border border-zinc-200 bg-zinc-50 p-1 dark:border-white/10 dark:bg-[#07040f]" aria-label="Filter by status">
                @foreach (['all' => 'All Statuses'] + array_map(fn ($s) => $s['label'], $statuses) as $key => $text)
                    <button type="button" wire:key="ls-{{ $key }}" wire:click="setLogStatus('{{ $key }}')" @class(['rounded-lg px-2.5 py-1 text-xs font-semibold transition', 'bg-white text-zinc-900 shadow-sm dark:bg-[#8b5cf6]/25 dark:text-white' => $logStatus === $key, 'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' => $logStatus !== $key])>{{ $text }}</button>
                @endforeach
            </nav>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 text-[11px] uppercase tracking-wide text-zinc-500 dark:border-white/10 dark:text-zinc-400">
                    <tr>
                        <th class="py-2 pr-3">Time</th>
                        <th class="py-2 pr-3">Channel</th>
                        <th class="py-2 pr-3">Command</th>
                        <th class="py-2 pr-3">Recipient / Model</th>
                        <th class="py-2 pr-3">Status</th>
                        <th class="py-2 pr-3 text-right">Tokens</th>
                        <th class="py-2"><span class="sr-only">Details</span></th>
                    </tr>
                </thead>
                @forelse ($this->logs as $log)
                    <tbody x-data="{ open: false }" wire:key="audit-{{ $log->id }}" class="border-b border-zinc-100 dark:border-white/5">
                        <tr class="align-top">
                            <td class="whitespace-nowrap py-2 pr-3 text-xs text-zinc-500 dark:text-zinc-400" title="{{ $log->created_at?->setTimezone(config('pmo.timezone'))->format('j M Y, H:i:s') }}">{{ $log->created_at?->diffForHumans() }}</td>
                            <td class="whitespace-nowrap py-2 pr-3 text-zinc-800 dark:text-zinc-100">{{ $log->channelIcon() }} {{ $log->channelLabel() }}</td>
                            <td class="py-2 pr-3 font-mono text-xs text-zinc-700 dark:text-zinc-200">{{ $log->command_name }}</td>
                            <td class="max-w-48 truncate py-2 pr-3 text-xs text-zinc-600 dark:text-zinc-300" title="{{ $log->recipient }}">{{ $log->recipient ?? '—' }}</td>
                            <td class="py-2 pr-3"><span class="{{ $badge }} {{ $log->statusBadge() }}">{{ $log->statusLabel() }}</span></td>
                            <td class="py-2 pr-3 text-right tabular-nums text-xs text-zinc-600 dark:text-zinc-300">{{ number_format($log->tokens_used) }}</td>
                            <td class="py-2 text-right"><button type="button" x-on:click="open = ! open" class="text-xs font-semibold text-[#8b5cf6] hover:underline" x-text="open ? 'Hide' : 'Details'" x-bind:aria-expanded="open">Details</button></td>
                        </tr>
                        <tr x-cloak x-show="open">
                            <td colspan="7" class="pb-3">
                                <div class="grid gap-3 rounded-xl bg-zinc-50 p-3 text-xs md:grid-cols-2 dark:bg-white/[0.03]">
                                    <div>
                                        <p class="mb-1 font-semibold text-zinc-600 dark:text-zinc-300">Message</p>
                                        <pre class="max-h-56 overflow-auto whitespace-pre-wrap font-mono text-[11px] text-zinc-700 dark:text-zinc-200">{{ \Illuminate\Support\Str::limit($log->message, 4000) }}</pre>
                                    </div>
                                    <div>
                                        <p class="mb-1 font-semibold text-zinc-600 dark:text-zinc-300">Response</p>
                                        @if ($log->error_message)
                                            <p class="mb-2 text-rose-600 dark:text-rose-400">{{ $log->error_message }}</p>
                                        @endif
                                        <pre class="max-h-56 overflow-auto whitespace-pre-wrap font-mono text-[11px] text-zinc-700 dark:text-zinc-200">{{ \Illuminate\Support\Str::limit((string) $log->response_payload, 4000) ?: '—' }}</pre>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                @empty
                    <tbody>
                        <tr><td colspan="7" class="py-10 text-center text-sm text-zinc-500 dark:text-zinc-400">No automation runs match these filters yet.</td></tr>
                    </tbody>
                @endforelse
            </table>
        </div>
        <div>{{ $this->logs->links() }}</div>
    </section>

    {{-- ═══════════════ Template Modal (Create / Edit) ═══════════════ --}}
    <x-pmo.modal show="showPromptModal" :title="$promptForm->id ? 'Edit Prompt Template' : 'New Prompt Template'" subtitle="Save an instruction you reuse with an AI action." max-width="max-w-xl">
        <form wire:submit="savePrompt" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="pt-title" class="{{ $label }}">Template Title</label>
                    <input id="pt-title" type="text" wire:model="promptForm.title" class="{{ $field }}" placeholder="e.g. Monday Standup Brief" maxlength="255">
                    @error('promptForm.title') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="pt-action" class="{{ $label }}">AI Action</label>
                    <select id="pt-action" wire:model="promptForm.action_type" class="{{ $field }}">
                        @foreach ($actions as $key => $meta)
                            <option value="{{ $key }}">{{ $meta['icon'] }} {{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                    @error('promptForm.action_type') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label for="pt-instruction" class="{{ $label }}">Custom Instruction</label>
                <textarea id="pt-instruction" wire:model="promptForm.custom_instruction" rows="4" class="{{ $field }}" placeholder="e.g. Focus on eMyHaji 2.0 and keep the summary under 150 words." maxlength="4000"></textarea>
                @error('promptForm.custom_instruction') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <span class="{{ $label }}">Icon</span>
                    <div class="flex flex-wrap gap-1">
                        @foreach (SavedAiPrompt::ICONS as $icon)
                            <button type="button" wire:key="pt-icon-{{ $loop->index }}" x-on:click="$wire.set('promptForm.icon', @js($icon), false)" class="flex size-8 items-center justify-center rounded-lg ring-1 transition"
                                x-bind:class="$wire.promptForm.icon === @js($icon) ? 'ring-2 ring-[#ff2e9a] bg-[#ff2e9a]/10' : 'ring-zinc-200 hover:bg-zinc-100 dark:ring-white/10 dark:hover:bg-white/5'">{{ $icon }}</button>
                        @endforeach
                    </div>
                    @error('promptForm.icon') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <span class="{{ $label }}">Colour</span>
                    <div class="flex flex-wrap gap-2">
                        @foreach (NeoPalette::COLORS as $key => $text)
                            <button type="button" wire:key="pt-color-{{ $key }}" x-on:click="$wire.set('promptForm.color', @js($key), false)" title="{{ $text }}" aria-label="{{ $text }}" class="size-7 rounded-full transition {{ NeoPalette::swatch($key) }}"
                                x-bind:class="$wire.promptForm.color === @js($key) ? 'ring-2 ring-offset-2 ring-zinc-900 dark:ring-white dark:ring-offset-[#0d0722]' : 'opacity-70 hover:opacity-100'"></button>
                        @endforeach
                    </div>
                    @error('promptForm.color') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2 pt-2">
                <div>
                    @if ($promptForm->id)
                        <button type="button" wire:click="confirmDeletePrompt({{ $promptForm->id }})" class="text-sm font-semibold text-rose-600 hover:underline dark:text-rose-400">Delete Template</button>
                    @endif
                </div>
                <div class="flex gap-2">
                    <button type="button" wire:click="closeModals" class="{{ $btnGhost }}">Cancel</button>
                    <button type="submit" class="{{ $btnPrimary }}">{{ $promptForm->id ? 'Save Changes' : 'Create Template' }}</button>
                </div>
            </div>
        </form>
    </x-pmo.modal>

    {{-- ═══════════════ Delete Template — Confirmation ═══════════════ --}}
    <x-pmo.modal show="showPromptDeleteModal" title="Delete Prompt Template" max-width="max-w-md">
        <div class="space-y-4">
            <div class="flex items-start gap-3 rounded-xl border border-rose-500/30 bg-rose-500/10 p-4">
                <span class="text-xl" aria-hidden="true">⚠️</span>
                <p class="text-sm text-zinc-700 dark:text-zinc-200">Delete the template <strong class="text-zinc-900 dark:text-white">“{{ $deletePromptTitle }}”</strong>? Past runs in the audit log are kept. This cannot be undone.</p>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="closeModals" class="{{ $btnGhost }}">Cancel</button>
                <button type="button" wire:click="destroyPrompt" class="{{ $btnDanger }}">Delete Template</button>
            </div>
        </div>
    </x-pmo.modal>

    {{-- ═══════════════ Clear Audit Logs — Confirmation ═══════════════ --}}
    <x-pmo.modal show="showClearModal" title="Clear Audit Logs" max-width="max-w-md">
        <div class="space-y-4">
            <div class="flex items-start gap-3 rounded-xl border border-rose-500/30 bg-rose-500/10 p-4">
                <span class="text-xl" aria-hidden="true">⚠️</span>
                <p class="text-sm text-zinc-700 dark:text-zinc-200">Automation audit records in the selected range will be permanently deleted. Tasks, templates and settings are not affected.</p>
            </div>
            <div>
                <label for="clear-audit-range" class="{{ $label }}">Remove</label>
                <select id="clear-audit-range" wire:model.live="clearRange" class="{{ $field }}">
                    @foreach ($clearRanges as $key => $text)
                        <option value="{{ $key }}">{{ $text }}</option>
                    @endforeach
                </select>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300"><strong class="tabular-nums text-zinc-900 dark:text-white">{{ $clearCount }}</strong> of {{ $stats['total'] }} {{ str('record')->plural($stats['total']) }} will be removed.</p>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="closeModals" class="{{ $btnGhost }}">Cancel</button>
                <button type="button" wire:click="clearLogs" @disabled($clearCount === 0) class="{{ $btnDanger }}">Clear Audit Logs</button>
            </div>
        </div>
    </x-pmo.modal>
</div>
