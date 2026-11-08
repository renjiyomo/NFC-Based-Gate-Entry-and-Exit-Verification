import { useForm } from '@inertiajs/react';
import {
    Field,
    FormErrors,
    FormPanel,
    ManagementPage,
    SaveActions,
    Select,
} from '../../components/management/Common';
import type { GateRecord } from '../../lib/management';

export default function GateForm({ record }: { record: GateRecord | null }) {
    const form = useForm({
        name: record?.name ?? '',
        code: record?.code ?? '',
        status: record?.status ?? 'active',
        record_version: record?.record_version ?? 1,
    });
    const back = record ? `/admin/gates/${record.id}` : '/admin/gates';
    return (
        <ManagementPage
            page="gates"
            title={record ? 'Edit gate station' : 'Add gate station'}
            description="Use a clear station name and a unique gate code."
            back={back}
        >
            <FormPanel title="Station details">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (record) form.put(back);
                        else form.post('/admin/gates');
                    }}
                >
                    <FormErrors errors={form.errors} />
                    <div className="form-grid">
                        <Field
                            name="name"
                            label="Gate name"
                            required
                            maxLength={120}
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            error={form.errors.name}
                        />
                        <Field
                            name="code"
                            label="Gate code"
                            required
                            maxLength={32}
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value)}
                            error={form.errors.code}
                            hint="Letters, numbers, hyphens and underscores; stored in uppercase."
                        />
                        <Select
                            name="status"
                            label="Record status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value as 'active' | 'inactive')
                            }
                            error={form.errors.status}
                        >
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </Select>
                    </div>
                    <p className="form-note">
                        Inactive gates cannot be operated. Archiving also revokes their existing
                        assignments.
                    </p>
                    <SaveActions
                        busy={form.processing}
                        cancel={back}
                        label={record ? 'Save changes' : 'Create gate station'}
                    />
                </form>
            </FormPanel>
        </ManagementPage>
    );
}
