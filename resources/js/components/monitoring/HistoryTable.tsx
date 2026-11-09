import { Link } from '@inertiajs/react';
import { DataTable } from '../management/Common';
import { Badge } from '../ui';
import { manilaTime } from '../../lib/management';
import { rowState, type HistoryRow } from '../../lib/monitoring';
import { sequenceLabel } from '../../lib/gateFeedback';
import { reasonLabel } from '../../lib/operations';

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
            emptyDescription="Try another date or adjust the filters. Rejected and duplicate scans appear under All scan attempts."
        >
            {rows.map((r) => (
                <tr key={`${r.view}-${r.id}`}>
                    <td>{manilaTime(r.time)}</td>
                    <td>
                        {r.person?.name ?? 'Unidentified coin'}
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
                                r.voided || r.outcome === 'denied' || r.sequence_notice
                                    ? 'warning'
                                    : r.passage_id
                                      ? 'success'
                                      : 'neutral'
                            }
                        >
                            {rowState(r)}
                        </Badge>
                        {(!r.passage_id || r.voided) && (
                            <span className="table-secondary">
                                {reasonLabel(r.review_reason ?? r.reason)}
                            </span>
                        )}
                        {r.sequence_notice && (
                            <details className="table-notice">
                                <summary>Sequence warning</summary>
                                <p>{sequenceLabel(r.sequence_notice)}</p>
                            </details>
                        )}
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
