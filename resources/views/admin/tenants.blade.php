<x-layouts.admin :title="'Tenants'">
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold" x-text="$store.lang.t('Tenant Management', 'Gestion des Tenants')"></h1>
            <button class="px-4 py-2 bg-cm-green text-white text-sm font-medium rounded-lg hover:bg-cm-green/90">
                + <span x-text="$store.lang.t('New Tenant', 'Nouveau Tenant')"></span>
            </button>
        </div>

        {{-- Tenant Cards --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($tenants as $tenant)
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <h3 class="font-semibold text-slate-900">{{ $tenant->name }}</h3>
                        <p class="text-xs text-slate-400">{{ $tenant->domain ?? 'No domain' }}</p>
                    </div>
                    @if($tenant->is_primary)
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-cm-green/10 text-cm-green">Primary</span>
                    @else
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-500">Secondary</span>
                    @endif
                </div>

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Plan</span>
                        <span class="font-medium text-slate-700">{{ ucfirst($tenant->plan?->value ?? 'free') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Language</span>
                        <span class="font-medium text-slate-700">{{ strtoupper($tenant->default_language?->value ?? 'en') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Created</span>
                        <span class="text-slate-700">{{ $tenant->created_at->format('M d, Y') }}</span>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-100 flex gap-2">
                    <button class="flex-1 px-3 py-1.5 text-xs font-medium rounded-lg bg-cm-green/10 text-cm-green hover:bg-cm-green/20 text-center">
                        Edit
                    </button>
                    @unless($tenant->is_primary)
                        <button class="px-3 py-1.5 text-xs font-medium rounded-lg bg-red-50 text-red-500 hover:bg-red-100">
                            Delete
                        </button>
                    @endunless
                </div>
            </div>
            @empty
            <div class="col-span-full bg-white rounded-xl shadow-sm border border-slate-200 p-12 text-center">
                <p class="text-slate-400">No tenants configured.</p>
            </div>
            @endforelse
        </div>
    </div>
</x-layouts.admin>
