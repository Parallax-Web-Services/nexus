<?php

return [
    'trusted_callback' => env('COUNCIL_BOARDROOM_CALLBACK'),
    'continuum_url' => env('COUNCIL_CONTINUUM_URL'),
    'trial_days' => (int) env('COUNCIL_TRIAL_DAYS', 14),
    'services' => ['continuum', 'slipstream', 'parity', 'plexus', 'positronic', 'pulsar'],
];
