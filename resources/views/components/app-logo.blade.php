@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="Kanban PMO" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-lg shadow-md shadow-[#ff2e9a]/30">
            <x-app-logo-icon class="size-8" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="Kanban PMO" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-lg shadow-md shadow-[#ff2e9a]/30">
            <x-app-logo-icon class="size-8" />
        </x-slot>
    </flux:brand>
@endif
