<?php

namespace App\Services\Summaries;

use App\Models\SummaryRequest;
use App\Models\SummaryResult;
use App\Models\SummaryTask;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ManagementGuard;
use App\Services\Monitoring\ReportFilters;
use App\Support\DatabaseIdentity;
use App\Support\DatabaseLocks;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Generation
{
    public function __construct(private Provider $provider, private Aggregates $aggregates) {}

    public function availability(): array
    {
        return $this->provider->configuration();
    }

    public function request(User $actor, ReportFilters $f, string $source, string $key): SummaryRequest
    {
        return DB::transaction(function () use ($actor, $f, $source, $key): SummaryRequest {
            $admin = app(ManagementGuard::class)->actor($actor);
            abort_unless($admin->auth_version === $actor->auth_version && ! $admin->must_change_password, 403);
            $existing = SummaryRequest::where('actor_id', $admin->id)->where('request_key', $key)->tap([DatabaseLocks::class, 'immutableRead'])->first();
            if ($existing) {
                if ($existing->source !== $source || $existing->period !== $f->values['period'] || $existing->period_from !== $f->values['from'] || $existing->period_to !== $f->values['to']) {
                    throw ValidationException::withMessages(['request_key' => 'This request key belongs to a different period. Start a new generation.']);
                }

                return $existing;
            }
            $snapshot = $this->aggregates->build($f, $source);
            $r = SummaryRequest::create(['public_id' => (string) Str::uuid(), 'request_key' => $key, 'actor_id' => $admin->id, 'actor_auth_version' => $admin->auth_version, 'source' => $source, 'period' => $f->values['period'], 'period_from' => $f->values['from'], 'period_to' => $f->values['to'], 'snapshot_at' => $f->asOf, 'snapshot' => $snapshot, 'snapshot_hash' => $this->aggregates->hash($snapshot), 'contract_version' => Aggregates::VERSION, 'created_at' => now()]);
            SummaryTask::create(['summary_request_id' => $r->id, 'claimed_at' => null, 'created_at' => now()]);
            $configuration = $this->availability();
            if (! $configuration['ready']) {
                $this->complete($r, 'unavailable', error: $configuration['reason']);
            }
            app(AuditLogger::class)->record('summary.requested', $admin, $r, ['source' => $source, 'period_start' => $r->period_from, 'period_end' => $r->period_to, 'policy_version' => Aggregates::VERSION]);

            return $r;
        }, 3);
    }

    public function process(SummaryTask $target): bool
    {
        $r = $target->request;
        $claimed = DB::transaction(function () use ($target, $r): bool {
            $actor = User::withTrashed()->whereKey($r->actor_id)->lockForUpdate()->firstOrFail();
            $control = DB::table('summary_controls')->where('id', 1)->lockForUpdate()->first();
            $task = SummaryTask::whereKey($target->id)->lockForUpdate()->firstOrFail();
            if ($task->claimed_at || SummaryResult::where('summary_request_id', $r->id)->exists()) {
                return false;
            }
            if (! $actor->isAdmin() || $actor->must_change_password || $actor->auth_version !== $r->actor_auth_version) {
                $this->complete($r, 'failed', error: 'account_changed');

                return false;
            }
            $configuration = $this->availability();
            if (! $configuration['ready']) {
                $this->complete($r, 'unavailable', error: $configuration['reason']);

                return false;
            }
            $allowed = in_array(DatabaseIdentity::name(), ['nfcheck_simulator', 'nfcheck_hardware', 'nfcheck_testing'], true)
                || (($configuration['scope'] ?? '') === 'anonymous_aggregates' && in_array(DatabaseIdentity::name(), ['nfcheck', 'nfcheck_online'], true));
            if (! $allowed) {
                $this->complete($r, 'unavailable', error: 'workspace_not_approved');

                return false;
            }
            $day = now('UTC')->toDateString();
            $claims = $control->quota_day === $day ? (int) $control->claims : 0;
            if ($claims >= config('ai_summaries.max_requests_per_day')) {
                $this->complete($r, 'unavailable', error: 'daily_limit');

                return false;
            }
            DB::table('summary_controls')->where('id', 1)->update(['quota_day' => $day, 'claims' => $claims + 1]);
            $task->forceFill(['claimed_at' => now()])->save();

            return true;
        }, 3);
        if (! $claimed) {
            return false;
        }
        // Provider requests run after the claim transaction commits. A claimed task
        // is never automatically retried after an uncertain response.
        try {
            if ($r->snapshot_hash !== $this->aggregates->hash($r->snapshot) || $r->contract_version !== Aggregates::VERSION) {
                throw new \UnexpectedValueException('snapshot_mismatch');
            }
            $reply = $this->provider->generate($r->snapshot);
            if (! isset($reply['output']) || ! is_array($reply['output'])) {
                throw new \UnexpectedValueException('invalid_output');
            }
            $valid = $this->aggregates->validate($reply['output'], $r->snapshot);
            $usage = [];
            foreach (['input_tokens', 'output_tokens'] as $name) {
                if (isset($reply[$name]) && is_int($reply[$name]) && $reply[$name] >= 0 && $reply[$name] <= 1000000) {
                    $usage[$name] = $reply[$name];
                }
            }
            $this->complete($r, 'ready', selection: $valid, usage: $usage);
        } catch (\UnexpectedValueException $e) {
            $this->complete($r, 'rejected', error: 'invalid_provider_output');
        } catch (\Throwable $e) {
            // Provider exception messages may contain credentials or response content.
            // Store only a controlled failure code, never the exception or raw text.
            $safe = in_array($e->getMessage(), ['quota_exceeded', 'provider_refused', 'provider_timeout', 'provider_unavailable', 'not_configured'], true) ? $e->getMessage() : 'provider_unavailable';
            $this->complete($r, 'failed', error: $safe);
        }

        return true;
    }

    public function recover(User $actor, SummaryRequest $target, string $source): void
    {
        DB::transaction(function () use ($actor, $target, $source): void {
            $admin = app(ManagementGuard::class)->actor($actor);
            abort_unless($admin->auth_version === $actor->auth_version, 403);
            abort_unless($target->source === $source, 404);
            $task = SummaryTask::where('summary_request_id', $target->id)->lockForUpdate()->firstOrFail();
            if ($target->result()->exists()) {
                return;
            }
            if (! $task->claimed_at || $task->claimed_at->greaterThan(now()->subMinutes(2))) {
                throw ValidationException::withMessages(['recovery' => 'Recovery is available two minutes after a worker claim. It closes the request without another provider call.']);
            }
            $this->complete($target, 'failed', error: 'worker_interrupted');
        }, 3);
    }

    private function complete(SummaryRequest $r, string $status, ?string $error = null, ?array $selection = null, array $usage = []): void
    {
        DB::transaction(function () use ($r, $status, $error, $selection, $usage): void {
            SummaryTask::where('summary_request_id', $r->id)->lockForUpdate()->firstOrFail();
            if ($r->result()->exists()) {
                return;
            }
            $c = $this->availability();
            SummaryResult::create(['summary_request_id' => $r->id, 'status' => $status, 'selected_facts' => $selection['ids'] ?? null, 'sentences' => $selection['sentences'] ?? null, 'error_code' => $error, 'provider' => $c['provider'], 'model' => $c['model'], ...$usage, 'created_at' => now()]);
            app(AuditLogger::class)->record('summary.'.$status, null, $r, ['source' => $r->source, 'reason' => $error ?? 'validated_fact_selection', 'policy_version' => Aggregates::VERSION]);
        }, 3);
    }
}
