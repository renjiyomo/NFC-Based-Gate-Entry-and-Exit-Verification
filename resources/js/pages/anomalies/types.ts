export interface Flag {
    id: string;
    title: string;
    rule: string;
    severity: string;
    source: string;
    at: string;
    status: string;
    reviewer: string | null;
    person: string | null;
    gate: string;
}
export const readable = (v: string) => v.replaceAll('_', ' ');
