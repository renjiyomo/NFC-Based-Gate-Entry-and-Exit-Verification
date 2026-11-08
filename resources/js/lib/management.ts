export type PersonDirectory = 'students' | 'employees';
export interface PersonRecord {
    id: number;
    kind: 'student' | 'employee';
    campus_number: string;
    full_name: string;
    status: 'active' | 'inactive';
    record_version: number;
    archived_at: string | null;
    photo_url: string | null;
    program: string | null;
    year_level: number | null;
    academic_term: string | null;
    department: string | null;
    position: string | null;
    access_active: boolean;
    nfc_credential?: { label: string; status: 'active' } | null;
}
export interface GateRecord {
    id: number;
    code: string;
    name: string;
    status: 'active' | 'inactive';
    archived_at: string | null;
    record_version: number;
    assignments_count: number;
}
export interface GuardRecord {
    id: number;
    name: string;
    email: string;
    status: 'active' | 'inactive';
    archived_at: string | null;
    record_version: number;
    must_change_password: boolean;
    assignments_count: number;
}
export interface Assignment {
    state: string;
    operable: boolean;
    id: number;
    guard_id: number;
    guard_name: string;
    gate_id: number;
    gate_name: string;
    gate_code: string;
    starts_at: string;
    ends_at: string | null;
    revoked_at: string | null;
}
export interface Grant {
    state: string;
    id: number;
    starts_at: string;
    ends_at: string | null;
    revoked_at: string | null;
    reason: string;
}
export interface Paginated<T> {
    data: T[];
    total: number;
    from: number | null;
    to: number | null;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}
export interface Filters {
    q: string;
    status: string;
    records: string;
    group: string;
    access: string;
    gate_id: string | number;
    per_page: string | number;
}
export interface Choice {
    id: number;
    name: string;
    code?: string;
    email?: string;
}
export const manilaTime = (date?: string | null) =>
    date
        ? new Intl.DateTimeFormat('en-PH', {
              timeZone: 'Asia/Manila',
              year: 'numeric',
              month: 'short',
              day: 'numeric',
              hour: 'numeric',
              minute: '2-digit',
          }).format(new Date(date))
        : 'No scheduled end';
export const manilaInput = (date = new Date().toISOString()) =>
    new Date(new Date(date).getTime() + 8 * 60 * 60 * 1000).toISOString().slice(0, 16);
export const intervalStatus = (item: { state: string }) => item.state;
