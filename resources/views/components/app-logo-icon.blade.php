{{-- Kanban PMO mark: three board columns with cards on a Neo pink → violet tile. --}}
@php($gid = 'kpmo-'.\Illuminate\Support\Str::random(6))
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" fill="none" role="img" aria-label="Kanban PMO" {{ $attributes }}>
    <defs>
        <linearGradient id="{{ $gid }}-bg" x1="0" y1="0" x2="40" y2="40" gradientUnits="userSpaceOnUse">
            <stop offset="0" stop-color="#ff2e9a" />
            <stop offset="1" stop-color="#8b5cf6" />
        </linearGradient>
    </defs>
    <rect width="40" height="40" rx="10" fill="url(#{{ $gid }}-bg)" />
    {{-- Column tracks --}}
    <rect x="7" y="8" width="7" height="24" rx="2.5" fill="#fff" fill-opacity=".16" />
    <rect x="16.5" y="8" width="7" height="24" rx="2.5" fill="#fff" fill-opacity=".16" />
    <rect x="26" y="8" width="7" height="24" rx="2.5" fill="#fff" fill-opacity=".16" />
    {{-- Cards --}}
    <rect x="8.25" y="9.5" width="4.5" height="6" rx="1.4" fill="#fff" />
    <rect x="8.25" y="17" width="4.5" height="4.5" rx="1.4" fill="#fff" fill-opacity=".75" />
    <rect x="17.75" y="9.5" width="4.5" height="9" rx="1.4" fill="#fff" />
    <rect x="27.25" y="9.5" width="4.5" height="4.5" rx="1.4" fill="#fff" fill-opacity=".75" />
    <rect x="27.25" y="15.5" width="4.5" height="6" rx="1.4" fill="#fff" />
    <rect x="27.25" y="23" width="4.5" height="4.5" rx="1.4" fill="#fff" fill-opacity=".6" />
</svg>
