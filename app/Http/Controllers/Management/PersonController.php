<?php

namespace App\Http\Controllers\Management;

use App\Enums\PersonType;
use App\Http\Requests\ManagementRequest;
use App\Models\Person;
use App\Services\Credentials\CredentialData;
use App\Services\PersonManagement;
use App\Support\DirectoryData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PersonController
{
    private function type(string $directory): PersonType
    {
        return $directory === 'students' ? PersonType::Student : PersonType::Employee;
    }

    private function person(string $directory, Person $person, string $ability = 'view'): void
    {
        Gate::authorize($ability, $person);
        abort_unless($person->person_type === $this->type($directory), 404);
    }

    public function index(Request $request, string $directory): Response
    {
        Gate::authorize('viewAny', Person::class);
        $filters = DirectoryData::filters($request);
        $type = $this->type($directory);
        $relation = $type === PersonType::Student ? 'studentProfile' : 'employeeProfile';
        $query = DirectoryData::records(Person::where('person_type', $type)->with($relation), $filters)
            ->withExists(['authorizations as access_active' => fn ($q) => $q->effective()]);
        if ($filters['q'] !== '') {
            $like = DirectoryData::like($filters['q']);
            $query->where(fn ($q) => $q->whereRaw("full_name LIKE ? ESCAPE '!'", [$like])->orWhereRaw("campus_number LIKE ? ESCAPE '!'", [$like]));
        }
        if ($filters['group'] !== '') {
            $query->whereHas($relation, fn ($q) => $q->whereRaw(($type === PersonType::Student ? 'program' : 'department')." LIKE ? ESCAPE '!'", [DirectoryData::like($filters['group'])]));
        }
        if ($filters['access']) {
            $method = $filters['access'] === 'authorized' ? 'whereHas' : 'whereDoesntHave';
            $query->$method('authorizations', fn ($q) => $q->effective());
        }

        return Inertia::render('management/PeopleIndex', ['directory' => $directory, 'filters' => $filters,
            'records' => $query->orderBy('full_name')->orderBy('id')->paginate((int) $filters['per_page'])->appends($filters)->through(fn (Person $person) => DirectoryData::person($person))]);
    }

    public function create(string $directory): Response
    {
        Gate::authorize('create', Person::class);

        return Inertia::render('management/PersonForm', ['directory' => $directory, 'record' => null]);
    }

    public function store(ManagementRequest $request, string $directory, PersonManagement $people): RedirectResponse
    {
        $person = $request->run(fn () => $people->save($request->user(), $this->type($directory), $request->validated(), photo: $request->file('photo')));

        return redirect('/admin/'.$directory.'/'.$person->id)->with('status', 'Person record created. Campus access requires a separate grant.');
    }

    public function show(string $directory, Person $person): Response
    {
        $this->person($directory, $person);
        $person->load(['studentProfile', 'employeeProfile'])->loadExists(['authorizations as access_active' => fn ($q) => $q->effective()]);

        return Inertia::render('management/PersonShow', ['directory' => $directory, 'record' => DirectoryData::person($person),
            'credentials' => $person->credentialAssignments()->with('credential.assignment.person', 'credential.replaces')->latest('id')->limit(10)->get()->map(fn ($assignment) => CredentialData::one($assignment->credential)),
            'grants' => $person->authorizations()->latest('id')->paginate(10)->through(fn ($grant) => ['id' => $grant->id, 'starts_at' => $grant->valid_from->toIso8601String(), 'ends_at' => $grant->valid_until?->toIso8601String(), 'reason' => $grant->reason, 'state' => DirectoryData::intervalState($grant->valid_from, $grant->valid_until, $grant->revoked_at), 'revoked_at' => $grant->revoked_at?->toIso8601String()])]);
    }

    public function edit(string $directory, Person $person): Response
    {
        $this->person($directory, $person, 'update');
        abort_if($person->trashed(), 404);

        return Inertia::render('management/PersonForm', ['directory' => $directory, 'record' => DirectoryData::person($person->load(['studentProfile', 'employeeProfile']))]);
    }

    public function update(ManagementRequest $request, string $directory, Person $person, PersonManagement $people): RedirectResponse
    {
        $this->person($directory, $person, 'update');
        $request->run(fn () => $people->save($request->user(), $this->type($directory), $request->validated(), $person, $request->file('photo')));

        return redirect('/admin/'.$directory.'/'.$person->id)->with('status', 'Person record updated.');
    }

    public function destroy(ManagementRequest $request, string $directory, Person $person, PersonManagement $people): RedirectResponse
    {
        $this->person($directory, $person, 'archive');
        $request->run(fn () => $people->archive($request->user(), $person, $request->integer('record_version')));

        return redirect('/admin/'.$directory)->with('status', 'Record archived and access grants revoked. Its history is retained.');
    }

    public function restore(ManagementRequest $request, string $directory, Person $person, PersonManagement $people): RedirectResponse
    {
        $this->person($directory, $person, 'archive');
        $request->run(fn () => $people->archive($request->user(), $person, $request->integer('record_version'), true));

        return redirect('/admin/'.$directory.'/'.$person->id)->with('status', 'Record restored as inactive. Review it before activating or granting access.');
    }

    public function photo(string $directory, Person $person): BinaryFileResponse
    {
        $this->person($directory, $person);
        abort_unless($person->photo_path && preg_match('/^person-photos\/[a-f0-9-]{36}\.jpg$/D', $person->photo_path) && Storage::disk('local')->exists($person->photo_path), 404);

        return response()->file(Storage::disk('local')->path($person->photo_path), ['Content-Type' => 'image/jpeg', 'Content-Disposition' => 'inline; filename="person-photo.jpg"']);
    }
}
