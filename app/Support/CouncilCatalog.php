<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class CouncilCatalog
{
    public function executiveName(string $key): string
    {
        return explode(' / ', config('council.catalog.plans.'.$key.'.name', ucfirst($key)))[0];
    }

    public function selection(array $input): array
    {
        $plans = config('council.catalog.plans', []);
        $key = $input['plan'] ?? 'full';
        $interval = $input['interval'] ?? 'monthly';
        if (! in_array($interval, ['monthly', 'annual'], true)) {
            throw ValidationException::withMessages(['interval' => 'Choose monthly or annual billing.']);
        }
        $keys = $key === 'custom' ? array_values(array_unique($input['executives'] ?? [])) : [$key];
        if ($keys === [] || count($keys) > 6) {
            throw ValidationException::withMessages(['executives' => 'Choose between one and six executives.']);
        }
        $selected = [];
        foreach ($keys as $item) {
            $plan = $plans[$item] ?? null;
            if (! $plan || ($key === 'custom' && count($plan['executives']) !== 1) || ! isset($plan['prices'][$interval])) {
                throw ValidationException::withMessages(['plan' => 'This council and billing interval are unavailable.']);
            }
            $selected[] = $plan + ['key' => $item];
        }

        return [
            'key' => $key,
            'interval' => $interval,
            'name' => $key === 'custom' ? 'Your Executive Council' : $selected[0]['name'],
            'plans' => $selected,
            'executives' => array_values(array_unique(array_merge(...array_column($selected, 'executives')))),
            'amount' => array_sum(array_map(fn ($plan) => $plan['prices'][$interval]['amount'], $selected)),
            'line_items' => array_map(fn ($plan) => ['price' => $plan['prices'][$interval]['id'], 'quantity' => 1], $selected),
        ];
    }

    public function servicesForPrices(array $priceIds): array
    {
        $services = [];
        foreach (config('council.catalog.plans', []) as $plan) {
            foreach ($plan['prices'] as $price) {
                if ($price['id'] && in_array($price['id'], $priceIds, true)) {
                    $services = array_merge($services, $plan['executives']);
                }
            }
        }

        return array_values(array_unique($services));
    }
}
