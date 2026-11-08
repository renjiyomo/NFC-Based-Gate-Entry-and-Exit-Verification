export interface CredentialPerson {
    id: number;
    name: string;
    number: string;
    kind: 'student' | 'employee';
    status: string;
    archived: boolean;
    record_version: number;
}
export interface CredentialRecord {
    id: string;
    label: string;
    status: string;
    source: 'physical' | 'simulated';
    person: CredentialPerson | null;
    pending_expires_at: string;
    read_back_at: string | null;
    activated_at: string | null;
    valid_until: string | null;
    closed_at: string | null;
    close_reason: string | null;
    assignment_ended_at: string | null;
    selected: boolean;
    replaces_id: string | null;
    created_at: string;
}
export interface PersonChoice {
    id: number;
    name: string;
    number: string;
    kind: string;
    record_version: number;
    current: { id: string; label: string; source: string; status: string } | null;
}
export interface IssuanceReceipt {
    credential: CredentialRecord;
    payload: string;
    completion_nonce: string;
}
export interface Inspection {
    eligible: boolean;
    reason: string;
    simulated: boolean;
    passage_recorded: false;
    person?: { id: number; name: string; number: string; kind: string };
    credential?: { id: string; label: string };
}
export class CredentialApiError extends Error {
    errors: Record<string, string>;
    constructor(errors: Record<string, string>) {
        super('Credential request could not finish.');
        this.errors = errors;
    }
}
// JSON only: secrets never pass through Inertia forms, page props or browser history.
export async function credentialPost<T>(
    url: string,
    data: object,
    signal?: AbortSignal,
): Promise<T> {
    const cookie = document.cookie.split('; ').find((part) => part.startsWith('XSRF-TOKEN='));
    const timeout = AbortSignal.timeout(30000);
    let response: Response;
    try {
        response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            redirect: 'error',
            signal: signal ? AbortSignal.any([timeout, signal]) : timeout,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': cookie
                    ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length))
                    : '',
            },
            body: JSON.stringify(data),
        });
    } catch {
        throw new CredentialApiError({
            request:
                'The connection ended. Review the registry before retrying; the server may have saved the change. A lost issuance receipt requires cancellation and a new issuance.',
        });
    }
    if (!response.headers.get('Content-Type')?.includes('application/json')) {
        throw new CredentialApiError({
            request:
                'The session or response is unavailable. Sign in again and review the registry.',
        });
    }
    if (response.status === 422) {
        const body = (await response.json()) as { errors?: Record<string, string[]> };
        const errors: Record<string, string> = {};
        for (const [field, values] of Object.entries(body.errors ?? {})) {
            if (Array.isArray(values) && typeof values[0] === 'string') errors[field] = values[0];
        }
        throw new CredentialApiError(
            Object.keys(errors).length ? errors : { request: 'Review the request details.' },
        );
    }
    if (!response.ok) {
        throw new CredentialApiError({
            request:
                response.status === 429
                    ? 'Too many requests. Wait a minute before trying again.'
                    : 'This action could not finish. Check your session and review the latest credential record.',
        });
    }
    return response.json() as Promise<T>;
}
export function credentialErrors(error: unknown): Record<string, string> {
    return error instanceof CredentialApiError
        ? error.errors
        : { request: 'This action could not finish. Review the registry before trying again.' };
}
