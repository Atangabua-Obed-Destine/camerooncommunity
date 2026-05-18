<?php

namespace App\Livewire\Marketplace;

use App\Models\MarketplaceListing;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class MyListings extends Component
{
    public string $filter = 'all'; // all|active|sold|expired|draft|paused

    public function setFilter(string $f): void
    {
        $this->filter = $f;
    }

    #[Computed]
    public function listings()
    {
        $q = MarketplaceListing::withTrashed()
            ->where('user_id', Auth::id())
            ->with(['category', 'media' => fn ($m) => $m->limit(1)])
            ->orderByDesc('updated_at');

        if ($this->filter !== 'all') {
            $q->where('status', $this->filter);
        }
        return $q->paginate(20);
    }

    public function pause(int $id): void
    {
        $l = MarketplaceListing::where('user_id', Auth::id())->find($id);
        if ($l) { $l->update(['status' => 'paused']); }
    }
    public function reactivate(int $id): void
    {
        $l = MarketplaceListing::where('user_id', Auth::id())->find($id);
        if ($l) { $l->update(['status' => 'active', 'published_at' => $l->published_at ?? now()]); }
    }
    public function markSold(int $id): void
    {
        $l = MarketplaceListing::where('user_id', Auth::id())->find($id);
        if ($l) { $l->update(['status' => 'sold', 'sold_at' => now()]); }
    }
    public function remove(int $id): void
    {
        $l = MarketplaceListing::where('user_id', Auth::id())->find($id);
        if ($l) { $l->update(['status' => 'removed']); $l->delete(); }
    }

    public function render()
    {
        return view('livewire.marketplace.my-listings');
    }
}
