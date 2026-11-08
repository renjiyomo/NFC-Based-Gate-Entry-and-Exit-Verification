import ChoicePicker from './ChoicePicker';
import { Link, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { Badge, Button, Panel } from '../ui';
import { ConfirmAction, DataTable, Field, FormErrors, Pagination } from './Common';
import {
    intervalStatus,
    manilaInput,
    manilaTime,
    type Assignment,
    type Paginated,
} from '../../lib/management';

export function AssignmentForm({
    context,
    id,
    enabled,
}: {
    context: 'gates' | 'guards';
    id: number;
    enabled: boolean;
}) {
    const form = useForm({
        guard_id: context === 'guards' ? id : '',
        gate_id: context === 'gates' ? id : '',
        starts_at: manilaInput(),
        ends_at: '',
    });
    return (
        <Panel title="Assign a gate station" subtitle="Schedule in Asia/Manila (UTC+8)">
            {enabled ? (
                <form
                    className="management-form"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(`/admin/${context}/${id}/assignments`, { preserveScroll: true });
                    }}
                >
                    <FormErrors errors={form.errors} />
                    <ChoicePicker
                        kind={context === 'gates' ? 'guards' : 'gates'}
                        name={context === 'gates' ? 'guard_id' : 'gate_id'}
                        label={
                            context === 'gates' ? 'Active security guard' : 'Active gate station'
                        }
                        required
                        value={context === 'gates' ? form.data.guard_id : form.data.gate_id}
                        error={context === 'gates' ? form.errors.guard_id : form.errors.gate_id}
                        onChange={(value) =>
                            form.setData(context === 'gates' ? 'guard_id' : 'gate_id', value)
                        }
                    />

                    <div className="form-grid">
                        <Field
                            name="starts_at"
                            label="Starts at"
                            type="datetime-local"
                            required
                            value={form.data.starts_at}
                            onChange={(e) => form.setData('starts_at', e.target.value)}
                            error={form.errors.starts_at}
                        />
                        <Field
                            name="ends_at"
                            label="Ends at (optional)"
                            type="datetime-local"
                            value={form.data.ends_at}
                            onChange={(e) => form.setData('ends_at', e.target.value)}
                            error={form.errors.ends_at}
                        />
                    </div>
                    <p className="field-hint">
                        Periods include the start and exclude the end. A blank end leaves the
                        assignment open until revoked. Overlapping periods at the same gate are
                        rejected.
                    </p>
                    <Button
                        type="submit"
                        variant="primary"
                        disabled={
                            form.processing ||
                            !(context === 'gates' ? form.data.guard_id : form.data.gate_id)
                        }
                    >
                        {form.processing ? 'Saving…' : 'Save assignment'}
                    </Button>
                </form>
            ) : (
                <p className="panel-message">Activate this record before creating assignments.</p>
            )}
        </Panel>
    );
}
function Reschedule({
    assignment,
    onDone,
    context,
    id,
}: {
    assignment: Assignment;
    onDone: () => void;
    context: 'gates' | 'guards';
    id: number;
}) {
    const dialog = useRef<HTMLDialogElement>(null);
    const form = useForm({
        guard_id: assignment.guard_id,
        gate_id: assignment.gate_id,
        starts_at: manilaInput(assignment.starts_at),
        ends_at: assignment.ends_at ? manilaInput(assignment.ends_at) : '',
    });
    return (
        <dialog
            ref={(element) => {
                dialog.current = element;
                if (element && !element.open) element.showModal();
            }}
            onCancel={onDone}
            className="management-dialog"
            aria-label="Replace assignment schedule"
        >
            <h2>Replace assignment schedule</h2>
            <p>
                {assignment.guard_name} · {assignment.gate_name}. The old schedule will be revoked
                and retained in history.
            </p>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/admin/${context}/${id}/assignments/${assignment.id}`, {
                        preserveScroll: true,
                        onSuccess: onDone,
                    });
                }}
            >
                <FormErrors errors={form.errors} />
                <Field
                    id="reschedule-start"
                    name="starts_at"
                    label="Starts at"
                    type="datetime-local"
                    required
                    value={form.data.starts_at}
                    onChange={(e) => form.setData('starts_at', e.target.value)}
                    error={form.errors.starts_at}
                />
                <Field
                    id="reschedule-end"
                    name="ends_at"
                    label="Ends at (optional)"
                    type="datetime-local"
                    value={form.data.ends_at}
                    onChange={(e) => form.setData('ends_at', e.target.value)}
                    error={form.errors.ends_at}
                />
                <div className="form-actions">
                    <Button disabled={form.processing} onClick={onDone}>
                        Cancel
                    </Button>
                    <Button type="submit" variant="primary" disabled={form.processing}>
                        {form.processing ? 'Saving…' : 'Replace schedule'}
                    </Button>
                </div>
            </form>
        </dialog>
    );
}
export function Assignments({
    records,
    context,
    id,
    enabled,
}: {
    records: Paginated<Assignment>;
    context: 'gates' | 'guards';
    id: number;
    enabled: boolean;
}) {
    const [editing, setEditing] = useState<Assignment | null>(null);
    return (
        <>
            <Panel
                title="Gate assignment history"
                subtitle="Times shown in Asia/Manila. Revoked schedules remain in history."
            >
                <DataTable
                    caption="Gate assignment history"
                    columns={[
                        'Guard / gate',
                        'Starts at',
                        'Ends at',
                        'State at last load',
                        'Actions',
                    ]}
                    empty="No gate assignments yet"
                    emptyDescription="Saved schedules will appear here, including expired and revoked assignments."
                    count={records.data.length}
                >
                    {records.data.map((assignment) => (
                        <tr key={assignment.id}>
                            <td>
                                <Link
                                    href={`/admin/guards/${assignment.guard_id}`}
                                    className="row-link"
                                >
                                    {assignment.guard_name}
                                </Link>
                                <small>
                                    <Link href={`/admin/gates/${assignment.gate_id}`}>
                                        {assignment.gate_code} · {assignment.gate_name}
                                    </Link>
                                </small>
                            </td>
                            <td>{manilaTime(assignment.starts_at)}</td>
                            <td>{manilaTime(assignment.ends_at)}</td>
                            <td>
                                <Badge
                                    tone={
                                        intervalStatus(assignment) === 'Current'
                                            ? 'success'
                                            : 'neutral'
                                    }
                                >
                                    {intervalStatus(assignment)}
                                </Badge>
                            </td>
                            <td>
                                <div className="row-actions">
                                    {!assignment.revoked_at && (
                                        <>
                                            {enabled && assignment.operable && (
                                                <Button onClick={() => setEditing(assignment)}>
                                                    Change schedule
                                                </Button>
                                            )}
                                            <ConfirmAction
                                                url={`/admin/${context}/${id}/assignments/${assignment.id}`}
                                                label="Revoke"
                                                title="Revoke gate assignment?"
                                                description={`${assignment.guard_name} will no longer be permitted to operate ${assignment.gate_name} under this assignment.`}
                                            />
                                        </>
                                    )}
                                </div>
                            </td>
                        </tr>
                    ))}
                </DataTable>
                <Pagination records={records} />
            </Panel>
            {editing && (
                <Reschedule
                    key={editing.id}
                    assignment={editing}
                    context={context}
                    id={id}
                    onDone={() => setEditing(null)}
                />
            )}
        </>
    );
}
