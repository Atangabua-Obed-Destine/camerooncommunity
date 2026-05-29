<?php

namespace App\Services\Payments;

/**
 * Resolves the available payment drivers for a marketplace order.
 *
 * Today this returns just the Manual driver. When real API drivers land
 * (MtnMomoDriver, OrangeMoneyDriver) they go into the $drivers array below
 * and the Checkout component will offer them automatically.
 */
class PaymentDriverManager
{
    /** @var PaymentDriver[] */
    protected array $drivers;

    public function __construct()
    {
        $this->drivers = [
            new ManualPaymentDriver(),
            // new MtnMomoDriver(),
            // new OrangeMoneyDriver(),
        ];
    }

    /** @return PaymentDriver[] */
    public function all(): array { return $this->drivers; }

    public function find(string $id): ?PaymentDriver
    {
        foreach ($this->drivers as $d) {
            if ($d->id() === $id) { return $d; }
        }
        return null;
    }

    /**
     * @param  \App\Models\MarketplaceOrder  $order
     * @return PaymentDriver[]
     */
    public function availableFor($order): array
    {
        return array_values(array_filter($this->drivers, fn ($d) => $d->isAvailableFor($order)));
    }
}
