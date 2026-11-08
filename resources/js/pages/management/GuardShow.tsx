import { useForm } from '@inertiajs/react';
import { Button, Panel } from '../../components/ui';
import {
    ArchiveActions,
    CurrentErrors,
    FormErrors,
    ManagementPage,
    RecordBadge,
} from '../../components/management/Common';
import { AssignmentForm, Assignments } from '../../components/management/Assignments';
import type { Assignment, GuardRecord, Paginated } from '../../lib/management';

export default function GuardShow({
    record,
    assignments,
}: {
    record: GuardRecord;
    assignments: Paginated<Assignment>;
}) {
    const setup = useForm({});
    const enabled = !record.archived_at && record.status === 'active';
    return (
        <ManagementPage
            page="guards"
            title={record.name}
            description={`Security guard · ${record.email}`}
            back="/admin/guards"
            action={
                <ArchiveActions
                    base={`/admin/guards/${record.id}`}
                    record={record}
                    name={record.name}
                />
            }
        >
            <CurrentErrors />
            <div className="record-detail-grid">
                <Panel title="Guard account">
                    <dl className="record-facts">
                        <div>
                            <dt>Account email</dt>
                            <dd>{record.email}</dd>
                        </div>
                        <div>
                            <dt>Account status</dt>
                            <dd>
                                <RecordBadge status={record.status} archived={record.archived_at} />
                            </dd>
                        </div>
                        <div>
                            <dt>Password setup</dt>
                            <dd>{record.must_change_password ? 'Pending' : 'Completed'}</dd>
                        </div>
                        <div>
                            <dt>Role</dt>
                            <dd>Security guard</dd>
                        </div>
                    </dl>
                    {enabled && (
                        <form
                            className="management-form"
                            onSubmit={(event) => {
                                event.preventDefault();
                                setup.post(`/admin/guards/${record.id}/setup-link`, {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <FormErrors errors={setup.errors} />
                            <Button type="submit" disabled={setup.processing}>
                                {setup.processing ? 'Requesting…' : 'Send password setup link'}
                            </Button>
                            <p className="field-hint">
                                The guard chooses their own password through their email link.
                                Recent links are not resent within one minute.
                            </p>
                        </form>
                    )}
                </Panel>
                <AssignmentForm context="guards" id={record.id} enabled={enabled} />
            </div>
            <Assignments records={assignments} context="guards" id={record.id} enabled={enabled} />
        </ManagementPage>
    );
}
