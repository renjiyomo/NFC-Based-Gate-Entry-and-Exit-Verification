<?php

return [
    // Development hypotheses, not institution-approved security policy.
    'threshold_rules' => env('ANOMALY_THRESHOLD_RULES', env('APP_ENV') === 'local'),
    'window_seconds' => 60,
    'denied_count' => 5,
    'revoked_count' => 3,
    'duplicate_count' => 5,
    'cross_gate_seconds' => 30,
    // Disabled until campus hours are supplied. Daily [open, close), Manila.
    'open_hour' => null,
    'close_hour' => null,
];
