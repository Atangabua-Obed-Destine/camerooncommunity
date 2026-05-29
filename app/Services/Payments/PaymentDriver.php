<?php

namespace App\Services\Payments;

use App\Models\MarketplaceOrder;

/**
 * Contract for any marketplace payment driver.
 *
 * Drivers describe how a buyer should pay and what receipt evidence we should
 * collect. The Manual driver (Phase 5 default) simply renders MoMo/OM transfer
 * instructions to the buyer and asks them to paste the operator reference; a
 * future MTN MoMo driver would call the Collections API and poll status.
 */
interface PaymentDriver
{
    /** Stable identifier persisted on marketplace_orders.provider. */
    public function id(): string;

    /** Bilingual short label. */
    public function label(string $locale = 'en'): string;

    /** Tailwind-friendly icon/emoji for buttons. */
    public function icon(): string;

    /**
     * Can this driver be offered for a given order?
     * (e.g. seller has no MoMo number on file → ManualDriver returns false)
     */
    public function isAvailableFor(MarketplaceOrder $order): bool;

    /**
     * Render an associative array describing what the buyer must do.
     * Returns: ['title' => string, 'steps' => string[], 'fields' => array[]]
     *   - steps: bullet instructions (already localized)
     *   - fields: ['key' => 'provider_ref', 'label' => 'Transaction ID', 'required' => true, ...]
     */
    public function checkoutInstructions(MarketplaceOrder $order, string $locale = 'en'): array;

    /**
     * Validate buyer-submitted evidence and (optionally) flip the order forward.
     * Manual driver moves Initiated → AwaitingPayment.
     *
     * @param  array<string,mixed>  $payload
     * @return array{ok:bool,message?:string,errors?:array<string,string>}
     */
    public function submitProof(MarketplaceOrder $order, array $payload): array;
}
