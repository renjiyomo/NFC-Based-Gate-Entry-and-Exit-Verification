<?php

namespace Tests\Feature;

use App\Models\AnomalyFlag;
use App\Models\AnomalyReview;
use App\Models\NfcCredential;
use App\Models\ScanAttempt;
use App\Services\Anomalies\Detection;
use App\Services\Anomalies\Review;
use App\Services\Anomalies\Rules;
use App\Services\Monitoring\PassageCorrections;
use App\Services\Monitoring\ReportFilters;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\MonitoringTestCase;

class AnomalyTest extends MonitoringTestCase
{
    public function test_simulator_flags_are_isolated_and_database_rejects_duplicate_detection_identity(): void
    {
        $physical = $this->subject();
        $this->passage($physical, '2026-11-09 01:00:00', 'exit');
        $simulated = $this->subject('employees', 'simulated');
        config(['nfcheck.nfc_simulator' => true]);
        $this->passage($simulated, '2026-11-09 02:00:00', 'exit', 'simulated');
        $this->finishTime();
        $f = new ReportFilters(['date' => '2026-11-09']);
        $this->assertSame(1, app(Detection::class)->run($f, 'physical', $this->admin)['created']);
        $this->assertSame(1, app(Detection::class)->run($f, 'simulated', $this->admin)['created']);
        $this->get('/admin/anomalies?date=2026-11-09')->assertOk()->assertInertia(fn (Assert $a) => $a->where('source', 'simulated')->has('flags.data', 1)->where('flags.data.0.source', 'simulated'));
        $row = AnomalyFlag::where('source', 'physical')->sole()->getRawOriginal();
        unset($row['id']);
        $row['public_id'] = (string) Str::uuid();
        try {
            DB::table('anomaly_flags')->insert($row);
            $this->fail('Duplicate detection identity allowed');
        } catch (QueryException $e) {
            $this->assertSame(1062, $e->errorInfo[1]);
        }
        $this->assertSame(2, AnomalyFlag::count());
    }

    private function runChecks(): array
    {
        return app(Detection::class)->run(new ReportFilters(['period' => 'monthly', 'date' => '2026-11-09']), 'physical', $this->admin);
    }

    public function test_sequence_rules_use_effective_predecessors_and_reruns_do_not_duplicate_flags(): void
    {
        $p = $this->subject();
        $first = $this->passage($p, '2026-11-08 01:00:00');
        $repeat = $this->passage($p, '2026-11-09 01:00:00');
        $this->passage($p, '2026-11-09 02:00:00', 'exit');
        $this->passage($p, '2026-11-09 03:00:00', 'exit');
        $this->scanPerson($p, '2026-11-09 04:00:00');
        $this->finishTime();
        $this->assertSame(2, $this->runChecks()['created']);
        $this->assertSame(0, $this->runChecks()['created']);
        $this->assertEqualsCanonicalizing(['repeated_entry', 'exit_without_entry'], AnomalyFlag::pluck('rule_code')->all());
        $flag = AnomalyFlag::where('rule_code', 'repeated_entry')->sole();
        $this->assertSame('still_supported', app(Detection::class)->context($flag));
        $this->assertEqualsCanonicalizing([$first->scan_attempt_id, $repeat->scan_attempt_id], $flag->evidence()->pluck('scan_attempts.id')->all());
        app(PassageCorrections::class)->append($this->admin, $first, ['action' => 'void', 'reason' => 'Synthetic correction removes the earlier Entry', 'expected_revision' => 0], 'physical');
        $this->assertSame('evidence_changed', app(Detection::class)->context($flag));
        $this->assertSame(2, AnomalyFlag::count());
        app(PassageCorrections::class)->append($this->admin, $repeat, ['action' => 'void', 'reason' => 'Synthetic correction removes the anchor Entry', 'expected_revision' => 0], 'physical');
        $this->assertSame('evidence_changed', app(Detection::class)->context($flag));
    }

    public function test_denied_revoked_and_duplicate_threshold_windows_are_exact_and_source_scoped(): void
    {
        config(['anomalies.threshold_rules' => true]);
        $p = $this->subject();
        $credential = NfcCredential::where('public_id', $this->receipts[$p->id]['credential']['id'])->sole();
        // Revocation changes mutable credential state; attempts still use the real verifier.
        $credential->forceFill(['status' => 'revoked'])->save();
        foreach (['01:00:00', '01:00:30', '01:00:59', '01:01:00', '01:01:01'] as $time) {
            $this->scanPerson($p, '2026-11-09 '.$time);
        }
        $this->finishTime();
        $policy = app(Rules::class)->policy();
        $attempts = ScanAttempt::orderBy('received_at')->get();
        $this->assertArrayNotHasKey('revoked_burst', app(Rules::class)->evaluate($attempts[1], $policy));
        $this->assertCount(3, app(Rules::class)->evaluate($attempts[2], $policy)['revoked_burst']['ids']);
        $this->assertArrayNotHasKey('denied_burst', app(Rules::class)->evaluate($attempts[4], $policy)); // first scan is exactly outside the 60-second window
        $policy['denied_count'] = 4;
        $this->assertCount(4, app(Rules::class)->evaluate($attempts[4], $policy)['denied_burst']['ids']);
        $this->assertSame([], app(Rules::class)->evaluate($attempts[2], [...$policy, 'threshold_rules' => false]));
    }

    public function test_review_workflow_keeps_history_rejects_stale_decisions_and_protects_sources(): void
    {
        $p = $this->subject();
        $this->passage($p, '2026-11-09 01:00:00', 'exit');
        $this->finishTime();
        $this->runChecks();
        $flag = AnomalyFlag::sole();
        $url = '/admin/anomalies/'.$flag->public_id;
        $this->get($url)->assertOk()->assertInertia(fn (Assert $a) => $a->component('anomalies/Show')->where('flag.context', 'still_supported')->has('evidence', 1));
        $this->post($url.'/reviews', ['status' => 'in_review', 'reason' => 'Review synthetic missing Entry context', 'expected_revision' => 0])->assertRedirect();
        $revision = AnomalyReview::sole()->id;
        $this->post($url.'/reviews', ['status' => 'resolved', 'reason' => 'Stale decision must not be saved', 'expected_revision' => 0])->assertSessionHasErrors('expected_revision');
        $this->post($url.'/reviews', ['status' => 'false_alarm', 'reason' => 'Approved test exception; initial Entry was deliberately absent', 'expected_revision' => $revision])->assertRedirect();
        $revision = AnomalyReview::max('id');
        $this->post($url.'/reviews', ['status' => 'resolved', 'reason' => 'Closed flag requires reopening', 'expected_revision' => $revision])->assertSessionHasErrors('status');
        $this->post($url.'/reviews', ['status' => 'open', 'reason' => 'Reopen to review additional test context', 'expected_revision' => $revision])->assertRedirect();
        $this->get('/admin/anomalies?date=2026-11-09&status=open')->assertOk()->assertInertia(fn (Assert $a) => $a->has('flags.data', 1)->where('openCount', 1));
        $this->assertSame(3, AnomalyReview::count());
        $this->get('/admin/anomalies?source=simulated')->assertSessionHasErrors('source');
        $this->post($url.'/reviews', ['status' => 'resolved', 'reason' => $this->receipts[$p->id]['payload'], 'expected_revision' => AnomalyReview::max('id')])->assertSessionHasErrors('reason')->assertSessionMissing('_old_input.reason');
        foreach (['anomaly_flags', 'anomaly_evidence', 'anomaly_reviews'] as $table) {
            foreach (['UPDATE '.$table.' SET '.($table === 'anomaly_flags' ? 'severity=severity' : ($table === 'anomaly_evidence' ? 'scan_attempt_id=scan_attempt_id' : 'reason=reason')).' WHERE 1=0', 'DELETE FROM '.$table.' WHERE 1=0'] as $sql) {
                try {
                    DB::statement($sql);
                    $this->fail('Immutable table mutation allowed');
                } catch (QueryException $e) {
                    $this->assertContains($e->errorInfo[1], [1142, 1143]);
                }
            }
        }
        config(['nfcheck.nfc_simulator' => true]);
        $this->get($url)->assertNotFound();
        config(['nfcheck.nfc_simulator' => false]);
        $this->signIn($this->guard);
        $this->get('/admin/anomalies')->assertForbidden();
        $this->get($url)->assertForbidden();
        $this->post('/admin/anomalies/detect', ['from' => '2026-11-09', 'to' => '2026-11-09'])->assertForbidden();
        $this->post($url.'/reviews', ['status' => 'resolved', 'reason' => 'Guard cannot resolve', 'expected_revision' => 0])->assertForbidden();
    }

    public function test_detection_bounds_policy_provenance_and_private_dtos(): void
    {
        $p = $this->subject();
        $this->passage($p, '2026-11-09 01:00:00', 'exit');
        $this->finishTime();
        $this->post('/admin/anomalies/detect', ['from' => '2026-09-01', 'to' => '2026-11-09'])->assertSessionHasErrors('detection');
        $this->post('/admin/anomalies/detect', ['from' => '2026-11-09', 'to' => '2026-11-09'])->assertRedirect();
        $flag = AnomalyFlag::sole();
        $this->assertSame(app(Rules::class)->version($flag->policy), $flag->rule_version);
        $response = $this->get('/admin/anomalies/'.$flag->public_id)->assertOk();
        foreach ($this->receipts[$p->id] as $key => $value) {
            if (in_array($key, ['payload', 'completion_nonce'], true)) {
                $this->assertStringNotContainsString($value, $response->getContent());
            }
        }
        $this->assertStringNotContainsString('payload_fingerprint', $response->getContent());
        $this->assertStringNotContainsString('session_digest', $response->getContent());
        $original = $flag->getRawOriginal();
        app(Review::class)->append($this->admin, $flag, 'physical', ['status' => 'resolved', 'reason' => 'Synthetic review completed without a wrongdoing claim', 'expected_revision' => 0]);
        $this->assertSame($original, $flag->fresh()->getRawOriginal());
    }
}
