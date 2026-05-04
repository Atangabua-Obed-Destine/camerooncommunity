@php
    /** @var \App\Models\YardMessage $msg */
    $resolver = app(\App\Services\ShareableResolver::class);
    $model = ($msg->shareable_type && $msg->shareable_id)
        ? $msg->shareable_type::query()->find($msg->shareable_id)
        : null;
    $p = $resolver->preview($model);
@endphp

@if ($p)
    <a href="{{ $p['url'] ?? '#' }}" class="yard-share-card yard-share-card--{{ $p['kind'] }}" target="_blank" rel="noopener">
        @if (!empty($p['image']))
            <img src="{{ \Illuminate\Support\Str::startsWith($p['image'], ['http', '/']) ? $p['image'] : asset('storage/' . $p['image']) }}"
                 class="yard-share-card__img" alt="" loading="lazy">
        @else
            <div class="yard-share-card__img yard-share-card__img--placeholder">
                <span>{{ strtoupper(substr($p['kind'], 0, 1)) }}</span>
            </div>
        @endif
        <div class="yard-share-card__body">
            <div class="yard-share-card__kind">{{ ucfirst($p['kind']) }}</div>
            <div class="yard-share-card__title">{{ $p['title'] }}</div>
            @if (!empty($p['subtitle']))
                <div class="yard-share-card__subtitle">{{ $p['subtitle'] }}</div>
            @endif
            <div class="yard-share-card__cta">{{ $p['cta'] ?? __('Open') }} →</div>
        </div>
    </a>
    @if (!empty($msg->content) && $msg->content !== ($p['title'] ?? null))
        <p class="yard-share-card__note">{!! nl2br(e($msg->content)) !!}</p>
    @endif
@else
    <p class="yard-share-card__missing">{{ __('Shared item is no longer available.') }}</p>
@endif
