<x-layouts.admin :title="'Settings'">
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold" x-text="$store.lang.t('Platform Settings', 'Paramètres de la Plateforme')"></h1>
        </div>

        @if(session('success'))
            <div class="p-3 bg-blue-50 text-blue-700 text-sm rounded-lg border border-blue-200">
                {{ session('success') }}
            </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════════
             BRANDING — Logo, Favicon, Site Name
        ═══════════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden"
             x-data="{
                 logoPreview: '{{ $branding['site_logo'] ? asset('storage/' . $branding['site_logo']) : '' }}',
                 faviconPreview: '{{ $branding['site_favicon'] ? asset('storage/' . $branding['site_favicon']) : '' }}',
                 removeLogo: false,
                 removeFavicon: false,
             }">
            <div class="px-5 py-3 bg-gradient-to-r from-cm-green/10 to-blue-50 border-b border-slate-200">
                <div class="flex items-center gap-2">
                    <span class="text-lg">🎨</span>
                    <h2 class="font-semibold text-slate-800" x-text="$store.lang.t('Branding & Identity', 'Marque & Identité')"></h2>
                </div>
                <p class="text-xs text-slate-500 mt-0.5" x-text="$store.lang.t('Customize your platform logo, favicon, and site name', 'Personnalisez le logo, le favicon et le nom du site')"></p>
            </div>

            <form method="POST" action="{{ route('admin.settings.branding') }}" enctype="multipart/form-data" class="p-5 space-y-6">
                @csrf

                @if($errors->any())
                <div class="p-3 bg-red-50 border border-red-200 rounded-lg">
                    <ul class="text-sm text-red-600 space-y-1">
                        @foreach($errors->all() as $error)
                            <li>• {{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                {{-- Site Name --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                    <div>
                        <label class="text-sm font-medium text-slate-700" x-text="$store.lang.t('Site Name', 'Nom du Site')"></label>
                        <p class="text-xs text-slate-400 mt-0.5" x-text="$store.lang.t('Appears in headers, titles, and emails', 'Affiché dans les en-têtes, titres et emails')"></p>
                    </div>
                    <div class="md:col-span-2">
                        <input type="text" name="site_name" value="{{ old('site_name', $branding['site_name']) }}" required maxlength="100"
                               class="w-full max-w-md rounded-lg border-slate-300 text-sm focus:ring-cm-green focus:border-cm-green"
                               placeholder="Cameroon Community">
                    </div>
                </div>

                {{-- Logo --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                    <div>
                        <label class="text-sm font-medium text-slate-700" x-text="$store.lang.t('Site Logo', 'Logo du Site')"></label>
                        <p class="text-xs text-slate-400 mt-0.5" x-text="$store.lang.t('Recommended: 200×200 px, PNG or SVG', 'Recommandé : 200×200 px, PNG ou SVG')"></p>
                    </div>
                    <div class="md:col-span-2">
                        <div class="flex items-start gap-5">
                            {{-- Preview --}}
                            <div class="w-20 h-20 rounded-xl border-2 border-dashed flex items-center justify-center bg-slate-50 overflow-hidden shrink-0 transition-all"
                                 :class="removeLogo ? 'border-red-300 bg-red-50' : 'border-slate-300'">
                                <template x-if="logoPreview && !removeLogo">
                                    <img :src="logoPreview" class="w-full h-full object-contain p-1">
                                </template>
                                <template x-if="!logoPreview || removeLogo">
                                    <span class="text-3xl">🇨🇲</span>
                                </template>
                            </div>
                            <div class="space-y-2">
                                <input type="file" name="site_logo" accept="image/*"
                                       @change="logoPreview = URL.createObjectURL($event.target.files[0]); removeLogo = false"
                                       class="text-sm text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-cm-green/10 file:text-cm-green hover:file:bg-cm-green/20 file:cursor-pointer">
                                <p class="text-xs text-slate-400" x-text="$store.lang.t('Max 2MB. PNG, JPG, SVG, or WebP.', 'Max 2 Mo. PNG, JPG, SVG ou WebP.')"></p>
                                @if($branding['site_logo'])
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="remove_logo" value="1"
                                           x-model="removeLogo"
                                           class="rounded border-slate-300 text-red-500 focus:ring-red-400">
                                    <span class="text-xs text-red-600" x-text="$store.lang.t('Remove current logo (revert to default)', 'Supprimer le logo actuel (retour au défaut)')"></span>
                                </label>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Favicon --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                    <div>
                        <label class="text-sm font-medium text-slate-700" x-text="$store.lang.t('Favicon', 'Favicon')"></label>
                        <p class="text-xs text-slate-400 mt-0.5" x-text="$store.lang.t('Browser tab icon. 32×32 or 64×64 px.', 'Icône de l\'onglet. 32×32 ou 64×64 px.')"></p>
                    </div>
                    <div class="md:col-span-2">
                        <div class="flex items-start gap-5">
                            {{-- Preview --}}
                            <div class="w-12 h-12 rounded-lg border-2 border-dashed flex items-center justify-center bg-slate-50 overflow-hidden shrink-0 transition-all"
                                 :class="removeFavicon ? 'border-red-300 bg-red-50' : 'border-slate-300'">
                                <template x-if="faviconPreview && !removeFavicon">
                                    <img :src="faviconPreview" class="w-full h-full object-contain p-0.5">
                                </template>
                                <template x-if="!faviconPreview || removeFavicon">
                                    <span class="text-lg">🇨🇲</span>
                                </template>
                            </div>
                            <div class="space-y-2">
                                <input type="file" name="site_favicon" accept="image/png,image/x-icon,image/svg+xml,image/jpeg"
                                       @change="faviconPreview = URL.createObjectURL($event.target.files[0]); removeFavicon = false"
                                       class="text-sm text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-cm-green/10 file:text-cm-green hover:file:bg-cm-green/20 file:cursor-pointer">
                                <p class="text-xs text-slate-400" x-text="$store.lang.t('Max 512KB. PNG, ICO, or SVG.', 'Max 512 Ko. PNG, ICO ou SVG.')"></p>
                                @if($branding['site_favicon'])
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="remove_favicon" value="1"
                                           x-model="removeFavicon"
                                           class="rounded border-slate-300 text-red-500 focus:ring-red-400">
                                    <span class="text-xs text-red-600" x-text="$store.lang.t('Remove favicon (revert to default)', 'Supprimer le favicon (retour au défaut)')"></span>
                                </label>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Live Preview --}}
                <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3" x-text="$store.lang.t('Preview', 'Aperçu')"></p>
                    <div class="flex items-center gap-3 bg-white rounded-lg border border-slate-200 px-4 py-3 shadow-sm">
                        <div class="w-9 h-9 rounded-full bg-cm-green/10 flex items-center justify-center overflow-hidden">
                            <template x-if="logoPreview && !removeLogo">
                                <img :src="logoPreview" class="w-7 h-7 object-contain">
                            </template>
                            <template x-if="!logoPreview || removeLogo">
                                <span class="text-lg">🇨🇲</span>
                            </template>
                        </div>
                        <span class="text-lg font-bold text-cm-green" x-text="$refs.siteNameInput?.value || '{{ $branding['site_name'] }}'"></span>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-2" x-text="$store.lang.t('This is how the header will appear across the platform', 'Voici comment l\'en-tête apparaîtra sur la plateforme')"></p>
                </div>

                {{-- Save --}}
                <div class="flex justify-end pt-2">
                    <button type="submit" class="px-6 py-2.5 bg-cm-green text-white font-medium rounded-lg hover:bg-cm-green/90 transition-colors">
                        <span x-text="$store.lang.t('Save Branding', 'Enregistrer la Marque')"></span>
                    </button>
                </div>
            </form>
        </div>

        {{-- Location Detection Mode (Test Setting) --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-6">
            <div class="px-5 py-3 bg-amber-50 border-b border-amber-200">
                <div class="flex items-center gap-2">
                    <span class="text-lg">🧪</span>
                    <h2 class="font-semibold text-amber-800">Testing / Development</h2>
                </div>
            </div>
            <div class="p-5 space-y-4">
                <form method="POST" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                        <div>
                            <label class="text-sm font-medium text-slate-700">Location Detection Mode</label>
                            <p class="text-xs text-slate-400 mt-0.5">GPS = real device location. IP = uses your IP address (works with VPN for testing different countries).</p>
                        </div>
                        <div class="md:col-span-2 flex items-center gap-4">
                            @php $locMode = \App\Models\PlatformSetting::getValue('location_detection_mode', 'gps'); @endphp
                            <select name="settings[location_detection_mode]" class="w-full max-w-xs rounded-lg border-slate-300 text-sm focus:ring-cm-green focus:border-cm-green">
                                <option value="gps" {{ $locMode === 'gps' ? 'selected' : '' }}>📡 GPS (Browser Geolocation)</option>
                                <option value="ip" {{ $locMode === 'ip' ? 'selected' : '' }}>🌐 IP Address (VPN-friendly)</option>
                            </select>
                            <button type="submit" class="px-4 py-2 bg-amber-500 text-white text-sm font-semibold rounded-lg hover:bg-amber-600 transition-colors whitespace-nowrap">Save</button>
                        </div>
                    </div>
                </form>
                @if($locMode === 'ip')
                <div class="flex items-center gap-2 text-xs text-amber-600 bg-amber-50 rounded-lg px-3 py-2 border border-amber-200">
                    <span>⚠️</span>
                    <span>IP mode is active — connect a VPN to simulate different countries. Location will be detected from your public IP address.</span>
                </div>
                @endif
            </div>
        </div>

        {{-- Email Verification --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-6">
            <div class="px-5 py-3 bg-slate-50 border-b border-slate-200">
                <div class="flex items-center gap-2">
                    <span class="text-lg">✉️</span>
                    <h2 class="font-semibold text-slate-700">Authentication</h2>
                </div>
            </div>
            <div class="p-5 space-y-4">
                <form method="POST" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                        <div>
                            <label class="text-sm font-medium text-slate-700">Require Email Verification</label>
                            <p class="text-xs text-slate-400 mt-0.5">When enabled, new users must click a link sent to their email before they can access the platform. When disabled, accounts are activated instantly on registration.</p>
                        </div>
                        <div class="md:col-span-2 flex items-center gap-4">
                            @php $requireVerify = (string) \App\Models\PlatformSetting::getValue('require_email_verification', '0') === '1'; @endphp
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="settings[require_email_verification]" value="0">
                                <input type="checkbox" name="settings[require_email_verification]" value="1"
                                       {{ $requireVerify ? 'checked' : '' }}
                                       class="sr-only peer">
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-cm-green rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-slate-300 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cm-green"></div>
                                <span class="ml-3 text-sm text-slate-600">{{ $requireVerify ? 'Enabled' : 'Disabled' }}</span>
                            </label>
                            <button type="submit" class="px-4 py-2 bg-cm-green text-white text-sm font-semibold rounded-lg hover:bg-cm-green/90 transition-colors whitespace-nowrap">Save</button>
                        </div>
                    </div>
                </form>
                @if(! $requireVerify)
                <div class="flex items-center gap-2 text-xs text-slate-500 bg-slate-50 rounded-lg px-3 py-2 border border-slate-200">
                    <span>ℹ️</span>
                    <span>Email verification is currently <strong>disabled</strong>. New registrations are auto-verified and can access the platform immediately.</span>
                </div>
                @else
                <div class="flex items-center gap-2 text-xs text-emerald-700 bg-emerald-50 rounded-lg px-3 py-2 border border-emerald-200">
                    <span>🔒</span>
                    <span>Email verification is currently <strong>required</strong>. New users will be redirected to a verification notice until they confirm their email.</span>
                </div>
                @endif
            </div>
        </div>

        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf

            @forelse($settings as $group => $items)
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-4">
                <div class="px-5 py-3 bg-slate-50 border-b border-slate-200">
                    <h2 class="font-semibold text-slate-700">{{ ucfirst($group) }}</h2>
                </div>
                <div class="p-5 space-y-4">
                    @foreach($items as $setting)
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                        <div>
                            <label class="text-sm font-medium text-slate-700">{{ str_replace('_', ' ', ucfirst($setting->key)) }}</label>
                            @if($setting->description)
                                <p class="text-xs text-slate-400 mt-0.5">{{ $setting->description }}</p>
                            @endif
                        </div>
                        <div class="md:col-span-2">
                            @if($setting->type === 'boolean')
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="hidden" name="settings[{{ $setting->key }}]" value="0">
                                    <input type="checkbox" name="settings[{{ $setting->key }}]" value="1"
                                           {{ $setting->value ? 'checked' : '' }}
                                           class="sr-only peer">
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-cm-green rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-slate-300 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cm-green"></div>
                                </label>
                            @elseif($setting->type === 'number')
                                <input type="number" name="settings[{{ $setting->key }}]" value="{{ $setting->value }}"
                                       class="w-full max-w-xs rounded-lg border-slate-300 text-sm focus:ring-cm-green focus:border-cm-green">
                            @elseif($setting->type === 'text' && strlen($setting->value ?? '') > 100)
                                <textarea name="settings[{{ $setting->key }}]" rows="3"
                                          class="w-full rounded-lg border-slate-300 text-sm focus:ring-cm-green focus:border-cm-green">{{ $setting->value }}</textarea>
                            @else
                                <input type="text" name="settings[{{ $setting->key }}]" value="{{ $setting->value }}"
                                       class="w-full max-w-md rounded-lg border-slate-300 text-sm focus:ring-cm-green focus:border-cm-green">
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @empty
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-12 text-center">
                <p class="text-slate-400">No settings configured.</p>
            </div>
            @endforelse

            @if($settings->count())
                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-2.5 bg-cm-green text-white font-medium rounded-lg hover:bg-cm-green/90 transition-colors">
                        <span x-text="$store.lang.t('Save Settings', 'Enregistrer les Paramètres')"></span>
                    </button>
                </div>
            @endif
        </form>
    </div>
</x-layouts.admin>
