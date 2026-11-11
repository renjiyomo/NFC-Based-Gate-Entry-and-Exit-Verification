<?php

namespace Tests\Feature;

use App\Models\AccessEvent;
use App\Models\SummaryRequest;
use App\Models\SummaryReview;
use App\Services\Monitoring\PassageCorrections;
use App\Services\Monitoring\ReportFilters;
use App\Services\Summaries\Aggregates;
use App\Services\Summaries\ConfiguredProvider;
use App\Services\Summaries\Generation;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MonitoringTestCase;

class SummaryTest extends MonitoringTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['ai_summaries.enabled' => true, 'ai_summaries.free_tier_confirmed' => true, 'ai_summaries.project_id' => 'synthetic-free-project', 'ai_summaries.api_key' => str_repeat('test-key-', 5)]);
    }

    private function validSelection(): array
    {
        return ['fact_ids' => ['entries', 'exits', 'attempts', 'denied', 'flags', 'people'], 'limitation_ids' => array_keys(Aggregates::LIMITATIONS)];
    }

    private function fake(?array $output = null): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode($output ?? $this->validSelection())]]]]], 'usageMetadata' => ['promptTokenCount' => 300, 'candidatesTokenCount' => 60, 'thoughtsTokenCount' => 2]])]);
    }

    private function requestSummary(?string $key = null): SummaryRequest
    {
        return app(Generation::class)->request($this->admin, new ReportFilters(['date' => '2026-11-09']), 'physical', $key ?? (string) Str::uuid());
    }

    public function test_inline_generation_completes_from_the_page_without_a_worker_or_duplicate_call(): void
    {
        config(['ai_summaries.inline_generation' => true]);
        $this->fake();
        $this->finishTime();
        $key = (string) Str::uuid();
        $this->post('/admin/summaries', ['period' => 'daily', 'date' => '2026-11-09', 'request_key' => $key])->assertRedirect();
        $summary = SummaryRequest::sole();
        $this->assertSame('ready', $summary->result->status);
        Http::assertSentCount(1);
        $this->post('/admin/summaries', ['period' => 'daily', 'date' => '2026-11-09', 'request_key' => $key])->assertRedirect();
        $this->assertSame(1, SummaryRequest::count());
        Http::assertSentCount(1);
        $this->get('/admin/summaries/'.$summary->public_id)->assertInertia(fn (Assert $page) => $page->where('summary.status', 'ready')->has('summary.selectedFacts', 6));
    }

    public function test_one_shot_worker_command_observes_limits_and_leaves_completed_tasks_alone(): void
    {
        $this->finishTime();
        $this->fake();
        $r = $this->requestSummary();
        $this->artisan('nfcheck:work-summaries', ['--limit' => 4])->assertExitCode(1);
        Http::assertNothingSent();
        $this->artisan('nfcheck:work-summaries', ['--limit' => 1])->assertExitCode(0);
        $this->assertSame('ready', $r->fresh()->result->status);
        $this->artisan('nfcheck:work-summaries', ['--limit' => 1])->assertExitCode(0);
        Http::assertSentCount(1);
    }

    public function test_weekly_summary_uses_manila_monday_to_sunday_boundaries(): void
    {
        $p = $this->subject();
        $this->passage($p, '2026-11-01 16:00:00');
        $this->passage($p, '2026-11-08 15:59:59', 'exit');
        $this->passage($p, '2026-11-08 16:00:00');
        $this->finishTime();
        $this->get('/admin/summaries?period=weekly&date=2026-11-08')->assertOk()->assertInertia(fn (Assert $a) => $a->where('period.from', '2026-11-02')->where('period.to', '2026-11-08')->where('facts.facts.entries.count', 1)->where('facts.facts.exits.count', 1)->where('facts.facts.attempts.count', 2));
        $this->post('/admin/summaries', ['period' => 'weekly', 'date' => '2026-11-08', 'request_key' => (string) Str::uuid()])->assertRedirect();
        $r = SummaryRequest::sole();
        $this->assertSame('weekly', $r->period);
        $this->assertSame('2026-11-02', $r->period_from);
        $this->assertSame(2, $r->snapshot['facts']['passages']['count']);
        Http::assertNothingSent();
    }

    public function test_validated_summary_uses_private_aggregates_and_a_separate_single_claim_worker(): void
    {
        $p = $this->subject();
        $this->passage($p, '2026-11-09 01:00:00');
        $this->passage($p, '2026-11-09 02:00:00', 'exit');
        $this->finishTime();
        $this->fake();
        $r = $this->requestSummary();
        $this->assertNull($r->result);
        Http::assertNothingSent();
        $this->assertTrue(app(Generation::class)->process($r->task));
        $this->assertFalse(app(Generation::class)->process($r->task));
        Http::assertSentCount(1);
        Http::assertSent(function (Request $q) use ($p): bool {
            $body = json_encode($q->data());
            foreach ([$p->full_name, $p->campus_number, $this->gate->code, $this->guard->name, $this->receipts[$p->id]['payload'], $this->receipts[$p->id]['completion_nonce']] as $value) {
                $this->assertStringNotContainsString($value, $body);
            }
            $this->assertFalse(str_contains($q->url(), config('ai_summaries.api_key')));
            $this->assertSame('application/json', $q['generationConfig']['responseMimeType']);

            return $q->hasHeader('x-goog-api-key', config('ai_summaries.api_key')) && $q['generationConfig']['maxOutputTokens'] === 600 && ! isset($q['tools']);
        });
        $r->refresh();
        $this->assertSame('ready', $r->result->status);
        $this->assertSame(62, $r->result->output_tokens);
        $this->assertContains('1 effective Entry passage was recorded.', $r->result->sentences);
        $this->get('/admin/summaries/'.$r->public_id)->assertOk()->assertInertia(fn (Assert $a) => $a->component('summaries/Show')->where('summary.status', 'ready')->where('summary.changed', false)->where('summary.review', 'unreviewed'));
        $this->assertSame(1, (int) DB::table('summary_controls')->value('claims'));
    }

    public function test_disabled_provider_does_not_call_any_service_or_block_gate_operations(): void
    {
        config(['ai_summaries.enabled' => false]);
        $p = $this->subject();
        $this->passage($p, '2026-11-09 01:00:00');
        $this->finishTime();
        $r = $this->requestSummary();
        $this->assertSame('unavailable', $r->result->status);
        $this->assertSame('disabled', $r->result->error_code);
        Http::assertNothingSent();
        $this->passage($p, '2026-11-09 03:00:00', 'exit');
        $this->assertSame(2, AccessEvent::count());
        $this->assertFalse(app(Generation::class)->process($r->task));
        $this->post('/admin/summaries/'.$r->public_id.'/reviews', ['decision' => 'approved', 'reason' => 'Unavailable drafts cannot be approved', 'expected_revision' => 0])->assertStatus(422);
    }

    public function test_request_key_is_idempotent_and_rejects_changed_periods(): void
    {
        $this->finishTime();
        $key = (string) Str::uuid();
        $a = $this->requestSummary($key);
        $b = $this->requestSummary($key);
        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, SummaryRequest::count());
        $this->post('/admin/summaries', ['period' => 'daily', 'date' => '2026-11-08', 'request_key' => $key])->assertSessionHasErrors('request_key');
        Http::assertNothingSent();
    }

    #[DataProvider('failures')]
    public function test_provider_failures_are_redacted_and_never_retried(int $http, string $code): void
    {
        $this->finishTime();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'untrusted response '.config('ai_summaries.api_key')]], $http)]);
        $r = $this->requestSummary();
        app(Generation::class)->process($r->task);
        $r->refresh();
        $this->assertSame('failed', $r->result->status);
        $this->assertSame($code, $r->result->error_code);
        $this->assertNull($r->result->sentences);
        $this->assertFalse(app(Generation::class)->process($r->task));
        Http::assertSentCount(1);
        $this->assertStringNotContainsString(config('ai_summaries.api_key'), $this->get('/admin/summaries/'.$r->public_id)->getContent());
    }

    public static function failures(): array
    {
        return [[429, 'quota_exceeded'], [403, 'provider_refused'], [503, 'provider_unavailable'], [302, 'provider_unavailable']];
    }

    public function test_timeout_or_uncertain_provider_response_never_creates_a_success_or_retry(): void
    {
        $this->finishTime();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::failedConnection()]);
        $r = $this->requestSummary();
        app(Generation::class)->process($r->task);
        $r->refresh();
        $this->assertSame('failed', $r->result->status);
        $this->assertSame('provider_unavailable', $r->result->error_code);
        $this->assertFalse(app(Generation::class)->process($r->task));
    }

    public function test_unknown_figures_prose_and_invalid_selections_are_rejected(): void
    {
        $this->finishTime();
        $snapshot = app(Aggregates::class)->build(new ReportFilters(['date' => '2026-11-09']), 'physical');
        $valid = $this->validSelection();
        foreach ([
            [...$valid, 'narrative' => 'There were 900 security incidents'],
            [...$valid, 'fact_ids' => ['entries', 'exits', 'attempts', 'denied', 'flags', 'invented']],
            [...$valid, 'fact_ids' => ['entries', 'entries', 'attempts', 'denied', 'flags']],
            [...$valid, 'fact_ids' => ['entries', 'exits', 'denied', 'flags', 'people']],
            [...$valid, 'fact_ids' => [['nested'], 'entries', 'exits', 'attempts', 'denied', 'flags']],
            [...$valid, 'limitation_ids' => ['presence_estimate']],
        ] as $bad) {
            try {
                app(Aggregates::class)->validate($bad, $snapshot);
                $this->fail('Invalid provider output accepted');
            } catch (\UnexpectedValueException $e) {
                $this->assertSame('invalid_facts', $e->getMessage() === 'invalid_structure' ? 'invalid_facts' : $e->getMessage());
            }
        }
        $this->fake([...$valid, 'fact_ids' => [...$valid['fact_ids'], 'invented']]);
        $r = $this->requestSummary();
        app(Generation::class)->process($r->task);
        $r->refresh();
        $this->assertSame('rejected', $r->result->status);
        $this->assertNull($r->result->sentences);
    }

    public function test_quota_claims_are_bounded_even_for_new_requests(): void
    {
        $this->finishTime();
        config(['ai_summaries.max_requests_per_day' => 1]);
        $this->fake();
        $a = $this->requestSummary();
        $b = $this->requestSummary();
        app(Generation::class)->process($a->task);
        app(Generation::class)->process($b->task);
        $this->assertSame('daily_limit', $b->fresh()->result->error_code);
        Http::assertSentCount(1);
    }

    public function test_snapshot_corrections_and_review_history_preserve_originals(): void
    {
        $p = $this->subject();
        $event = $this->passage($p, '2026-11-09 01:00:00');
        $this->finishTime();
        $this->fake();
        $r = $this->requestSummary();
        app(Generation::class)->process($r->task);
        $original = $r->fresh()->getRawOriginal();
        $url = '/admin/summaries/'.$r->public_id;
        $this->post($url.'/reviews', ['decision' => 'approved', 'reason' => 'Synthetic snapshot checked against source counts', 'expected_revision' => 0])->assertRedirect();
        $revision = SummaryReview::max('id');
        $this->post($url.'/reviews', ['decision' => 'rejected', 'reason' => 'Stale decision must not overwrite', 'expected_revision' => 0])->assertSessionHasErrors('expected_revision');
        $this->post($url.'/reviews', ['decision' => 'rejected', 'reason' => 'New context needs another summary', 'expected_revision' => $revision])->assertRedirect();
        app(PassageCorrections::class)->append($this->admin, $event, ['action' => 'void', 'reason' => 'Synthetic correction after generation', 'expected_revision' => 0], 'physical');
        $this->get($url)->assertOk()->assertInertia(fn (Assert $a) => $a->where('summary.changed', true)->where('summary.snapshot.facts.entries.count', 1)->where('summary.review', 'rejected')->has('reviews.data', 2));
        $this->assertSame($original, $r->fresh()->getRawOriginal());
        $this->post($url.'/reviews', ['decision' => 'approved', 'reason' => $this->receipts[$p->id]['payload'], 'expected_revision' => SummaryReview::max('id')])->assertSessionHasErrors('reason')->assertSessionMissing('_old_input.reason');
    }

    public function test_permissions_source_isolation_and_forbidden_input_are_enforced(): void
    {
        $this->finishTime();
        $r = $this->requestSummary();
        $url = '/admin/summaries/'.$r->public_id;
        $this->get('/admin/summaries?source=simulated')->assertSessionHasErrors('source');
        $this->post('/admin/summaries', ['period' => 'daily', 'date' => '2026-11-09', 'request_key' => (string) Str::uuid(), 'api_key' => 'never-accept-this-in-a-form'])->assertSessionHasErrors('api_key')->assertSessionMissing('_old_input.api_key');
        config(['nfcheck.nfc_simulator' => true]);
        $this->get($url)->assertNotFound();
        config(['nfcheck.nfc_simulator' => false]);
        $this->signIn($this->guard);
        $this->get('/admin/summaries')->assertForbidden();
        $this->get($url)->assertForbidden();
        $this->post('/admin/summaries', ['request_key' => (string) Str::uuid()])->assertForbidden();
        $this->post($url.'/reviews', ['decision' => 'approved', 'reason' => 'Guard cannot approve', 'expected_revision' => 0])->assertForbidden();
        $this->post($url.'/recover')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_interrupted_worker_can_be_closed_without_another_call(): void
    {
        $this->finishTime();
        $r = $this->requestSummary();
        $url = '/admin/summaries/'.$r->public_id;
        $this->post($url.'/recover')->assertSessionHasErrors('recovery');
        $r->task->forceFill(['claimed_at' => now()->subMinutes(3)])->save();
        $this->post($url.'/recover')->assertRedirect();
        $this->assertSame('worker_interrupted', $r->fresh()->result->error_code);
        $this->assertFalse(app(Generation::class)->process($r->task));
        Http::assertNothingSent();
    }

    public function test_account_changes_cancel_queued_generation_and_tables_keep_history(): void
    {
        $this->finishTime();
        $r = $this->requestSummary();
        $this->admin->forceFill(['status' => 'inactive'])->save();
        app(Generation::class)->process($r->task);
        $this->assertSame('account_changed', $r->fresh()->result->error_code);
        Http::assertNothingSent();
        foreach (['summary_requests', 'summary_results', 'summary_reviews'] as $table) {
            foreach (['UPDATE '.$table.' SET '.($table === 'summary_requests' ? 'source=source' : ($table === 'summary_results' ? 'status=status' : 'reason=reason')).' WHERE id=0', 'DELETE FROM '.$table.' WHERE id=0'] as $sql) {
                try {
                    DB::statement($sql);
                    $this->fail('Immutable summary history mutation allowed');
                } catch (QueryException $e) {
                    $this->assertContains($e->errorInfo[1], [1142, 1143]);
                }
            }
        }
    }

    public function test_free_only_configuration_cannot_silently_switch_to_paid_provider_or_main(): void
    {
        $p = app(ConfiguredProvider::class);
        $this->assertTrue($p->configuration()['ready']);
        config(['ai_summaries.provider' => 'openai']);
        $this->assertFalse($p->configuration()['ready']);
        config(['ai_summaries.provider' => 'gemini_free', 'ai_summaries.free_tier_confirmed' => false]);
        $this->assertSame('free_tier_unconfirmed', $p->configuration()['reason']);
        config(['ai_summaries.free_tier_confirmed' => true, 'database.connections.mysql.database' => 'nfcheck']);
        $this->assertSame('workspace_not_approved', $p->configuration()['reason']);
        Http::assertNothingSent();
    }
}
