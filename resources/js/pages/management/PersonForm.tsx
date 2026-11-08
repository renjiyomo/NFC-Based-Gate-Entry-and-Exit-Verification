import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    Field,
    FormErrors,
    FormPanel,
    ManagementPage,
    Portrait,
    SaveActions,
    Select,
} from '../../components/management/Common';
import type { PersonDirectory, PersonRecord } from '../../lib/management';

export default function PersonForm({
    directory,
    record,
}: {
    directory: PersonDirectory;
    record: PersonRecord | null;
}) {
    const student = directory === 'students';
    const base = `/admin/${directory}`;
    const [preview, setPreview] = useState<string | null>(record?.photo_url ?? null);
    useEffect(
        () => () => {
            if (preview?.startsWith('blob:')) URL.revokeObjectURL(preview);
        },
        [preview],
    );
    const form = useForm({
        full_name: record?.full_name ?? '',
        campus_number: record?.campus_number ?? '',
        status: record?.status ?? 'active',
        program: record?.program ?? '',
        year_level: record?.year_level?.toString() ?? '',
        academic_term: record?.academic_term ?? '',
        department: record?.department ?? '',
        position: record?.position ?? '',
        photo: null as File | null,
        remove_photo: false,
        record_version: record?.record_version ?? 1,
        _method: record ? 'put' : 'post',
    });
    return (
        <ManagementPage
            page={directory}
            title={`${record ? 'Edit' : 'Add'} ${student ? 'student' : 'employee'}`}
            description="Collect only the identity and affiliation details needed for campus verification."
            back={record ? `${base}/${record.id}` : base}
        >
            <FormPanel title="Identity details">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(record ? `${base}/${record.id}` : base, { forceFormData: true });
                    }}
                >
                    <FormErrors errors={form.errors} />
                    <div className="form-grid">
                        <Field
                            name="full_name"
                            label="Full name"
                            required
                            maxLength={120}
                            autoComplete="off"
                            value={form.data.full_name}
                            onChange={(e) => form.setData('full_name', e.target.value)}
                            error={form.errors.full_name}
                        />
                        <Field
                            name="campus_number"
                            label={student ? 'University student number' : 'Employee number'}
                            required
                            maxLength={64}
                            value={form.data.campus_number}
                            onChange={(e) => form.setData('campus_number', e.target.value)}
                            error={form.errors.campus_number}
                        />
                        {student ? (
                            <>
                                <Field
                                    name="program"
                                    label="Program / course"
                                    required
                                    maxLength={120}
                                    value={form.data.program}
                                    onChange={(e) => form.setData('program', e.target.value)}
                                    error={form.errors.program}
                                />
                                <Field
                                    name="year_level"
                                    label="Year level (optional)"
                                    type="number"
                                    min={1}
                                    max={255}
                                    value={form.data.year_level}
                                    onChange={(e) => form.setData('year_level', e.target.value)}
                                    error={form.errors.year_level}
                                />
                                <Field
                                    name="academic_term"
                                    label="Academic term (optional)"
                                    maxLength={50}
                                    value={form.data.academic_term}
                                    onChange={(e) => form.setData('academic_term', e.target.value)}
                                    error={form.errors.academic_term}
                                />
                            </>
                        ) : (
                            <>
                                <Field
                                    name="department"
                                    label="Department"
                                    required
                                    maxLength={120}
                                    value={form.data.department}
                                    onChange={(e) => form.setData('department', e.target.value)}
                                    error={form.errors.department}
                                />
                                <Field
                                    name="position"
                                    label="Position"
                                    required
                                    maxLength={120}
                                    value={form.data.position}
                                    onChange={(e) => form.setData('position', e.target.value)}
                                    error={form.errors.position}
                                />
                            </>
                        )}
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
                    <div className="photo-editor">
                        <Portrait src={preview} name={form.data.full_name || 'Person'} />
                        <div>
                            <Field
                                name="photo"
                                label="Identity photo (optional)"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                error={form.errors.photo}
                                hint="JPEG, PNG or WebP. Up to 2 MB and 4096 × 4096 pixels. Stored privately; resized and stripped of embedded metadata."
                                onChange={(e) => {
                                    const file = e.target.files?.[0] ?? null;
                                    form.setData('photo', file);
                                    form.setData('remove_photo', false);
                                    if (preview?.startsWith('blob:')) URL.revokeObjectURL(preview);
                                    setPreview(
                                        file
                                            ? URL.createObjectURL(file)
                                            : (record?.photo_url ?? null),
                                    );
                                }}
                            />
                            {record?.photo_url && (
                                <label className="checkbox-control">
                                    <input
                                        type="checkbox"
                                        checked={form.data.remove_photo}
                                        disabled={!!form.data.photo}
                                        onChange={(e) => {
                                            form.setData('remove_photo', e.target.checked);
                                            setPreview(e.target.checked ? null : record.photo_url);
                                        }}
                                    />
                                    Remove existing photo
                                </label>
                            )}
                        </div>
                    </div>
                    <p className="form-note">
                        Record status does not issue an NFC credential or grant campus access.
                        Manage access grants from the saved record. Inactive records block access;
                        existing grant periods may apply again when the record is reactivated.
                    </p>
                    <SaveActions
                        busy={form.processing}
                        cancel={record ? `${base}/${record.id}` : base}
                        label={record ? 'Save changes' : 'Create record'}
                    />
                </form>
            </FormPanel>
        </ManagementPage>
    );
}
