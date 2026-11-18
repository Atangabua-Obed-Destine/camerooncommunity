<?php

namespace App\Services;

use App\Enums\OfferStatus;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceOffer;
use App\Models\User;

/**
 * Everything that changes an offer, for the listing page, the Offers screen and
 * (later) the app's API, so the three can't disagree again.
 *
 * The negotiation lives on one row:
 *
 *   pending    the buyer offered `amount`; the seller accepts, rejects or counters
 *   countered  the seller answered with `counter_amount`; now the buyer accepts it
 *              (the price becomes `amount`) or declines it
 *   accepted / rejected / withdrawn / expired   closed
 *
 * A counter used to create a second offer, owned by the seller, which landed in
 * the seller's own Received tab and which the buyer had no way to accept.
 *
 * Each step tells the other side, in their language. Every method answers
 * whether it did anything: false means the offer wasn't in a state that allows
 * it, or wasn't this member's to act on.
 */
class OfferService
{
    public function __construct(private NotificationService $notifier) {}

    public function make(MarketplaceListing $listing, User $buyer, float $amount, ?string $message = null): MarketplaceOffer
    {
        // One open offer per buyer per listing: a new one replaces the last.
        MarketplaceOffer::where('listing_id', $listing->id)
            ->where('buyer_id', $buyer->id)
            ->whereIn('status', [OfferStatus::Pending, OfferStatus::Countered])
            ->update(['status' => OfferStatus::Withdrawn, 'responded_at' => now()]);

        $offer = MarketplaceOffer::create([
            'listing_id' => $listing->id,
            'buyer_id'   => $buyer->id,
            'seller_id'  => $listing->user_id,
            'amount'     => $amount,
            'currency'   => $listing->currency,
            'message'    => $message ?: null,
            'status'     => OfferStatus::Pending,
        ]);

        $this->tell($offer, $offer->seller, 'marketplace.offer_received', 'received',
            [':name offered :amount', ':name propose :amount'],
            ['For ":title". Accept, counter or decline.', 'Pour « :title ». Acceptez, faites une contre-offre ou refusez.'],
            $buyer);

        return $offer;
    }

    public function withdraw(MarketplaceOffer $offer, User $buyer): bool
    {
        if ((int) $offer->buyer_id !== (int) $buyer->id || ! $offer->status->isOpen()) {
            return false;
        }

        $offer->update(['status' => OfferStatus::Withdrawn, 'responded_at' => now()]);

        $this->tell($offer, $offer->seller, 'marketplace.offer_withdrawn', 'received',
            [':name withdrew their offer', ':name a retiré son offre'],
            ['On ":title".', 'Sur « :title ».'],
            $buyer);

        return true;
    }

    public function accept(MarketplaceOffer $offer, User $seller): bool
    {
        if ((int) $offer->seller_id !== (int) $seller->id || $offer->status !== OfferStatus::Pending) {
            return false;
        }

        $this->close($offer, OfferStatus::Accepted, $offer->amount);

        $this->tell($offer, $offer->buyer, 'marketplace.offer_accepted', 'sent',
            ['Your offer was accepted', 'Votre offre a été acceptée'],
            [':amount for ":title". Arrange payment with the seller.', ':amount pour « :title ». Organisez le paiement avec le vendeur.'],
            $seller);

        return true;
    }

    public function reject(MarketplaceOffer $offer, User $seller): bool
    {
        if ((int) $offer->seller_id !== (int) $seller->id || ! $offer->status->isOpen()) {
            return false;
        }

        $offer->update(['status' => OfferStatus::Rejected, 'responded_at' => now()]);

        $this->tell($offer, $offer->buyer, 'marketplace.offer_rejected', 'sent',
            ['Your offer was declined', 'Votre offre a été refusée'],
            ['On ":title".', 'Sur « :title ».'],
            $seller);

        return true;
    }

    public function counter(MarketplaceOffer $offer, User $seller, float $amount): bool
    {
        if ((int) $offer->seller_id !== (int) $seller->id || $offer->status !== OfferStatus::Pending) {
            return false;
        }

        $offer->update([
            'status'         => OfferStatus::Countered,
            'counter_amount' => $amount,
            'responded_at'   => now(),
            'expires_at'     => now()->addDays(7),
        ]);

        $this->tell($offer, $offer->buyer, 'marketplace.offer_countered', 'sent',
            [':name countered with :counter', ':name propose :counter'],
            ['For ":title". Accept it or decline it on your Offers page.', 'Pour « :title ». Acceptez ou refusez depuis vos offres.'],
            $seller);

        return true;
    }

    public function acceptCounter(MarketplaceOffer $offer, User $buyer): bool
    {
        if ((int) $offer->buyer_id !== (int) $buyer->id || $offer->status !== OfferStatus::Countered || ! $offer->counter_amount) {
            return false;
        }

        $this->close($offer, OfferStatus::Accepted, $offer->counter_amount);

        $this->tell($offer, $offer->seller, 'marketplace.counter_accepted', 'received',
            [':name accepted your counter-offer', ':name a accepté votre contre-offre'],
            [':amount for ":title". Arrange payment with the buyer.', ':amount pour « :title ». Organisez le paiement avec l\'acheteur.'],
            $buyer);

        return true;
    }

    public function declineCounter(MarketplaceOffer $offer, User $buyer): bool
    {
        if ((int) $offer->buyer_id !== (int) $buyer->id || $offer->status !== OfferStatus::Countered) {
            return false;
        }

        $offer->update(['status' => OfferStatus::Rejected, 'responded_at' => now()]);

        $this->tell($offer, $offer->seller, 'marketplace.counter_declined', 'received',
            [':name declined your counter-offer', ':name a refusé votre contre-offre'],
            ['On ":title".', 'Sur « :title ».'],
            $buyer);

        return true;
    }

    /** Accept at $price, and close every other open offer on the listing. */
    private function close(MarketplaceOffer $offer, OfferStatus $status, $price): void
    {
        MarketplaceOffer::where('listing_id', $offer->listing_id)
            ->where('id', '!=', $offer->id)
            ->whereIn('status', [OfferStatus::Pending, OfferStatus::Countered])
            ->update(['status' => OfferStatus::Rejected, 'responded_at' => now()]);

        $offer->update(['status' => $status, 'amount' => $price, 'responded_at' => now()]);
    }

    /**
     * @param  array{0: string, 1: string}  $title  English, French
     * @param  array{0: string, 1: string}  $body   English, French
     */
    private function tell(MarketplaceOffer $offer, ?User $to, string $type, string $tab, array $title, array $body, User $from): void
    {
        if (! $to) {
            return;
        }

        $fr = ($to->language_pref?->value ?? $to->language_pref) === 'fr';
        $offer->loadMissing('listing');
        $vars = [
            ':name'    => $from->name ?: $from->username,
            ':amount'  => $offer->formattedAmount(),
            ':counter' => number_format((float) $offer->counter_amount, 0, '.', ',') . ' ' . $offer->currency,
            ':title'   => $offer->listing?->title ?? '',
        ];

        $this->notifier->send(
            $to,
            $type,
            strtr($title[$fr ? 1 : 0], $vars),
            strtr($body[$fr ? 1 : 0], $vars),
            [
                'offer_id'   => $offer->id,
                'listing_id' => $offer->listing_id,
                'url'        => route('marketplace.offers', ['tab' => $tab]),
            ],
        );
    }
}
