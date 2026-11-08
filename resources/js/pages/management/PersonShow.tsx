import { useForm } from '@inertiajs/react';
import { Badge, Button, Panel } from '../../components/ui';
import {
    ArchiveActions,
    ConfirmAction,
    CurrentErrors,
    DataTable,
    Field,
    FormErrors,
    ManagementPage,
    Pagination,
    Portrait,
    RecordBadge,
} from '../../components/management/Common';
import {
    intervalStatus,
    manilaInput,
    manilaTime,
    type Grant,
    type Paginated,
    type PersonDirectory,
    type PersonRecord,
} from '../../lib/management';

export default function PersonShow({
    directory,
    record,
    grants,
}: {
    directory: PersonDirectory;
    record: PersonRecord;
    grants: Paginated<Grant>;
}) {
    const base = `/admin/${directory}/${record.id}`;
    const form = useForm({
        starts_at: manilaInput(),
        ends_at: '',
        reason: '',
        record_version: record.record_version,
    });
    return (
        <ManagementPage
            page={directory}
            title={record.full_name}
            description={`${directory === 'students' ? 'Student' : 'Employee'} · ${record.campus_number}`}
            back={`/admin/${directory}`}
            action={<ArchiveActions base={base} record={record} name={record.full_name} />}
        >
            <CurrentErrors />
            <div className="record-detail-grid">
                <Panel title="Identity and affiliation">
                    <div className="identity-summary">
                        <Portrait src={record.photo_url} name={record.full_name} />
                        <div>
                            <RecordBadge status={record.status} archived={record.archived_at} />
                            <p>{record.kind === 'student' ? record.program : record.department}</p>
                            <p className="small-muted">
                                {record.kind === 'student'
                                    ? [
                                          record.year_level ? `Year ${record.year_level}` : null,
                                          record.academic_term,
                                      ]
                                          .filter(Boolean)
                                          .join(' · ') || 'Year and term not specified'
                                    : record.position}
                            </p>
                        </div>
                    </div>
                    <dl className="record-facts">
                        <div>
                            <dt>Campus access now</dt>
                            <dd>
                                <Badge tone={record.access_active ? 'success' : 'neutral'}>
                                    {record.access_active ? 'Authorized' : 'Not authorized'}
                                </Badge>
                            </dd>
                        </div>
                        <div>
                            <dt>Identity photo</dt>
                            <dd>{record.photo_url ? 'Available privately' : 'Not supplied'}</dd>
                        </div>
                        <div>
                            <dt>NFC credential</dt>
                            <dd>Issuance pending a later phase</dd>
                        </div>
                    </dl>
                </Panel>
                <Panel title="Grant campus access" subtitle="Schedule in Asia/Manila (UTC+8)">
                    {record.archived_at || record.status !== 'active' ? (
                        <p className="panel-message">
                            {record.archived_at
                                ? 'Restore and activate the record before granting access.'
                                : 'Activate the person record before granting access.'}
                        </p>
                    ) : (
                        <form
                            className="management-form"
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.setData('record_version', record.record_version);
                                form.transform((data) => ({
                                    ...data,
                                    record_version: record.record_version,
                                }));
                                form.post(`${base}/grants`, {
                                    preserveScroll: true,
                                    onSuccess: () => form.reset('reason', 'ends_at'),
                                });
                            }}
                        >
                            <FormErrors errors={form.errors} />
                            <div className="form-grid">
                                <Field
                                    name="starts_at"
                                    label="Valid from"
                                    type="datetime-local"
                                    required
                                    value={form.data.starts_at}
                                    onChange={(e) => form.setData('starts_at', e.target.value)}
                                    error={form.errors.starts_at}
                                />
                                <Field
                                    name="ends_at"
                                    label="Valid until (optional)"
                                    type="datetime-local"
                                    value={form.data.ends_at}
                                    onChange={(e) => form.setData('ends_at', e.target.value)}
                                    error={form.errors.ends_at}
                                />
                            </div>
                            <Field
                                name="reason"
                                label="Reason for this grant"
                                required
                                maxLength={255}
                                value={form.data.reason}
                                onChange={(e) => form.setData('reason', e.target.value)}
                                error={form.errors.reason}
                            />
                            <p className="field-hint">
                                Leaving the end blank creates a grant with no scheduled expiry.
                                Existing overlapping grants must be revoked first.
                            </p>
                            <Button type="submit" variant="primary" disabled={form.processing}>
                                {form.processing ? 'Saving…' : 'Save access grant'}
                            </Button>
                        </form>
                    )}
                </Panel>
            </div>
            <Panel
                title="Access grant history"
                subtitle="Grant periods include their start and exclude their end. Times shown in Asia/Manila."
            >
                <DataTable
                    caption="Access grant history"
                    columns={['Valid from', 'Valid until', 'Reason', 'State', 'Action']}
                    empty="No access grants yet"
                    emptyDescription="Saved access grants and their revocations will appear here."
                    count={grants.data.length}
                >
                    {grants.data.map((grant) => (
                        <tr key={grant.id}>
                            <td>{manilaTime(grant.starts_at)}</td>
                            <td>{manilaTime(grant.ends_at)}</td>
                            <td>{grant.reason}</td>
                            <td>
                                <Badge
                                    tone={
                                        intervalStatus(grant) === 'Current' ? 'success' : 'neutral'
                                    }
                                >
                                    {intervalStatus(grant)}
                                </Badge>
                            </td>
                            <td>
                                {!grant.revoked_at && (
                                    <ConfirmAction
                                        url={`${base}/grants/${grant.id}`}
                                        label="Revoke grant"
                                        title="Revoke this access grant?"
                                        description={`The grant for ${record.full_name} starting ${manilaTime(grant.starts_at)} will stop applying immediately. Its history is retained.`}
                                    />
                                )}
                            </td>
                        </tr>
                    ))}
                </DataTable>
                <Pagination records={grants} />
            </Panel>
            <p className="availability-note">
                A current grant and active record do not verify physical identity or passage.
                Credential issuance and verification history are added in later phases.
            </p>
        </ManagementPage>
    );
}
