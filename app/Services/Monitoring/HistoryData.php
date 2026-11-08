<?php

namespace App\Services\Monitoring;

use App\Models\AccessEvent;
use App\Models\ScanAttempt;

class HistoryData
{
    public static function row(AccessEvent|ScanAttempt $record, ReportFilters $f): array
    {
        $passage = $record instanceof AccessEvent;
        $event = $passage ? $record : $record->passage;
        $attempt = $passage ? $record->attempt : $record;
        $review = $attempt->review_status === 'pending' && $attempt->review_expires_at->lessThanOrEqualTo($f->asOf) ? 'expired' : $attempt->review_status;

        return ['id' => $record->public_id, 'view' => $passage ? 'passages' : 'attempts', 'time' => ($passage ? $record->confirmed_at : $record->received_at)->toIso8601String(),
            'person' => $record->person ? ['name' => $record->person->full_name, 'number' => $record->person->campus_number, 'kind' => $record->person->person_type->value] : null,
            'gate' => ['code' => $record->gate->code, 'name' => $record->gate->name], 'guard' => $record->guardUser->name, 'direction' => $record->direction, 'source' => $record->source,
            'outcome' => $attempt->outcome, 'reason' => $attempt->reason, 'review' => $review, 'review_reason' => $attempt->review_reason,
            'credential' => $attempt->credential?->label, 'passage_id' => $event?->public_id, 'voided' => $event?->latestDecision?->action === 'void', 'duplicate' => $attempt->duplicate_of_event_id !== null,
            'sequence_notice' => $event?->sequence_notice, 'identity_method' => $event?->identity_method, 'confirmed_at' => $event?->confirmed_at->toIso8601String()];
    }
}
