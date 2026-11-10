<?php

namespace Tests\Feature;

use App\Models\Gate;
use App\Models\NfcCredential;
use App\Models\Person;
use App\Services\Credentials\CredentialLifecycle;
use App\Services\PersonPhotos;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToCheckFileExistence;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Tests\ManagementTestCase;

class CloudPhotosTest extends ManagementTestCase
{
    public function test_private_object_storage_upload_read_replace_and_remove_keep_authorized_routes(): void
    {
        config(['nfcheck.photo_disk' => 's3']);
        Storage::fake('local');
        Storage::fake('s3');
        $data = $this->student();
        $this->post('/admin/students', [...$data, 'photo' => UploadedFile::fake()->image('private.png', 64, 64)])->assertSessionHasNoErrors();
        $person = Person::where('campus_number', $data['campus_number'])->sole();
        $old = $person->photo_path;
        Storage::disk('s3')->assertExists($old);
        $this->assertCount(0, Storage::disk('local')->allFiles());
        $url = '/admin/students/'.$person->id.'/photo';
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertStreamedContent(Storage::disk('s3')->get($old));
        $this->signIn($this->user(['role' => 'guard']))->get($url)->assertForbidden();
        $this->signIn($this->admin);
        $this->post('/admin/students/'.$person->id, [...$data, '_method' => 'put', 'record_version' => 1, 'photo' => UploadedFile::fake()->image('new.png', 32, 32)])->assertSessionHasNoErrors();
        Storage::disk('s3')->assertMissing($old);
        $new = $person->fresh()->photo_path;
        Storage::disk('s3')->assertExists($new);
        $this->put('/admin/students/'.$person->id, [...$data, 'record_version' => 2, 'remove_photo' => true])->assertSessionHasNoErrors();
        Storage::disk('s3')->assertMissing($new);
        $this->get($url)->assertNotFound();
    }

    public function test_object_response_never_requires_a_local_filesystem_path(): void
    {
        config(['nfcheck.photo_disk' => 's3']);
        $path = 'person-photos/11111111-1111-4111-8111-111111111111.jpg';
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, 'synthetic image bytes');
        rewind($stream);
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->once()->with($path)->andReturnTrue();
        $disk->shouldReceive('readStream')->once()->with($path)->andReturn($stream);
        $disk->shouldNotReceive('path');
        Storage::shouldReceive('disk')->once()->with('s3')->andReturn($disk);
        $response = app(PersonPhotos::class)->response($path);
        ob_start();
        $response->sendContent();
        $this->assertSame('synthetic image bytes', ob_get_clean());
        $this->assertFalse(is_resource($stream));
    }

    public function test_photo_storage_rejects_public_disks_and_unsafe_keys(): void
    {
        $photos = app(PersonPhotos::class);
        $this->assertFalse($photos->exists('../secrets.json'));
        config(['nfcheck.photo_disk' => 'public']);
        $this->expectException(\LogicException::class);
        $photos->exists('person-photos/11111111-1111-4111-8111-111111111111.jpg');
    }

    public function test_gate_photo_streams_from_object_storage_only_for_the_scan_owner_and_activity_window(): void
    {
        config(['nfcheck.photo_disk' => 's3']);
        Storage::fake('s3');
        $path = app(PersonPhotos::class)->store(UploadedFile::fake()->image('test.png', 32, 32));
        $person = $this->makePerson();
        $person->forceFill(['photo_path' => $path])->save();
        $service = app(CredentialLifecycle::class);
        $receipt = $service->issue($this->admin, ['person_id' => $person->id, 'record_version' => $person->record_version, 'label' => 'CLOUD-'.Str::random(8), 'mode' => 'new', 'request_id' => (string) Str::uuid()], 'issuer', 'physical');
        $credential = NfcCredential::where('public_id', $receipt['credential']['id'])->sole();
        $service->readBack($this->admin, $credential, 'issuer', $receipt['payload'], $receipt['completion_nonce'], 'physical');
        $service->activate($this->admin, $credential, 'issuer', $receipt['payload'], $receipt['completion_nonce'], 'physical');
        $gate = Gate::create(['code' => 'CLOUD-'.Str::random(8), 'name' => 'Synthetic gate', 'status' => 'active']);
        $guard = $this->user(['role' => 'guard', 'gate_id' => $gate->id]);
        $this->signIn($guard);
        $scan = $this->postJson('/guard/gates/'.$gate->id.'/automatic-scan', ['request_id' => (string) Str::uuid(), 'payload' => $receipt['payload']])->assertOk();
        $this->withCredentials()->withCookie(config('session.cookie'), app('session')->getId());
        $url = $scan->json('identity.photo_url');
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertStreamedContent(Storage::disk('s3')->get($path));
        $this->travel(31)->minutes();
        $this->get($url)->assertNotFound();
    }

    public function test_storage_failures_do_not_expose_provider_request_details(): void
    {
        config(['nfcheck.photo_disk' => 's3']);
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->once()->andThrow(UnableToCheckFileExistence::forLocation('synthetic-private-key', new \RuntimeException('signed-secret-marker')));
        Storage::shouldReceive('disk')->once()->with('s3')->andReturn($disk);
        try {
            app(PersonPhotos::class)->exists('person-photos/11111111-1111-4111-8111-111111111111.jpg');
            $this->fail('The storage failure was not reported.');
        } catch (ServiceUnavailableHttpException $e) {
            $this->assertStringNotContainsString('signed-secret-marker', $e->getMessage());
            $this->assertNull($e->getPrevious());
        }
    }

    public function test_cloud_recovery_routes_are_unavailable_without_email_delivery(): void
    {
        config(['nfcheck.password_recovery' => false]);
        $this->post('/logout');
        $this->get('/forgot-password')->assertNotFound();
        $this->post('/forgot-password', ['email' => 'test@example.invalid'])->assertNotFound();
        $this->get('/reset-password/synthetic-token')->assertNotFound();
    }
}
