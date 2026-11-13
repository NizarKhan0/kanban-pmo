@props([
    'show',
    'title',
    'subtitle' => null,
    'maxWidth' => 'max-w-lg',
    'close' => 'closeModals',
    'stacked' => false,
])

<div
    x-cloak
    x-show="$wire.{{ $show }}"
    x-on:keydown.escape.window="if ($wire.{{ $show }} && ! $event.defaultPrevented) { $event.preventDefault(); $wire.{{ $close }}() }"
    @class(['fixed inset-0 flex items-end justify-center p-4 sm:items-center', $stacked ? 'z-[60]' : 'z-50'])
    role="dialog"
    aria-modal="true"
>
    <div
        x-show="$wire.{{ $show }}"
        x-transition.opacity
        x-on:click="$wire.{{ $close }}()"
        class="absolute inset-0 bg-[#07040f]/70 backdrop-blur-sm"
    ></div>

    <div
        x-show="$wire.{{ $show }}"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-3 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        {{ $attributes->class([
            'relative w-full overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-2xl',
            'dark:border-[#8b5cf6]/25 dark:bg-[#0d0722] dark:shadow-[#8b5cf6]/10',
            $maxWidth,
        ]) }}
    >
        <div class="h-1 w-full bg-gradient-to-r from-[#ff2e9a] via-[#8b5cf6] to-[#ff2e9a]"></div>

        <div class="flex items-start justify-between gap-4 px-6 pt-5">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $title }}</h2>
                @if ($subtitle)
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $subtitle }}</p>
                @endif
            </div>
            <button
                type="button"
                x-on:click="$wire.{{ $close }}()"
                class="rounded-lg p-1.5 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-white/10 dark:hover:text-white"
                aria-label="Close"
            >
                <svg class="size-5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
            </button>
        </div>

        <div class="max-h-[75vh] overflow-y-auto px-6 pb-6 pt-4">
            {{ $slot }}
        </div>
    </div>
</div>
