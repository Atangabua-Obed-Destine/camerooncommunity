<?php

namespace App\Support;

class AvatarPalette
{
    /**
     * Tailwind background-gradient classes used for default avatars.
     * Picked for good contrast against white text and pleasant variety.
     */
    public const PALETTE = [
        'bg-gradient-to-br from-rose-400 to-rose-600',
        'bg-gradient-to-br from-pink-400 to-pink-600',
        'bg-gradient-to-br from-fuchsia-400 to-fuchsia-600',
        'bg-gradient-to-br from-purple-400 to-purple-600',
        'bg-gradient-to-br from-violet-400 to-violet-600',
        'bg-gradient-to-br from-indigo-400 to-indigo-600',
        'bg-gradient-to-br from-blue-400 to-blue-600',
        'bg-gradient-to-br from-sky-400 to-sky-600',
        'bg-gradient-to-br from-cyan-500 to-cyan-700',
        'bg-gradient-to-br from-teal-400 to-teal-600',
        'bg-gradient-to-br from-emerald-400 to-emerald-600',
        'bg-gradient-to-br from-green-500 to-green-700',
        'bg-gradient-to-br from-lime-500 to-lime-700',
        'bg-gradient-to-br from-amber-400 to-amber-600',
        'bg-gradient-to-br from-orange-400 to-orange-600',
        'bg-gradient-to-br from-red-400 to-red-600',
        'bg-gradient-to-br from-slate-500 to-slate-700',
        'bg-gradient-to-br from-stone-500 to-stone-700',
    ];

    /**
     * Deterministically map a seed (user id, room id, name, etc.) to a color class.
     * The same seed always returns the same color, so a user keeps a stable identity.
     */
    public static function colorClass(string|int|null $seed): string
    {
        $seed = (string) ($seed ?? '');
        if ($seed === '') {
            return self::PALETTE[0];
        }
        $hash = crc32($seed);
        return self::PALETTE[$hash % count(self::PALETTE)];
    }
}
