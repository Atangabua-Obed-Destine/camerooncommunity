<div class="min-h-[calc(100vh-96px)] bg-slate-100">
    <div class="max-w-2xl mx-auto px-3 sm:px-4 py-6">
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 overflow-hidden">

            {{-- Header --}}
            <div class="px-5 py-4 bg-gradient-to-r from-cm-green to-cm-green/80 text-white">
                <h1 class="text-xl font-extrabold">
                    @if ($existingId)
                        <span x-data x-text="$store.lang.t('Update your review','Modifier votre avis')"></span>
                    @else
                        <span x-data x-text="$store.lang.t('Rate your experience','Évaluez votre expérience')"></span>
                    @endif
                </h1>
                <p class="text-sm opacity-90 mt-0.5">
                    <span x-data x-text="$store.lang.t('Your honest feedback helps the community.','Votre avis honnête aide la communauté.')"></span>
                </p>
            </div>

            {{-- Listing + Seller --}}
            <div class="px-5 py-4 flex items-center gap-3 border-b border-slate-100">
                @if ($listing->coverUrl())
                    <img src="{{ $listing->coverUrl() }}" alt="" class="w-14 h-14 rounded-lg object-cover ring-1 ring-slate-200">
                @else
                    <div class="w-14 h-14 rounded-lg bg-slate-200 grid place-items-center text-2xl">📦</div>
                @endif
                <div class="min-w-0 flex-1">
                    <div class="font-bold text-slate-900 truncate">{{ $listing->title }}</div>
                    <div class="text-xs text-slate-600">
                        <span x-data x-text="$store.lang.t('Sold by','Vendu par')"></span>
                        <span class="font-semibold">{{ $this->sellerName }}</span>
                    </div>
                </div>
            </div>

            {{-- Rating + comment --}}
            <form wire:submit.prevent="save" class="p-5 space-y-5" x-data="{ hover: 0 }">
                <div>
                    <div class="text-[13px] font-semibold text-slate-700 mb-2">
                        <span x-data x-text="$store.lang.t('Your rating','Votre note')"></span>
                        <span class="text-cm-red">*</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        @for ($i = 1; $i <= 5; $i++)
                            <button type="button"
                                    wire:click="setRating({{ $i }})"
                                    @mouseenter="hover = {{ $i }}"
                                    @mouseleave="hover = 0"
                                    class="text-4xl leading-none transition-transform hover:scale-110"
                                    :class="(hover ? hover : {{ $rating }}) >= {{ $i }} ? 'text-cm-yellow' : 'text-slate-300'">
                                ★
                            </button>
                        @endfor
                        <span class="ml-3 text-sm font-bold text-slate-700" x-text="(hover || {{ $rating }}) + ' / 5'"></span>
                    </div>
                    @error('rating') <p class="text-xs text-cm-red mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-[13px] font-semibold text-slate-700 mb-1">
                        <span x-data x-text="$store.lang.t('Comment (optional)','Commentaire (optionnel)')"></span>
                    </label>
                    <textarea wire:model="comment" rows="5" maxlength="1000"
                              placeholder="{{ app()->getLocale() === 'fr' ? 'Comment s\'est passé l\'achat, l\'état de l\'article, la communication…' : 'How was the purchase, item condition, communication…' }}"
                              class="w-full rounded-xl ring-1 ring-slate-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-cm-green focus:outline-none"></textarea>
                    <div class="flex justify-between text-[11px] text-slate-500 mt-1">
                        <span x-data x-text="$store.lang.t('Be specific and respectful.','Soyez précis et respectueux.')"></span>
                        <span>{{ mb_strlen($comment) }} / 1000</span>
                    </div>
                    @error('comment') <p class="text-xs text-cm-red mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <a href="{{ route('marketplace.show', ['slug' => $listing->slug]) }}" wire:navigate
                       class="text-sm font-semibold text-slate-700 hover:underline">
                        <span x-data x-text="$store.lang.t('Cancel','Annuler')"></span>
                    </a>
                    <button type="submit"
                            class="text-sm font-semibold text-white bg-cm-green hover:bg-cm-green/90 rounded-full px-6 py-2.5 shadow">
                        @if ($existingId)
                            <span x-data x-text="$store.lang.t('Update review','Mettre à jour')"></span>
                        @else
                            <span x-data x-text="$store.lang.t('Post review','Publier')"></span>
                        @endif
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
