export interface ReportFilters {
    period: string;
    date: string;
    from: string;
    to: string;
    q: string;
    kind: string;
    gate_id: string | number;
    guard_id: string | number;
    direction: string;
    view: 'passages' | 'attempts';
    outcome: string;
    review: string;
    validity: string;
    per_page: number;
}
export interface HistoryRow {
    id: string;
    view: 'passages' | 'attempts';
    time: string;
    person: { name: string; number: string; kind: string } | null;
    gate: { code: string; name: string };
    guard: string;
    direction: string;
    source: string;
    outcome: string;
    reason: string;
    review: string;
    review_reason: string | null;
    credential: string | null;
    passage_id: string | null;
    voided: boolean;
    duplicate: boolean;
    sequence_notice: string | null;
    identity_method: string | null;
    confirmed_at: string | null;
}
export interface Comparison {
    label: string;
    entries: number;
    exits: number;
}
export interface Presence {
    inside: number;
    students_inside: number;
    employees_inside: number;
    outside: number;
    unknown: number;
    as_of: string;
}
export interface Summary {
    entries: number;
    exits: number;
    passages: number;
    unique_people: number;
    attempts: number;
    denied: number;
    duplicates: number;
    voided: number;
    trend: { date: string; entries: number; exits: number }[];
    gates: Comparison[];
    kinds: Comparison[];
    hourly: { label: string; value: number }[];
    denied_reasons: { label: string; value: number }[];
    presence: Presence;
}
export interface Period {
    timezone: string;
    from: string;
    to: string;
    as_of: string;
}
export const number = (value: number) => new Intl.NumberFormat('en-PH').format(value);
export function reportUrl(
    path: string,
    filters: ReportFilters,
    extra: Record<string, string> = {},
): string {
    return `${path}?${new URLSearchParams({ ...Object.fromEntries(Object.entries(filters).map(([k, v]) => [k, String(v)])), ...extra })}`;
}
export function rowState(r: HistoryRow): string {
    if (r.voided) return 'Voided passage';
    if (r.duplicate) return 'Duplicate suppressed';
    if (r.passage_id) return 'Passage recorded';
    return r.outcome === 'denied'
        ? 'Verification denied'
        : `Review ${r.review.replaceAll('_', ' ')}`;
}
