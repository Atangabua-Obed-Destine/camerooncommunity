<?php

namespace App\Enums;

/**
 * Lifecycle of a marketplace order (Phase 5).
 *
 *   initiated         → row created, no payment instructions shown yet (rare)
 *   awaiting_payment  → buyer has seen instructions and is sending funds
 *   paid              → seller marks "received" — funds confirmed
 *   released          → buyer confirms receipt OR auto-release window passed
 *   cancelled         → either party cancelled before payment
 *   disputed          → either party opened a dispute (Phase 7 wires the UI)
 */
enum OrderStatus: string
{
    case Initiated       = 'initiated';
    case AwaitingPayment = 'awaiting_payment';
    case Paid            = 'paid';
    case Released        = 'released';
    case Cancelled       = 'cancelled';
    case Disputed        = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::Initiated       => 'Initiated',
            self::AwaitingPayment => 'Awaiting payment',
            self::Paid            => 'Paid',
            self::Released        => 'Released',
            self::Cancelled       => 'Cancelled',
            self::Disputed        => 'Disputed',
        };
    }

    public function labelFr(): string
    {
        return match ($this) {
            self::Initiated       => 'Initié',
            self::AwaitingPayment => 'En attente de paiement',
            self::Paid            => 'Payé',
            self::Released        => 'Libéré',
            self::Cancelled       => 'Annulé',
            self::Disputed        => 'Litige',
        };
    }

    /** Tailwind chip classes (bg, text, ring). */
    public function chip(): string
    {
        return match ($this) {
            self::Initiated, self::AwaitingPayment => 'bg-amber-100 text-amber-800 ring-amber-200',
            self::Paid     => 'bg-blue-100 text-blue-800 ring-blue-200',
            self::Released => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
            self::Cancelled => 'bg-slate-200 text-slate-700 ring-slate-300',
            self::Disputed  => 'bg-red-100 text-red-800 ring-red-200',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Initiated, self::AwaitingPayment, self::Paid], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Released, self::Cancelled], true);
    }
}
