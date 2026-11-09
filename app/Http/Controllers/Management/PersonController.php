<?php

namespace App\Http\Controllers\Management;

use App\Enums\PersonType;
use App\Http\Requests\ManagementRequest;
use App\Models\Person;
use App\Services\Credentials\CredentialData;
use App\Services\PersonManagement;
use App\Support\CampusCatalog;
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
        $catalog = CampusCatalog::options();
        $groups = $type === PersonType::Student ? array_column($catalog['programs'], 'code') : $catalog['departments'];
        abort_if($filters['group'] !== '' && ! in_array($filters['group'], [...$groups, '__legacy__'], true), 422, 'Choose a directory option.');
        $maxYear = max(array_column($catalog['programs'], 'max_year_level'));
        abort_if($filters['year_level'] !== '' && ($type !== PersonType::Student || ! in_array($filters['year_level'], [...array_map('strval', range(1, $maxYear)), '__legacy__'], true)), 422, 'Choose a year level.');
        $relation = $type === PersonType::Student ? 'studentProfile' : 'employeeProfile';
        $query = DirectoryData::records(Person::where('person_type', $type)->with([$relation, 'activeCredentialAssignment.credential']), $filters);
        if ($filters['q'] !== '') {
            $like = DirectoryData::like($filters['q']);
            $query->where(fn ($q) => $q->whereRaw("full_name LIKE ? ESCAPE '!'", [$like])->orWhereRaw("campus_number LIKE ? ESCAPE '!'", [$like]));
        }
        if ($filters['group'] !== '') {
            $column = $type === PersonType::Student ? 'program' : 'department';
            $query->whereHas($relation, fn ($q) => $filters['group'] === '__legacy__'
                ? $q->where(fn ($old) => $old->whereNotIn($column, $groups)->orWhereNull($column))
                : $q->where($column, $filters['group']));
        }
        if ($filters['year_level'] !== '') {
            $query->whereHas('studentProfile', fn ($q) => $filters['year_level'] === '__legacy__'
                ? $q->where(fn ($old) => $old->whereNotBetween('year_level', [1, $maxYear])->orWhereNull('year_level'))
                : $q->where('year_level', (int) $filters['year_level']));
        }

        return Inertia::render('management/PeopleIndex', ['directory' => $directory, 'filters' => $filters, 'catalog' => $catalog,
            'records' => $query->orderBy('full_name')->orderBy('id')->paginate((int) $filters['per_page'])->appends($filters)->through(fn (Person $person) => DirectoryData::person($person))]);
    }

    public function create(string $directory): Response
    {
        Gate::authorize('create', Person::class);

        return Inertia::render('management/PersonForm', ['directory' => $directory, 'record' => null, 'catalog' => CampusCatalog::options()]);
    }

    public function store(ManagementRequest $request, string $directory, PersonManagement $people): RedirectResponse
    {
        $person = $request->run(fn () => $people->save($request->user(), $this->type($directory), $request->validated(), photo: $request->file('photo')));

        return redirect('/admin/'.$directory.'/'.$person->id)->with('status', 'Person record created. Issue and activate an NFC credential to enable scanning.');
    }

    public function show(string $directory, Person $person): Response
    {
        $this->person($directory, $person);
        $person->load(['studentProfile', 'employeeProfile']);

        return Inertia::render('management/PersonShow', ['directory' => $directory, 'record' => DirectoryData::person($person),
            'credentials' => $person->credentialAssignments()->with('credential.assignment.person', 'credential.replaces')->orderByRaw('id = ? DESC', [$person->active_credential_assignment_id ?? 0])->latest('id')->limit(10)->get()->map(fn ($assignment) => CredentialData::one($assignment->credential))]);
    }

    public function edit(string $directory, Person $person): Response
    {
        $this->person($directory, $person, 'update');
        abort_if($person->trashed(), 404);

        return Inertia::render('management/PersonForm', ['directory' => $directory, 'record' => DirectoryData::person($person->load(['studentProfile', 'employeeProfile'])), 'catalog' => CampusCatalog::options()]);
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

        return redirect('/admin/'.$directory)->with('status', 'Person archived and NFC credentials closed. History is retained.');
    }

    public function restore(ManagementRequest $request, string $directory, Person $person, PersonManagement $people): RedirectResponse
    {
        $this->person($directory, $person, 'archive');
        $request->run(fn () => $people->archive($request->user(), $person, $request->integer('record_version'), true));

        return redirect('/admin/'.$directory.'/'.$person->id)->with('status', 'Person restored as inactive. Review the record before enabling it and issuing a new coin.');
    }

    public function photo(string $directory, Person $person): BinaryFileResponse
    {
        $this->person($directory, $person);
        abort_unless($person->photo_path && preg_match('/^person-photos\/[a-f0-9-]{36}\.jpg$/D', $person->photo_path) && Storage::disk('local')->exists($person->photo_path), 404);

        return response()->file(Storage::disk('local')->path($person->photo_path), ['Content-Type' => 'image/jpeg', 'Content-Disposition' => 'inline; filename="person-photo.jpg"']);
    }
}
