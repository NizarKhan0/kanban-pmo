<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth motion-reduce:scroll-auto">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="Kanban PMO — a project command center with an interactive Kanban board, team workload, daily standup reports, an AI PMO assistant and Telegram, Gmail and WhatsApp automation.">
    <title>Kanban PMO — Project Command Center</title>
    <link rel="icon" href="data:,">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $sections = ['features' => 'Features', 'how-it-works' => 'How It Works', 'standup' => 'Standup', 'security' => 'Security'];

    // One action per place: the nav carries Log in / Dashboard, the hero carries sign-up.
    $showSignUp = ! auth()->check() && Route::has('register');

    $features = [
        ['🗂️', 'Interactive Kanban Board', 'Drag cards across your workflow, reorder columns and see WIP limits light up red the moment a stage is overloaded.', ['Drag-and-drop', 'WIP limits', 'Done-column sync']],
        ['👥', 'Projects & Team Roster', 'Keep a portfolio of projects and a roster of PICs. Removing a person never deletes their cards — history is preserved.', ['PIC history', 'Workload badges', 'Set as Me']],
        ['✅', 'Tasks, Subtasks & Filters', 'Rich task cards with subtasks, tags, due dates and blockers. Filter by project, assignee, priority or due date in a keystroke.', ['Ctrl + K search', 'My Tasks view', '6 sort modes']],
        ['📊', 'Analytics & Standup', 'Live widgets for completion rate, priorities and team workload, plus a daily standup report ready to paste into chat or email.', ['Live widgets', 'Standup notes', 'Activity log']],
        ['🤖', 'AI PMO Assistant', 'Summarise the day, analyse risks, capture tasks from meeting notes, split work into subtasks and sharpen card definitions.', ['5 AI actions', 'Prompt templates', 'Free simulation mode']],
        ['📡', 'Omnichannel Automation', 'Send standups and overdue alerts to Telegram, Gmail and WhatsApp — on a schedule or with one click — with a full audit trail.', ['Telegram', 'Gmail', 'WhatsApp']],
    ];

    $columns = [
        ['To Do', '#60a5fa', null, [
            ['Prepare SRS document', 'eMyHaji 2.0', 'FL', 'bg-amber-500', 'High', 'bg-rose-500/20 text-rose-300', '17 Nov', '2/5', 40, ['srs']],
            ['Set up staging environment', 'MyResearch', 'AN', 'bg-[#8b5cf6]', 'Medium', 'bg-amber-500/20 text-amber-300', '19 Nov', null, 0, ['devops']],
        ]],
        ['In Progress', '#38bdf8', '3/7', [
            ['FPX payment integration', 'eMyHaji 2.0', 'AN', 'bg-[#8b5cf6]', 'High', 'bg-rose-500/20 text-rose-300', 'Overdue · 12 Nov', '2/5', 40, ['payment', 'fpx']],
            ['Grant workflow engine', 'MyResearch', 'AN', 'bg-[#8b5cf6]', 'High', 'bg-rose-500/20 text-rose-300', '21 Nov', '1/3', 33, []],
        ]],
        ['Review', '#c084fc', null, [
            ['Monthly report module UAT', 'SPK-KuliM', 'HM', 'bg-cyan-500', 'High', 'bg-rose-500/20 text-rose-300', '16 Nov', '3/4', 75, ['uat']],
        ]],
        ['Done', '#34d399', null, [
            ['Disbursement module go-live', 'eBantuanJKM 2.0', 'AN', 'bg-[#8b5cf6]', 'Done', 'bg-emerald-500/20 text-emerald-300', null, '3/3', 100, ['release']],
        ]],
    ];
@endphp
<body class="min-h-screen bg-[#07040f] font-sans text-zinc-300 antialiased selection:bg-[#ff2e9a]/30 selection:text-white">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-[#0d0722]">Skip to content</a>

    {{-- Glow background --}}
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden" aria-hidden="true">
        <div class="absolute -top-40 left-1/2 h-[36rem] w-[60rem] -translate-x-1/2 rounded-full bg-[#8b5cf6]/20 blur-[120px]"></div>
        <div class="absolute right-[-10rem] top-[30rem] h-[28rem] w-[28rem] rounded-full bg-[#ff2e9a]/15 blur-[120px]"></div>
        <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.03)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.03)_1px,transparent_1px)] bg-[size:48px_48px] [mask-image:radial-gradient(ellipse_at_top,black_30%,transparent_75%)]"></div>
    </div>

    {{-- ═══════════════ Navigation ═══════════════ --}}
    <header id="site-header" class="sticky top-0 z-40 border-b border-transparent bg-[#07040f]/60 backdrop-blur-xl transition-[background-color,border-color,box-shadow] duration-300 data-[scrolled=true]:border-white/10 data-[scrolled=true]:bg-[#07040f]/90 data-[scrolled=true]:shadow-lg data-[scrolled=true]:shadow-black/40">
        <nav class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 sm:px-6" aria-label="Main">
            <a href="{{ route('home') }}" id="brand" class="flex shrink-0 items-center gap-2.5 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-[#ff2e9a]">
                <x-app-logo-icon class="size-9 rounded-[9px] shadow-lg shadow-[#ff2e9a]/30" />
                <span class="text-base font-bold tracking-tight text-white">Kanban <span class="text-[#ff2e9a]">PMO</span></span>
            </a>
            <div class="hidden items-center gap-1 rounded-full border border-white/10 bg-white/[0.03] p-1 text-sm font-medium lg:flex">
                @foreach ($sections as $id => $label)
                    <a href="#{{ $id }}" data-nav-link="{{ $id }}"
                        class="group relative rounded-full px-4 py-1.5 text-zinc-400 transition hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-[#ff2e9a] data-[active=true]:bg-white/[0.08] data-[active=true]:text-white">
                        {{ $label }}
                        <span class="pointer-events-none absolute inset-x-4 -bottom-px h-0.5 scale-x-0 rounded-full bg-gradient-to-r from-[#ff2e9a] to-[#8b5cf6] transition-transform duration-300 group-data-[active=true]:scale-x-100" aria-hidden="true"></span>
                    </a>
                @endforeach
            </div>
            <div class="flex items-center gap-1.5 sm:gap-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="group inline-flex h-10 items-center gap-2 whitespace-nowrap rounded-full border border-white/20 bg-white/[0.05] px-4 text-sm font-semibold text-white transition hover:border-[#ff2e9a]/60 hover:bg-white/[0.09] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#ff2e9a] focus-visible:ring-offset-2 focus-visible:ring-offset-[#07040f]">
                        <svg class="size-4 text-zinc-300 transition group-hover:text-[#ff2e9a]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
                        Dashboard
                    </a>
                @else
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="group inline-flex h-10 items-center gap-2 whitespace-nowrap rounded-full border border-white/20 bg-white/[0.05] px-4 text-sm font-semibold text-white transition hover:border-[#ff2e9a]/60 hover:bg-white/[0.09] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#ff2e9a] focus-visible:ring-offset-2 focus-visible:ring-offset-[#07040f]">
                            <svg class="size-4 text-zinc-300 transition group-hover:text-[#ff2e9a]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>
                            Log in
                        </a>
                    @endif
                @endauth
                <button type="button" id="menu-toggle" class="ml-1 inline-flex size-10 items-center justify-center rounded-full text-zinc-300 transition hover:bg-white/[0.08] hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-[#ff2e9a] focus-visible:ring-offset-2 focus-visible:ring-offset-[#07040f] lg:hidden"
                    aria-controls="mobile-menu" aria-expanded="false" aria-label="Open menu">
                    <svg data-icon="open" class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    <svg data-icon="close" class="hidden size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>
        </nav>

        <div id="mobile-menu" class="hidden border-t border-white/5 bg-[#07040f]/95 backdrop-blur-xl lg:hidden">
            <div class="mx-auto max-w-7xl space-y-1 px-4 py-4 sm:px-6">
                @foreach ($sections as $id => $label)
                    <a href="#{{ $id }}" data-menu-link data-nav-link="{{ $id }}"
                        class="group flex items-center gap-3 rounded-xl px-3 py-3 text-base font-medium text-zinc-300 transition hover:bg-white/5 hover:text-white data-[active=true]:bg-white/[0.06] data-[active=true]:text-white">
                        <span class="h-5 w-1 rounded-full bg-white/10 transition group-data-[active=true]:bg-gradient-to-b group-data-[active=true]:from-[#ff2e9a] group-data-[active=true]:to-[#8b5cf6]" aria-hidden="true"></span>
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>
    </header>

    <main id="main" class="overflow-x-clip">
        {{-- ═══════════════ Hero ═══════════════ --}}
        <section class="mx-auto max-w-7xl px-4 pb-16 pt-12 sm:px-6 sm:pt-20 lg:pt-24">
            <div class="mx-auto max-w-3xl text-center">
                <p class="inline-flex items-center gap-2 rounded-full border border-[#8b5cf6]/30 bg-[#8b5cf6]/10 px-3 py-1 text-xs font-semibold text-violet-200">
                    <span class="size-1.5 animate-pulse rounded-full bg-[#ff2e9a]"></span> PMO Command Center for delivery teams
                </p>
                <h1 class="mt-6 text-[2.15rem] font-extrabold leading-[1.1] tracking-tight text-white min-[380px]:text-4xl sm:text-5xl lg:text-6xl">
                    Run every project from
                    <span class="bg-gradient-to-r from-[#ff2e9a] via-[#c084fc] to-[#8b5cf6] bg-clip-text text-transparent">one Kanban board.</span>
                </h1>
                <p class="mx-auto mt-6 max-w-2xl text-base leading-relaxed text-zinc-400 sm:text-lg">
                    Plan work, balance your team’s workload, spot overdue cards early and send the daily standup to Telegram, Gmail or WhatsApp — with an AI PMO assistant at your side.
                </p>
                @if ($showSignUp)
                    <div class="mt-9 flex justify-center">
                        <a href="{{ route('register') }}" class="w-full rounded-xl bg-gradient-to-r from-[#ff2e9a] to-[#8b5cf6] px-6 py-3 text-sm font-semibold text-white shadow-xl shadow-[#ff2e9a]/30 transition hover:brightness-110 sm:w-auto">Get Started Free →</a>
                    </div>
                @endif
            </div>

            {{-- Board mockup --}}
            <div class="relative mx-auto mt-16 max-w-6xl">
                <div class="absolute -inset-4 rounded-[2rem] bg-gradient-to-r from-[#ff2e9a]/20 via-[#8b5cf6]/20 to-[#ff2e9a]/20 blur-2xl" aria-hidden="true"></div>
                <div class="relative overflow-hidden rounded-2xl border border-white/10 bg-[#0d0722]/90 shadow-2xl" role="img" aria-label="Preview of the Kanban PMO board with four columns of task cards">
                    <div class="flex items-center gap-2 border-b border-white/5 px-4 py-3">
                        <span class="size-3 rounded-full bg-rose-500/70"></span><span class="size-3 rounded-full bg-amber-500/70"></span><span class="size-3 rounded-full bg-emerald-500/70"></span>
                        <span class="ml-3 hidden rounded-md bg-white/5 px-3 py-1 text-[11px] text-zinc-500 sm:block">🔍 Search tasks, tags, notes… <kbd class="ml-6 rounded border border-white/10 px-1 font-mono">Ctrl K</kbd></span>
                        <span class="ml-auto rounded-md bg-gradient-to-r from-[#ff2e9a] to-[#8b5cf6] px-2.5 py-1 text-[11px] font-semibold text-white">＋ Add Task</span>
                    </div>
                    <div class="grid grid-cols-2 gap-px bg-white/5 p-px sm:grid-cols-4" aria-hidden="true">
                        @foreach ($columns as [$name, $color, $wip, $cards])
                            <div @class(['bg-[#0d0722] p-3', 'hidden sm:block' => $loop->index >= 2])>
                                <div class="mb-1 h-1 rounded-full" style="background: {{ $color }}"></div>
                                <div class="mb-3 flex items-center gap-2 pt-1">
                                    <span class="text-xs font-semibold text-white">{{ $name }}</span>
                                    <span class="rounded-full bg-white/10 px-1.5 text-[10px] font-semibold text-zinc-300">{{ count($cards) }}</span>
                                    @if ($wip)<span class="ml-auto rounded-full bg-white/5 px-1.5 text-[10px] font-semibold text-zinc-400">WIP {{ $wip }}</span>@endif
                                </div>
                                <div class="space-y-2">
                                    @foreach ($cards as [$title, $project, $initials, $avatar, $prio, $prioClass, $due, $subs, $pct, $tags])
                                        <div @class(['rounded-lg border bg-[#130a2e] p-2.5', 'border-rose-500/50' => str_starts_with((string) $due, 'Overdue'), 'border-white/5' => ! str_starts_with((string) $due, 'Overdue')])>
                                            <div class="flex items-start gap-2">
                                                <p @class(['flex-1 text-[11px] font-semibold leading-snug text-white', 'line-through decoration-emerald-400/70 opacity-70' => $prio === 'Done'])>{{ $title }}</p>
                                                <span class="flex size-5 shrink-0 items-center justify-center rounded-full text-[8px] font-bold text-white {{ $avatar }}">{{ $initials }}</span>
                                            </div>
                                            <p class="mt-1 text-[10px] text-zinc-500">📁 {{ $project }}</p>
                                            @if ($tags)
                                                <div class="mt-1.5 flex gap-1">@foreach ($tags as $tag)<span class="rounded bg-[#8b5cf6]/15 px-1 text-[9px] font-semibold text-violet-300">#{{ $tag }}</span>@endforeach</div>
                                            @endif
                                            @if ($subs)
                                                <div class="mt-2 flex items-center gap-1.5">
                                                    <div class="h-1 flex-1 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-gradient-to-r from-[#8b5cf6] to-[#ff2e9a]" style="width: {{ $pct }}%"></div></div>
                                                    <span class="text-[9px] font-semibold text-zinc-400">{{ $subs }}</span>
                                                </div>
                                            @endif
                                            <div class="mt-2 flex flex-wrap gap-1">
                                                <span class="rounded-full px-1.5 py-0.5 text-[9px] font-semibold {{ $prioClass }}">{{ $prio }}</span>
                                                @if ($due)
                                                    <span @class(['rounded-full px-1.5 py-0.5 text-[9px] font-semibold', 'bg-rose-600 text-white' => str_starts_with($due, 'Overdue'), 'bg-white/10 text-zinc-300' => ! str_starts_with($due, 'Overdue')])>📅 {{ $due }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- ═══════════════ Features ═══════════════ --}}
        <section id="features" class="mx-auto max-w-7xl scroll-mt-20 px-4 py-16 sm:px-6 sm:py-20 lg:py-24">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#ff2e9a]">Features</p>
                <h2 class="mt-3 text-2xl font-bold tracking-tight text-white sm:text-3xl lg:text-4xl">Everything a PMO needs, in one place</h2>
                <p class="mt-4 text-zinc-400">From the first card to the Friday steering committee — plan, track, report and automate without switching tools.</p>
            </div>
            <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($features as [$icon, $title, $text, $chips])
                    <article class="group relative overflow-hidden rounded-2xl border border-white/10 bg-[#0d0722]/80 p-6 transition hover:-translate-y-1 hover:border-[#8b5cf6]/40">
                        <div class="pointer-events-none absolute -right-10 -top-10 size-32 rounded-full bg-gradient-to-br from-[#ff2e9a]/20 to-transparent opacity-0 blur-2xl transition group-hover:opacity-100"></div>
                        <span class="flex size-11 items-center justify-center rounded-xl bg-gradient-to-br from-[#ff2e9a]/20 to-[#8b5cf6]/20 text-xl ring-1 ring-white/10" aria-hidden="true">{{ $icon }}</span>
                        <h3 class="mt-5 text-lg font-semibold text-white">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-zinc-400">{{ $text }}</p>
                        <div class="mt-4 flex flex-wrap gap-1.5">
                            @foreach ($chips as $chip)
                                <span class="rounded-full border border-white/10 px-2 py-0.5 text-[11px] font-medium text-zinc-300">{{ $chip }}</span>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        {{-- ═══════════════ How it works ═══════════════ --}}
        <section id="how-it-works" class="scroll-mt-20 border-y border-white/5 bg-white/[0.02]">
            <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:py-24">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#ff2e9a]">How It Works</p>
                    <h2 class="mt-3 text-2xl font-bold tracking-tight text-white sm:text-3xl lg:text-4xl">Up and running in three steps</h2>
                </div>
                <ol class="mt-14 grid gap-6 md:grid-cols-3">
                    @foreach ([
                        ['Set up your team', 'Add your projects and PICs with their email and WhatsApp numbers. Mark yourself as “Me” to get a personal task view.'],
                        ['Plan on the board', 'Create cards with subtasks, due dates and tags, set WIP limits per stage and drag work forward as it progresses.'],
                        ['Automate the routine', 'Let the daily standup and overdue alerts go out on schedule, and ask the AI assistant for risks and next steps.'],
                    ] as $i => [$title, $text])
                        <li class="relative rounded-2xl border border-white/10 bg-[#0d0722]/80 p-6">
                            <span class="flex size-10 items-center justify-center rounded-full bg-gradient-to-br from-[#ff2e9a] to-[#8b5cf6] text-sm font-bold text-white shadow-lg shadow-[#ff2e9a]/30">{{ $i + 1 }}</span>
                            <h3 class="mt-5 text-lg font-semibold text-white">{{ $title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-zinc-400">{{ $text }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- ═══════════════ Standup showcase ═══════════════ --}}
        <section id="standup" class="mx-auto grid max-w-7xl scroll-mt-20 items-center gap-12 px-4 py-16 sm:px-6 sm:py-20 lg:py-24 lg:grid-cols-2">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#ff2e9a]">Daily Standup</p>
                <h2 class="mt-3 text-2xl font-bold tracking-tight text-white sm:text-3xl lg:text-4xl">Your standup, written for you</h2>
                <p class="mt-4 leading-relaxed text-zinc-400">One click turns the board into a clean report — grouped by stage, with owners, due dates, subtask progress and an <span class="font-semibold text-rose-300">[OVERDUE]</span> flag where it matters. Copy it, or send it straight to the team.</p>
                <ul class="mt-6 space-y-3 text-sm text-zinc-300">
                    @foreach (['Formatted for WhatsApp, Telegram or email', 'Add notes for blockers and announcements', 'Scheduled delivery every morning'] as $point)
                        <li class="flex items-center gap-3"><span class="flex size-5 items-center justify-center rounded-full bg-emerald-500/20 text-[11px] text-emerald-300" aria-hidden="true">✓</span>{{ $point }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="relative">
                <div class="absolute -inset-3 rounded-3xl bg-gradient-to-br from-[#8b5cf6]/25 to-[#ff2e9a]/20 blur-2xl" aria-hidden="true"></div>
                <div class="relative rounded-2xl border border-white/10 bg-[#0d0722] p-5 shadow-2xl">
                    <div class="mb-3 flex items-center justify-between">
                        <span class="text-xs font-semibold text-zinc-400">💬 Team chat</span>
                        <span class="rounded-full bg-emerald-500/15 px-2 py-0.5 text-[10px] font-semibold text-emerald-300">Sent 08:30</span>
                    </div>
<pre class="overflow-x-auto whitespace-pre-wrap rounded-xl bg-[#07040f] p-4 font-mono text-[12px] leading-relaxed text-zinc-300">📋 <b class="text-white">Daily Standup Report</b>
Monday, 17 November
📊 Summary: 14 tasks · 2 done · <span class="text-rose-300">2 overdue</span>

⚡ <b class="text-white">In Progress</b> (4)
• <span class="text-rose-300">[OVERDUE]</span> FPX payment integration (High)
   — Afqah N. · eMyHaji 2.0 · 2/5 subtasks
   ↳ Blocker: awaiting merchant credentials
• Grant workflow engine — Afqah N. · Due 21 Nov

🔍 <b class="text-white">Review</b> (2)
• Monthly report module UAT — Haziq M. · 3/4 subtasks

📝 <b class="text-white">Notes</b>
• UAT session moved to Thursday 10:00</pre>
                </div>
            </div>
        </section>

        {{-- ═══════════════ Security ═══════════════ --}}
        <section id="security" class="scroll-mt-20 border-y border-white/5 bg-white/[0.02]">
            <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:py-24">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#ff2e9a]">Your Data, Your Control</p>
                    <h2 class="mt-3 text-2xl font-bold tracking-tight text-white sm:text-3xl lg:text-4xl">Built to keep work safe</h2>
                </div>
                <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['🔒', 'Private workspaces', 'Every project, card and log belongs to one workspace and is never visible to another account.'],
                        ['🛡️', 'History preserved', 'Removing a PIC or project never deletes their cards — names are kept for the record.'],
                        ['🗝️', 'Keys stay in .env', 'AI and channel credentials live in server configuration and are never included in exports.'],
                        ['💾', 'Snapshots & export', 'Create snapshots before big changes, restore in one click and export to JSON or CSV anytime.'],
                    ] as [$icon, $title, $text])
                        <div class="rounded-2xl border border-white/10 bg-[#0d0722]/80 p-6">
                            <span class="text-2xl" aria-hidden="true">{{ $icon }}</span>
                            <h3 class="mt-4 font-semibold text-white">{{ $title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-zinc-400">{{ $text }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

    </main>

    <footer class="border-t border-white/5">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 py-8 text-sm text-zinc-500 sm:flex-row sm:px-6">
            <p class="flex items-center gap-2">
                <x-app-logo-icon class="size-6 rounded-md" />
                © {{ now()->year }} Kanban PMO
            </p>
        </div>
    </footer>

    <button type="button" id="back-to-top" aria-label="Back to top" title="Back to top"
        class="pointer-events-none fixed right-4 z-50 flex size-12 translate-y-4 items-center justify-center rounded-full bg-gradient-to-br from-[#ff2e9a] to-[#8b5cf6] text-white opacity-0 shadow-xl shadow-[#ff2e9a]/30 ring-1 ring-white/20 transition duration-300 hover:brightness-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-white sm:right-6 motion-reduce:transition-none"
        style="bottom: max(1.25rem, calc(env(safe-area-inset-bottom) + 0.75rem));">
        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
    </button>

    <script>
        (() => {
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

            // Mobile / tablet menu
            const toggle = document.getElementById('menu-toggle');
            const menu = document.getElementById('mobile-menu');
            const setMenu = (open) => {
                menu.classList.toggle('hidden', !open);
                toggle.setAttribute('aria-expanded', String(open));
                toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
                toggle.querySelector('[data-icon="open"]').classList.toggle('hidden', open);
                toggle.querySelector('[data-icon="close"]').classList.toggle('hidden', !open);
            };
            toggle.addEventListener('click', () => setMenu(menu.classList.contains('hidden')));
            menu.querySelectorAll('[data-menu-link]').forEach((link) => link.addEventListener('click', () => setMenu(false)));
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !menu.classList.contains('hidden')) { setMenu(false); toggle.focus(); } });
            window.matchMedia('(min-width: 1024px)').addEventListener('change', (e) => { if (e.matches) setMenu(false); });

            // Active section in the navbar (scrollspy)
            const header = document.getElementById('site-header');
            const links = [...document.querySelectorAll('[data-nav-link]')];
            const sections = [...new Set(links.map((l) => l.dataset.navLink))]
                .map((id) => document.getElementById(id))
                .filter(Boolean);
            const setActive = (id) => {
                links.forEach((link) => {
                    const on = link.dataset.navLink === id;
                    link.dataset.active = String(on);
                    on ? link.setAttribute('aria-current', 'location') : link.removeAttribute('aria-current');
                });
            };
            const spy = () => {
                header.dataset.scrolled = String(window.scrollY > 8);
                const marker = header.offsetHeight + window.innerHeight * 0.3;
                let current = null;
                for (const section of sections) {
                    if (section.getBoundingClientRect().top <= marker) current = section.id;
                }
                // At the very bottom, the last section is active even if it is short.
                if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4 && sections.length) {
                    current = sections[sections.length - 1].id;
                }
                setActive(current);
            };
            let spyTicking = false;
            window.addEventListener('scroll', () => { if (!spyTicking) { spyTicking = true; requestAnimationFrame(() => { spy(); spyTicking = false; }); } }, { passive: true });
            window.addEventListener('resize', spy, { passive: true });
            links.forEach((link) => link.addEventListener('click', () => setActive(link.dataset.navLink)));
            spy();

            // Back to top
            const top = document.getElementById('back-to-top');
            let ticking = false;
            const update = () => {
                const show = window.scrollY > 600;
                top.classList.toggle('opacity-0', !show);
                top.classList.toggle('pointer-events-none', !show);
                top.classList.toggle('translate-y-4', !show);
                top.tabIndex = show ? 0 : -1;
                ticking = false;
            };
            window.addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(update); } }, { passive: true });
            top.addEventListener('click', () => {
                window.scrollTo({ top: 0, behavior: reduceMotion.matches ? 'auto' : 'smooth' });
                document.getElementById('brand').focus({ preventScroll: true });
            });
            update();
        })();
    </script>
</body>
</html>
