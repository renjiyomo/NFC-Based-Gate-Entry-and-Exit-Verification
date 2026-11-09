import { Link } from '@inertiajs/react';
import { DataTable } from '../management/Common';
import { Badge } from '../ui';
import { manilaTime } from '../../lib/management';
import { rowState, type HistoryRow } from '../../lib/monitoring';

export default function HistoryTable({ rows }: { rows: HistoryRow[] }) {
    return (
        <DataTable
            caption="Gate activity records"
            columns={[
                'Time · Manila',
                'Person',
                'Gate / account',
                'Direction',
                'Result',
                'Details',
            ]}
            count={rows.length}
            empty="No matching records"
            emptyDescription="Choose another period or adjust filters. Denied and unconfirmed scans do not count as passage."
        >
            {rows.map((r) => (
                <tr key={`${r.view}-${r.id}`}>
                    <td>{manilaTime(r.time)}</td>
                    <td>
                        {r.person?.name ?? 'Unidentified reference'}
                        <span className="table-secondary">
                            {r.person?.number ?? 'No registered person'}
                        </span>
                    </td>
                    <td>
                        {r.gate.code}
                        <span className="table-secondary">{r.guard}</span>
                    </td>
                    <td className="capitalize">{r.direction}</td>
                    <td>
                        <Badge
                            tone={
                                r.voided || r.outcome === 'denied'
                                    ? 'warning'
                                    : r.passage_id
                                      ? 'success'
                                      : 'neutral'
                            }
                        >
                            {rowState(r)}
                        </Badge>
                        <span className="table-secondary">{r.review_reason ?? r.reason}</span>
                    </td>
                    <td>
                        <Link className="table-action" href={`/admin/history/${r.view}/${r.id}`}>
                            View record
                        </Link>
                    </td>
                </tr>
            ))}
        </DataTable>
    );
}
