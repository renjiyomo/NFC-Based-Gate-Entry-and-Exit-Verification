<?php

return [
    // Never allows preview access in production, even if enabled accidentally.
    'ui_preview' => (bool) env('NFCHECK_UI_PREVIEW', false),
];
