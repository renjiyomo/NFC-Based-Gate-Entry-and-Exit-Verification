import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, ChevronLeft, ChevronRight, Inbox, Plus, UserRound } from 'lucide-react';
import { useRef, type ReactNode } from 'react';
import { Field, Select } from './FormFields';
import ChoicePicker from './ChoicePicker';
import AppLayout from '../../layouts/AppLayout';
import type { PageId } from '../../lib/navigation';
import type { Filters, Paginated } from '../../lib/management';
import { Badge, Button, PageHeading, Panel } from '../ui';

export function ManagementPage({
    page,
    title,
    description,
    back,
    action,
    children,
}: {
    page: PageId;
    title: string;
    description: string;
    back?: string;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <AppLayout page={page}>
            <Head title={title} />
            {back && (
                <Link href={back} className="management-back">
                    <ArrowLeft size={16} aria-hidden="true" />
                    Back to {page === 'guards' ? 'gate accounts' : page}
                </Link>
            )}
            <PageHeading
                eyebrow={
                    page === 'scanner'
                        ? 'Gate operations'
                        : page === 'anomalies' || page === 'summaries'
                          ? 'Human review'
                          : page === 'history'
                            ? 'Gate activity'
                            : page === 'dashboard' || page === 'analytics' || page === 'reports'
                              ? 'Gate monitoring'
                              : page === 'students' || page === 'employees'
                                ? 'People'
                                : 'Access management'
                }
                title={title}
                description={description}
                action={action}
            />
            {children}
        </AppLayout>
    );
}
export { Field, Select } from './FormFields';

export function FormErrors({ errors }: { errors: Record<string, string> }) {
    const { url } = usePage();
    const values = [...new Set(Object.values(errors))];
    return values.length ? (
        <div className="form-errors" role="alert">
            <strong>Review these details</strong>
            <ul>
                {values.map((error) => (
                    <li key={error}>{error}</li>
                ))}
            </ul>
            {errors.record_version && (
                <Link href={url} preserveState={false}>
                    Reload the latest record
                </Link>
            )}
        </div>
    ) : null;
}
export function CurrentErrors() {
    const { errors } = usePage().props;
    return <FormErrors errors={errors as Record<string, string>} />;
}
export function RecordBadge({ status, archived }: { status: string; archived?: string | null }) {
    return (
        <Badge tone={archived ? 'warning' : status === 'active' ? 'success' : 'neutral'}>
            {archived ? 'Archived' : status === 'active' ? 'Active' : 'Inactive'}
        </Badge>
    );
}
export function Portrait({ src, name }: { src: string | null; name: string }) {
    return src ? (
        <img src={src} alt={`${name}, identity photo`} className="person-portrait" loading="lazy" />
    ) : (
        <span
            className="person-portrait portrait-placeholder"
            role="img"
            aria-label="No identity photo"
        >
            <UserRound size={24} aria-hidden="true" />
        </span>
    );
}
export function DataTable({
    caption,
    columns,
    empty,
    emptyDescription = 'Add a record or adjust the filters to see results.',
    children,
    count,
}: {
    caption: string;
    columns: string[];
    empty: string;
    emptyDescription?: string;
    children: ReactNode;
    count: number;
}) {
    return (
        <div
            className="data-table-wrap management-table"
            role="region"
            aria-label={`${caption}; scroll horizontally to view more columns`}
            tabIndex={0}
        >
            <table className="data-table">
                <caption className="sr-only">{caption}</caption>
                <thead>
                    <tr>
                        {columns.map((title) => (
                            <th key={title} scope="col">
                                {title}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {count ? (
                        children
                    ) : (
                        <tr>
                            <td colSpan={columns.length}>
                                <div className="management-empty">
                                    <Inbox size={30} aria-hidden="true" />
                                    <h3>{empty}</h3>
                                    <p>{emptyDescription}</p>
                                </div>
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}
export function Pagination({ records }: { records: Paginated<unknown> }) {
    return (
        <nav className="management-pagination" aria-label="Record pagination">
            <span>
                {records.total
                    ? `Showing ${records.from}–${records.to} of ${records.total}`
                    : 'No matching records'}
            </span>
            <div>
                {records.prev_page_url ? (
                    <Link
                        href={records.prev_page_url}
                        className="button button-secondary"
                        preserveScroll
                    >
                        <ChevronLeft size={16} aria-hidden="true" />
                        Previous
                    </Link>
                ) : (
                    <Button disabled>
                        <ChevronLeft size={16} aria-hidden="true" />
                        Previous
                    </Button>
                )}
                <span>
                    Page {records.current_page} of {records.last_page}
                </span>
                {records.next_page_url ? (
                    <Link
                        href={records.next_page_url}
                        className="button button-secondary"
                        preserveScroll
                    >
                        Next
                        <ChevronRight size={16} aria-hidden="true" />
                    </Link>
                ) : (
                    <Button disabled>
                        Next
                        <ChevronRight size={16} aria-hidden="true" />
                    </Button>
                )}
            </div>
        </nav>
    );
}
export function DirectoryFilters({
    filters,
    url,
    groupLabel,
    gates,
}: {
    filters: Filters;
    url: string;
    groupLabel?: string;
    gates?: boolean;
}) {
    const form = useForm({ ...filters });
    return (
        <form
            className="directory-filters"
            onSubmit={(event) => {
                event.preventDefault();
                form.get(url, { preserveState: true, preserveScroll: true });
            }}
        >
            <div className="filter-fields">
                <Field
                    name="q"
                    label="Search"
                    type="search"
                    maxLength={100}
                    placeholder="Name or reference"
                    value={form.data.q}
                    onChange={(e) => form.setData('q', e.target.value)}
                />
                {groupLabel && (
                    <Field
                        name="group"
                        label={groupLabel}
                        maxLength={120}
                        value={form.data.group}
                        onChange={(e) => form.setData('group', e.target.value)}
                    />
                )}
                <Select
                    name="status"
                    label="Record status"
                    value={form.data.status}
                    onChange={(e) => form.setData('status', e.target.value)}
                >
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </Select>
                <Select
                    name="records"
                    label="Records"
                    value={form.data.records}
                    onChange={(e) => form.setData('records', e.target.value)}
                >
                    <option value="current">Current records</option>
                    <option value="archived">Archived records</option>
                    <option value="all">All records</option>
                </Select>

                {gates && (
                    <ChoicePicker
                        kind="gates"
                        name="gate_id"
                        label="Current gate assignment"
                        active={false}
                        value={form.data.gate_id}
                        onChange={(value) => form.setData('gate_id', value)}
                    />
                )}
                <Select
                    name="per_page"
                    label="Rows per page"
                    value={form.data.per_page}
                    onChange={(e) => form.setData('per_page', e.target.value)}
                >
                    {[15, 30, 50].map((size) => (
                        <option key={size} value={size}>
                            {size}
                        </option>
                    ))}
                </Select>
            </div>
            <div className="filter-actions">
                <Button type="submit" variant="primary" disabled={form.processing}>
                    {form.processing ? 'Applying…' : 'Apply filters'}
                </Button>
                <Link href={url} className="button button-quiet">
                    Clear filters
                </Link>
            </div>
        </form>
    );
}
export function AddLink({ href, children }: { href: string; children: ReactNode }) {
    return (
        <Link href={href} className="button button-primary">
            <Plus size={17} aria-hidden="true" />
            {children}
        </Link>
    );
}
export function FormPanel({ title, children }: { title: string; children: ReactNode }) {
    return (
        <Panel title={title} subtitle="Fields marked * are required.">
            <div className="management-form">{children}</div>
        </Panel>
    );
}
export function SaveActions({
    busy,
    cancel,
    label = 'Save changes',
}: {
    busy: boolean;
    cancel: string;
    label?: string;
}) {
    return (
        <div className="form-actions">
            <Button type="submit" variant="primary" disabled={busy}>
                {busy ? 'Saving…' : label}
            </Button>
            <Link href={cancel} className="button button-secondary">
                Cancel
            </Link>
        </div>
    );
}
export function ConfirmAction({
    url,
    method = 'delete',
    version,
    label,
    title,
    description,
}: {
    url: string;
    method?: 'delete' | 'post';
    version?: number;
    label: string;
    title: string;
    description: string;
}) {
    const dialog = useRef<HTMLDialogElement>(null);
    const form = useForm({ record_version: version });
    return (
        <>
            <Button className="button-danger" onClick={() => dialog.current?.showModal()}>
                {label}
            </Button>
            <dialog ref={dialog} className="management-dialog" aria-label={title}>
                <h2>{title}</h2>
                <p>{description}</p>
                <FormErrors errors={form.errors} />
                <div className="form-actions">
                    <Button
                        autoFocus
                        disabled={form.processing}
                        onClick={() => dialog.current?.close()}
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="primary"
                        disabled={form.processing}
                        onClick={() =>
                            form.submit(method, url, {
                                preserveScroll: true,
                                onSuccess: () => dialog.current?.close(),
                            })
                        }
                    >
                        {form.processing ? 'Saving…' : label}
                    </Button>
                </div>
            </dialog>
        </>
    );
}
export function ArchiveActions({
    base,
    record,
    name,
}: {
    base: string;
    record: { record_version: number; archived_at: string | null };
    name: string;
}) {
    return (
        <div className="record-actions">
            {record.archived_at ? (
                <ConfirmAction
                    url={`${base}/restore`}
                    method="post"
                    version={record.record_version}
                    label="Restore record"
                    title={`Restore ${name}?`}
                    description="The record will return as inactive. Review its status and credential before enabling scanning."
                />
            ) : (
                <>
                    <Link href={`${base}/edit`} className="button button-secondary">
                        Edit record
                    </Link>
                    <ConfirmAction
                        url={base}
                        version={record.record_version}
                        label="Archive record"
                        title={`Archive ${name}?`}
                        description="This record will be made inactive and hidden from current directories. Scanning will be blocked. Its details and history are retained, and it can be restored later."
                    />
                </>
            )}
        </div>
    );
}
