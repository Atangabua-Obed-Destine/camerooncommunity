@php
    $lang = app()->getLocale();
    $user = auth()->user();
    $allCats = $this->categories();
    $roots = $allCats->whereNull('parent_id');
    $pickedCat = $categoryId ? $allCats->firstWhere('id', $categoryId) : null;

    $previewMedia = array_values($mediaItems ?? []);
    $cover = collect($previewMedia)->firstWhere('is_cover', true) ?? ($previewMedia[0] ?? null);
    $maxImages = (int) \App\Models\PlatformSetting::getValue('marketplace_max_images', 10);
    $usedImages = count($previewMedia);

    $locStr = trim(($city ? $city . ', ' : '') . ($region ?: $country));
    $_attrSchema = \App\Support\CategoryAttributeSchema::forCategory($categoryId);

    $condLabel = collect($this->conditionOptions())->firstWhere('v', $condition);
    $condLabel = $condLabel ? ($lang === 'fr' ? $condLabel['fr'] : $condLabel['l']) : '';

    $isEdit = (bool) ($listing && $listing->status?->value !== 'draft');
@endphp

{{-- Facebook-Marketplace-style "Item for sale" create flow: a scrollable form
     on the left, a live preview of the listing on the right. --}}
<div class="lg:h-full bg-slate-100"
     wire:poll.20s="autosaveDraft"
     x-data="{ more: false, pi: 0, lb: false, limgs: @js(collect($previewMedia)->pluck('url')->values()) }"
     x-on:keydown.escape.window="lb = false">

@if ($listingType === null)
    {{-- ═══════════════ STEP 0 · CHOOSE LISTING TYPE (Facebook entry) ═══════════════ --}}
    <div class="min-h-[calc(100vh-160px)] grid place-items-center px-4 py-10">
        <div class="w-full max-w-3xl">
            <a href="{{ route('marketplace.index') }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-cm-green transition mb-4">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                GoMarket
            </a>
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">{{ $lang === 'fr' ? 'Créer une annonce' : 'Create new listing' }}</h1>
            <p class="mt-1 text-slate-600">{{ $lang === 'fr' ? 'Choisissez un type d\'annonce pour commencer.' : 'Choose a listing type to get started.' }}</p>

            <div class="mt-6 grid sm:grid-cols-3 gap-4">
                @foreach ([
                    ['item', '📦', 'Item for sale', 'Article à vendre', 'Create a single listing for one or more items to sell.', 'Créez une annonce pour un ou plusieurs articles.'],
                    ['vehicle', '🚗', 'Vehicle for sale', 'Véhicule à vendre', 'Sell a car, motorcycle or other vehicle.', 'Vendez une voiture, une moto ou autre véhicule.'],
                    ['home', '🏠', 'Home for sale or rent', 'Logement à vendre/louer', 'List a property to sell or rent out.', 'Publiez un bien à vendre ou à louer.'],
                ] as $t)
                    @php [$key, $icon, $en, $fr, $dEn, $dFr] = $t; @endphp
                    <button type="button" wire:click="chooseType('{{ $key }}')"
                            class="group text-center p-6 rounded-2xl bg-white ring-1 ring-slate-200 hover:ring-cm-green hover:shadow-md transition">
                        <div class="w-20 h-20 mx-auto rounded-2xl bg-slate-100 group-hover:bg-cm-green/10 grid place-items-center text-4xl transition group-hover:scale-105">{{ $icon }}</div>
                        <div class="mt-3 font-bold text-slate-900">{{ $lang === 'fr' ? $fr : $en }}</div>
                        <div class="mt-1 text-xs text-slate-500 leading-snug">{{ $lang === 'fr' ? $dFr : $dEn }}</div>
                    </button>
                @endforeach
            </div>
        </div>
    </div>
@else
    <div class="lg:h-full lg:flex">

    {{-- ═══════════════ LEFT · FORM ═══════════════ --}}
    <aside class="w-full lg:w-[416px] xl:w-[440px] bg-white lg:h-full lg:overflow-y-auto border-r border-slate-200 flex flex-col">

        {{-- Header --}}
        <div class="sticky top-0 z-10 bg-white/95 backdrop-blur px-4 sm:px-5 pt-4 pb-3 border-b border-slate-100">
            <a href="{{ route('marketplace.index') }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-cm-green transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                GoMarket
            </a>
            <div class="mt-1 flex items-center justify-between gap-2">
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">
                    @if ($isEdit)
                        {{ $lang === 'fr' ? 'Modifier l\'article' : 'Edit item' }}
                    @elseif ($listingType === 'vehicle')
                        {{ $lang === 'fr' ? 'Véhicule à vendre' : 'Vehicle for sale' }}
                    @elseif ($listingType === 'home')
                        {{ $lang === 'fr' ? 'Logement à vendre/louer' : 'Home for sale or rent' }}
                    @else
                        {{ $lang === 'fr' ? 'Article à vendre' : 'Item for sale' }}
                    @endif
                </h1>
                @unless ($isEdit)
                    <button type="button" wire:click="backToTypes"
                            class="text-[12px] font-semibold text-cm-green hover:underline shrink-0">
                        {{ $lang === 'fr' ? 'Changer' : 'Change' }}
                    </button>
                @endunless
            </div>
            {{-- Seller chip --}}
            <div class="mt-2.5 flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-full bg-cm-green/15 grid place-items-center text-cm-green font-bold uppercase overflow-hidden shrink-0">
                    @if ($user?->avatar)
                        <img src="{{ asset('storage/' . $user->avatar) }}" alt="" class="w-full h-full object-cover">
                    @else
                        {{ substr($user?->name ?? 'U', 0, 1) }}
                    @endif
                </div>
                <div class="min-w-0">
                    <div class="text-sm font-bold text-slate-900 truncate">{{ $user?->name }}</div>
                    <div class="text-[11px] text-slate-500 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.2 12 2.2 7.1 4.4 7.1 7.1 9.3 12 12 12zm0 2.4c-3.3 0-9.8 1.6-9.8 4.9v2.5h19.6v-2.5c0-3.3-6.5-4.9-9.8-4.9z"/></svg>
                        {{ $lang === 'fr' ? 'Publier sur Marketplace · Public' : 'Listing to Marketplace · Public' }}
                    </div>
                </div>
                @if ($lastAutosavedAt)
                    <span class="ml-auto text-[10px] text-emerald-600 font-semibold flex items-center gap-1 shrink-0"
                          x-data="{ ts: @js($lastAutosavedAt) }" x-init="$watch('$wire.lastAutosavedAt', v => ts = v)">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span x-text="new Date(ts).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})"></span>
                    </span>
                @endif
            </div>
        </div>

        {{-- Form body --}}
        <div class="px-4 sm:px-5 py-4 space-y-5 flex-1">

            {{-- ─── Photos ─── --}}
            <div>
                <div class="flex items-baseline justify-between mb-1.5">
                    <span class="text-[15px] font-bold text-slate-900">{{ $lang === 'fr' ? 'Photos' : 'Photos' }}</span>
                    <span class="text-[12px] text-slate-500">{{ $usedImages }}/{{ $maxImages }} · {{ $lang === 'fr' ? 'jusqu\'à '.$maxImages : 'up to '.$maxImages }}</span>
                </div>

                @if ($usedImages === 0)
                    <label for="photos-empty" class="block w-full border-2 border-dashed border-slate-300 rounded-xl p-8 text-center cursor-pointer hover:border-cm-green hover:bg-cm-green/5 transition group">
                        <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 group-hover:bg-cm-green/10 grid place-items-center transition">
                            <svg class="w-6 h-6 text-slate-500 group-hover:text-cm-green" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5V18a2 2 0 002 2h14a2 2 0 002-2v-1.5M12 4v12m0-12l-4 4m4-4l4 4"/></svg>
                        </div>
                        <div class="mt-2 text-sm font-bold text-slate-800">{{ $lang === 'fr' ? 'Ajouter des photos' : 'Add photos' }}</div>
                        <div class="mt-0.5 text-xs text-slate-500">{{ $lang === 'fr' ? 'ou glissez-déposez' : 'or drag and drop' }}</div>
                        <input type="file" wire:model="photos" multiple accept="image/*" class="hidden" wire:key="photos-empty" id="photos-empty">
                    </label>
                @else
                    {{-- Drag a thumbnail onto another to reorder; the first photo is the cover (FB-style). --}}
                    <div class="grid grid-cols-3 gap-2"
                         x-data="{
                            drag: null,
                            start(id, e) { this.drag = id; e.dataTransfer.effectAllowed = 'move'; },
                            drop(targetId) {
                                if (this.drag === null || this.drag === targetId) { this.drag = null; return; }
                                const wrap = $el;
                                const dragEl = wrap.querySelector('[data-mid=\'' + this.drag + '\']');
                                const tgtEl  = wrap.querySelector('[data-mid=\'' + targetId + '\']');
                                if (dragEl && tgtEl) { wrap.insertBefore(dragEl, tgtEl); }
                                const order = [...wrap.querySelectorAll('[data-mid]')].map(n => parseInt(n.dataset.mid));
                                this.drag = null;
                                $wire.reorderMedia(order);
                            }
                         }">
                        @foreach ($previewMedia as $m)
                            <div wire:key="mphoto-{{ $m['id'] }}" data-mid="{{ $m['id'] }}" draggable="true"
                                 @dragstart="start({{ $m['id'] }}, $event)"
                                 @dragover.prevent
                                 @drop.prevent="drop({{ $m['id'] }})"
                                 class="relative aspect-square rounded-lg overflow-hidden ring-2 {{ $m['is_cover'] ? 'ring-cm-green' : 'ring-slate-200' }} group bg-slate-100 cursor-move">
                                <img src="{{ $m['url'] }}" class="w-full h-full object-cover pointer-events-none" alt="">
                                @if ($m['is_cover'])
                                    <span class="absolute top-1 left-1 bg-cm-green text-white text-[9px] font-bold uppercase px-1.5 py-0.5 rounded-full">★</span>
                                @else
                                    <button type="button" wire:click="makeCover({{ $m['id'] }})"
                                            class="absolute top-1 left-1 bg-black/55 hover:bg-cm-green text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full opacity-0 group-hover:opacity-100 transition"
                                            title="{{ $lang === 'fr' ? 'Couverture' : 'Set cover' }}">★</button>
                                @endif
                                <button type="button" wire:click="removeMedia({{ $m['id'] }})"
                                        class="absolute top-1 right-1 bg-black/55 hover:bg-cm-red text-white w-5 h-5 rounded-full text-[11px] grid place-items-center opacity-0 group-hover:opacity-100 transition">✕</button>
                            </div>
                        @endforeach
                        @if ($usedImages < $maxImages)
                            <label for="photos-grid" class="aspect-square rounded-lg border-2 border-dashed border-slate-300 grid place-items-center cursor-pointer hover:border-cm-green hover:bg-cm-green/5 transition text-slate-400 hover:text-cm-green">
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                <input id="photos-grid" type="file" wire:model="photos" multiple accept="image/*" class="hidden" wire:key="photos-grid">
                            </label>
                        @endif
                    </div>
                    <p class="mt-1.5 text-[11px] text-slate-400">{{ $lang === 'fr' ? 'Glissez pour réorganiser · la 1ʳᵉ photo est la couverture.' : 'Drag to reorder · the first photo is the cover.' }}</p>
                @endif

                <div wire:loading wire:target="photos" class="mt-2 flex items-center gap-2 text-xs text-cm-green font-medium">
                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                    {{ $lang === 'fr' ? 'Téléversement…' : 'Uploading…' }}
                </div>
                @error('photos') <p class="text-xs text-cm-red mt-1.5 font-medium">⚠ {{ $message }}</p> @enderror
                @error('photos.*') <p class="text-xs text-cm-red mt-1.5 font-medium">⚠ {{ $message }}</p> @enderror
            </div>

            {{-- ─── Title ─── --}}
            <label class="block">
                <span class="text-[13px] font-semibold text-slate-700">{{ $lang === 'fr' ? 'Titre' : 'Title' }}</span>
                <input type="text" wire:model.live.debounce.400ms="title" maxlength="180"
                       placeholder="{{ $lang === 'fr' ? 'Ex: iPhone 13 Pro 128 Go' : 'e.g. iPhone 13 Pro 128GB' }}"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-[15px] text-slate-900 placeholder-slate-400 focus:border-cm-green focus:ring-1 focus:ring-cm-green focus:outline-none transition">
                @error('title') <p class="text-xs text-cm-red mt-1 font-medium">⚠ {{ $message }}</p> @enderror
            </label>

            {{-- ─── Price ─── --}}
            <div>
                <span class="text-[13px] font-semibold text-slate-700">{{ $lang === 'fr' ? 'Prix' : 'Price' }}</span>
                <div class="mt-1 flex gap-2">
                    <div class="flex-1 flex rounded-lg border border-slate-300 focus-within:border-cm-green focus-within:ring-1 focus-within:ring-cm-green transition overflow-hidden {{ in_array($priceType, ['free','contact']) ? 'opacity-50 pointer-events-none' : '' }}">
                        <input type="number" min="0" step="any" wire:model.live.debounce.400ms="price"
                               placeholder="0"
                               class="flex-1 min-w-0 border-0 px-3 py-2.5 text-[15px] text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-0">
                        <select wire:model.live="currency" class="bg-slate-100 border-0 px-2 text-sm font-semibold text-slate-700 focus:outline-none focus:ring-0 cursor-pointer">
                            <option>XAF</option><option>GBP</option><option>EUR</option><option>USD</option><option>CAD</option>
                        </select>
                    </div>
                    <select wire:model.live="priceType" class="rounded-lg border border-slate-300 px-2 py-2.5 text-sm text-slate-700 focus:border-cm-green focus:ring-1 focus:ring-cm-green focus:outline-none cursor-pointer">
                        @foreach ($this->priceTypeOptions() as $o)
                            <option value="{{ $o['v'] }}">{{ $lang === 'fr' ? $o['fr'] : $o['l'] }}</option>
                        @endforeach
                    </select>
                </div>
                @error('price') <p class="text-xs text-cm-red mt-1 font-medium">⚠ {{ $message }}</p> @enderror
            </div>

            {{-- ─── Category ─── --}}
            <label class="block">
                <span class="text-[13px] font-semibold text-slate-700">{{ $lang === 'fr' ? 'Catégorie' : 'Category' }}</span>
                <select wire:model.live="categoryId"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-[15px] text-slate-900 bg-white focus:border-cm-green focus:ring-1 focus:ring-cm-green focus:outline-none transition cursor-pointer">
                    <option value="">{{ $lang === 'fr' ? '— Choisir une catégorie —' : '— Select a category —' }}</option>
                    @foreach ($roots as $root)
                        @php $children = $allCats->where('parent_id', $root->id); @endphp
                        <optgroup label="{{ $root->icon }} {{ $root->localizedName() }}">
                            <option value="{{ $root->id }}">{{ $root->localizedName() }}{{ $children->isNotEmpty() ? ($lang === 'fr' ? ' (général)' : ' (general)') : '' }}</option>
                            @foreach ($children as $child)
                                <option value="{{ $child->id }}">{{ $child->localizedName() }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('categoryId') <p class="text-xs text-cm-red mt-1 font-medium">⚠ {{ $message }}</p> @enderror
            </label>

            {{-- ─── Condition ─── --}}
            <label class="block">
                <span class="text-[13px] font-semibold text-slate-700">{{ $lang === 'fr' ? 'État' : 'Condition' }}</span>
                <select wire:model.live="condition"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-[15px] text-slate-900 bg-white focus:border-cm-green focus:ring-1 focus:ring-cm-green focus:outline-none transition cursor-pointer">
                    @foreach ($this->conditionOptions() as $o)
                        <option value="{{ $o['v'] }}">{{ $lang === 'fr' ? $o['fr'] : $o['l'] }}</option>
                    @endforeach
                </select>
                @error('condition') <p class="text-xs text-cm-red mt-1 font-medium">⚠ {{ $message }}</p> @enderror
            </label>

            {{-- ─── More details (collapsible) ─── --}}
            <div class="border-t border-slate-200 pt-3">
                <button type="button" @click="more = !more"
                        class="w-full flex items-center justify-between text-left group">
                    <span class="text-[15px] font-bold text-slate-900">{{ $lang === 'fr' ? 'Plus de détails' : 'More details' }}</span>
                    <svg class="w-5 h-5 text-slate-500 transition-transform" :class="more && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>

                <div x-show="more" x-cloak class="space-y-4 pt-3">
                    {{-- Description --}}
                    <label class="block">
                        <span class="text-[13px] font-semibold text-slate-700">{{ $lang === 'fr' ? 'Description' : 'Description' }}</span>
                        <textarea wire:model.live.debounce.500ms="description" rows="4" maxlength="5000"
                                  placeholder="{{ $lang === 'fr' ? 'Décrivez l\'état, les caractéristiques…' : 'Describe condition, features…' }}"
                                  class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-[15px] text-slate-900 placeholder-slate-400 focus:border-cm-green focus:ring-1 focus:ring-cm-green focus:outline-none transition resize-y"></textarea>
                        @error('description') <p class="text-xs text-cm-red mt-1 font-medium">⚠ {{ $message }}</p> @enderror
                    </label>

                    {{-- Quantity --}}
                    <label class="block">
                        <span class="text-[13px] font-semibold text-slate-700">{{ $lang === 'fr' ? 'Quantité disponible' : 'Quantity available' }}</span>
                        <input type="number" min="1" wire:model.live="quantity"
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-[15px] text-slate-900 focus:border-cm-green focus:ring-1 focus:ring-cm-green focus:outline-none transition">
                    </label>

                    {{-- Category-specific attributes --}}
                    @if (! empty($_attrSchema))
                        <div class="space-y-3 p-3 rounded-lg bg-slate-50 ring-1 ring-slate-100">
                            <div class="text-[12px] font-bold text-slate-700">{{ $lang === 'fr' ? 'Détails de la catégorie' : 'Category details' }}</div>
                            @foreach ($_attrSchema as $f)
                                <label class="block">
                                    <span class="text-[12px] font-semibold text-slate-600 flex items-center gap-1">
                                        @if (! empty($f['icon'])) <span>{{ $f['icon'] }}</span> @endif
                                        <span>{{ $lang === 'fr' ? $f['labelFr'] : $f['label'] }}</span>
                                        @if (! empty($f['required'])) <span class="text-cm-red">*</span> @endif
                                    </span>
                                    @if ($f['type'] === 'select')
                                        <select wire:model.live="attrs.{{ $f['key'] }}"
                                                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 bg-white focus:border-cm-green focus:ring-1 focus:ring-cm-green focus:outline-none">
                                            <option value="">{{ $lang === 'fr' ? '— Choisir —' : '— Select —' }}</option>
                                            @foreach ($f['options'] as $opt)
                                                <option value="{{ $opt['value'] }}">{{ $lang === 'fr' ? ($opt['labelFr'] ?? $opt['label']) : $opt['label'] }}</option>
                                            @endforeach
                                        </select>
                                    @elseif ($f['type'] === 'toggle')
                                        <div class="mt-1 inline-flex items-center gap-2">
                                            <input type="checkbox" wire:model.live="attrs.{{ $f['key'] }}" class="w-5 h-5 rounded text-cm-green focus:ring-cm-green">
                                            <span class="text-sm text-slate-700">{{ $lang === 'fr' ? 'Oui' : 'Yes' }}</span>
                                        </div>
                                    @elseif ($f['type'] === 'number')
                                        <input type="number" min="0" wire:model.live="attrs.{{ $f['key'] }}" placeholder="{{ $f['help'] ?? '' }}"
                                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-cm-green focus:ring-1 focus:ring-cm-green focus:outline-none">
                                    @else
                                        <input type="text" wire:model.live="attrs.{{ $f['key'] }}" maxlength="160" placeholder="{{ $f['help'] ?? '' }}"
                                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-cm-green focus:ring-1 focus:ring-cm-green focus:outline-none">
                                    @endif
                                    @error('attrs.'.$f['key']) <p class="text-[11px] text-cm-red mt-0.5">{{ $message }}</p> @enderror
                                </label>
                            @endforeach
                        </div>
                    @endif

                    {{-- Tags --}}
                    <div>
                        <span class="text-[13px] font-semibold text-slate-700">{{ $lang === 'fr' ? 'Mots-clés (aide à la recherche)' : 'Product tags (helps search)' }}</span>
                        <div class="mt-1 flex gap-2">
                            <input type="text" wire:model="tagInput" wire:keydown.enter.prevent="addTag" maxlength="20"
                                   placeholder="{{ $lang === 'fr' ? 'Ajouter…' : 'Add a tag…' }}"
                                   class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:border-cm-green focus:ring-1 focus:ring-cm-green focus:outline-none">
                            <button type="button" wire:click="addTag" class="px-4 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-bold">＋</button>
                        </div>
                        @if (count($tags))
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($tags as $tag)
                                    <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-medium">
                                        #{{ $tag }}
                                        <button type="button" wire:click="removeTag('{{ addslashes($tag) }}')" class="text-slate-400 hover:text-cm-red">✕</button>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ─── Delivery / fulfillment ─── --}}
            <div class="border-t border-slate-200 pt-3">
                <div class="text-[15px] font-bold text-slate-900 mb-2">{{ $lang === 'fr' ? 'Remise & livraison' : 'Delivery method' }}</div>
                <div class="grid grid-cols-2 gap-2">
                    @foreach ($this->fulfillmentOptions() as $o)
                        <button type="button" wire:click="$set('fulfillment', '{{ $o['v'] }}')"
                                class="p-2.5 text-left rounded-lg border transition
                                {{ $fulfillment === $o['v'] ? 'border-cm-green bg-cm-green/5 ring-1 ring-cm-green' : 'border-slate-300 hover:border-cm-green/50' }}">
                            <div class="text-lg">{{ $o['icon'] }}</div>
                            <div class="mt-0.5 text-[12px] font-semibold text-slate-800 leading-tight">{{ $lang === 'fr' ? $o['fr'] : $o['l'] }}</div>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- ─── Location ─── --}}
            <div>
                <div class="text-[15px] font-bold text-slate-900 mb-2">{{ $lang === 'fr' ? 'Localisation' : 'Location' }}</div>
                <div class="grid grid-cols-2 gap-2">
                    <label class="block">
                        <span class="text-[12px] font-semibold text-slate-600">{{ $lang === 'fr' ? 'Région' : 'Region' }}</span>
                        <input type="text" wire:model.live.debounce.500ms="region"
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-cm-green focus:ring-1 focus:ring-cm-green focus:outline-none">
                    </label>
                    <label class="block">
                        <span class="text-[12px] font-semibold text-slate-600">{{ $lang === 'fr' ? 'Ville' : 'City' }}</span>
                        <input type="text" wire:model.live.debounce.500ms="city"
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-cm-green focus:ring-1 focus:ring-cm-green focus:outline-none">
                    </label>
                    <label class="block">
                        <span class="text-[12px] font-semibold text-slate-600">{{ $lang === 'fr' ? 'Pays' : 'Country' }}</span>
                        <input type="text" wire:model.live="country" maxlength="2"
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 uppercase focus:border-cm-green focus:ring-1 focus:ring-cm-green focus:outline-none">
                    </label>
                    <label class="block">
                        <span class="text-[12px] font-semibold text-slate-600">{{ $lang === 'fr' ? 'Quartier' : 'Neighborhood' }}</span>
                        <input type="text" wire:model.live.debounce.500ms="neighborhood"
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-cm-green focus:ring-1 focus:ring-cm-green focus:outline-none">
                    </label>
                </div>
                {{-- Live map preview of the entered location --}}
                @if ($locStr)
                    <div class="mt-2 rounded-lg overflow-hidden ring-1 ring-slate-200 bg-slate-100">
                        <iframe src="https://maps.google.com/maps?q={{ rawurlencode($locStr) }}&z=11&output=embed"
                                class="w-full h-40 border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                                title="{{ $lang === 'fr' ? 'Carte' : 'Map' }}"></iframe>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-400">📍 {{ $locStr }}</p>
                @endif
            </div>

            {{-- ─── Audience ─── --}}
            <label class="block">
                <span class="text-[13px] font-semibold text-slate-700">{{ $lang === 'fr' ? 'Audience' : 'Audience' }}</span>
                <select wire:model.live="visibility"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-[15px] text-slate-900 bg-white focus:border-cm-green focus:ring-1 focus:ring-cm-green focus:outline-none transition cursor-pointer">
                    @foreach ($this->visibilityOptions() as $o)
                        <option value="{{ $o['v'] }}">{{ $o['icon'] }} {{ $lang === 'fr' ? $o['fr'] : $o['l'] }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        @if ($errors->any())
            <div class="mb-4 p-4 bg-red-50 text-red-600 rounded-lg text-sm" x-init="window.scrollTo({top: 0, behavior: 'smooth'})">
                <strong>Please fix the following errors before publishing:</strong>
                <ul class="list-disc pl-5 mt-1">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Publish bar --}}
        <div class="sticky bottom-0 bg-white border-t border-slate-200 px-4 sm:px-5 py-3">
            <button type="button" wire:click="publish" wire:loading.attr="disabled" wire:target="publish"
                    class="w-full inline-flex items-center justify-center gap-2 bg-cm-green text-white font-extrabold py-3 rounded-lg hover:bg-cm-green/90 shadow-sm disabled:opacity-60 transition">
                <svg wire:loading wire:target="publish" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                {{ $isEdit ? ($lang === 'fr' ? 'Enregistrer' : 'Save changes') : ($lang === 'fr' ? 'Publier' : 'Publish') }}
            </button>
        </div>
    </aside>

    {{-- ═══════════════ RIGHT · LIVE PREVIEW ═══════════════ --}}
    <section class="flex-1 lg:h-full lg:overflow-y-auto px-4 lg:px-10 py-5 lg:py-8">
        <div class="text-sm font-bold text-slate-500 mb-3">{{ $lang === 'fr' ? 'Aperçu' : 'Preview' }}</div>

        <div class="max-w-2xl bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 overflow-hidden">
            {{-- Image --}}
            <div class="relative bg-[#18191a] aspect-[4/3] flex items-center justify-center">
                @if (! empty($previewMedia))
                    @foreach ($previewMedia as $i => $m)
                        <img src="{{ $m['url'] }}" x-show="pi === {{ $i }}" alt=""
                             @click="lb = true"
                             class="absolute inset-0 w-full h-full object-contain cursor-zoom-in" @if($i>0) style="display:none" @endif>
                    @endforeach
                    @if (count($previewMedia) > 1)
                        <button type="button" @click="pi = Math.max(0, pi-1)"
                                class="absolute left-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-white/90 hover:bg-white grid place-items-center shadow z-10">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <button type="button" @click="pi = Math.min({{ count($previewMedia)-1 }}, pi+1)"
                                class="absolute right-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-white/90 hover:bg-white grid place-items-center shadow z-10">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    @endif
                @else
                    <div class="flex flex-col items-center gap-2 text-slate-400">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15A2.25 2.25 0 002.25 6.75v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
                        <span class="text-xs font-semibold uppercase tracking-wide">{{ $lang === 'fr' ? 'Vos photos ici' : 'Your photos here' }}</span>
                    </div>
                @endif
            </div>

            <div class="p-5">
                {{-- Title + price --}}
                <h2 class="text-xl font-bold text-slate-900 leading-tight">
                    {{ $title ?: ($lang === 'fr' ? 'Titre de l\'article' : 'Item title') }}
                </h2>
                <div class="mt-1 text-xl font-bold text-slate-900">
                    @if ($priceType === 'free') {{ $lang === 'fr' ? 'Gratuit' : 'Free' }}
                    @elseif ($priceType === 'contact') <span class="text-base text-slate-600">{{ $lang === 'fr' ? 'Prix sur demande' : 'Contact for price' }}</span>
                    @elseif ($price === null || $price === '') <span class="text-base text-slate-400">{{ $currency }} 0</span>
                    @else {{ number_format((float) $price, 0, '.', ',') }} {{ $currency }}
                        @if ($priceType === 'negotiable') <span class="text-xs font-normal text-slate-500">· {{ $lang === 'fr' ? 'négociable' : 'negotiable' }}</span> @endif
                    @endif
                </div>
                <div class="mt-1 text-[13px] text-slate-500">
                    {{ $lang === 'fr' ? 'Publié à l\'instant' : 'Listed just now' }}@if ($locStr) · {{ $locStr }}@endif
                </div>

                {{-- chips --}}
                <div class="mt-3 flex flex-wrap gap-1.5 text-[12px]">
                    @if ($pickedCat)
                        <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 font-medium">{{ $pickedCat->icon }} {{ $pickedCat->localizedName() }}</span>
                    @endif
                    @if ($condLabel)
                        <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 font-medium">{{ $condLabel }}</span>
                    @endif
                </div>

                {{-- Details --}}
                <div class="mt-4 border-t border-slate-200 pt-4">
                    <h3 class="text-base font-bold text-slate-900 mb-1.5">{{ $lang === 'fr' ? 'Détails' : 'Details' }}</h3>
                    <p class="text-sm text-slate-700 whitespace-pre-line leading-relaxed">
                        {{ $description ?: ($lang === 'fr' ? 'La description de votre article apparaîtra ici.' : 'Your item description will appear here.') }}
                    </p>
                </div>

                {{-- Seller --}}
                <div class="mt-4 border-t border-slate-200 pt-4">
                    <h3 class="text-base font-bold text-slate-900 mb-2.5">{{ $lang === 'fr' ? 'Informations du vendeur' : 'Seller information' }}</h3>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-cm-green/15 grid place-items-center text-cm-green font-bold uppercase overflow-hidden shrink-0">
                            @if ($user?->avatar)
                                <img src="{{ asset('storage/' . $user->avatar) }}" alt="" class="w-full h-full object-cover">
                            @else
                                {{ substr($user?->name ?? 'U', 0, 1) }}
                            @endif
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-slate-900 truncate">{{ $user?->name }}</div>
                            <div class="text-[11px] text-slate-500">{{ $lang === 'fr' ? 'Membre depuis' : 'Joined' }} {{ $user?->created_at?->translatedFormat('M Y') }}</div>
                        </div>
                    </div>
                    <button type="button" disabled
                            class="mt-3 w-full inline-flex items-center justify-center gap-2 bg-cm-green/90 text-white font-bold py-2.5 rounded-lg cursor-default opacity-90">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
                        {{ $lang === 'fr' ? 'Message' : 'Message' }}
                    </button>
                </div>
            </div>
        </div>

        <p class="max-w-2xl mt-3 text-[12px] text-slate-400">
            {{ $lang === 'fr'
                ? 'Ceci est un aperçu. Votre annonce ressemblera à ceci sur GoMarket.'
                : 'This is a preview. Your listing will look like this on GoMarket.' }}
        </p>
    </section>
    </div>{{-- /two-pane --}}
@endif

    {{-- ═══ Preview photo lightbox (teleported to body to cover the full screen) ═══ --}}
    <template x-teleport="body">
    <div x-show="lb" x-cloak @click.self="lb = false" x-transition.opacity
         class="fixed inset-0 z-[80] bg-black flex items-center justify-center">
        <button type="button" @click="lb = false" aria-label="{{ $lang === 'fr' ? 'Fermer' : 'Close' }}"
                class="absolute top-4 right-4 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white grid place-items-center z-10">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <img :src="limgs[pi]" class="max-w-[92vw] max-h-[88vh] w-auto h-auto object-contain select-none" alt="">
        <template x-if="limgs.length > 1">
            <div>
                <button type="button" @click.stop="pi = (pi - 1 + limgs.length) % limgs.length"
                        class="absolute left-4 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 text-white grid place-items-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <button type="button" @click.stop="pi = (pi + 1) % limgs.length"
                        class="absolute right-4 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 text-white grid place-items-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </button>
                <span class="absolute bottom-5 left-1/2 -translate-x-1/2 text-sm font-semibold text-white bg-white/10 px-3 py-1 rounded-full"
                      x-text="(pi + 1) + ' / ' + limgs.length"></span>
            </div>
        </template>
    </div>
    </template>
</div>
