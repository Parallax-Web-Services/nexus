<?php

namespace App\Models\Cashier;

use Laravel\Cashier\SubscriptionItem as CashierSubscriptionItem;

class SubscriptionItem extends CashierSubscriptionItem
{
    /**
     * @var string
     */
    protected $table = 'cashier_subscription_items';
}
