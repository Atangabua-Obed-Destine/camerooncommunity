{{--
    Delete account, at the bottom of Settings (the profile's dialog and the
    account page). What it does is App\Services\AccountDeletion: the account is
    anonymised, not wiped, so other people's conversations and orders stay whole.
--}}
@php($byPassword = auth()->user()->password && auth()->user()->google_id === null)
<div x-data="{ open: @js($errors->has('delete_confirm')) }" class="rounded-xl border border-red-200 bg-red-50/40 p-4">
    <button type="button" @click="open = ! open"
            class="flex w-full items-center justify-between text-left text-sm font-semibold text-red-700">
        <span x-text="$store.lang.t('Delete my account', 'Supprimer mon compte')">{{ \App\Support\Bilingual::t('Delete my account', 'Supprimer mon compte') }}</span>
        <svg class="h-4 w-4 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
    </button>

    <div x-show="open" x-cloak class="mt-3 space-y-3">
        <p class="text-xs leading-relaxed text-slate-600" x-text="$store.lang.t(
            'This can\'t be undone. Your profile, photos, location history, connections and listings are deleted. Messages you sent stay in other people\'s chats as “Deleted member”, and orders and reviews are kept without your name.',
            'C\'est définitif. Votre profil, vos photos, votre historique de localisation, vos connexions et vos annonces sont supprimés. Vos messages restent dans les discussions des autres sous « Membre supprimé », et les commandes et avis sont conservés sans votre nom.'
        )"></p>

        <form method="POST" action="{{ route('account.destroy') }}" class="space-y-2"
              @submit="if (! confirm($store.lang.t('Delete your account for good?', 'Supprimer votre compte définitivement ?'))) $event.preventDefault()">
            @csrf
            @method('DELETE')
            <label class="block text-xs font-medium text-slate-700"
                   x-text="{{ $byPassword ? "\$store.lang.t('Your password', 'Votre mot de passe')" : "\$store.lang.t('Type DELETE to confirm', 'Tapez DELETE pour confirmer')" }}"></label>
            <input name="delete_confirm" type="{{ $byPassword ? 'password' : 'text' }}" required
                   autocomplete="{{ $byPassword ? 'current-password' : 'off' }}"
                   class="w-full rounded-lg border-slate-300 px-3 py-2 text-sm focus:border-red-500 focus:ring-red-500">
            @error('delete_confirm') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            <button type="submit"
                    class="w-full rounded-lg bg-red-600 py-2.5 text-sm font-bold text-white hover:bg-red-700"
                    x-text="$store.lang.t('Delete my account', 'Supprimer mon compte')"></button>
        </form>
    </div>
</div>
