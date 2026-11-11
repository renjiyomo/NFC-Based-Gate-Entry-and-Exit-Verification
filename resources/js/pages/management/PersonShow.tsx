import { Link } from '@inertiajs/react';
import { Panel } from '../../components/ui';
import {
    ArchiveActions,
    CurrentErrors,
    DataTable,
    ManagementPage,
    Portrait,
    RecordBadge,
} from '../../components/management/Common';
import { CredentialBadge } from '../../components/credentials/Shared';
import type { CredentialRecord } from '../../lib/credentials';
import type { PersonDirectory, PersonRecord } from '../../lib/management';
import { useWorkspace } from '../../lib/workspace';
export default function PersonShow({
    directory,
    record,
    credentials = [],
}: {
    directory: PersonDirectory;
    record: PersonRecord;
    credentials: CredentialRecord[];
}) {
    const { simulator } = useWorkspace(),
        student = directory === 'students',
        base = `/admin/${directory}/${record.id}`;
    const coin = credentials.find((c) => c.selected && c.status === 'active');
    const editable = !record.archived_at && record.status === 'active';
    const fields = student
        ? [
              ['Program', record.program],
              ['Year level', record.year_level ? `Year ${record.year_level}` : null],
              ['Academic year', record.academic_year],
              ['Semester', record.semester],
          ]
        : [
              ['Department', record.department],
              ['Position', record.position],
          ];
    return (
        <ManagementPage
            page={directory}
            title={student ? 'Student record' : 'Employee record'}
            description=""
            back={`/admin/${directory}`}
        >
            <CurrentErrors />
            <Panel className="person-profile">
                <div className="profile-heading">
                    <div className="profile-photo">
                        <Portrait src={record.photo_url} name={record.full_name} />
                    </div>
                    <div className="profile-identity">
                        <h2>{record.full_name}</h2>
                        <p className="profile-number">{record.campus_number}</p>
                        <div className="profile-status">
                            <span>{student ? 'Student' : 'Employee'}</span>
                            <RecordBadge status={record.status} archived={record.archived_at} />
                        </div>
                    </div>
                    <div className="profile-actions">
                        <ArchiveActions base={base} record={record} name={record.full_name} />
                    </div>
                </div>
                <dl className="profile-fields">
                    {fields.map(([label, value]) => (
                        <div key={label}>
                            <dt>{label}</dt>
                            <dd>{value ?? 'Not provided'}</dd>
                        </div>
                    ))}
                </dl>
            </Panel>
            <Panel className="person-coin-card">
                <div className="person-coin-summary">
                    <div>
                        <h2>{coin ? 'NFC coin active' : 'No active NFC coin'}</h2>
                        <p>
                            {coin
                                ? `${coin.label} · Ready for Entry and Exit scans.`
                                : editable
                                  ? 'Issue a coin to enable Entry and Exit records.'
                                  : 'Activate this person’s record before issuing a coin.'}
                        </p>
                    </div>
                    <div className="person-coin-actions">
                        {coin && (
                            <Link
                                className="button button-secondary"
                                href={`/admin/credentials/${coin.id}`}
                            >
                                Manage coin
                            </Link>
                        )}
                        {editable && (
                            <Link
                                className="button button-primary"
                                href={`${simulator ? '/simulator/issue' : '/admin/credentials/create'}?person_id=${record.id}`}
                            >
                                {coin ? 'Replace coin' : 'Issue NFC coin'}
                            </Link>
                        )}
                    </div>
                </div>
            </Panel>
            <Panel
                title="Coin history"
                action={
                    <Link
                        className="text-link"
                        href={`/admin/credentials?status=all&q=${encodeURIComponent(record.campus_number)}`}
                    >
                        View all
                    </Link>
                }
            >
                <DataTable
                    caption="Person NFC credentials"
                    columns={['Coin label', 'Status', 'Current coin', 'Details']}
                    count={credentials.length}
                    empty="No coins issued yet"
                    emptyDescription="A coin will appear here after you prepare its issuance."
                >
                    {credentials.map((c) => (
                        <tr key={c.id}>
                            <td>{c.label}</td>
                            <td>
                                <CredentialBadge record={c} />
                            </td>
                            <td>{c.selected ? 'Current' : 'Previous'}</td>
                            <td>
                                <Link className="row-link" href={`/admin/credentials/${c.id}`}>
                                    View coin
                                </Link>
                            </td>
                        </tr>
                    ))}
                </DataTable>
            </Panel>
        </ManagementPage>
    );
}
