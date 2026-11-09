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
    const { simulator } = useWorkspace();
    const base = `/admin/${directory}/${record.id}`;
    const activeCoin = credentials.find((c) => c.selected && c.status === 'active');
    return (
        <ManagementPage
            page={directory}
            title={record.full_name}
            description={record.campus_number}
            back={`/admin/${directory}`}
            action={<ArchiveActions base={base} record={record} name={record.full_name} />}
        >
            <CurrentErrors />
            <div className="record-detail-grid">
                <Panel title="Person details">
                    <div className="identity-cell">
                        <Portrait src={record.photo_url} name={record.full_name} />
                        <RecordBadge status={record.status} archived={record.archived_at} />
                    </div>
                    <dl className="record-facts">
                        <div>
                            <dt>{directory === 'students' ? 'Program' : 'Department'}</dt>
                            <dd>{record.program ?? record.department}</dd>
                        </div>
                        {directory === 'students' ? (
                            <>
                                <div>
                                    <dt>Year level</dt>
                                    <dd>{record.year_level ?? 'Not supplied'}</dd>
                                </div>
                                <div>
                                    <dt>Academic year / semester</dt>
                                    <dd>
                                        {record.academic_year ?? 'Not updated'} ·{' '}
                                        {record.semester ?? 'Not updated'}
                                    </dd>
                                </div>
                            </>
                        ) : (
                            <div>
                                <dt>Position</dt>
                                <dd>{record.position}</dd>
                            </div>
                        )}
                    </dl>
                    <Link className="button button-secondary" href={`${base}/edit`}>
                        Edit person
                    </Link>
                </Panel>
                <Panel title="NFC access">
                    <p className="panel-message">
                        An active person with a selected active NFC credential can scan. No separate
                        campus-access grant is required. Deactivate the person or revoke the
                        credential to stop access.
                    </p>
                    {!record.archived_at && record.status === 'active' && (
                        <Link
                            className="button button-primary"
                            href={`${simulator ? '/simulator/issue' : '/admin/credentials/create'}?person_id=${record.id}`}
                        >
                            {activeCoin ? 'Replace NFC coin' : 'Issue NFC coin'}
                        </Link>
                    )}
                    {activeCoin && (
                        <p>
                            <Link
                                href={`/admin/credentials/${activeCoin.id}`}
                                className="button button-secondary"
                            >
                                Manage coin / report lost
                            </Link>
                        </p>
                    )}
                    <p className="form-note">
                        For a lost coin, revoke it as lost immediately, then issue a replacement
                        after checking the person's identity. The missing coin is not needed.
                    </p>
                </Panel>
            </div>
            <Panel
                title="Credential history"
                subtitle="Closed records remain in history. Check a coin before writing."
            >
                <DataTable
                    caption="Person NFC credentials"
                    columns={['Label', 'State', 'Selection', 'Details']}
                    count={credentials.length}
                    empty="No credential issued"
                >
                    {credentials.map((c) => (
                        <tr key={c.id}>
                            <td>{c.label}</td>
                            <td>
                                <CredentialBadge record={c} />
                            </td>
                            <td>{c.selected ? 'Selected' : 'Not selected'}</td>
                            <td>
                                <Link href={`/admin/credentials/${c.id}`}>View credential</Link>
                            </td>
                        </tr>
                    ))}
                </DataTable>
            </Panel>
        </ManagementPage>
    );
}
