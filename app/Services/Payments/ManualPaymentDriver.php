<?php

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Models\MarketplaceOrder;

/**
 * Manual mobile-money driver — no API integration.
 *
 * The buyer sees the seller's MoMo number + the order reference, completes
 * the transfer in their MoMo / Orange Money app or USSD menu, then pastes
 * the operator transaction ID back into the order. The seller manually
 * confirms receipt, which flips the order to Paid. This is the safe,
 * works-everywhere default until real provider integration is added.
 */
class ManualPaymentDriver implements PaymentDriver
{
    public function id(): string { return 'manual'; }

    public function label(string $locale = 'en'): string
    {
        return $locale === 'fr' ? 'Mobile Money (manuel)' : 'Mobile Money (manual)';
    }

    public function icon(): string { return '📱'; }

    public function isAvailableFor(MarketplaceOrder $order): bool
    {
        return ! empty($order->seller?->momo_number);
    }

    public function checkoutInstructions(MarketplaceOrder $order, string $locale = 'en'): array
    {
        $seller = $order->seller;
        $fr     = $locale === 'fr';

        $providerLabel = match ($seller?->momo_provider) {
            'mtn'     => 'MTN MoMo',
            'orange'  => 'Orange Money',
            'express' => 'Express Union',
            default   => $fr ? 'Mobile Money' : 'Mobile Money',
        };

        return [
            'title' => ($fr ? 'Payer par ' : 'Pay with ') . $providerLabel,
            'recipient' => [
                'name'     => $seller?->name,
                'number'   => $seller?->momo_number,
                'provider' => $providerLabel,
            ],
            'amount'    => $order->formattedAmount(),
            'reference' => $order->reference,
            'steps' => $fr ? [
                'Ouvrez votre application Mobile Money ou composez le code USSD.',
                'Envoyez ' . $order->formattedAmount() . ' au numéro ' . ($seller?->momo_number ?: '—') . '.',
                'Saisissez la référence ' . $order->reference . ' dans le motif/message.',
                'Copiez l\'identifiant de transaction reçu par SMS.',
                'Collez-le ci-dessous et envoyez — le vendeur sera notifié.',
            ] : [
                'Open your Mobile Money app or dial the USSD shortcode.',
                'Send ' . $order->formattedAmount() . ' to ' . ($seller?->momo_number ?: '—') . '.',
                'Use ' . $order->reference . ' as the message / reference.',
                'Copy the transaction ID from the operator SMS.',
                'Paste it below and submit — the seller will be notified.',
            ],
            'fields' => [
                [
                    'key'         => 'provider_ref',
                    'label'       => $fr ? 'ID de transaction MoMo' : 'MoMo transaction ID',
                    'placeholder' => $fr ? 'ex. CI230415.1234.A87654' : 'e.g. CI230415.1234.A87654',
                    'required'    => true,
                ],
                [
                    'key'         => 'buyer_note',
                    'label'       => $fr ? 'Message au vendeur (optionnel)' : 'Note for seller (optional)',
                    'placeholder' => $fr ? 'Adresse de livraison, heure souhaitée…' : 'Delivery address, preferred time…',
                    'required'    => false,
                    'type'        => 'textarea',
                ],
            ],
        ];
    }

    public function submitProof(MarketplaceOrder $order, array $payload): array
    {
        $ref  = trim((string) ($payload['provider_ref'] ?? ''));
        $note = trim((string) ($payload['buyer_note'] ?? ''));

        if ($ref === '' || mb_strlen($ref) < 4) {
            return [
                'ok'     => false,
                'errors' => ['provider_ref' => 'Please paste the transaction ID from your operator SMS.'],
            ];
        }

        $order->forceFill([
            'provider_ref' => mb_substr($ref, 0, 120),
            'buyer_note'   => $note !== '' ? mb_substr($note, 0, 2000) : null,
            'status'       => OrderStatus::AwaitingPayment,
        ])->save();

        return ['ok' => true, 'message' => 'Payment evidence sent — waiting for seller confirmation.'];
    }
}
