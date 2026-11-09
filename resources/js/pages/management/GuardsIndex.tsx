import { Link } from '@inertiajs/react';
import { Panel } from '../../components/ui';
import {
    DataTable,
    DirectoryFilters,
    ManagementPage,
    Pagination,
    RecordBadge,
    AddLink,
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
            title="Gate accounts"
            description="One username and password per gate. Guards share the gate session across shifts."
            action={<AddLink href="/admin/gates/create">Add gate and account</AddLink>}
        >
            <Panel
                title="Gate logins"
                subtitle="Passwords are set or reset by an administrator. No email activation is required."
            >
                <DirectoryFilters filters={filters} url="/admin/guards" />
                <DataTable
                    caption="Gate accounts"
                    columns={['Gate', 'Username', 'Status', 'Manage']}
                    empty="No gate accounts"
                    count={records.data.length}
                >
                    {records.data.map((a) => (
                        <tr key={a.id}>
                            <td>{a.name}</td>
                            <td>{a.username}</td>
                            <td>
                                <RecordBadge status={a.status} archived={a.archived_at} />
                            </td>
                            <td>
                                <Link className="row-link" href={`/admin/gates/${a.gate_id}/edit`}>
                                    Edit account
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
