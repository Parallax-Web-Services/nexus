<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\Service;
use App\Support\CouncilCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncCouncilCatalog extends Command
{
    protected $signature = 'council:sync-catalog';

    protected $description = 'Map approved council prices to Nexus plans and executive services';

    public function handle(): int
    {
        if (config('council.catalog.livemode') !== config('council.livemode')) {
            $this->error('Council catalog and Nexus billing environment must match.');

            return self::FAILURE;
        }
        $plans = config('council.catalog.plans', []);
        foreach ($plans as $plan) {
            foreach ($plan['prices'] as $price) {
                if (empty($price['id'])) {
                    $this->error('Provision the Stripe catalog and import its manifest first.');

                    return self::FAILURE;
                }
            }
        }
        DB::transaction(function () use ($plans) {
            foreach ($plans as $plan) {
                $services = collect($plan['executives'])->map(function ($key) {
                    $name = app(CouncilCatalog::class)->executiveName($key);
                    $service = Service::firstOrCreate(['key' => $key], ['name' => $name]);
                    if ($key === 'parity' && $service->name !== $name) {
                        $service->update(['name' => $name]);
                    }

                    return $service->id;
                })->all();
                foreach ($plan['prices'] as $interval => $price) {
                    $row = Plan::updateOrCreate(['billing_account' => config('council.account'), 'stripe_price_id' => $price['id']], [
                        'name' => $plan['name'].' / '.$interval,
                        'pricing' => '$'.number_format($price['amount'] / 100, 2).' / '.$price['interval'],
                    ]);
                    $row->services()->sync($services);
                }
            }
        });
        $this->info('Council plans mapped to executive services.');

        return self::SUCCESS;
    }
}
