export interface Facts {
    contract: string;
    period: { from: string; to: string; timezone: string };
    facts: Record<string, { count: number; sentence: string }>;
    limitations: Record<string, string>;
    gate_coverage: string;
}
export interface SummaryRow {
    id: string;
    from: string;
    to: string;
    period: string;
    source: string;
    at: string;
    actor: string;
    status: string;
    review: string;
    completed_at: string | null;
}
export const readable = (s: string) => s.replaceAll('_', ' ');
