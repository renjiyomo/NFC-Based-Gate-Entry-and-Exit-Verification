<?php

return [
    'enabled' => (bool) env('NFCHECK_CLOUD', false),
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('NFCHECK_TRUSTED_PROXIES', ''))))),
];
