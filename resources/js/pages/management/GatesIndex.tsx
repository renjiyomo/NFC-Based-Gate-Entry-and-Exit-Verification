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
import type { Filters, GateRecord, Paginated } from '../../lib/management';

export default function GatesIndex({
    records,
    filters,
}: {
    records: Paginated<GateRecord>;
    filters: Filters;
}) {
    return (
        <ManagementPage
            page="gates"
            title="Gates & accounts"
            description="One shared login for each campus gate."
            action={<AddLink href="/admin/gates/create">Add gate</AddLink>}
        >
            <Panel>
                <DirectoryFilters filters={filters} url="/admin/gates" />
                <DataTable
                    caption="Campus gates"
                    columns={['Gate station', 'Gate code', 'Record status', 'Username', 'Details']}
                    empty="No gate stations match these filters"
                    count={records.data.length}
                >
                    {records.data.map((gate) => (
                        <tr key={gate.id}>
                            <td>
                                <strong>{gate.name}</strong>
                            </td>
                            <td>{gate.code}</td>
                            <td>
                                <RecordBadge status={gate.status} archived={gate.archived_at} />
                            </td>
                            <td>{gate.username ?? 'Not configured'}</td>
                            <td>
                                <Link
                                    className="row-link"
                                    href={`/admin/gates/${gate.id}`}
                                    aria-label={`View ${gate.name}`}
                                >
                                    View station
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
