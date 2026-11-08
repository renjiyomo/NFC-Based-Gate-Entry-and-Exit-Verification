<?php

namespace App\Http\Controllers\Management;

use App\Http\Requests\ManagementRequest;
use App\Models\AccessAuthorization;
use App\Models\Person;
use App\Services\AccessManagement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class AuthorizationController
{
    public function store(ManagementRequest $request, string $directory, Person $person, AccessManagement $access): RedirectResponse
    {
        Gate::authorize('update', $person);
        abort_unless($person->person_type->value === ($directory === 'students' ? 'student' : 'employee'), 404);
        $request->run(fn () => $access->grant($request->user(), $person, $request->validated()));

        return redirect('/admin/'.$directory.'/'.$person->id)->with('status', 'Campus access grant saved. Credential issuance is a separate workflow.');
    }

    public function destroy(ManagementRequest $request, string $directory, Person $person, AccessAuthorization $grant, AccessManagement $access): RedirectResponse
    {
        Gate::authorize('update', $person);
        abort_unless($person->person_type->value === ($directory === 'students' ? 'student' : 'employee'), 404);
        $request->run(fn () => $access->revokeGrant($request->user(), $person, $grant));

        return redirect('/admin/'.$directory.'/'.$person->id)->with('status', 'Campus access grant revoked.');
    }
}
