<div>
    @if ($visible)
        <div class="yard-share-modal" x-data x-trap.inert.noscroll="true"
             @keydown.escape.window="$wire.close()">
            <div class="yard-share-modal__backdrop" wire:click="close"></div>

            <div class="yard-share-modal__panel">
                <header class="yard-share-modal__header">
                    <h3 class="yard-share-modal__title">{{ __('Share to chat') }}</h3>
                    <button type="button" wire:click="close" class="yard-share-modal__close" aria-label="{{ __('Close') }}">×</button>
                </header>

                {{-- Preview of the item being shared --}}
                @if ($this->preview)
                    @php($p = $this->preview)
                    <div class="yard-share-modal__preview yard-share-card yard-share-card--{{ $p['kind'] }}">
                        @if (!empty($p['image']))
                            <img src="{{ \Illuminate\Support\Str::startsWith($p['image'], ['http', '/']) ? $p['image'] : asset('storage/' . $p['image']) }}"
                                 class="yard-share-card__img" alt="">
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
                        </div>
                    </div>
                @else
                    <div class="yard-share-modal__preview">
                        <p class="text-sm text-slate-500">{{ __('This item could not be loaded.') }}</p>
                    </div>
                @endif

                {{-- Optional note --}}
                <div class="yard-share-modal__note">
                    <input type="text" wire:model.live.debounce.300ms="note"
                           class="yard-share-modal__note-input"
                           placeholder="{{ __('Add a note (optional)') }}"
                           maxlength="500">
                </div>

                {{-- Room search --}}
                <div class="yard-share-modal__search">
                    <input type="text" wire:model.live.debounce.250ms="search"
                           class="yard-share-modal__search-input"
                           placeholder="{{ __('Search your chats…') }}">
                </div>

                {{-- Room list --}}
                <ul class="yard-share-modal__rooms">
                    @forelse ($this->rooms as $room)
                        <li class="yard-share-modal__room">
                            <button type="button" wire:click="shareTo({{ $room->id }})"
                                    wire:loading.attr="disabled"
                                    class="yard-share-modal__room-btn">
                                <div class="yard-share-modal__room-avatar">
                                    @if ($room->avatar)
                                        <img src="{{ asset('storage/' . $room->avatar) }}" alt="">
                                    @else
                                        <span>{{ strtoupper(substr($room->name ?? '?', 0, 1)) }}</span>
                                    @endif
                                </div>
                                <div class="yard-share-modal__room-meta">
                                    <div class="yard-share-modal__room-name">{{ $room->name ?? __('Unnamed') }}</div>
                                    <div class="yard-share-modal__room-type">{{ ucfirst(str_replace('_', ' ', $room->room_type->value)) }}</div>
                                </div>
                                <div class="yard-share-modal__room-cta">{{ __('Send') }}</div>
                            </button>
                        </li>
                    @empty
                        <li class="yard-share-modal__empty">{{ __('No chats found.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    @endif
</div>
