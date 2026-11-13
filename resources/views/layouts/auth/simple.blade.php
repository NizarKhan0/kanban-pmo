<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="h-svh overflow-hidden bg-zinc-50 antialiased dark:bg-[#07040f]">
        <div class="grid h-svh overflow-hidden lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)]">

            {{-- ═══════════ Brand panel (desktop) ═══════════ --}}
            <aside class="relative hidden h-svh overflow-hidden bg-[#07040f] text-zinc-300 lg:flex lg:flex-col" aria-label="Kanban PMO">
                <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                    <div class="absolute -left-32 -top-32 size-[30rem] rounded-full bg-[#8b5cf6]/30 blur-[110px]"></div>
                    <div class="absolute -bottom-40 -right-24 size-[28rem] rounded-full bg-[#ff2e9a]/25 blur-[110px]"></div>
                    <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.035)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.035)_1px,transparent_1px)] bg-[size:44px_44px] [mask-image:radial-gradient(ellipse_at_center,black_35%,transparent_80%)]"></div>
                </div>

                <div class="relative flex min-h-0 flex-1 flex-col p-8 xl:p-12">
                    <a href="{{ route('home') }}" class="flex items-center gap-3" wire:navigate>
                        <x-app-logo-icon class="size-10 shadow-lg shadow-[#ff2e9a]/30 rounded-[10px]" />
                        <span class="text-lg font-bold tracking-tight text-white">Kanban <span class="text-[#ff2e9a]">PMO</span></span>
                    </a>

                    <div class="my-auto max-w-lg py-6">
                        <p class="inline-flex items-center gap-2 rounded-full border border-[#8b5cf6]/30 bg-[#8b5cf6]/10 px-3 py-1 text-xs font-semibold text-violet-200">
                            <span class="size-1.5 animate-pulse rounded-full bg-[#ff2e9a]"></span> PMO Command Center
                        </p>
                        <h2 class="mt-5 text-4xl font-extrabold leading-tight tracking-tight text-white xl:text-5xl [@media(max-height:700px)]:text-3xl">
                            Every project,
                            <span class="bg-gradient-to-r from-[#ff2e9a] via-[#c084fc] to-[#8b5cf6] bg-clip-text text-transparent">one clear board.</span>
                        </h2>
                        <p class="mt-4 text-base leading-relaxed text-zinc-400">Plan work, balance your team and send the daily standup — all from one place.</p>

                        <ul class="mt-7 space-y-3 text-sm [@media(max-height:620px)]:hidden">
                            @foreach ([
                                ['🗂️', 'Drag-and-drop Kanban with WIP limits'],
                                ['📊', 'Live analytics and daily standup reports'],
                                ['🤖', 'AI assistant with Telegram, Gmail and WhatsApp alerts'],
                            ] as [$icon, $text])
                                <li class="flex items-center gap-3">
                                    <span class="flex size-8 items-center justify-center rounded-lg bg-white/5 ring-1 ring-white/10" aria-hidden="true">{{ $icon }}</span>
                                    <span class="text-zinc-300">{{ $text }}</span>
                                </li>
                            @endforeach
                        </ul>

                        {{-- Mini board preview --}}
                        <div class="mt-8 rounded-2xl border border-white/10 bg-[#0d0722]/80 p-3 shadow-2xl backdrop-blur [@media(max-height:860px)]:hidden" aria-hidden="true">
                            <div class="grid grid-cols-3 gap-2">
                                @foreach ([
                                    ['To Do', '#60a5fa', [['Prepare SRS document', 'High', 'bg-rose-500/20 text-rose-300', 40]]],
                                    ['In Progress', '#38bdf8', [['FPX payment integration', 'Overdue', 'bg-rose-600 text-white', 40], ['Grant workflow engine', 'High', 'bg-rose-500/20 text-rose-300', 33]]],
                                    ['Done', '#34d399', [['Disbursement go-live', 'Done', 'bg-emerald-500/20 text-emerald-300', 100]]],
                                ] as [$col, $color, $cards])
                                    <div class="rounded-xl bg-white/[0.03] p-2">
                                        <div class="mb-2 h-1 rounded-full" style="background: {{ $color }}"></div>
                                        <p class="mb-2 text-[10px] font-semibold text-white">{{ $col }}</p>
                                        <div class="space-y-1.5">
                                            @foreach ($cards as [$title, $pill, $pillClass, $pct])
                                                <div class="rounded-lg border border-white/5 bg-[#130a2e] p-2">
                                                    <p class="text-[10px] font-semibold leading-snug text-zinc-100">{{ $title }}</p>
                                                    <div class="mt-1.5 h-1 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-gradient-to-r from-[#8b5cf6] to-[#ff2e9a]" style="width: {{ $pct }}%"></div></div>
                                                    <span class="mt-1.5 inline-block rounded-full px-1.5 py-0.5 text-[9px] font-semibold {{ $pillClass }}">{{ $pill }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <p class="text-xs text-zinc-500">© {{ now()->year }} Kanban PMO · Your data stays in your workspace.</p>
                </div>
            </aside>

            {{-- ═══════════ Form panel ═══════════ --}}
            <main class="relative flex h-svh flex-col items-center justify-center overflow-hidden px-5 py-4 sm:px-8">
                <div class="pointer-events-none absolute -right-24 -top-24 size-72 rounded-full bg-[#8b5cf6]/10 blur-3xl dark:bg-[#8b5cf6]/15 lg:hidden" aria-hidden="true"></div>
                <div class="pointer-events-none absolute -bottom-24 -left-24 size-72 rounded-full bg-[#ff2e9a]/10 blur-3xl dark:bg-[#ff2e9a]/10" aria-hidden="true"></div>

                <div class="relative flex max-h-full w-full max-w-md flex-col gap-4">
                    <div class="flex items-center justify-between">
                        <a href="{{ route('home') }}" class="flex items-center gap-2.5 lg:invisible" wire:navigate>
                            <x-app-logo-icon class="size-9 rounded-[9px] shadow-md shadow-[#ff2e9a]/30" />
                            <span class="font-bold tracking-tight text-zinc-900 dark:text-white">Kanban <span class="text-[#ff2e9a]">PMO</span></span>
                        </a>
                        <a href="{{ route('home') }}" class="text-xs font-semibold text-zinc-500 transition hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white" wire:navigate>← Back to home</a>
                    </div>

                    <div class="min-h-0 rounded-2xl border border-zinc-200 bg-white p-6 shadow-xl shadow-zinc-900/5 sm:p-7 dark:border-white/10 dark:bg-[#0d0722] dark:shadow-black/40">
                        <div class="-mx-6 -mt-6 mb-5 h-1 rounded-t-2xl bg-gradient-to-r from-[#ff2e9a] via-[#8b5cf6] to-[#ff2e9a] sm:-mx-7 sm:-mt-7" aria-hidden="true"></div>
                        <div class="flex flex-col gap-6">
                            {{ $slot }}
                        </div>
                    </div>

                    <p class="text-center text-xs text-zinc-500 dark:text-zinc-500 [@media(max-height:760px)]:hidden">Protected workspace · Your projects are private to your account.</p>
                </div>
            </main>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
