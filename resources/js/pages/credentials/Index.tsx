import { Link, router, useForm } from '@inertiajs/react';
import {
    AddLink,
    DataTable,
    Field,
    ManagementPage,
    Pagination,
    Select,
} from '../../components/management/Common';
import { CredentialBadge, SimulatorNotice } from '../../components/credentials/Shared';
import { Button, Panel } from '../../components/ui';
import type { CredentialRecord } from '../../lib/credentials';
import { manilaTime, type Paginated } from '../../lib/management';
import { useWorkspace } from '../../lib/workspace';

export default function Index({
    filters,
    records,
}: {
    filters: { q: string; status: string; source: string; per_page: number };
    records: Paginated<CredentialRecord>;
}) {
    const form = useForm({ ...filters });
    const { simulator } = useWorkspace();
    return (
        <ManagementPage
            page="credentials"
            title="NFC credentials"
            description="Issue, activate and close credentials with a permanent assignment history."
            action={
                <AddLink href={simulator ? '/simulator/issue' : '/admin/credentials/create'}>
                    Issue credential
                </AddLink>
            }
        >
            {simulator && <SimulatorNotice />}
            <form
                className="directory-filters"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.get('/admin/credentials', { preserveState: true });
                }}
            >
                <div className="filter-fields">
                    <Field
                        name="q"
                        label="Search"
                        type="search"
                        maxLength={100}
                        placeholder="Label, person or campus number"
                        value={form.data.q}
                        onChange={(e) => form.setData('q', e.target.value)}
                    />
                    <Select
                        name="status"
                        label="Credential status"
                        value={form.data.status}
                        onChange={(e) => form.setData('status', e.target.value)}
                    >
                        <option value="current">Current: active and pending</option>
                        <option value="all">All history</option>
                        {['pending', 'active', 'revoked', 'cancelled', 'expired'].map((s) => (
                            <option key={s} value={s}>
                                {s}
                            </option>
                        ))}
                    </Select>
                    <Select
                        name="source"
                        label="Source"
                        value={form.data.source}
                        onChange={(e) => form.setData('source', e.target.value)}
                    >
                        <option value="">All sources</option>
                        <option value="physical">Physical tag</option>
                        <option value="simulated">Simulated</option>
                    </Select>
                    <Select
                        name="per_page"
                        label="Rows per page"
                        value={form.data.per_page}
                        onChange={(e) => form.setData('per_page', Number(e.target.value))}
                    >
                        {[15, 30, 50].map((s) => (
                            <option key={s} value={s}>
                                {s}
                            </option>
                        ))}
                    </Select>
                </div>
                <div className="filter-actions">
                    <Button type="submit" variant="primary" disabled={form.processing}>
                        Apply filters
                    </Button>
                    <Link href="/admin/credentials" className="button button-quiet">
                        Clear filters
                    </Link>
                    <Button
                        disabled={form.processing}
                        onClick={() => router.reload({ only: ['records'] })}
                    >
                        Refresh list
                    </Button>
                    <Link href="/admin/credentials?status=all" className="button button-quiet">
                        View all history
                    </Link>
                </div>
            </form>
            <Panel
                title={
                    filters.status === 'current'
                        ? 'Current NFC credentials'
                        : 'NFC credential history'
                }
                subtitle="These statuses describe credential references, not whether a physical coin still contains them. Check a coin before reuse."
            >
                <DataTable
                    caption="Credential registry"
                    columns={[
                        'Credential',
                        'Assigned person',
                        'State / source',
                        'Issued',
                        'Action',
                    ]}
                    empty="No credentials found"
                    emptyDescription="Issue a reference to an active person, write the tag and complete a matching read-back before activation."
                    count={records.data.length}
                >
                    {records.data.map((r) => (
                        <tr key={r.id}>
                            <td>
                                <Link
                                    href={`/admin/credentials/${r.id}`}
                                    className="table-primary-link"
                                >
                                    {r.label}
                                </Link>
                            </td>
                            <td>
                                {r.person?.name ?? 'Unavailable'}
                                <span className="table-secondary">{r.person?.number}</span>
                            </td>
                            <td>
                                <CredentialBadge record={r} />
                            </td>
                            <td>{manilaTime(r.created_at)}</td>
                            <td>
                                <Link href={`/admin/credentials/${r.id}`} className="table-action">
                                    View credential
                                </Link>
                            </td>
                        </tr>
                    ))}
                </DataTable>
                <Pagination records={records} />
            </Panel>
            <p className="availability-note">
                An active person and their selected active coin can record Entry or Exit. Closed
                credentials stay in history.
            </p>
        </ManagementPage>
    );
}
