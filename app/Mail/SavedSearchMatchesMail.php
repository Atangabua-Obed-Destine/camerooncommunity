<?php

namespace App\Mail;

use App\Models\MarketplaceSavedSearch;
use App\Support\MarketplaceQueryBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class SavedSearchMatchesMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public MarketplaceSavedSearch $search,
        public Collection $matches,
    ) {}

    public function envelope(): Envelope
    {
        $count = $this->matches->count();
        $subject = trans_choice(
            '{1} 1 new match for ":name"|[2,*] :count new matches for ":name"',
            $count,
            ['count' => $count, 'name' => $this->search->name]
        );
        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.marketplace.saved-search-matches',
            with: [
                'search'      => $this->search,
                'matches'     => $this->matches,
                'summary'     => MarketplaceQueryBuilder::summarize(
                    is_array($this->search->filters) ? $this->search->filters : [],
                    app()->getLocale(),
                ),
                'matchesUrl'  => MarketplaceQueryBuilder::toUrl(
                    is_array($this->search->filters) ? $this->search->filters : [],
                ),
            ],
        );
    }
}
