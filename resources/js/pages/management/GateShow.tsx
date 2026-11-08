import { Panel } from '../../components/ui';
import {
    ArchiveActions,
    CurrentErrors,
    ManagementPage,
    RecordBadge,
} from '../../components/management/Common';
import { AssignmentForm, Assignments } from '../../components/management/Assignments';
import type { Assignment, GateRecord, Paginated } from '../../lib/management';

export default function GateShow({
    record,
    assignments,
}: {
    record: GateRecord;
    assignments: Paginated<Assignment>;
}) {
    const enabled = !record.archived_at && record.status === 'active';
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
                />
            }
        >
            <CurrentErrors />
            <div className="record-detail-grid">
                <Panel title="Station details">
                    <dl className="record-facts">
                        <div>
                            <dt>Gate code</dt>
                            <dd>{record.code}</dd>
                        </div>
                        <div>
                            <dt>Record status</dt>
                            <dd>
                                <RecordBadge status={record.status} archived={record.archived_at} />
                            </dd>
                        </div>
                        <div>
                            <dt>Gate scanning</dt>
                            <dd>Unavailable until the scanning phase</dd>
                        </div>
                    </dl>
                    <p className="panel-message">
                        An active station still requires an active guard and a current assignment
                        before any future verification operation.
                    </p>
                </Panel>
                <AssignmentForm context="gates" id={record.id} enabled={enabled} />
            </div>
            <Assignments records={assignments} context="gates" id={record.id} enabled={enabled} />
        </ManagementPage>
    );
}
