<?php

namespace App\Models\Cashier;

use Laravel\Cashier\Subscription as CashierSubscription;

class Subscription extends CashierSubscription
{
    /**
     * Nexus already uses `subscriptions` for its legacy entitlement layer
     * (now `service_subscriptions`), so Cashier gets its own tables.
     *
     * @var string
     */
    protected $table = 'cashier_subscriptions';
}
