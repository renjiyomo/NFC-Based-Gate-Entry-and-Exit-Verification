import { Link } from '@inertiajs/react';
import { Panel } from '../../components/ui';
import {
    AddLink,
    DataTable,
    DirectoryFilters,
    ManagementPage,
    Pagination,
    RecordBadge,
} from '../../components/management/Common';
import type { Filters, GuardRecord, Paginated } from '../../lib/management';

export default function GuardsIndex({
    records,
    filters,
}: {
    records: Paginated<GuardRecord>;
    filters: Filters;
}) {
    return (
        <ManagementPage
            page="guards"
            title="Security personnel"
            description="Manage guard accounts and their gate assignments."
            action={<AddLink href="/admin/guards/create">Add security guard</AddLink>}
        >
            <Panel
                title="Security guards"
                subtitle="Administrator accounts are managed separately and do not receive guard permissions."
            >
                <DirectoryFilters filters={filters} url="/admin/guards" gates />
                <DataTable
                    caption="Security guard accounts"
                    columns={[
                        'Security guard',
                        'Email',
                        'Account status',
                        'Password setup',
                        'Current schedules',
                        'Details',
                    ]}
                    empty="No security guards match these filters"
                    count={records.data.length}
                >
                    {records.data.map((guard) => (
                        <tr key={guard.id}>
                            <td>
                                <strong>{guard.name}</strong>
                            </td>
                            <td>{guard.email}</td>
                            <td>
                                <RecordBadge status={guard.status} archived={guard.archived_at} />
                            </td>
                            <td>{guard.must_change_password ? 'Pending' : 'Completed'}</td>
                            <td>{guard.assignments_count}</td>
                            <td>
                                <Link
                                    href={`/admin/guards/${guard.id}`}
                                    className="row-link"
                                    aria-label={`View ${guard.name}`}
                                >
                                    View account
                                </Link>
                            </td>
                        </tr>
                    ))}
                </DataTable>
                <Pagination records={records} />
            </Panel>
        </ManagementPage>
    );
}
