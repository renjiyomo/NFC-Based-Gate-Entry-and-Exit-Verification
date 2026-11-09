import { useForm } from '@inertiajs/react';
import {
    Field,
    Select,
    FormErrors,
    FormPanel,
    ManagementPage,
    SaveActions,
} from '../../components/management/Common';
import type { GateRecord } from '../../lib/management';
export default function GateForm({ record }: { record: GateRecord | null }) {
    const form = useForm({
        name: record?.name ?? '',
        code: record?.code ?? '',
        status: record?.status ?? 'active',
        username: record?.username ?? '',
        password: '',
        password_confirmation: '',
        record_version: record?.record_version ?? 1,
    });
    const base = '/admin/gates';
    return (
        <ManagementPage
            page="gates"
            title={record ? 'Edit gate and account' : 'Add gate and account'}
            description="One login per gate. Guards use the same session across shifts."
            back={base}
        >
            <FormPanel title="Gate and login">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (record)
                            form.put(`${base}/${record.id}`, {
                                onFinish: () => form.reset('password', 'password_confirmation'),
                            });
                        else
                            form.post(base, {
                                onFinish: () => form.reset('password', 'password_confirmation'),
                            });
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
                        />
                        <Field
                            name="username"
                            label="Username"
                            required
                            autoComplete="off"
                            maxLength={64}
                            value={form.data.username}
                            onChange={(e) => form.setData('username', e.target.value)}
                            error={form.errors.username}
                        />
                        <Field
                            name="password"
                            label={record?.account_id ? 'New password (optional)' : 'Password'}
                            type="password"
                            required={!record?.account_id}
                            autoComplete="new-password"
                            value={form.data.password}
                            onChange={(e) => form.setData('password', e.target.value)}
                            error={form.errors.password}
                            hint="At least 15 characters. Leave blank to keep an existing password."
                        />
                        <Field
                            name="password_confirmation"
                            label="Confirm password"
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password_confirmation}
                            onChange={(e) => form.setData('password_confirmation', e.target.value)}
                            error={form.errors.password_confirmation}
                        />
                        <Select
                            name="status"
                            label="Gate status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value as 'active' | 'inactive')
                            }
                        >
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </Select>
                    </div>
                    <p className="form-note">
                        The gate and its account are saved together. No email activation, guard-name
                        duplication or shift dates are required.
                    </p>
                    <SaveActions
                        busy={form.processing}
                        cancel={base}
                        label={record ? 'Save gate and account' : 'Create gate and account'}
                    />
                </form>
            </FormPanel>
        </ManagementPage>
    );
}
