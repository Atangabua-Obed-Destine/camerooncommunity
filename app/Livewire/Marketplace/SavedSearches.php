<?php

namespace App\Livewire\Marketplace;

use App\Models\MarketplaceSavedSearch;
use App\Support\MarketplaceQueryBuilder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.marketplace', ['active' => 'marketplace'])]
#[Title('Saved Searches')]
class SavedSearches extends Component
{
    public ?int $editingId = null;
    public string $editName = '';

    /**
     * All saved searches for the current user, decorated with:
     *   - matches_url  : full /marketplace URL that reproduces the search
     *   - summary      : human readable filter blurb (locale-aware)
     *   - new_matches  : count of listings published since last_notified_at
     */
    #[Computed]
    public function searches()
    {
        $user = auth()->user();
        if (! $user) { return collect(); }

        $rows = MarketplaceSavedSearch::where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->get();

        $locale = app()->getLocale();
        return $rows->map(function (MarketplaceSavedSearch $s) use ($locale) {
            $filters = is_array($s->filters) ? $s->filters : [];
            $since = $s->last_notified_at ?? $s->created_at;
            $newMatches = MarketplaceQueryBuilder::build($filters)
                ->where('published_at', '>', $since)
                ->count();
            $s->setAttribute('matches_url', MarketplaceQueryBuilder::toUrl($filters));
            $s->setAttribute('summary', MarketplaceQueryBuilder::summarize($filters, $locale));
            $s->setAttribute('new_matches', $newMatches);
            return $s;
        });
    }

    /** Total "new since last notified" — surfaced as a sidebar badge. */
    #[Computed]
    public function totalNewMatches(): int
    {
        return (int) $this->searches->sum('new_matches');
    }

    public function startRename(int $id): void
    {
        $s = $this->ownedSearch($id);
        if (! $s) { return; }
        $this->editingId = $id;
        $this->editName = (string) $s->name;
    }

    public function cancelRename(): void
    {
        $this->editingId = null;
        $this->editName = '';
    }

    public function saveRename(): void
    {
        if (! $this->editingId) { return; }
        $s = $this->ownedSearch($this->editingId);
        if (! $s) { return; }

        $name = trim($this->editName);
        if ($name === '') {
            $this->dispatch('toast', type: 'error', message: __('Name cannot be empty'));
            return;
        }
        $s->update(['name' => mb_substr($name, 0, 120)]);
        $this->cancelRename();
        unset($this->searches);
        $this->dispatch('toast', type: 'success', message: __('Renamed'));
    }

    public function toggleEmail(int $id): void
    {
        $s = $this->ownedSearch($id);
        if (! $s) { return; }
        $s->update(['notify_email' => ! $s->notify_email]);
        unset($this->searches);
    }

    public function togglePush(int $id): void
    {
        $s = $this->ownedSearch($id);
        if (! $s) { return; }
        $s->update(['notify_push' => ! $s->notify_push]);
        unset($this->searches);
    }

    public function delete(int $id): void
    {
        $s = $this->ownedSearch($id);
        if (! $s) { return; }
        $s->delete();
        unset($this->searches, $this->totalNewMatches);
        $this->dispatch('toast', type: 'success', message: __('Saved search deleted'));
        $this->dispatch('savedSearchesUpdated');
    }

    /** Mark a single search as seen (resets new_matches to 0). */
    public function markSeen(int $id): void
    {
        $s = $this->ownedSearch($id);
        if (! $s) { return; }
        $s->update(['last_notified_at' => now()]);
        unset($this->searches, $this->totalNewMatches);
        $this->dispatch('savedSearchesUpdated');
    }

    public function markAllSeen(): void
    {
        MarketplaceSavedSearch::where('user_id', auth()->id())
            ->update(['last_notified_at' => now()]);
        unset($this->searches, $this->totalNewMatches);
        $this->dispatch('toast', type: 'success', message: __('All caught up'));
        $this->dispatch('savedSearchesUpdated');
    }

    #[On('savedSearchesUpdated')]
    public function bust(): void
    {
        unset($this->searches, $this->totalNewMatches);
    }

    protected function ownedSearch(int $id): ?MarketplaceSavedSearch
    {
        return MarketplaceSavedSearch::where('id', $id)
            ->where('user_id', auth()->id())
            ->first();
    }

    public function render()
    {
        return view('livewire.marketplace.saved-searches');
    }
}

