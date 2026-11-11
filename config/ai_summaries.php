<?php

return [
    'enabled' => env('AI_SUMMARIES_ENABLED', false),
    'inline_generation' => env('AI_SUMMARIES_INLINE', env('APP_ENV') !== 'testing'),
    'allow_aggregate_workspaces' => env('AI_SUMMARIES_ALLOW_AGGREGATES', false),
    'provider' => 'gemini_free',
    'api_key' => env('AI_SUMMARIES_API_KEY'),
    'model' => 'gemini-3.1-flash-lite',
    'project_id' => env('AI_SUMMARIES_PROJECT_ID'),
    'free_tier_confirmed' => env('AI_SUMMARIES_FREE_TIER_CONFIRMED', false),
    'max_requests_per_day' => 3,
    'timeout_seconds' => 12,
    'max_output_tokens' => 600,
];
