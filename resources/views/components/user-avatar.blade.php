@props([
    'user' => null,
    // Any Tailwind size pair, e.g. 'h-8 w-8'. Kept as a prop rather than a
    // fixed scale because these sit in headers, drawers and tables that were
    // each already sized differently.
    'size' => 'h-8 w-8',
    'text' => 'text-sm',
    // Two letters reads better in a table, one in a header button.
    'letters' => 1,
    'ring' => '',
])

@php
    /**
     * One place that decides how a person is pictured.
     *
     * The header, the drawer, the admin bar and the profile pages each drew
     * their own circle, and most of them only ever drew the initial — so a
     * member who had uploaded a photo still saw a letter everywhere outside
     * the chat. Behaviour belongs in one component rather than in a dozen
     * near-identical fragments.
     */
    $avatarUser = $user ?? auth()->user();

    $avatarName = $avatarUser?->username ?? $avatarUser?->name ?? '?';
    $avatarInitials = strtoupper(mb_substr($avatarName, 0, (int) $letters));

    // Stored on the public disk; the column holds the path, not a URL.
    $avatarPath = $avatarUser?->avatar;
    $avatarUrl = $avatarPath
        ? (str_starts_with($avatarPath, 'http') ? $avatarPath : asset('storage/' . $avatarPath))
        : null;

    // Same seed the chat uses, so one person keeps one colour across the app.
    $avatarColour = \App\Support\AvatarPalette::colorClass('user:' . ($avatarUser?->id ?? $avatarName));
@endphp

@if ($avatarUrl)
    <img src="{{ $avatarUrl }}"
         alt="{{ $avatarName }}"
         loading="lazy"
         {{ $attributes->class([
             $size,
             $ring,
             'shrink-0 rounded-full object-cover bg-slate-200',
         ]) }}>
@else
    <div {{ $attributes->class([
            $size,
            $text,
            $ring,
            $avatarColour,
            'shrink-0 rounded-full grid place-items-center font-bold text-white select-none',
         ]) }}
         aria-label="{{ $avatarName }}">{{ $avatarInitials }}</div>
@endif
