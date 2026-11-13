<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Neo theme accent palette shared by Projects and Team Members.
 *
 * Class strings are written out in full so Tailwind can detect them.
 */
final class NeoPalette
{
    /** @var array<string, string> */
    public const COLORS = [
        'violet' => 'Violet',
        'pink' => 'Neon Pink',
        'fuchsia' => 'Fuchsia',
        'cyan' => 'Cyan',
        'emerald' => 'Emerald',
        'amber' => 'Amber',
        'rose' => 'Rose',
        'sky' => 'Sky',
    ];

    /** @var array<string, string> */
    private const BADGE = [
        'violet' => 'bg-violet-500/15 text-violet-700 ring-violet-500/30 dark:text-violet-300',
        'pink' => 'bg-[#ff2e9a]/15 text-[#c4106f] ring-[#ff2e9a]/30 dark:text-[#ff7ac0]',
        'fuchsia' => 'bg-fuchsia-500/15 text-fuchsia-700 ring-fuchsia-500/30 dark:text-fuchsia-300',
        'cyan' => 'bg-cyan-500/15 text-cyan-700 ring-cyan-500/30 dark:text-cyan-300',
        'emerald' => 'bg-emerald-500/15 text-emerald-700 ring-emerald-500/30 dark:text-emerald-300',
        'amber' => 'bg-amber-500/15 text-amber-700 ring-amber-500/30 dark:text-amber-300',
        'rose' => 'bg-rose-500/15 text-rose-700 ring-rose-500/30 dark:text-rose-300',
        'sky' => 'bg-sky-500/15 text-sky-700 ring-sky-500/30 dark:text-sky-300',
    ];

    /** @var array<string, string> */
    private const SWATCH = [
        'violet' => 'bg-[#8b5cf6]',
        'pink' => 'bg-[#ff2e9a]',
        'fuchsia' => 'bg-fuchsia-500',
        'cyan' => 'bg-cyan-500',
        'emerald' => 'bg-emerald-500',
        'amber' => 'bg-amber-500',
        'rose' => 'bg-rose-500',
        'sky' => 'bg-sky-500',
    ];

    /** @var array<string, string> */
    private const BAR = [
        'violet' => 'from-[#8b5cf6] to-[#8b5cf6]/0',
        'pink' => 'from-[#ff2e9a] to-[#ff2e9a]/0',
        'fuchsia' => 'from-fuchsia-500 to-fuchsia-500/0',
        'cyan' => 'from-cyan-500 to-cyan-500/0',
        'emerald' => 'from-emerald-500 to-emerald-500/0',
        'amber' => 'from-amber-500 to-amber-500/0',
        'rose' => 'from-rose-500 to-rose-500/0',
        'sky' => 'from-sky-500 to-sky-500/0',
    ];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::COLORS);
    }

    public static function badge(?string $color): string
    {
        return self::BADGE[$color] ?? self::BADGE['violet'];
    }

    public static function swatch(?string $color): string
    {
        return self::SWATCH[$color] ?? self::SWATCH['violet'];
    }

    public static function bar(?string $color): string
    {
        return self::BAR[$color] ?? self::BAR['violet'];
    }
}
