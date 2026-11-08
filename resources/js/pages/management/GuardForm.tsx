import { useForm } from '@inertiajs/react';
import {
    Field,
    FormErrors,
    FormPanel,
    ManagementPage,
    SaveActions,
    Select,
} from '../../components/management/Common';
import type { GuardRecord } from '../../lib/management';

export default function GuardForm({ record }: { record: GuardRecord | null }) {
    const form = useForm({
        name: record?.name ?? '',
        email: record?.email ?? '',
        status: record?.status ?? 'active',
        record_version: record?.record_version ?? 1,
    });
    const back = record ? `/admin/guards/${record.id}` : '/admin/guards';
    return (
        <ManagementPage
            page="guards"
            title={record ? 'Edit security guard' : 'Add security guard'}
            description="Guard accounts can operate only their assigned active stations."
            back={back}
        >
            <FormPanel title="Guard account">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (record) form.put(back);
                        else form.post('/admin/guards');
                    }}
                >
                    <FormErrors errors={form.errors} />
                    <div className="form-grid">
                        <Field
                            name="name"
                            label="Full name"
                            required
                            maxLength={120}
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            error={form.errors.name}
                        />
                        <Field
                            name="email"
                            label="Account email"
                            type="email"
                            required
                            maxLength={255}
                            readOnly={!!record}
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                            error={form.errors.email}
                            hint={
                                record
                                    ? 'The account email is fixed in this management workflow.'
                                    : 'The guard will receive a link to set their own password.'
                            }
                        />
                        {record && (
                            <Select
                                name="status"
                                label="Account status"
                                value={form.data.status}
                                onChange={(e) =>
                                    form.setData('status', e.target.value as 'active' | 'inactive')
                                }
                                error={form.errors.status}
                            >
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </Select>
                        )}
                    </div>
                    <p className="form-note">
                        {record
                            ? 'Disabling an account ends existing sessions and blocks station access. Archiving also revokes assignments.'
                            : 'This creates an active security guard account and sends a password setup link. No administrator permission or gate assignment is granted.'}
                    </p>
                    <SaveActions
                        busy={form.processing}
                        cancel={back}
                        label={record ? 'Save changes' : 'Create guard and send setup link'}
                    />
                </form>
            </FormPanel>
        </ManagementPage>
    );
}
