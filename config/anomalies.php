<?php

return [
    'threshold_rules' => env('ACTIVITY_CHECKS_ENABLED', true),
    'window_seconds' => 60,
    'denied_count' => 5,
    'revoked_count' => 3,
    'duplicate_count' => 5,
    'rapid_count' => 3,
    'credential_alerts' => true,
    'cross_gate_enabled' => false,
    'cross_gate_seconds' => 30,
    // Disabled until campus hours are supplied. Daily [open, close), Manila.
    'open_hour' => null,
    'close_hour' => null,
];
