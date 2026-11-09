export type Direction = 'entry' | 'exit';
export interface Station {
    id: number;
    code: string;
    name: string;
}
export interface Passage {
    voided?: boolean;
    id: string;
    direction: Direction;
    confirmed_at: string;
    source: string;
    identity_method: string;
    sequence_notice: string | null;
}
export interface Operation {
    id: string;
    request_id: string;
    gate_id: number;
    direction: Direction;
    source: string;
    server_time: string;
    verification: { outcome: 'allowed' | 'denied'; reason: string; received_at: string };
    current_check: { eligible: boolean; reason: string };
    review: {
        status: string;
        expires_at: string | null;
        reviewed_at: string | null;
        reason: string | null;
    };
    identity: {
        name: string;
        number: string;
        kind: string;
        photo_url: string | null;
        sequence_notice: string | null;
    } | null;
    passage: Passage | null;
    duplicate_of: Passage | null;
}
export interface ActivityRecord {
    voided?: boolean;
    id: string;
    person: string | null;
    gate: string;
    guard: string;
    direction: Direction;
    source: string;
    outcome: string;
    reason: string;
    review_status: string;
    review_reason: string | null;
    received_at: string;
    confirmed_at: string | null;
    duplicate: boolean;
}
export interface ActivityFeed {
    records: ActivityRecord[];
    server_time: string;
    minutes: number | null;
    limit: number;
}
export class OperationError extends Error {
    errors: Record<string, string>;
    status: number;
    constructor(message: string, status = 0, errors?: Record<string, string>) {
        super(message);
        this.status = status;
        this.errors = errors ?? { request: message };
    }
}
export async function operationRequest<T>(
    url: string,
    data?: object,
    signal?: AbortSignal,
): Promise<T> {
    const cookie = document.cookie.split('; ').find((part) => part.startsWith('XSRF-TOKEN='));
    let response: Response;
    try {
        response = await fetch(url, {
            method: data ? 'POST' : 'GET',
            credentials: 'same-origin',
            redirect: 'error',
            cache: 'no-store',
            signal: AbortSignal.any([AbortSignal.timeout(12000), ...(signal ? [signal] : [])]),
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(data
                    ? {
                          'Content-Type': 'application/json',
                          'X-XSRF-TOKEN': cookie ? decodeURIComponent(cookie.slice(11)) : '',
                      }
                    : {}),
            },
            ...(data ? { body: JSON.stringify(data) } : {}),
        });
    } catch {
        throw new OperationError(
            'The connection ended. Recover this request before continuing; the server may have saved it.',
        );
    }
    if (!response.headers.get('Content-Type')?.includes('application/json'))
        throw new OperationError(
            'The session is unavailable. Sign in again and review recent activity.',
            response.status,
        );
    if (response.status === 422) {
        const body = (await response.json()) as { errors?: Record<string, string[]> };
        const errors: Record<string, string> = {};
        for (const [field, values] of Object.entries(body.errors ?? {}))
            if (Array.isArray(values) && typeof values[0] === 'string') errors[field] = values[0];
        throw new OperationError('Review the request details.', 422, errors);
    }
    if (!response.ok)
        throw new OperationError(
            response.status === 404
                ? 'This request was not found in this guard session and station.'
                : response.status === 403
                  ? 'Your current account or gate assignment does not permit this action. Reload the station list.'
                  : response.status === 429
                    ? 'Too many requests. Wait a minute, then recover the current request.'
                    : 'The action could not finish. Recover this request before continuing.',
            response.status,
        );
    return response.json() as Promise<T>;
}
export function operationErrors(error: unknown): Record<string, string> {
    return error instanceof OperationError
        ? error.errors
        : {
              request:
                  'The operation could not finish. Recover the current request before continuing.',
          };
}
export function operationBase(gateId: number, simulated: boolean): string {
    return `${simulated ? '/simulator/guard/gates' : '/guard/gates'}/${gateId}`;
}
export function reasonLabel(reason: string): string {
    const labels: Record<string, string> = {
        credential_valid: 'Credential accepted',
        automatic_nfc_scan: 'Recorded automatically from NFC scan',
        eligible_for_review: 'Credential accepted',
        malformed: 'The tag does not contain a valid NFCheck reference',
        unknown: 'Reference is not registered',
        revoked: 'Credential has been revoked',
        cancelled: 'Issuance was cancelled',
        pending: 'Credential is not activated',
        expired: 'Credential has expired',
        person_inactive: 'Person is inactive or archived',
        not_selected: 'Credential is no longer selected',
        access_not_authorized: 'No current campus access grant',
        simulated_not_permitted: 'Simulated references cannot be used for physical operations',
        source_mismatch: 'Credential source does not match this workspace',
        review_expired: 'The identity review expired; scan again',
        person_record_changed: 'The identity or access record changed; scan again',
        duplicate_passage: 'A matching passage was already recorded within the duplicate window',
        operator_cancelled: 'Guard cancelled the review',
        identity_mismatch: 'Guard reported an identity mismatch',
        observed_passage: 'Guard confirmed observed passage',
    };
    return reason.startsWith('recheck_')
        ? `Authorization changed: ${reasonLabel(reason.slice(8))}`
        : (labels[reason] ?? reason.replaceAll('_', ' '));
}
export function sequenceLabel(value: string): string {
    return value === 'exit_without_recorded_entry'
        ? 'No earlier entry is recorded. Review the person; this missing context does not automatically deny an exit.'
        : value === 'repeated_entry'
          ? 'The latest recorded direction is also Entry. Check that you observed a new passage.'
          : 'The latest recorded direction is also Exit. Check that you observed a new passage.';
}
export function reviewStatusLabel(status: string): string {
    return (
        (
            {
                pending: 'Review pending',
                not_applicable: 'No review',
                confirmed: 'Passage recorded',
                identity_mismatch: 'Identity mismatch',
                cancelled: 'Review closed',
                expired: 'Review expired',
            } as Record<string, string>
        )[status] ?? 'Review unavailable'
    );
}
