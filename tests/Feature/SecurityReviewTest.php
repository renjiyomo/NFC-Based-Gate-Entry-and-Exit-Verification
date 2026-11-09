<?php

namespace Tests\Feature;

use App\Models\AnomalyFlag;
use App\Models\SummaryRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\ManagementTestCase;

class SecurityReviewTest extends ManagementTestCase
{
    public function test_csp_restricts_active_content_with_fresh_nonces_and_preserves_privacy_headers(): void
    {
        $response = $this->get('/login');
        $csp = $response->headers->get('Content-Security-Policy');
        foreach (["default-src 'none'", "script-src 'self' 'nonce-", "script-src-attr 'none'", "form-action 'self'", "frame-ancestors 'none'", "object-src 'none'", "connect-src 'self'"] as $directive) {
            $this->assertStringContainsString($directive, $csp);
        }
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertStringNotContainsString('generativelanguage.googleapis.com', $csp);
        preg_match("/nonce-([^']+)/", $csp, $first);
        $this->assertGreaterThanOrEqual(32, strlen($first[1]));
        $next = $this->get('/login');
        preg_match("/nonce-([^']+)/", $next->headers->get('Content-Security-Policy'), $second);
        $this->assertNotSame($first[1], $second[1]);
        $next->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_every_privileged_mutation_rejects_missing_csrf_before_any_write(): void
    {
        Http::preventStrayRequests();
        // Laravel disables CSRF automatically for tests. Exercise the real path.
        $this->app->instance('env', 'local');
        config(['nfcheck.nfc_simulator' => true]);
        foreach ([
            '/admin/students', '/admin/credentials/issue', '/admin/anomalies/detect', '/admin/summaries',
            '/admin/history/passages/00000000-0000-4000-8000-000000000001/corrections',
            '/admin/summaries/00000000-0000-4000-8000-000000000001/reviews',
            '/guard/gates/1/scan', '/simulator/guard/gates/1/scan',
        ] as $url) {
            $this->withHeaders(['Sec-Fetch-Site' => 'cross-site'])->post($url, [])->assertStatus(419);
        }
        $this->assertSame(0, AnomalyFlag::count());
        $this->assertSame(0, SummaryRequest::count());
        Http::assertNothingSent();
    }

    public function test_valid_csrf_fallback_allows_same_origin_workflow_and_wrong_tokens_fail(): void
    {
        $this->app->instance('env', 'local');
        $token = str_repeat('c', 40);
        $this->withSession(['_token' => $token])->post('/admin/gates', [...['code' => 'CSRF-CHECK', 'name' => 'Synthetic CSRF station', 'status' => 'active'], '_token' => 'wrong'])->assertStatus(419);
        $this->withSession(['_token' => $token])->post('/admin/gates', ['code' => 'CSRF-CHECK', 'name' => 'Synthetic CSRF station', 'status' => 'active', '_token' => $token])->assertRedirect();
    }

    public function test_sql_and_html_inputs_remain_data_and_do_not_expand_results_or_generate_scripts(): void
    {
        $payload = '<img src=x onerror=alert(1)><script>alert(2)</script>';
        $p = $this->makePerson('students', ['full_name' => $payload]);
        $response = $this->get('/admin/students/'.$p->id)->assertOk();
        $this->assertStringNotContainsString('<script>alert(2)</script>', $response->getContent());
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $response->getContent());
        $response->assertInertia(fn (Assert $a) => $a->where('record.full_name', $payload));
        $this->get('/admin/students?q='.urlencode("' OR 1=1 --"))->assertOk()->assertInertia(fn (Assert $a) => $a->where('records.total', 0));
        $this->getJson('/admin/history?gate_id='.urlencode('1 OR 1=1'))->assertStatus(422);
        $this->getJson('/admin/anomalies?rule='.urlencode("' OR 1=1 --"))->assertStatus(422);
    }

    public function test_active_formats_corrupt_images_and_private_path_traversal_are_rejected(): void
    {
        Storage::fake('local');
        foreach ([
            UploadedFile::fake()->createWithContent('document.php', '<?php echo "x"; ?>'),
            UploadedFile::fake()->createWithContent('photo.jpg', '<svg onload="alert(1)"></svg>'),
            UploadedFile::fake()->createWithContent('photo.png', "\x89PNG\r\n\x1a\ninvalid"),
        ] as $photo) {
            $this->post('/admin/students', [...$this->student(), 'photo' => $photo])->assertSessionHasErrors('photo');
        }
        $p = $this->makePerson();
        $p->forceFill(['photo_path' => '../../.env'])->save();
        $this->get('/admin/students/'.$p->id.'/photo')->assertNotFound();
        $this->get('/storage/../../.env')->assertNotFound();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
