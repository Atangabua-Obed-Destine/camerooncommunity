@php $lang = app()->getLocale(); $progress = (int) (($step / 5) * 100); @endphp
<div class="min-h-[calc(100vh-96px)] bg-slate-100" wire:poll.15s="autosaveDraft">
    <div class="max-w-3xl mx-auto px-3 sm:px-4 lg:px-6 py-5 lg:py-6">

        {{-- ─── Header ─── --}}
        <div class="mb-5 flex items-start gap-3">
            <a href="{{ route('marketplace.index') }}" wire:navigate
               class="hidden sm:grid w-11 h-11 place-items-center rounded-full bg-white ring-1 ring-slate-200 text-slate-700 hover:bg-slate-50 hover:ring-cm-green/40 transition shrink-0"
               title="{{ $lang === 'fr' ? 'Retour à GoMarket' : 'Back to GoMarket' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div class="flex-1 min-w-0">
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                    <span x-data x-text="$store.lang.t('Sell on GoMarket','Vendre sur GoMarket')"></span>
                </h1>
                @if ($lastAutosavedAt)
                    <p class="text-[11px] text-emerald-600 font-semibold mt-0.5 flex items-center gap-1"
                       x-data="{ ts: @js($lastAutosavedAt) }"
                       x-init="$watch('$wire.lastAutosavedAt', v => ts = v)">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span x-data x-text="$store.lang.t('Draft saved','Brouillon enregistré')"></span>
                        ·
                        <span x-text="new Date(ts).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})"></span>
                    </p>
                @endif
                <p class="mt-1 text-sm text-slate-600">
                    <span x-data x-text="$store.lang.t('Reach buyers across Cameroon and the diaspora.','Atteignez des acheteurs au Cameroun et dans la diaspora.')"></span>
                </p>
            </div>
        </div>

        {{-- ─── Stepper card ─── --}}
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-3 sm:p-4 mb-4">
            {{-- Progress bar --}}
            <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden mb-3">
                <div class="h-full bg-gradient-to-r from-cm-green to-emerald-400 rounded-full transition-all duration-500"
                     style="width: {{ $progress }}%"></div>
            </div>
            {{-- Step pills --}}
            <ol class="flex items-center gap-1 sm:gap-2 overflow-x-auto -mx-1 px-1">
                @foreach (['Category|Catégorie|🏷️','Photos|Photos|📷','Details|Détails|📝','Location|Lieu|📍','Review|Vérifier|✓'] as $i => $pair)
                    @php [$en, $fr, $icon] = explode('|', $pair); $n = $i + 1; $done = $step > $n; $current = $step === $n; @endphp
                    <li class="flex items-center gap-1.5 sm:gap-2 shrink-0">
                        <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-full text-[12px] font-bold flex items-center justify-center transition
                            {{ $current ? 'bg-cm-green text-white shadow-md ring-4 ring-cm-green/20' : ($done ? 'bg-cm-green/15 text-cm-green' : 'bg-slate-100 text-slate-500') }}">
                            {{ $done ? '✓' : $n }}
                        </span>
                        <span class="text-xs sm:text-sm hidden sm:inline {{ $current ? 'font-bold text-slate-900' : 'text-slate-500 font-medium' }}">
                            {{ $lang === 'fr' ? $fr : $en }}
                        </span>
                        @unless ($loop->last)
                            <span class="w-3 sm:w-6 h-px bg-slate-300"></span>
                        @endunless
                    </li>
                @endforeach
            </ol>
        </div>

        {{-- ─── Main card ─── --}}
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-4 sm:p-6">

            {{-- ────────── STEP 1: CATEGORY ────────── --}}
            @if ($step === 1)
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-slate-900" x-data x-text="$store.lang.t('Choose a category','Choisissez une catégorie')"></h2>
                    <p class="text-sm text-slate-500 mt-0.5" x-data x-text="$store.lang.t('Pick the best match so buyers can find your item.','Choisissez la catégorie qui correspond le mieux à votre article.')"></p>
                </div>
                @php $roots = $this->categories()->whereNull('parent_id'); @endphp
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                    @foreach ($roots as $root)
                        <button type="button" wire:click="$set('categoryId', {{ $root->id }})"
                                class="text-left p-4 rounded-2xl ring-2 transition group
                                {{ $categoryId === $root->id ? 'ring-cm-green bg-cm-green/5 shadow-sm' : 'ring-slate-200 hover:ring-cm-green/50 hover:bg-slate-50' }}">
                            <div class="text-3xl group-hover:scale-110 transition-transform">{{ $root->icon }}</div>
                            <div class="mt-2 text-sm font-bold text-slate-900 leading-tight">
                                {{ $root->localizedName() }}
                            </div>
                        </button>
                    @endforeach
                </div>

                @if ($categoryId)
                    @php
                        $picked = $this->categories()->firstWhere('id', $categoryId);
                        $children = $picked && ! $picked->parent_id
                            ? $this->categories()->where('parent_id', $picked->id)
                            : collect();
                    @endphp
                    @if ($children->isNotEmpty())
                        <div class="mt-5 pt-5 border-t border-slate-200">
                            <p class="text-[13px] font-bold text-slate-700 mb-2.5" x-data x-text="$store.lang.t('Subcategory (recommended)','Sous-catégorie (recommandée)')"></p>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($children as $child)
                                    <button type="button" wire:click="$set('categoryId', {{ $child->id }})"
                                            class="text-sm px-3.5 py-2 rounded-full ring-1 font-medium transition
                                            {{ $categoryId === $child->id ? 'bg-cm-green text-white ring-cm-green shadow' : 'bg-white ring-slate-200 text-slate-700 hover:ring-cm-green/50 hover:bg-cm-green/5' }}">
                                        {{ $child->icon }} {{ $child->localizedName() }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif
                @error('categoryId') <p class="text-sm text-cm-red mt-3 font-medium">⚠ {{ $message }}</p> @enderror
            @endif

            {{-- ────────── STEP 2: PHOTOS ────────── --}}
            @if ($step === 2)
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-slate-900" x-data x-text="$store.lang.t('Add photos','Ajoutez des photos')"></h2>
                    <p class="text-sm text-slate-500 mt-0.5" x-data
                       x-text="$store.lang.t('Up to 10 images. First image is the cover (tap ★ to change).','Jusqu’à 10 images. La première est la couverture (appuyez sur ★ pour changer).')"></p>
                </div>

                <label class="block w-full border-2 border-dashed border-slate-300 rounded-2xl p-10 text-center cursor-pointer hover:border-cm-green hover:bg-cm-green/5 transition group">
                    <div class="text-5xl group-hover:scale-110 transition-transform">📸</div>
                    <div class="mt-3 text-base font-bold text-slate-800" x-data
                         x-text="$store.lang.t('Click or drop images here','Cliquez ou déposez des images ici')"></div>
                    <div class="mt-1 text-xs text-slate-500" x-data
                         x-text="$store.lang.t('JPG, PNG, WebP — up to 10 MB each','JPG, PNG, WebP — jusqu’à 10 Mo chacune')"></div>
                    <input type="file" wire:model="photos" multiple accept="image/*" class="hidden">
                </label>

                <div wire:loading wire:target="photos" class="mt-3 flex items-center gap-2 text-sm text-cm-green font-medium">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                    <span x-data x-text="$store.lang.t('Uploading…','Téléversement…')"></span>
                </div>

                @php $allMedia = array_merge($existingMedia, $uploadedMedia); @endphp
                @if (count($allMedia))
                    <div class="mt-5 grid grid-cols-3 sm:grid-cols-4 gap-2.5">
                        @foreach ($allMedia as $m)
                            <div class="relative aspect-square rounded-xl overflow-hidden ring-2 {{ $m['is_cover'] ? 'ring-cm-green shadow-md' : 'ring-slate-200' }} group bg-slate-100">
                                <img src="{{ $m['url'] }}" class="w-full h-full object-cover" alt="">
                                @if ($m['is_cover'])
                                    <span class="absolute top-1.5 left-1.5 bg-cm-green text-white text-[10px] font-bold uppercase px-2 py-0.5 rounded-full shadow">★ Cover</span>
                                @else
                                    <button type="button" wire:click="makeCover({{ $m['id'] }})"
                                            class="absolute top-1.5 left-1.5 bg-black/60 hover:bg-cm-green text-white text-[10px] font-bold px-2 py-0.5 rounded-full opacity-0 group-hover:opacity-100 transition"
                                            title="{{ $lang === 'fr' ? 'Définir comme couverture' : 'Set as cover' }}">
                                        ★ {{ $lang === 'fr' ? 'Couv.' : 'Cover' }}
                                    </button>
                                @endif
                                <button type="button" wire:click="removeMedia({{ $m['id'] }})"
                                        class="absolute top-1.5 right-1.5 bg-black/60 hover:bg-cm-red text-white text-[11px] w-6 h-6 rounded-full opacity-0 group-hover:opacity-100 transition flex items-center justify-center"
                                        title="{{ $lang === 'fr' ? 'Supprimer' : 'Remove' }}">✕</button>
                            </div>
                        @endforeach
                    </div>
                @endif
                @error('photos.*') <p class="text-sm text-cm-red mt-3 font-medium">⚠ {{ $message }}</p> @enderror
            @endif

            {{-- ────────── STEP 3: DETAILS ────────── --}}
            @if ($step === 3)
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-slate-900" x-data x-text="$store.lang.t('Item details','Détails de l\'article')"></h2>
                    <p class="text-sm text-slate-500 mt-0.5" x-data x-text="$store.lang.t('A clear title and description sell faster.','Un titre clair et une description vendent plus vite.')"></p>
                </div>
                <div class="space-y-4">
                    <label class="block">
                        <span class="text-[13px] font-bold text-slate-700" x-data x-text="$store.lang.t('Title','Titre')"></span>
                        <input type="text" wire:model.blur="title" maxlength="180"
                               placeholder="{{ $lang === 'fr' ? 'Ex: iPhone 13 Pro 128 Go, état neuf' : 'e.g. iPhone 13 Pro 128GB, like new' }}"
                               class="mt-1.5 w-full rounded-xl bg-slate-100 border-0 px-3.5 py-2.5 text-[15px] text-slate-900 placeholder-slate-400 focus:bg-white focus:ring-2 focus:ring-cm-green focus:outline-none transition">
                        @error('title') <p class="text-xs text-cm-red mt-1 font-medium">⚠ {{ $message }}</p> @enderror
                    </label>

                    <label class="block">
                        <span class="text-[13px] font-bold text-slate-700" x-data x-text="$store.lang.t('Description','Description')"></span>
                        <textarea wire:model.blur="description" rows="5"
                                  placeholder="{{ $lang === 'fr' ? 'Décrivez l\'état, les caractéristiques, l\'historique…' : 'Describe condition, features, history…' }}"
                                  class="mt-1.5 w-full rounded-xl bg-slate-100 border-0 px-3.5 py-2.5 text-[15px] text-slate-900 placeholder-slate-400 focus:bg-white focus:ring-2 focus:ring-cm-green focus:outline-none transition resize-y"></textarea>
                        @error('description') <p class="text-xs text-cm-red mt-1 font-medium">⚠ {{ $message }}</p> @enderror
                    </label>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="block">
                            <span class="text-[13px] font-bold text-slate-700" x-data x-text="$store.lang.t('Price type','Type de prix')"></span>
                            <select wire:model.live="priceType" class="mt-1.5 w-full rounded-xl bg-slate-100 border-0 px-3.5 py-2.5 text-[15px] text-slate-900 focus:bg-white focus:ring-2 focus:ring-cm-green focus:outline-none transition">
                                @foreach ($this->priceTypeOptions() as $o)
                                    <option value="{{ $o['v'] }}">{{ $lang === 'fr' ? $o['fr'] : $o['l'] }}</option>
                                @endforeach
                            </select>
                        </label>

                        @if (! in_array($priceType, ['free', 'contact']))
                            <label class="block">
                                <span class="text-[13px] font-bold text-slate-700" x-data x-text="$store.lang.t('Price','Prix')"></span>
                                <div class="mt-1.5 flex rounded-xl bg-slate-100 ring-0 focus-within:ring-2 focus-within:ring-cm-green transition overflow-hidden">
                                    <input type="number" min="0" step="any" wire:model.blur="price" placeholder="0"
                                           class="flex-1 bg-transparent border-0 px-3.5 py-2.5 text-[15px] text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-0">
                                    <select wire:model="currency"
                                            class="bg-slate-200 border-0 px-3 py-2.5 text-sm font-semibold text-slate-800 focus:outline-none focus:ring-0 cursor-pointer">
                                        <option>XAF</option>
                                        <option>GBP</option>
                                        <option>EUR</option>
                                        <option>USD</option>
                                        <option>CAD</option>
                                    </select>
                                </div>
                                @error('price') <p class="text-xs text-cm-red mt-1 font-medium">⚠ {{ $message }}</p> @enderror
                            </label>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <label class="block">
                            <span class="text-[13px] font-bold text-slate-700" x-data x-text="$store.lang.t('Condition','État')"></span>
                            <select wire:model="condition" class="mt-1.5 w-full rounded-xl bg-slate-100 border-0 px-3.5 py-2.5 text-[15px] text-slate-900 focus:bg-white focus:ring-2 focus:ring-cm-green focus:outline-none transition">
                                @foreach ($this->conditionOptions() as $o)
                                    <option value="{{ $o['v'] }}">{{ $lang === 'fr' ? $o['fr'] : $o['l'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-[13px] font-bold text-slate-700" x-data x-text="$store.lang.t('Quantity','Quantité')"></span>
                            <input type="number" min="1" wire:model="quantity"
                                   class="mt-1.5 w-full rounded-xl bg-slate-100 border-0 px-3.5 py-2.5 text-[15px] text-slate-900 focus:bg-white focus:ring-2 focus:ring-cm-green focus:outline-none transition">
                        </label>
                    </div>

                    {{-- Category-specific attributes (Phase 4) --}}
                    @php($_attrSchema = \App\Support\CategoryAttributeSchema::forCategory($categoryId))
                    @if (! empty($_attrSchema))
                        <div class="mt-2 p-4 rounded-2xl bg-cm-green/5 ring-1 ring-cm-green/20">
                            <div class="flex items-center gap-2 mb-3">
                                <svg class="w-4 h-4 text-cm-green" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                                <div class="text-sm font-bold text-slate-900">
                                    <span x-data x-text="$store.lang.t('Category details','Détails de la catégorie')"></span>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach ($_attrSchema as $f)
                                    <label class="block">
                                        <span class="text-[12px] font-semibold text-slate-700 flex items-center gap-1">
                                            @if (! empty($f['icon'])) <span>{{ $f['icon'] }}</span> @endif
                                            <span>{{ $lang === 'fr' ? $f['labelFr'] : $f['label'] }}</span>
                                            @if (! empty($f['required'])) <span class="text-cm-red">*</span> @endif
                                        </span>
                                        @if ($f['type'] === 'select')
                                            <select wire:model="attrs.{{ $f['key'] }}"
                                                    class="mt-1 w-full rounded-xl bg-white border-0 ring-1 ring-slate-200 px-3 py-2 text-sm text-slate-900 focus:ring-2 focus:ring-cm-green focus:outline-none">
                                                <option value="">{{ $lang === 'fr' ? '— Choisir —' : '— Select —' }}</option>
                                                @foreach ($f['options'] as $opt)
                                                    <option value="{{ $opt['value'] }}">{{ $lang === 'fr' ? ($opt['labelFr'] ?? $opt['label']) : $opt['label'] }}</option>
                                                @endforeach
                                            </select>
                                        @elseif ($f['type'] === 'toggle')
                                            <div class="mt-1 inline-flex items-center gap-2">
                                                <input type="checkbox" wire:model="attrs.{{ $f['key'] }}"
                                                       class="w-5 h-5 rounded text-cm-green focus:ring-cm-green">
                                                <span class="text-sm text-slate-700">{{ $lang === 'fr' ? 'Oui' : 'Yes' }}</span>
                                            </div>
                                        @elseif ($f['type'] === 'number')
                                            <div class="mt-1 relative">
                                                <input type="number" min="0" wire:model.blur="attrs.{{ $f['key'] }}"
                                                       placeholder="{{ $f['help'] ?? '' }}"
                                                       class="w-full rounded-xl bg-white border-0 ring-1 ring-slate-200 px-3 py-2 text-sm text-slate-900 focus:ring-2 focus:ring-cm-green focus:outline-none {{ ! empty($f['suffix']) ? 'pr-12' : '' }}">
                                                @if (! empty($f['suffix']))
                                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-500">{{ $f['suffix'] }}</span>
                                                @endif
                                            </div>
                                        @else
                                            <input type="text" wire:model.blur="attrs.{{ $f['key'] }}" maxlength="160"
                                                   placeholder="{{ $f['help'] ?? '' }}"
                                                   class="mt-1 w-full rounded-xl bg-white border-0 ring-1 ring-slate-200 px-3 py-2 text-sm text-slate-900 focus:ring-2 focus:ring-cm-green focus:outline-none">
                                        @endif
                                        @error('attrs.'.$f['key']) <p class="text-[11px] text-cm-red mt-0.5">{{ $message }}</p> @enderror
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- ────────── STEP 4: LOCATION & FULFILLMENT ────────── --}}
            @if ($step === 4)
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-slate-900" x-data x-text="$store.lang.t('Location & delivery','Lieu & livraison')"></h2>
                    <p class="text-sm text-slate-500 mt-0.5" x-data x-text="$store.lang.t('Where is the item and how can buyers get it?','Où se trouve l\'article et comment l\'obtenir ?')"></p>
                </div>
                <div class="space-y-5">
                    <div>
                        <div class="text-[13px] font-bold text-slate-700 mb-2" x-data x-text="$store.lang.t('How buyers get it','Mode de remise')"></div>
                        <div class="grid grid-cols-2 gap-2.5">
                            @foreach ($this->fulfillmentOptions() as $o)
                                <button type="button" wire:click="$set('fulfillment', '{{ $o['v'] }}')"
                                        class="p-3.5 text-left rounded-2xl ring-2 transition
                                        {{ $fulfillment === $o['v'] ? 'ring-cm-green bg-cm-green/5 shadow-sm' : 'ring-slate-200 hover:ring-cm-green/50 hover:bg-slate-50' }}">
                                    <div class="text-2xl">{{ $o['icon'] }}</div>
                                    <div class="mt-1 text-sm font-bold text-slate-900">{{ $lang === 'fr' ? $o['fr'] : $o['l'] }}</div>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <label class="block">
                            <span class="text-[13px] font-bold text-slate-700" x-data x-text="$store.lang.t('Country','Pays')"></span>
                            <input type="text" wire:model="country" maxlength="2"
                                   class="mt-1.5 w-full rounded-xl bg-slate-100 border-0 px-3.5 py-2.5 text-[15px] text-slate-900 focus:bg-white focus:ring-2 focus:ring-cm-green focus:outline-none transition uppercase">
                        </label>
                        <label class="block">
                            <span class="text-[13px] font-bold text-slate-700" x-data x-text="$store.lang.t('Region','Région')"></span>
                            <input type="text" wire:model="region"
                                   class="mt-1.5 w-full rounded-xl bg-slate-100 border-0 px-3.5 py-2.5 text-[15px] text-slate-900 focus:bg-white focus:ring-2 focus:ring-cm-green focus:outline-none transition">
                        </label>
                        <label class="block">
                            <span class="text-[13px] font-bold text-slate-700" x-data x-text="$store.lang.t('City','Ville')"></span>
                            <input type="text" wire:model="city"
                                   class="mt-1.5 w-full rounded-xl bg-slate-100 border-0 px-3.5 py-2.5 text-[15px] text-slate-900 focus:bg-white focus:ring-2 focus:ring-cm-green focus:outline-none transition">
                        </label>
                    </div>
                    <label class="block">
                        <span class="text-[13px] font-bold text-slate-700" x-data x-text="$store.lang.t('Neighborhood (optional)','Quartier (optionnel)')"></span>
                        <input type="text" wire:model="neighborhood"
                               placeholder="{{ $lang === 'fr' ? 'Ex: Bonapriso, Bastos…' : 'e.g. Bonapriso, Bastos…' }}"
                               class="mt-1.5 w-full rounded-xl bg-slate-100 border-0 px-3.5 py-2.5 text-[15px] text-slate-900 placeholder-slate-400 focus:bg-white focus:ring-2 focus:ring-cm-green focus:outline-none transition">
                    </label>
                </div>
            @endif

            {{-- ────────── STEP 5: VISIBILITY & REVIEW ────────── --}}
            @if ($step === 5)
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-slate-900" x-data x-text="$store.lang.t('Review & publish','Vérifier & publier')"></h2>
                    <p class="text-sm text-slate-500 mt-0.5" x-data x-text="$store.lang.t('Last step — control visibility and preview your listing.','Dernière étape — visibilité et aperçu.')"></p>
                </div>
                <div class="space-y-5">
                    <div>
                        <div class="text-[13px] font-bold text-slate-700 mb-2" x-data x-text="$store.lang.t('Who can see this?','Qui peut voir ?')"></div>
                        <div class="grid grid-cols-3 gap-2.5">
                            @foreach ($this->visibilityOptions() as $o)
                                <button type="button" wire:click="$set('visibility', '{{ $o['v'] }}')"
                                        class="p-3 text-center rounded-2xl ring-2 transition
                                        {{ $visibility === $o['v'] ? 'ring-cm-green bg-cm-green/5 shadow-sm' : 'ring-slate-200 hover:ring-cm-green/50 hover:bg-slate-50' }}">
                                    <div class="text-2xl">{{ $o['icon'] }}</div>
                                    <div class="mt-1 text-xs font-bold text-slate-900">{{ $lang === 'fr' ? $o['fr'] : $o['l'] }}</div>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <div class="text-[13px] font-bold text-slate-700 mb-2" x-data x-text="$store.lang.t('Tags (helps search)','Mots-clés (aide à la recherche)')"></div>
                        <div class="flex gap-2">
                            <input type="text" wire:model="tagInput" wire:keydown.enter.prevent="addTag" maxlength="20"
                                   placeholder="{{ $lang === 'fr' ? 'Ajouter un mot-clé…' : 'Add a tag…' }}"
                                   class="flex-1 rounded-xl bg-slate-100 border-0 px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:bg-white focus:ring-2 focus:ring-cm-green focus:outline-none transition">
                            <button type="button" wire:click="addTag" class="px-5 rounded-xl bg-cm-green text-white text-sm font-bold hover:bg-cm-green/90 shadow-sm">＋</button>
                        </div>
                        @if (count($tags))
                            <div class="mt-2.5 flex flex-wrap gap-1.5">
                                @foreach ($tags as $tag)
                                    <span class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full bg-cm-green/10 text-cm-green font-semibold">
                                        #{{ $tag }}
                                        <button type="button" wire:click="removeTag('{{ addslashes($tag) }}')" class="text-cm-red hover:text-red-700">✕</button>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Live preview --}}
                    <div class="mt-2 p-4 rounded-2xl ring-2 ring-cm-green/30 bg-cm-green/5">
                        <div class="text-[11px] font-bold uppercase tracking-wide text-cm-green mb-3 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <span x-data x-text="$store.lang.t('Live preview','Aperçu en direct')"></span>
                        </div>
                        <div class="grid grid-cols-[120px_1fr] gap-3 bg-white rounded-xl p-3 ring-1 ring-slate-200">
                            @php $previewMedia = array_merge($existingMedia, $uploadedMedia); $cover = collect($previewMedia)->firstWhere('is_cover', true) ?? ($previewMedia[0] ?? null); @endphp
                            <div class="aspect-square rounded-lg bg-gradient-to-br from-slate-100 to-slate-200 overflow-hidden">
                                @if ($cover)
                                    <img src="{{ $cover['url'] }}" class="w-full h-full object-cover" alt="">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-3xl text-slate-400">📦</div>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <div class="text-[15px] font-bold text-slate-900 line-clamp-2 leading-snug">{{ $title ?: ($lang === 'fr' ? 'Titre de votre annonce' : 'Your listing title') }}</div>
                                <div class="text-cm-green font-extrabold text-lg mt-1">
                                    @if ($priceType === 'free') {{ $lang === 'fr' ? 'Gratuit' : 'Free' }}
                                    @elseif ($priceType === 'contact') <span class="text-sm">{{ $lang === 'fr' ? 'Prix sur demande' : 'Contact for price' }}</span>
                                    @else {{ number_format((float) $price, 0, '.', ',') }} {{ $currency }}
                                        @if ($priceType === 'negotiable') <span class="text-xs font-normal text-slate-500">{{ $lang === 'fr' ? '(négociable)' : '(negotiable)' }}</span> @endif
                                    @endif
                                </div>
                                <div class="text-xs text-slate-500 mt-1.5 truncate flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C8 2 5 5 5 9c0 5 7 13 7 13s7-8 7-13c0-4-3-7-7-7zm0 9.5a2.5 2.5 0 110-5 2.5 2.5 0 010 5z"/></svg>
                                    {{ trim(($city ? $city . ', ' : '') . ($region ?: $country)) ?: '—' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ─── Navigation buttons ─── --}}
            <div class="mt-7 pt-5 border-t border-slate-200 flex items-center justify-between gap-3">
                <button type="button" wire:click="prevStep" @if ($step === 1) disabled @endif
                        class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-full text-sm font-bold text-slate-700 hover:bg-slate-100 disabled:opacity-30 disabled:hover:bg-transparent transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    <span x-data x-text="$store.lang.t('Back','Retour')"></span>
                </button>
                @if ($step < $maxStep)
                    <button type="button" wire:click="nextStep"
                            class="inline-flex items-center gap-1.5 bg-cm-green text-white font-bold px-7 py-2.5 rounded-full hover:bg-cm-green/90 shadow-md transition">
                        <span x-data x-text="$store.lang.t('Next','Suivant')"></span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                @else
                    <button type="button" wire:click="publish" wire:loading.attr="disabled" wire:target="publish"
                            class="inline-flex items-center gap-2 bg-cm-yellow text-slate-900 font-extrabold px-7 py-2.5 rounded-full hover:bg-cm-yellow/90 shadow-lg disabled:opacity-60 transition">
                        <svg wire:loading.remove wire:target="publish" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <svg wire:loading wire:target="publish" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                        <span x-data x-text="$store.lang.t('Publish listing','Publier l\'annonce')"></span>
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
