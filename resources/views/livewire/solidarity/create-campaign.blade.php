<div>
    {{-- Slide-over panel --}}
    <div x-data="{ open: @entangle('showPanel') }"
         x-show="open" x-cloak
         class="fixed inset-0 z-50">
        {{-- Backdrop --}}
        <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="absolute inset-0 bg-black/40" @click="$wire.close()"></div>

        {{-- Panel --}}
        <div x-show="open" x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
             class="absolute right-0 inset-y-0 w-full max-w-lg bg-white shadow-2xl overflow-y-auto">

            {{-- Header --}}
            <div class="sticky top-0 bg-white border-b border-slate-200 px-6 py-4 flex items-center gap-3 z-10">
                <button wire:click="close" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <div>
                    <h2 class="font-bold text-slate-900" x-text="$store.lang.t('Start a Solidarity Campaign', 'Lancer une Campagne de Solidarité')"></h2>
                    <p class="text-xs text-slate-500" x-text="$store.lang.t('Step ' + {{ $step }} + ' of 2', 'Étape ' + {{ $step }} + ' sur 2')"></p>
                </div>
            </div>

            <div class="p-6">
                {{-- Step 1 --}}
                @if($step === 1)
                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1" x-text="$store.lang.t('Campaign Title *', 'Titre de la Campagne *')"></label>
                        <input type="text" wire:model="title"
                               :placeholder="$store.lang.t('In memory of... / Support for...', 'En mémoire de... / Soutien pour...')"
                               class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-cm-green focus:ring-1 focus:ring-cm-green">
                        @error('title') <p class="mt-1 text-xs text-cm-red">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1" x-text="$store.lang.t('Category *', 'Catégorie *')"></label>
                        <select wire:model="category" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-cm-green focus:ring-1 focus:ring-cm-green">
                            <option value="" x-text="$store.lang.t('Select category...', 'Sélectionner la catégorie...')"></option>
                            @foreach($categories as $cat)
                            <option value="{{ $cat->value }}">{{ $cat->icon() }} {{ $cat->label() }}</option>
                            @endforeach
                        </select>
                        @error('category') <p class="mt-1 text-xs text-cm-red">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1" x-text="$store.lang.t('Beneficiary Full Name *', 'Nom Complet du Bénéficiaire *')"></label>
                        <input type="text" wire:model="beneficiaryName"
                               class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-cm-green focus:ring-1 focus:ring-cm-green">
                        @error('beneficiaryName') <p class="mt-1 text-xs text-cm-red">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1" x-text="$store.lang.t('Relationship to Community *', 'Lien avec la Communauté *')"></label>
                        <input type="text" wire:model="beneficiaryRelationship"
                               :placeholder="$store.lang.t('How does this person connect to the community?', 'Comment cette personne est-elle liée à la communauté ?')"
                               class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-cm-green focus:ring-1 focus:ring-cm-green">
                        @error('beneficiaryRelationship') <p class="mt-1 text-xs text-cm-red">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1" x-text="$store.lang.t('Full Description *', 'Description Complète *')"></label>
                        <textarea wire:model="description" rows="5"
                                  :placeholder="$store.lang.t('What happened, why the community should support, how funds will be used...', 'Que s\\'est-il passé, pourquoi la communauté devrait soutenir, comment les fonds seront utilisés...')"
                                  class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-cm-green focus:ring-1 focus:ring-cm-green resize-none"></textarea>
                        @error('description') <p class="mt-1 text-xs text-cm-red">{{ $message }}</p> @enderror

                        {{-- AI Help me write --}}
                        @if(app(\App\Services\AIService::class)->isAvailable())
                        <div class="mt-2" x-data="{ showDraft: false }">
                            <button type="button" @click="showDraft = !showDraft"
                                    class="text-xs text-cm-green hover:underline flex items-center gap-1">
                                🤖 <span x-text="$store.lang.t('Help me write this', 'Aide-moi à écrire')"></span>
                            </button>
                            <div x-show="showDraft" x-transition class="mt-2 p-3 bg-cm-green/5 rounded-lg border border-cm-green/10">
                                <p class="text-xs text-slate-500 mb-2" x-text="$store.lang.t('Briefly describe the situation — Kamer AI will draft a description for you.', 'Décrivez brièvement la situation — Kamer IA rédigera une description pour vous.')"></p>
                                <textarea wire:model="draftSituation" rows="2"
                                          class="w-full rounded-lg border-slate-300 text-xs focus:ring-cm-green focus:border-cm-green"
                                          :placeholder="$store.lang.t('e.g. My uncle passed away and the family needs help with funeral costs...', 'ex. Mon oncle est décédé et la famille a besoin d\\'aide pour les frais funéraires...')"></textarea>
                                <button type="button" wire:click="helpMeWrite"
                                        class="mt-2 px-3 py-1.5 bg-cm-green text-white text-xs rounded-lg hover:bg-cm-green/90 disabled:opacity-50"
                                        wire:loading.attr="disabled" wire:target="helpMeWrite">
                                    <span wire:loading.remove wire:target="helpMeWrite" x-text="$store.lang.t('✨ Generate Draft', '✨ Générer le Brouillon')"></span>
                                    <span wire:loading wire:target="helpMeWrite" x-text="$store.lang.t('Writing...', 'Rédaction...')"></span>
                                </button>
                            </div>
                        </div>
                        @endif
                    </div>

                    <button wire:click="nextStep" class="w-full rounded-xl bg-cm-green py-3.5 text-sm font-bold text-white transition-colors hover:bg-cm-green-light">
                        <span x-text="$store.lang.t('Next →', 'Suivant →')"></span>
                    </button>
                </div>
                @endif

                {{-- Step 2 --}}
                @if($step === 2)
                <div class="space-y-5">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1" x-text="$store.lang.t('Target Amount *', 'Montant Cible *')"></label>
                            <input type="number" wire:model="targetAmount" min="1" step="0.01"
                                   class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-cm-green focus:ring-1 focus:ring-cm-green">
                            @error('targetAmount') <p class="mt-1 text-xs text-cm-red">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1" x-text="$store.lang.t('Currency *', 'Devise *')"></label>
                            <select wire:model="currency" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-cm-green focus:ring-1 focus:ring-cm-green">
                                <option value="GBP">🇬🇧 GBP (£)</option>
                                <option value="EUR">🇪🇺 EUR (€)</option>
                                <option value="USD">🇺🇸 USD ($)</option>
                                <option value="XAF">🇨🇲 XAF (FCFA)</option>
                                <option value="CAD">🇨🇦 CAD (C$)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1" x-text="$store.lang.t('Campaign Deadline (optional)', 'Date Limite (optionnel)')"></label>
                        <input type="date" wire:model="deadline" min="{{ now()->addDay()->format('Y-m-d') }}"
                               class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-cm-green focus:ring-1 focus:ring-cm-green">
                        @error('deadline') <p class="mt-1 text-xs text-cm-red">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1" x-text="$store.lang.t('Upload Proof Document', 'Télécharger Document Justificatif')"></label>
                        <p class="text-xs text-slate-400 mb-2" x-text="$store.lang.t('Strongly recommended: death certificate, hospital letter, etc.', 'Fortement recommandé : certificat de décès, lettre d\\'hôpital, etc.')"></p>
                        <input type="file" wire:model="proofDocument" accept=".pdf,.jpg,.jpeg,.png,.webp"
                               class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-cm-green/10 file:text-cm-green hover:file:bg-cm-green/20">
                        @error('proofDocument') <p class="mt-1 text-xs text-cm-red">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center gap-3">
                        <button wire:click="$toggle('isAnonymousAllowed')"
                                class="relative w-11 h-6 rounded-full transition-colors {{ $isAnonymousAllowed ? 'bg-cm-green' : 'bg-slate-300' }}"
                                type="button">
                            <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform {{ $isAnonymousAllowed ? 'translate-x-5' : '' }}"></span>
                        </button>
                        <span class="text-sm text-slate-700" x-text="$store.lang.t('Allow Anonymous Contributions', 'Permettre les Contributions Anonymes')"></span>
                    </div>

                    {{-- Fee breakdown --}}
                    <div class="bg-slate-50 rounded-xl p-4 border border-slate-200">
                        <p class="text-xs font-medium text-slate-500 mb-1" x-text="$store.lang.t('Platform Fee Information', 'Information sur les Frais')"></p>
                        <p class="text-sm text-slate-700">
                            <span x-text="$store.lang.t('Platform fee: 5%', 'Frais plateforme : 5%')"></span>
                            <br>
                            <span class="text-xs text-slate-500" x-text="$store.lang.t(
                                'For every £100 contributed, £95 goes to the beneficiary\\'s family.',
                                'Pour chaque 100£ contribué, 95£ vont à la famille du bénéficiaire.'
                            )"></span>
                        </p>
                    </div>

                    <div class="flex gap-3">
                        <button wire:click="$set('step', 1)" class="flex-1 rounded-xl border border-slate-300 py-3.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50">
                            <span x-text="$store.lang.t('← Back', '← Retour')"></span>
                        </button>
                        <button wire:click="submit" class="flex-1 rounded-xl bg-cm-green py-3.5 text-sm font-bold text-white transition-colors hover:bg-cm-green-light"
                                wire:loading.attr="disabled" wire:loading.class="opacity-50">
                            <span wire:loading.remove x-text="$store.lang.t('Submit for Approval', 'Soumettre pour Approbation')"></span>
                            <span wire:loading class="flex items-center justify-center gap-2">
                                <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" class="opacity-25"/><path fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 100 8v4a8 8 0 01-8-8z" class="opacity-75"/></svg>
                                <span x-text="$store.lang.t('Submitting...', 'Soumission...')"></span>
                            </span>
                        </button>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
