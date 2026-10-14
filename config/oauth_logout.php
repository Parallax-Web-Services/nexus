<?php

return [
    // Exact post-logout pages belonging to the deployed OAuth applications,
    // comma-separated in OAUTH_LOGOUT_RETURN_URLS. Anything else is refused.
    'return_urls' => array_values(array_filter(array_map('trim', explode(',', (string) env('OAUTH_LOGOUT_RETURN_URLS', ''))))),
];
