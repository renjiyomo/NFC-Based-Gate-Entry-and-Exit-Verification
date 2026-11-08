<?php

namespace App\Services;

use App\Enums\PersonType;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PersonManagement
{
    public function __construct(private ManagementGuard $guard, private AuditLogger $audit, private PersonPhotos $photos) {}

    public function save(User $actor, PersonType $type, array $data, ?Person $target = null, ?UploadedFile $photo = null): Person
    {
        $newPhoto = $photo ? $this->photos->store($photo) : null;
        $oldPhoto = null;
        try {
            $person = DB::transaction(function () use ($actor, $type, $data, $target, $newPhoto, &$oldPhoto): Person {
                $actor = $this->guard->actor($actor);
                $person = $target ? Person::withTrashed()->lockForUpdate()->findOrFail($target->id) : new Person(['person_type' => $type]);
                abort_unless($person->person_type === $type, 404);
                if ($target) {
                    $this->guard->version($person, (int) $data['record_version']);
                    $this->guard->editable($person);
                }
                $person->fill(Arr::only($data, ['campus_number', 'full_name', 'status']));
                if ($newPhoto || ($data['remove_photo'] ?? false)) {
                    $oldPhoto = $person->photo_path;
                    $person->photo_path = $newPhoto;
                }
                $person->forceFill(['record_version' => $target ? (int) $person->record_version + 1 : 1])->save();
                $relation = $type === PersonType::Student ? $person->studentProfile() : $person->employeeProfile();
                $fields = $type === PersonType::Student ? ['program', 'year_level', 'academic_term'] : ['department', 'position'];
                $relation->updateOrCreate(['person_id' => $person->id], Arr::only($data, $fields));
                $this->audit->record($target ? 'person.updated' : 'person.created', $actor, $person, ['status' => $person->status->value]);

                return $person;
            }, 3);
        } catch (\Throwable $exception) {
            $this->photos->remove($newPhoto);
            throw $exception;
        }
        $this->photos->remove($oldPhoto);

        return $person;
    }

    public function archive(User $actor, Person $target, int $version, bool $restore = false): Person
    {
        return DB::transaction(function () use ($actor, $target, $version, $restore): Person {
            $actor = $this->guard->actor($actor);
            $person = Person::withTrashed()->lockForUpdate()->findOrFail($target->id);
            $this->guard->version($person, $version);
            if ($restore ? ! $person->trashed() : $person->trashed()) {
                return $person;
            }
            $person->forceFill(['status' => 'inactive', 'record_version' => (int) $person->record_version + 1])->save();
            if ($restore) {
                $person->restore();
            } else {
                $person->authorizations()->where('status', 'active')->whereNull('revoked_at')->update(['status' => 'revoked', 'revoked_at' => now(), 'revoked_by' => $actor->id, 'updated_at' => now()]);
                $person->delete();
            }
            $this->audit->record($restore ? 'person.restored' : 'person.archived', $actor, $person);

            return $person;
        }, 3);
    }
}
