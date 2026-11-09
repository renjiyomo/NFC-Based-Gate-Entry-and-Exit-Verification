import { useForm } from '@inertiajs/react';
import {
    Field,
    Select,
    FormErrors,
    FormPanel,
    ManagementPage,
    SaveActions,
    Portrait,
} from '../../components/management/Common';
import type { PersonDirectory, PersonRecord } from '../../lib/management';

type Catalog = {
    programs: { code: string; name: string; max_year_level: number }[];
    departments: string[];
    semesters: string[];
};
export default function PersonForm({
    directory,
    record,
    catalog,
}: {
    directory: PersonDirectory;
    record: PersonRecord | null;
    catalog: Catalog;
}) {
    const student = directory === 'students';
    const base = `/admin/${directory}`;
    const form = useForm({
        full_name: record?.full_name ?? '',
        campus_number: record?.campus_number ?? '',
        status: record?.status ?? 'active',
        program: record?.program ?? '',
        year_level: record?.year_level?.toString() ?? '',
        academic_year: record?.academic_year ?? '',
        semester: record?.semester ?? '',
        department: record?.department ?? '',
        position: record?.position ?? '',
        photo: null as File | null,
        remove_photo: false,
        record_version: record?.record_version ?? 1,
        _method: record ? 'put' : 'post',
    });
    const max = catalog.programs.find((p) => p.code === form.data.program)?.max_year_level ?? 4;
    return (
        <ManagementPage
            page={directory}
            title={`${record ? 'Edit' : 'Add'} ${student ? 'student' : 'employee'}`}
            description="Register the person, then issue an NFC credential."
            back={record ? `${base}/${record.id}` : base}
        >
            <FormPanel title="Identity details">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
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
                                <Select
                                    name="program"
                                    label="Program / course"
                                    required
                                    value={form.data.program}
                                    onChange={(e) => {
                                        form.setData('program', e.target.value);
                                        form.setData('year_level', '');
                                    }}
                                    error={form.errors.program}
                                >
                                    <option value="">Select a BU Polangui program</option>
                                    {catalog.programs.map((p) => (
                                        <option key={p.code} value={p.code}>
                                            {p.code} · {p.name}
                                        </option>
                                    ))}
                                </Select>
                                <Select
                                    name="year_level"
                                    label="Year level"
                                    required
                                    value={form.data.year_level}
                                    onChange={(e) => form.setData('year_level', e.target.value)}
                                    error={form.errors.year_level}
                                >
                                    <option value="">Select year level</option>
                                    {Array.from({ length: max }, (_, i) => i + 1).map((y) => (
                                        <option key={y} value={y}>
                                            Year {y}
                                        </option>
                                    ))}
                                </Select>
                                <Field
                                    name="academic_year"
                                    label="Academic year"
                                    required
                                    placeholder="2026-2027"
                                    maxLength={9}
                                    value={form.data.academic_year}
                                    onChange={(e) => form.setData('academic_year', e.target.value)}
                                    error={form.errors.academic_year}
                                    hint="Use consecutive years, for example 2026-2027."
                                />
                                <Select
                                    name="semester"
                                    label="Semester"
                                    required
                                    value={form.data.semester}
                                    onChange={(e) => form.setData('semester', e.target.value)}
                                    error={form.errors.semester}
                                >
                                    <option value="">Select semester</option>
                                    {catalog.semesters.map((s) => (
                                        <option key={s}>{s}</option>
                                    ))}
                                </Select>
                            </>
                        ) : (
                            <>
                                <Select
                                    name="department"
                                    label="Department"
                                    required
                                    value={form.data.department}
                                    onChange={(e) => form.setData('department', e.target.value)}
                                    error={form.errors.department}
                                >
                                    <option value="">Select department</option>
                                    {catalog.departments.map((d) => (
                                        <option key={d}>{d}</option>
                                    ))}
                                </Select>
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
                        >
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </Select>
                    </div>
                    <div className="photo-editor">
                        <Portrait src={record?.photo_url ?? null} name={form.data.full_name} />
                        <div>
                            <Field
                                name="photo"
                                label="Identity photo (optional)"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                onChange={(e) => form.setData('photo', e.target.files?.[0] ?? null)}
                                error={form.errors.photo}
                                hint="JPEG, PNG or WebP, up to 2 MB. Stored privately."
                            />
                            {record?.photo_url && (
                                <label className="checkbox-control">
                                    <input
                                        type="checkbox"
                                        checked={form.data.remove_photo}
                                        onChange={(e) =>
                                            form.setData('remove_photo', e.target.checked)
                                        }
                                    />
                                    Remove current photo
                                </label>
                            )}
                        </div>
                    </div>
                    <p className="form-note">
                        An active person with an activated, selected NFC credential can scan.
                        Inactive records and revoked or expired credentials are rejected.
                    </p>
                    <SaveActions
                        busy={form.processing}
                        cancel={base}
                        label={record ? 'Save changes' : 'Create record'}
                    />
                </form>
            </FormPanel>
        </ManagementPage>
    );
}
