<?php

namespace App\Services\Summaries;

use App\Support\DatabaseIdentity;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ConfiguredProvider implements Provider
{
    private function settings(): array
    {
        $c = config('ai_summaries');
        // Tests never load private workstation keys. No key is accepted by a web form.
        if (! app()->environment('testing')) {
            $path = base_path('.tools/secrets/ai-provider.json');
            if (is_readable($path)) {
                $raw = @file_get_contents($path);
                $file = is_string($raw) && strlen($raw) <= 4096 ? json_decode($raw, true) : null;
                if (! is_array($file)) {
                    return [...$c, 'enabled' => false];
                }
                $c = [...$c, ...array_intersect_key($file, array_flip(['enabled', 'provider', 'model', 'api_key', 'project_id', 'free_tier_confirmed']))];
            }
        }

        return $c;
    }

    public function configuration(): array
    {
        $c = $this->settings();
        $reason = null;
        if ($c['enabled'] !== true) {
            $reason = 'disabled';
        } elseif (! app()->environment(['local', 'testing']) || ! in_array(DatabaseIdentity::name(), ['nfcheck_simulator', 'nfcheck_hardware', 'nfcheck_testing'], true)) {
            $reason = 'workspace_not_approved';
        } elseif ($c['provider'] !== 'gemini_free' || $c['model'] !== 'gemini-3.1-flash-lite') {
            $reason = 'unsupported_provider';
        } elseif ($c['free_tier_confirmed'] !== true) {
            $reason = 'free_tier_unconfirmed';
        } elseif (! is_string($c['project_id']) || ! preg_match('/^[a-z][a-z0-9-]{4,61}[a-z0-9]$/D', $c['project_id'])) {
            $reason = 'not_configured';
        } elseif (! is_string($c['api_key']) || ! preg_match('/^[\x21-\x7E]{20,1024}$/D', $c['api_key'])) {
            $reason = 'not_configured';
        }

        return ['ready' => $reason === null, 'provider' => 'gemini_free', 'model' => 'gemini-3.1-flash-lite', 'reason' => $reason, 'daily_limit' => 3, 'billing' => 'Free Tier only; project billing must remain disabled.'];
    }

    public function generate(array $snapshot): array
    {
        $availability = $this->configuration();
        if (! $availability['ready']) {
            throw new \RuntimeException('not_configured');
        }
        $c = $this->settings();
        $schema = ['type' => 'object', 'properties' => [
            'fact_ids' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => array_keys($snapshot['facts'])], 'minItems' => 5, 'maxItems' => 12],
            'limitation_ids' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => array_keys(Aggregates::LIMITATIONS)], 'minItems' => 3, 'maxItems' => 3],
        ], 'required' => ['fact_ids', 'limitation_ids'], 'additionalProperties' => false];
        $prompt = 'Select and order 5 to 12 distinct fact IDs for a concise gate activity summary. Always include entries, exits, attempts, denied and flags. Prefer other informative counts and gate observations. Return every limitation ID. Never return prose, new figures, identities, incident claims or extra fields. All inputs are synthetic aggregate facts.';
        $body = ['systemInstruction' => ['parts' => [['text' => $prompt]]], 'contents' => [['role' => 'user', 'parts' => [['text' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]]]], 'generationConfig' => ['responseMimeType' => 'application/json', 'responseJsonSchema' => $schema, 'maxOutputTokens' => 600, 'thinkingConfig' => ['thinkingLevel' => 'minimal']]];
        if (strlen(json_encode($body, JSON_THROW_ON_ERROR)) > 20000) {
            throw new \UnexpectedValueException('request_too_large');
        }
        try {
            $response = Http::connectTimeout(4)->timeout(12)->withOptions(['allow_redirects' => false, 'verify' => true, 'progress' => static function ($total, $downloaded): void {
                if ($total > 65536 || $downloaded > 65536) {
                    throw new \UnexpectedValueException('response_too_large');
                }
            }])
                ->withHeaders(['x-goog-api-key' => $c['api_key'], 'Accept' => 'application/json'])->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent', $body);
        } catch (ConnectionException $e) {
            throw new \RuntimeException('provider_unavailable');
        }
        if ($response->status() === 429) {
            throw new \RuntimeException('quota_exceeded');
        }
        if (in_array($response->status(), [401, 403], true)) {
            throw new \RuntimeException('provider_refused');
        }
        if (! $response->successful()) {
            throw new \RuntimeException('provider_unavailable');
        }
        $data = $response->json();
        $candidates = $data['candidates'] ?? [];
        if (count($candidates) !== 1 || ($candidates[0]['finishReason'] ?? '') !== 'STOP') {
            throw new \UnexpectedValueException('incomplete_response');
        }
        $text = '';
        foreach ($candidates[0]['content']['parts'] ?? [] as $part) {
            if ($part['thought'] ?? false) {
                continue;
            }
            if (! isset($part['text']) || ! is_string($part['text'])) {
                throw new \UnexpectedValueException('invalid_part');
            }
            $text .= $part['text'];
        }
        if (strlen($text) > 8192) {
            throw new \UnexpectedValueException('response_too_large');
        }
        try {
            $output = json_decode($text, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \UnexpectedValueException('invalid_json');
        }
        if (! is_array($output)) {
            throw new \UnexpectedValueException('invalid_json');
        }
        $usage = $data['usageMetadata'] ?? [];

        return ['output' => $output, 'input_tokens' => $usage['promptTokenCount'] ?? null, 'output_tokens' => isset($usage['candidatesTokenCount']) ? (int) $usage['candidatesTokenCount'] + (int) ($usage['thoughtsTokenCount'] ?? 0) : null];
    }
}
