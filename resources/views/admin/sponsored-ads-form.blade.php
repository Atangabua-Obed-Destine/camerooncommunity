<x-layouts.admin :title="$ad ? 'Edit Ad' : 'Create Ad'">
    <div class="max-w-3xl space-y-6">
        {{-- Header --}}
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.sponsored-ads') }}"
               class="w-9 h-9 rounded-lg bg-slate-100 flex items-center justify-center text-slate-500 hover:bg-slate-200 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
            </a>
            <h1 class="text-2xl font-bold"
                x-text="$store.lang.t('{{ $ad ? 'Edit Ad' : 'Create Sponsored Ad' }}', '{{ $ad ? 'Modifier Annonce' : 'Créer une Annonce Sponsorisée' }}')"></h1>
        </div>

        {{-- Form --}}
        <form method="POST"
              action="{{ $ad ? route('admin.sponsored-ads.update', $ad) : route('admin.sponsored-ads.store') }}"
              enctype="multipart/form-data"
              class="space-y-6">
            @csrf
            @if($ad) @method('PUT') @endif

            {{-- Validation errors --}}
            @if($errors->any())
                <div class="p-4 bg-red-50 border border-red-200 rounded-xl">
                    <ul class="text-sm text-red-600 space-y-1">
                        @foreach($errors->all() as $error)
                            <li>• {{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Basic Info --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-5">
                <h2 class="text-sm font-semibold text-slate-900 uppercase tracking-wide"
                    x-text="$store.lang.t('Ad Details', 'Détails de l\'annonce')"></h2>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"
                           x-text="$store.lang.t('Title *', 'Titre *')"></label>
                    <input type="text" name="title" value="{{ old('title', $ad?->title) }}" required maxlength="255"
                           class="w-full rounded-lg border-slate-300 focus:ring-cm-green focus:border-cm-green"
                           placeholder="e.g. Cameroon Business Hub – Grand Opening">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"
                           x-text="$store.lang.t('Description', 'Description')"></label>
                    <textarea name="description" rows="3" maxlength="1000"
                              class="w-full rounded-lg border-slate-300 focus:ring-cm-green focus:border-cm-green"
                              placeholder="Short description of the ad...">{{ old('description', $ad?->description) }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"
                           x-text="$store.lang.t('Advertiser Name', 'Nom de l\'annonceur')"></label>
                    <input type="text" name="advertiser_name" value="{{ old('advertiser_name', $ad?->advertiser_name) }}" maxlength="255"
                           class="w-full rounded-lg border-slate-300 focus:ring-cm-green focus:border-cm-green"
                           placeholder="e.g. MTN Cameroon">
                </div>

                <div x-data="{ preview: '{{ $ad?->imageUrl() ?? '' }}', useUrl: {{ ($ad?->image_url ? 'true' : 'false') }} }">
                    <label class="block text-sm font-medium text-slate-700 mb-1"
                           x-text="$store.lang.t('Ad Image', 'Image de l\'annonce')"></label>

                    {{-- Toggle: Upload vs URL --}}
                    <div class="flex gap-2 mb-3">
                        <button type="button" @click="useUrl = false"
                                class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors"
                                :class="!useUrl ? 'bg-cm-green text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                            📁 Upload File
                        </button>
                        <button type="button" @click="useUrl = true"
                                class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors"
                                :class="useUrl ? 'bg-cm-green text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                            🔗 Image URL
                        </button>
                    </div>

                    <div class="flex items-start gap-4">
                        <div class="w-32 h-32 rounded-lg border-2 border-dashed border-slate-300 overflow-hidden flex items-center justify-center bg-slate-50">
                            <template x-if="preview">
                                <img :src="preview" class="w-full h-full object-cover">
                            </template>
                            <template x-if="!preview">
                                <span class="text-slate-300 text-4xl">📷</span>
                            </template>
                        </div>
                        <div class="flex-1">
                            {{-- File upload --}}
                            <div x-show="!useUrl">
                                <input type="file" name="image" accept="image/*"
                                       @change="preview = URL.createObjectURL($event.target.files[0])"
                                       class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-cm-green file:text-white hover:file:bg-cm-green/90 file:cursor-pointer">
                                <p class="text-xs text-slate-400 mt-1">Max 2MB. JPG, PNG, or GIF.</p>
                            </div>
                            {{-- URL input --}}
                            <div x-show="useUrl">
                                <input type="url" name="image_url" value="{{ old('image_url', $ad?->image_url) }}"
                                       @input="preview = $event.target.value"
                                       class="w-full rounded-lg border-slate-300 focus:ring-cm-green focus:border-cm-green text-sm"
                                       placeholder="https://example.com/image.jpg">
                                <p class="text-xs text-slate-400 mt-1" x-text="$store.lang.t('Paste an image URL from the web', 'Collez un lien d\'image depuis le web')"></p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Video URL --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"
                           x-text="$store.lang.t('YouTube Video URL (optional)', 'URL Vidéo YouTube (optionnel)')"></label>
                    <input type="url" name="video_url" value="{{ old('video_url', $ad?->video_url) }}"
                           class="w-full rounded-lg border-slate-300 focus:ring-cm-green focus:border-cm-green text-sm"
                           placeholder="https://www.youtube.com/watch?v=...">
                    <p class="text-xs text-slate-400 mt-1" x-text="$store.lang.t('If set, video will be shown instead of image in the ad card', 'Si défini, la vidéo sera affichée à la place de l\'image')"></p>
                </div>
            </div>

            {{-- Link & CTA --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-5">
                <h2 class="text-sm font-semibold text-slate-900 uppercase tracking-wide"
                    x-text="$store.lang.t('Link & Call to Action', 'Lien & Appel à l\'action')"></h2>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">URL</label>
                    <input type="url" name="link_url" value="{{ old('link_url', $ad?->link_url) }}" maxlength="500"
                           class="w-full rounded-lg border-slate-300 focus:ring-cm-green focus:border-cm-green"
                           placeholder="https://example.com/offer">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"
                           x-text="$store.lang.t('Button Label', 'Texte du bouton')"></label>
                    <input type="text" name="link_label" value="{{ old('link_label', $ad?->link_label) }}" maxlength="100"
                           class="w-full rounded-lg border-slate-300 focus:ring-cm-green focus:border-cm-green"
                           placeholder="e.g. Learn More, Shop Now, Visit Site">
                </div>
            </div>

            {{-- Placement & Status --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-5">
                <h2 class="text-sm font-semibold text-slate-900 uppercase tracking-wide"
                    x-text="$store.lang.t('Placement & Settings', 'Emplacement & Paramètres')"></h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1"
                               x-text="$store.lang.t('Placement *', 'Emplacement *')"></label>
                        <select name="placement" required
                                class="w-full rounded-lg border-slate-300 focus:ring-cm-green focus:border-cm-green">
                            <option value="yard_sidebar" {{ old('placement', $ad?->placement) === 'yard_sidebar' ? 'selected' : '' }}>💬 Yard Sidebar</option>
                            <option value="home_banner" {{ old('placement', $ad?->placement) === 'home_banner' ? 'selected' : '' }}>🏠 Home Banner</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Status *</label>
                        <select name="status" required
                                class="w-full rounded-lg border-slate-300 focus:ring-cm-green focus:border-cm-green">
                            <option value="draft" {{ old('status', $ad?->status ?? 'draft') === 'draft' ? 'selected' : '' }}>📝 Draft</option>
                            <option value="active" {{ old('status', $ad?->status) === 'active' ? 'selected' : '' }}>✅ Active</option>
                            <option value="paused" {{ old('status', $ad?->status) === 'paused' ? 'selected' : '' }}>⏸ Paused</option>
                            @if($ad)
                            <option value="expired" {{ old('status', $ad?->status) === 'expired' ? 'selected' : '' }}>❌ Expired</option>
                            @endif
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1"
                               x-text="$store.lang.t('Priority (0-100)', 'Priorité (0-100)')"></label>
                        <input type="number" name="priority" value="{{ old('priority', $ad?->priority ?? 0) }}" min="0" max="100"
                               class="w-full rounded-lg border-slate-300 focus:ring-cm-green focus:border-cm-green">
                        <p class="text-xs text-slate-400 mt-1" x-text="$store.lang.t('Higher = shown first', 'Plus élevé = affiché en premier')"></p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1"
                               x-text="$store.lang.t('Budget (optional)', 'Budget (optionnel)')"></label>
                        <input type="number" name="budget" value="{{ old('budget', $ad?->budget) }}" min="0" step="0.01"
                               class="w-full rounded-lg border-slate-300 focus:ring-cm-green focus:border-cm-green"
                               placeholder="0.00">
                    </div>
                </div>
            </div>

            {{-- Schedule --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-5">
                <h2 class="text-sm font-semibold text-slate-900 uppercase tracking-wide"
                    x-text="$store.lang.t('Schedule', 'Planification')"></h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1"
                               x-text="$store.lang.t('Start Date', 'Date de début')"></label>
                        <input type="date" name="starts_at" value="{{ old('starts_at', $ad?->starts_at?->format('Y-m-d')) }}"
                               class="w-full rounded-lg border-slate-300 focus:ring-cm-green focus:border-cm-green">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1"
                               x-text="$store.lang.t('Expiry Date', 'Date d\'expiration')"></label>
                        <input type="date" name="expires_at" value="{{ old('expires_at', $ad?->expires_at?->format('Y-m-d')) }}"
                               class="w-full rounded-lg border-slate-300 focus:ring-cm-green focus:border-cm-green">
                    </div>
                </div>
            </div>

            {{-- Ad Stats (edit only) --}}
            @if($ad)
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <h2 class="text-sm font-semibold text-slate-900 uppercase tracking-wide mb-4">Performance</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="text-center p-3 rounded-lg bg-slate-50">
                        <p class="text-2xl font-bold text-slate-900">{{ number_format($ad->impressions) }}</p>
                        <p class="text-xs text-slate-500">Impressions</p>
                    </div>
                    <div class="text-center p-3 rounded-lg bg-slate-50">
                        <p class="text-2xl font-bold text-slate-900">{{ number_format($ad->clicks) }}</p>
                        <p class="text-xs text-slate-500">Clicks</p>
                    </div>
                    <div class="text-center p-3 rounded-lg bg-slate-50">
                        <p class="text-2xl font-bold text-slate-900">{{ number_format($ad->ctr(), 2) }}%</p>
                        <p class="text-xs text-slate-500">CTR</p>
                    </div>
                    <div class="text-center p-3 rounded-lg bg-slate-50">
                        <p class="text-2xl font-bold text-slate-900">${{ number_format($ad->spent, 2) }}</p>
                        <p class="text-xs text-slate-500">Spent</p>
                    </div>
                </div>
            </div>
            @endif

            {{-- Submit --}}
            <div class="flex items-center gap-3">
                <button type="submit"
                        class="px-6 py-2.5 bg-cm-green text-white text-sm font-medium rounded-lg hover:bg-cm-green/90 transition-colors shadow-sm">
                    <span x-text="$store.lang.t('{{ $ad ? 'Update Ad' : 'Create Ad' }}', '{{ $ad ? 'Mettre à jour' : 'Créer l\'annonce' }}')"></span>
                </button>
                <a href="{{ route('admin.sponsored-ads') }}"
                   class="px-6 py-2.5 text-slate-600 text-sm font-medium rounded-lg hover:bg-slate-100 transition-colors"
                   x-text="$store.lang.t('Cancel', 'Annuler')"></a>
            </div>
        </form>
    </div>
</x-layouts.admin>
