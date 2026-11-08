<?php

return [
    // Never allows preview access in production, even if enabled accidentally.
    'ui_preview' => (bool) env('NFCHECK_UI_PREVIEW', false),
    'nfc_simulator' => (bool) env('NFCHECK_NFC_SIMULATOR', false),
    'hardware_lab' => (bool) env('NFCHECK_HARDWARE_LAB', false),
    'review_seconds' => (int) env('NFCHECK_REVIEW_SECONDS', 30),
    'duplicate_seconds' => (int) env('NFCHECK_DUPLICATE_SECONDS', 10),
    'guard_activity_minutes' => 30,
];
