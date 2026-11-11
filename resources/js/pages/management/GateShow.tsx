import { Link } from '@inertiajs/react';
import { Panel } from '../../components/ui';
import {
    ArchiveActions,
    CurrentErrors,
    ManagementPage,
    RecordBadge,
} from '../../components/management/Common';
import type { GateRecord } from '../../lib/management';
export default function GateShow({ record }: { record: GateRecord }) {
    return (
        <ManagementPage
            page="gates"
            title={record.name}
            description={`Gate station · ${record.code}`}
            back="/admin/gates"
            action={
                <ArchiveActions
                    base={`/admin/gates/${record.id}`}
                    record={record}
                    name={record.name}
                    editLabel="Edit gate"
                />
            }
        >
            <CurrentErrors />
            <Panel title="Gate account">
                <dl className="record-facts">
                    <div>
                        <dt>Gate code</dt>
                        <dd>{record.code}</dd>
                    </div>
                    <div>
                        <dt>Username</dt>
                        <dd>{record.username ?? 'Account not created'}</dd>
                    </div>
                    <div>
                        <dt>Gate status</dt>
                        <dd>
                            <RecordBadge status={record.status} archived={record.archived_at} />
                        </dd>
                    </div>
                </dl>
                <div className="gate-account-footer">
                    <p>One shared login for this gate.</p>
                    {!record.archived_at && (
                        <Link
                            className="button button-secondary"
                            href={`/admin/gates/${record.id}/edit#password`}
                        >
                            {record.account_id ? 'Reset password' : 'Create account'}
                        </Link>
                    )}
                </div>
            </Panel>
        </ManagementPage>
    );
}
