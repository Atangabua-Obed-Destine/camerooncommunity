<x-layouts.admin :title="'CMS'">
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold" x-text="$store.lang.t('Content Management', 'Gestion de Contenu')"></h1>
            <button class="px-4 py-2 bg-cm-green text-white text-sm font-medium rounded-lg hover:bg-cm-green/90 transition-colors"
                    x-data @click="$dispatch('open-page-editor')">
                + <span x-text="$store.lang.t('New Page', 'Nouvelle Page')"></span>
            </button>
        </div>

        {{-- Pages Table --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-left">
                            <th class="px-4 py-3 font-semibold text-slate-600">Page Title</th>
                            <th class="px-4 py-3 font-semibold text-slate-600">Slug</th>
                            <th class="px-4 py-3 font-semibold text-slate-600">Status</th>
                            <th class="px-4 py-3 font-semibold text-slate-600">Updated</th>
                            <th class="px-4 py-3 font-semibold text-slate-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($pages as $page)
                        <tr class="hover:bg-slate-50/50">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ $page->title }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-500 font-mono text-xs">/{{ $page->slug }}</td>
                            <td class="px-4 py-3">
                                @if($page->is_published)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">Published</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">Draft</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-500 text-xs">{{ $page->updated_at->diffForHumans() }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    <button class="px-2 py-1 text-xs rounded bg-cm-green/10 text-cm-green hover:bg-cm-green/20 font-medium">Edit</button>
                                    <button class="px-2 py-1 text-xs rounded bg-red-50 text-red-500 hover:bg-red-100">Delete</button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center">
                                <div class="text-4xl mb-3">📝</div>
                                <p class="text-slate-400 font-medium" x-text="$store.lang.t('No pages yet', 'Aucune page encore')"></p>
                                <p class="text-xs text-slate-400 mt-1" x-text="$store.lang.t('Create your first CMS page.', 'Créez votre première page CMS.')"></p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Announcements Section --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-slate-900" x-text="$store.lang.t('Announcements', 'Annonces')"></h2>
                <button class="text-sm text-cm-green hover:underline">+ New</button>
            </div>
            <div class="text-center py-8 text-slate-400">
                <p class="text-sm" x-text="$store.lang.t('Announcement management coming soon', 'Gestion des annonces bientôt disponible')"></p>
            </div>
        </div>
    </div>
</x-layouts.admin>
