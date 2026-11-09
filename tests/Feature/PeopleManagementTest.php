<?php

namespace Tests\Feature;

use App\Enums\PersonType;
use App\Models\AuditLog;
use App\Models\Person;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\ManagementTestCase;

class PeopleManagementTest extends ManagementTestCase
{
    public static function directories(): array
    {
        return [['students'], ['employees']];
    }

    #[DataProvider('directories')]
    public function test_person_and_profile_create_edit_and_detail_persist_in_mysql(string $directory): void
    {
        $data = $directory === 'students' ? $this->student() : $this->employee();
        $this->get('/admin/'.$directory.'/create')->assertOk();
        $this->post('/admin/'.$directory, [...$data, 'person_type' => 'visitor', 'role' => 'admin'])->assertRedirect()->assertSessionHasNoErrors();
        $person = Person::where('campus_number', $data['campus_number'])->sole();
        $this->assertSame($directory === 'students' ? PersonType::Student : PersonType::Employee, $person->person_type);
        $table = $directory === 'students' ? 'student_profiles' : 'employee_profiles';
        $field = $directory === 'students' ? 'program' : 'department';
        $this->assertDatabaseHas($table, ['person_id' => $person->id, $field => $data[$field]]);
        $this->get('/admin/'.$directory.'/'.$person->id)->assertOk()->assertInertia(fn (Assert $page) => $page->component('management/PersonShow')->where('record.full_name', $data['full_name'])->where('record.access_active', false)->missing('record.photo_path'));
        $this->get('/admin/'.$directory.'/'.$person->id.'/edit')->assertOk();
        $this->put('/admin/'.$directory.'/'.$person->id, [...$data, 'full_name' => 'Updated synthetic identity', $field => ($directory === 'students' ? 'BSIT' : 'Engineering Department'), 'record_version' => 1])->assertSessionHasNoErrors()->assertRedirect('/admin/'.$directory.'/'.$person->id);
        $this->assertSame('Updated synthetic identity', $person->fresh()->full_name);
        $this->assertSame(2, $person->fresh()->record_version);
        $this->assertDatabaseHas($table, ['person_id' => $person->id, $field => ($directory === 'students' ? 'BSIT' : 'Engineering Department')]);
        $other = $directory === 'students' ? 'employees' : 'students';
        $this->get('/admin/'.$other.'/'.$person->id)->assertNotFound();
        $this->put('/admin/'.$other.'/'.$person->id, [...$data, 'record_version' => 2])->assertNotFound();
    }

    public function test_identity_validation_and_classification_uniqueness_include_archived_records(): void
    {
        $data = $this->student(['campus_number' => 'TEST-001']);
        $this->post('/admin/students', $data)->assertSessionHasNoErrors();
        $person = Person::where('campus_number', 'TEST-001')->sole();
        $this->post('/admin/students', $data)->assertSessionHasErrors('campus_number');
        $this->delete('/admin/students/'.$person->id, ['record_version' => 1])->assertRedirect('/admin/students');
        $this->post('/admin/students', $data)->assertSessionHasErrors('campus_number');
        $this->post('/admin/employees', $this->employee(['campus_number' => 'TEST-001']))->assertSessionHasNoErrors();
        $this->post('/admin/students', $this->student(['full_name' => str_repeat('X', 121), 'year_level' => 0, 'status' => 'superuser']))->assertSessionHasErrors(['full_name', 'year_level', 'status']);
        $this->post('/admin/employees', $this->employee(['department' => '', 'position' => "bad\nposition"]))->assertSessionHasErrors(['department', 'position']);
    }

    public function test_stale_edits_do_not_overwrite_newer_changes(): void
    {
        $person = $this->makePerson();
        $data = $this->student(['campus_number' => $person->campus_number, 'full_name' => 'First saved revision', 'record_version' => 1]);
        $this->put('/admin/students/'.$person->id, $data)->assertSessionHasNoErrors();
        $this->put('/admin/students/'.$person->id, [...$data, 'full_name' => 'Stale replacement'])->assertRedirect('/admin/students/'.$person->id.'/edit')->assertSessionHasErrors('record_version');
        $this->assertSame('First saved revision', $person->fresh()->full_name);
    }

    public function test_archive_and_restore_retain_identity_and_profile_but_revoke_access(): void
    {
        $person = $this->makePerson();
        $grant = $person->authorizations()->create(['status' => 'active', 'valid_from' => now()->subMinute(), 'granted_by' => $this->admin->id]);
        $this->delete('/admin/students/'.$person->id, ['record_version' => 1])->assertRedirect('/admin/students');
        $archived = Person::withTrashed()->findOrFail($person->id);
        $this->assertTrue($archived->trashed());
        $this->assertSame('inactive', $archived->status->value);
        $this->assertNotNull($grant->fresh()->revoked_at);
        $this->assertDatabaseHas('student_profiles', ['person_id' => $person->id]);
        $this->get('/admin/students')->assertInertia(fn (Assert $page) => $page->where('records.total', 0));
        $this->get('/admin/students?records=archived')->assertInertia(fn (Assert $page) => $page->where('records.total', 1));
        $this->get('/admin/students/'.$person->id)->assertOk();
        $this->post('/admin/students/'.$person->id.'/restore', ['record_version' => 2])->assertRedirect('/admin/students/'.$person->id);
        $restored = Person::findOrFail($person->id);
        $this->assertSame('inactive', $restored->status->value);
        $this->assertSame(3, $restored->record_version);
        $this->assertNotNull($grant->fresh()->revoked_at);
        $this->assertSame(1, AuditLog::where('action', 'person.archived')->count());
    }

    public function test_search_filters_pagination_and_literal_wildcards_are_server_backed(): void
    {
        for ($i = 0; $i < 17; $i++) {
            $this->makePerson('students', ['full_name' => sprintf('Synthetic identity %02d', $i), 'program' => $i < 10 ? 'BSCS' : 'BSCpE']);
        }
        $this->makePerson('employees', ['full_name' => 'Synthetic employee']);
        $this->makePerson('students', ['full_name' => 'Literal %_ identity', 'status' => 'inactive']);
        $this->get('/admin/students?per_page=15')->assertInertia(fn (Assert $page) => $page->where('records.total', 18)->has('records.data', 15)->where('records.current_page', 1));
        $this->get('/admin/students?per_page=15&page=2')->assertInertia(fn (Assert $page) => $page->has('records.data', 3)->where('records.current_page', 2));
        $this->get('/admin/students?group=BSCS&status=active')->assertInertia(fn (Assert $page) => $page->where('records.total', 10));
        $this->get('/admin/students?q=%25_')->assertInertia(fn (Assert $page) => $page->where('records.total', 1)->where('records.data.0.full_name', 'Literal %_ identity'));
        $this->get('/admin/students?q='.urlencode("' OR 1=1 --"))->assertInertia(fn (Assert $page) => $page->where('records.total', 0));
        $this->get('/admin/students?per_page=10000')->assertStatus(422);
        $this->get('/admin/students?q[]=invalid')->assertStatus(422);
    }

    public function test_directory_dropdowns_use_exact_catalogue_values_and_keep_older_records_findable(): void
    {
        $it = $this->makePerson('students', ['program' => 'BSIT', 'year_level' => 1]);
        $this->makePerson('students', ['program' => 'BSIT Animation', 'year_level' => 2]);
        $older = $this->makePerson('students', ['program' => 'Older stored programme', 'year_level' => 7]);
        $this->makePerson('employees', ['department' => 'Nursing Department']);
        $this->makePerson('employees', ['department' => 'Computer Studies Department']);
        $this->makePerson('employees', ['department' => 'Older stored department']);
        $this->get('/admin/students?group=BSIT&year_level=1')->assertInertia(fn (Assert $p) => $p->has('catalog.programs', 17)->where('records.total', 1)->where('records.data.0.id', $it->id));
        $this->get('/admin/students?group=__legacy__&year_level=__legacy__')->assertInertia(fn (Assert $p) => $p->where('records.total', 1)->where('records.data.0.id', $older->id));
        $this->get('/admin/students?page=2')->assertInertia(fn (Assert $p) => $p->missing('filters.page'));
        $this->get('/admin/employees?group='.urlencode('Nursing Department'))->assertInertia(fn (Assert $p) => $p->has('catalog.departments', 6)->where('records.total', 1));
        $this->get('/admin/employees?group=__legacy__')->assertInertia(fn (Assert $p) => $p->where('records.total', 1));
        foreach (['group=Invented', 'group[]=BSIT', 'year_level[]=1', 'year_level=999'] as $filter) {
            $this->get('/admin/students?'.$filter)->assertStatus(422);
        }
        $this->get('/admin/employees?year_level=1')->assertStatus(422);
    }

    public function test_photos_are_reencoded_privately_and_only_authorized_admins_can_read_them(): void
    {
        Storage::fake('local');
        $data = $this->student();
        $this->post('/admin/students', [...$data, 'photo' => UploadedFile::fake()->image('untrusted-name.png', 1200, 900)])->assertSessionHasNoErrors();
        $person = Person::where('campus_number', $data['campus_number'])->sole();
        $this->assertMatchesRegularExpression('/^person-photos\/[a-f0-9-]+\.jpg$/', $person->photo_path);
        $path = Storage::disk('local')->path($person->photo_path);
        $size = getimagesize($path);
        $this->assertSame('image/jpeg', $size['mime']);
        $this->assertLessThanOrEqual(800, max($size[0], $size[1]));
        $url = '/admin/students/'.$person->id.'/photo';
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('Cache-Control', 'no-store, private');
        $this->signIn($this->user(['role' => 'guard']))->get($url)->assertForbidden();
        $this->get('/storage/'.$person->photo_path)->assertNotFound();
        $this->signIn($this->admin);
        $oldPath = $person->photo_path;
        $this->post('/admin/students/'.$person->id, [...$data, '_method' => 'put', 'record_version' => 1, 'photo' => UploadedFile::fake()->image('replacement.webp', 64, 64)])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($oldPath);
        $this->assertNotSame($oldPath, $person->fresh()->photo_path);
        $this->put('/admin/students/'.$person->id, [...$data, 'record_version' => 2, 'remove_photo' => true])->assertSessionHasNoErrors();
        $this->assertNull($person->fresh()->photo_path);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_unsafe_or_oversized_photos_are_rejected(): void
    {
        Storage::fake('local');
        foreach ([UploadedFile::fake()->create('payload.svg', 10, 'image/svg+xml'), UploadedFile::fake()->image('too-wide.png', 4097, 33), UploadedFile::fake()->image('too-large.jpg')->size(2049)] as $photo) {
            $this->post('/admin/students', [...$this->student(), 'photo' => $photo])->assertSessionHasErrors('photo');
        }
        $this->assertSame(0, Person::count());
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_photo_reencoding_discards_embedded_payload_and_failed_updates_keep_the_old_photo(): void
    {
        Storage::fake('local');
        $upload = UploadedFile::fake()->image('portrait.png', 64, 64);
        file_put_contents($upload->getRealPath(), '<?php synthetic_payload_marker(); ?>', FILE_APPEND);
        $data = $this->student();
        $this->post('/admin/students', [...$data, 'photo' => $upload])->assertSessionHasNoErrors();
        $person = Person::where('campus_number', $data['campus_number'])->sole();
        $old = $person->photo_path;
        $this->assertStringNotContainsString('synthetic_payload_marker', Storage::disk('local')->get($old));
        $audit = \Mockery::mock(AuditLogger::class);
        $audit->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit unavailable'));
        $this->app->instance(AuditLogger::class, $audit);
        $this->post('/admin/students/'.$person->id, [...$data, '_method' => 'put', 'record_version' => 1, 'photo' => UploadedFile::fake()->image('new.png', 64, 64)])->assertStatus(500);
        $this->assertSame($old, $person->fresh()->photo_path);
        Storage::disk('local')->assertExists($old);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_audit_failure_rolls_back_profile_and_cleans_up_new_photo(): void
    {
        Storage::fake('local');
        $audit = \Mockery::mock(AuditLogger::class);
        $audit->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit unavailable'));
        $this->app->instance(AuditLogger::class, $audit);
        $data = $this->student();
        $this->post('/admin/students', [...$data, 'photo' => UploadedFile::fake()->image('photo.png', 64, 64)])->assertStatus(500);
        $this->assertDatabaseMissing('persons', ['campus_number' => $data['campus_number']]);
        $this->assertDatabaseCount('student_profiles', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }
}
