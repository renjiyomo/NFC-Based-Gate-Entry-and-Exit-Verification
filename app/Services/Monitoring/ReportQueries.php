<?php

namespace App\Services\Monitoring;

use App\Models\AccessEvent;
use App\Models\Gate;
use App\Models\NfcCredential;
use App\Models\Person;
use App\Models\ScanAttempt;
use App\Support\DatabaseSql;
use App\Support\DirectoryData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReportQueries
{
    private function filters(Builder $query, ReportFilters $filters): Builder
    {
        $v = $filters->values;
        foreach (['gate_id', 'guard_id', 'direction'] as $key) {
            if ($v[$key] !== '') {
                $query->where($key, $v[$key]);
            }
        }
        if ($v['q'] !== '' || $v['kind'] !== '') {
            $query->whereHas('person', function ($p) use ($v): void {
                if ($v['kind'] !== '') {
                    $p->where('person_type', $v['kind']);
                }
                if ($v['q'] !== '') {
                    $like = DirectoryData::like($v['q']);
                    $p->where(fn ($s) => $s->whereRaw("LOWER(full_name) LIKE LOWER(?) ESCAPE '!'", [$like])->orWhereRaw("LOWER(campus_number) LIKE LOWER(?) ESCAPE '!'", [$like]));
                }
            });
        }

        return $query;
    }

    public function events(ReportFilters $f, string $source, bool $effective = true): Builder
    {
        $q = AccessEvent::query()->where('source', $source)->where('confirmed_at', '>=', $f->from->format('Y-m-d H:i:s.u'))->where('confirmed_at', '<', $f->until->format('Y-m-d H:i:s.u'))->where('confirmed_at', '<=', $f->asOf->format('Y-m-d H:i:s.u'));
        if ($effective) {
            $q->effective();
        }

        return $this->filters($q, $f);
    }

    public function attempts(ReportFilters $f, string $source): Builder
    {
        return $this->filters(ScanAttempt::query()->where('source', $source)->where('received_at', '>=', $f->from->format('Y-m-d H:i:s.u'))->where('received_at', '<', $f->until->format('Y-m-d H:i:s.u'))->where('received_at', '<=', $f->asOf->format('Y-m-d H:i:s.u')), $f);
    }

    public function history(ReportFilters $f, string $source): Builder
    {
        $v = $f->values;
        if ($v['view'] === 'passages') {
            $q = $this->events($f, $source, $v['validity'] === 'effective');
            if ($v['validity'] === 'voided') {
                $q->whereHas('latestDecision', fn ($d) => $d->where('action', 'void'));
            }

            return $q->with(['person', 'attempt.credential', 'gate', 'guardUser', 'latestDecision'])->orderByDesc('confirmed_at')->orderByDesc('id');
        }
        $q = $this->attempts($f, $source);
        if ($v['outcome'] !== '') {
            $q->where('outcome', $v['outcome']);
        }
        if ($v['review'] === 'duplicate') {
            $q->whereNotNull('duplicate_of_event_id');
        } elseif ($v['review'] === 'expired') {
            $q->where(fn ($s) => $s->where('review_status', 'expired')->orWhere(fn ($p) => $p->where('review_status', 'pending')->where('review_expires_at', '<=', $f->asOf->format('Y-m-d H:i:s.u'))));
        } elseif ($v['review'] !== '') {
            $q->where('review_status', $v['review']);
            if ($v['review'] === 'pending') {
                $q->where('review_expires_at', '>', $f->asOf->format('Y-m-d H:i:s.u'));
            }
        }

        return $q->with(['person', 'credential', 'gate', 'guardUser', 'passage.latestDecision', 'duplicate'])->orderByDesc('received_at')->orderByDesc('id');
    }

    public function summary(ReportFilters $f, string $source): array
    {
        $events = $this->events($f, $source);
        $counts = (clone $events)->selectRaw("COUNT(*) total, COALESCE(SUM(CASE WHEN direction='entry' THEN 1 ELSE 0 END),0) entries, COALESCE(SUM(CASE WHEN direction='exit' THEN 1 ELSE 0 END),0) exits, COUNT(DISTINCT person_id) people")->first();
        $attempts = $this->attempts($f, $source);
        $checks = (clone $attempts)->selectRaw("COUNT(*) total, COALESCE(SUM(CASE WHEN outcome='denied' THEN 1 ELSE 0 END),0) denied, COALESCE(SUM(CASE WHEN duplicate_of_event_id IS NOT NULL THEN 1 ELSE 0 END),0) duplicates")->first();
        $date = DatabaseSql::manilaDate();
        $hour = DatabaseSql::manilaHour();
        $days = (clone $events)->selectRaw("$date AS report_date, SUM(CASE WHEN direction='entry' THEN 1 ELSE 0 END) entries, SUM(CASE WHEN direction='exit' THEN 1 ELSE 0 END) exits")->groupByRaw($date)->get()->keyBy('report_date');
        $trend = [];
        for ($day = $f->from->setTimezone('Asia/Manila'); $day->lessThan($f->until) && $day->lessThanOrEqualTo($f->asOf); $day = $day->addDay()) {
            $row = $days->get($day->toDateString());
            $trend[] = ['date' => $day->toDateString(), 'entries' => (int) ($row?->entries ?? 0), 'exits' => (int) ($row?->exits ?? 0)];
        }
        $hourRows = (clone $events)->selectRaw("$hour AS report_hour, COUNT(*) total")->groupByRaw($hour)->pluck('total', 'report_hour');
        $hourly = array_map(fn ($h) => ['label' => sprintf('%02d:00', $h), 'value' => (int) ($hourRows[$h] ?? 0)], range(0, 23));
        $gateRows = (clone $events)->join('gates as rg', 'rg.id', '=', 'access_events.gate_id')->selectRaw("rg.id, rg.code, rg.name, SUM(CASE WHEN access_events.direction='entry' THEN 1 ELSE 0 END) entries, SUM(CASE WHEN access_events.direction='exit' THEN 1 ELSE 0 END) exits")->groupBy('rg.id', 'rg.code', 'rg.name')->orderBy('rg.code')->get()->map(fn ($r) => ['label' => $r->code.' · '.$r->name, 'entries' => (int) $r->entries, 'exits' => (int) $r->exits])->all();
        $kindRows = (clone $events)->join('persons as rp', 'rp.id', '=', 'access_events.person_id')->selectRaw("rp.person_type kind, SUM(CASE WHEN access_events.direction='entry' THEN 1 ELSE 0 END) entries, SUM(CASE WHEN access_events.direction='exit' THEN 1 ELSE 0 END) exits")->groupBy('rp.person_type')->get()->keyBy('kind');
        $kinds = array_map(fn ($kind) => ['label' => ucfirst($kind), 'entries' => (int) ($kindRows->get($kind)?->entries ?? 0), 'exits' => (int) ($kindRows->get($kind)?->exits ?? 0)], ['student', 'employee']);
        $reasons = (clone $attempts)->where('outcome', 'denied')->selectRaw('reason label, COUNT(*) value')->groupBy('reason')->orderByDesc('value')->orderBy('reason')->get()->map(fn ($r) => ['label' => $r->label, 'value' => (int) $r->value])->all();

        return ['entries' => (int) $counts->entries, 'exits' => (int) $counts->exits, 'passages' => (int) $counts->total, 'unique_people' => (int) $counts->people, 'attempts' => (int) $checks->total, 'denied' => (int) $checks->denied, 'duplicates' => (int) $checks->duplicates, 'voided' => $this->events($f, $source, false)->count() - (int) $counts->total, 'trend' => $trend, 'hourly' => $hourly, 'gates' => $gateRows, 'kinds' => $kinds, 'denied_reasons' => $reasons, 'presence' => $this->presence($f, $source)];
    }

    public function presence(ReportFilters $f, string $source): array
    {
        // Campus-wide last effective direction. Gate/guard and lower period bounds
        // must not make an earlier Entry disappear from a later presence estimate.
        $ranked = AccessEvent::query()->effective()->where('source', $source)->where('confirmed_at', '<', $f->until->format('Y-m-d H:i:s.u'))->where('confirmed_at', '<=', $f->asOf->format('Y-m-d H:i:s.u'))
            ->select(['person_id', 'direction'])->selectRaw('ROW_NUMBER() OVER (PARTITION BY person_id ORDER BY confirmed_at DESC, id DESC) position_number');
        $people = Person::withTrashed();
        if ($f->values['kind'] !== '') {
            $people->where('person_type', $f->values['kind']);
        }
        if ($f->values['q'] !== '') {
            $like = DirectoryData::like($f->values['q']);
            $people->where(fn ($q) => $q->whereRaw("LOWER(full_name) LIKE LOWER(?) ESCAPE '!'", [$like])->orWhereRaw("LOWER(campus_number) LIKE LOWER(?) ESCAPE '!'", [$like]));
        }
        $row = DB::query()->fromSub($people->select('id'), 'people')->leftJoinSub($ranked, 'last_event', fn ($j) => $j->on('last_event.person_id', '=', 'people.id')->where('last_event.position_number', 1))
            ->selectRaw("COALESCE(SUM(CASE WHEN last_event.direction='entry' THEN 1 ELSE 0 END),0) inside_count, COALESCE(SUM(CASE WHEN last_event.direction='exit' THEN 1 ELSE 0 END),0) outside_count, COALESCE(SUM(CASE WHEN last_event.person_id IS NULL THEN 1 ELSE 0 END),0) unknown_count")->first();

        return ['inside' => (int) $row->inside_count, 'outside' => (int) $row->outside_count, 'unknown' => (int) $row->unknown_count, 'as_of' => $f->cutoff()->toIso8601String()];
    }

    public function directoryCounts(string $source): array
    {
        $people = Person::selectRaw("COALESCE(SUM(CASE WHEN person_type='student' THEN 1 ELSE 0 END),0) students, COALESCE(SUM(CASE WHEN person_type='employee' THEN 1 ELSE 0 END),0) employees")->first();
        $active = NfcCredential::query()->join('credential_assignments as ca', 'ca.credential_id', '=', 'nfc_credentials.id')->join('persons as p', 'p.active_credential_assignment_id', '=', 'ca.id')
            ->where('nfc_credentials.source', $source)->where('nfc_credentials.status', 'active')->whereNull('ca.ended_at')->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>', now()))->count();

        return ['students' => (int) $people->students, 'employees' => (int) $people->employees, 'active_credentials' => $active, 'active_gates' => Gate::where('status', 'active')->count()];
    }
}
