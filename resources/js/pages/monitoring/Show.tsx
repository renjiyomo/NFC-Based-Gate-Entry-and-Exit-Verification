import { Link, useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { ManagementPage, Select, FormErrors, Pagination } from '../../components/management/Common';
import { Badge, Button, Panel } from '../../components/ui';
import { manilaTime, type Paginated } from '../../lib/management';
import { rowState, type HistoryRow } from '../../lib/monitoring';
import { sequenceLabel } from '../../lib/gateFeedback';
import { reasonLabel, reviewStatusLabel } from '../../lib/operations';

type Correction = { id: string; action: string; reason: string; actor: string; at: string };
export default function Show({
    record: r,
    corrections,
    revision,
}: {
    record: HistoryRow;
    corrections: Paginated<Correction> | null;
    revision: number;
}) {
    const form = useForm({ action: 'note', reason: '', expected_revision: revision });
    useEffect(() => {
        form.setData('expected_revision', revision);
    }, [revision]);
    return (
        <ManagementPage
            page="history"
            title={r.view === 'passages' ? 'Entry & exit record' : 'Scan details'}
            description="Review the scan result and any saved corrections."
            back="/admin/history"
        >
            <Panel title={rowState(r)} className="activity-record">
                <Badge
                    tone={
                        r.voided || r.outcome === 'denied'
                            ? 'warning'
                            : r.passage_id
                              ? 'success'
                              : 'neutral'
                    }
                >
                    {r.source} source
                </Badge>
                <dl className="record-facts">
                    {[
                        [
                            'Person',
                            r.person
                                ? `${r.person.name} · ${r.person.number}`
                                : 'Unidentified coin',
                        ],
                        ['Time · Manila', manilaTime(r.time)],
                        ['Gate', `${r.gate.code} · ${r.gate.name}`],
                        ['Gate account', r.guard],
                        ['Direction', r.direction === 'entry' ? 'Entry' : 'Exit'],
                        ['Credential label', r.credential ?? 'Not identified'],
                        ['Credential check', reasonLabel(r.reason)],
                        [
                            'Scan status',
                            `${reviewStatusLabel(r.review)}${r.review_reason ? ' · ' + reasonLabel(r.review_reason) : ''}`,
                        ],
                        [
                            'Passage',
                            r.passage_id
                                ? r.voided
                                    ? 'Original retained; excluded from effective totals'
                                    : 'Included in Entry & Exit totals'
                                : 'No passage recorded',
                        ],
                        [
                            'Recording method',
                            r.identity_method === 'nfc_scan'
                                ? 'Automatic NFC scan'
                                : r.identity_method === 'campus_document'
                                  ? 'University document comparison (legacy)'
                                  : r.identity_method === 'photo'
                                    ? 'Photo comparison (legacy)'
                                    : (r.identity_method?.replaceAll('_', ' ') ?? 'No passage'),
                        ],
                        [
                            'Sequence notice',
                            r.sequence_notice ? sequenceLabel(r.sequence_notice) : 'None',
                        ],
                    ].map(([label, value]) => (
                        <div key={label}>
                            <dt>{label}</dt>
                            <dd>{value}</dd>
                        </div>
                    ))}
                </dl>
                {r.passage_id && r.view === 'attempts' && (
                    <Link className="text-link" href={`/admin/history/passages/${r.passage_id}`}>
                        Open linked passage
                    </Link>
                )}
            </Panel>
            {r.passage_id && (
                <Panel
                    title="Correct this record"
                    subtitle="Add a note or exclude an incorrect passage from totals. The original record stays in history."
                >
                    <form
                        className="management-form"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(`/admin/history/passages/${r.passage_id}/corrections`, {
                                preserveScroll: true,
                                onSuccess: () => form.reset('reason'),
                            });
                        }}
                    >
                        <FormErrors errors={form.errors} />
                        <Select
                            name="action"
                            label="Correction action"
                            value={form.data.action}
                            onChange={(e) => form.setData('action', e.target.value)}
                        >
                            <option value="note">Add a note</option>
                            {r.voided ? (
                                <option value="reinstate">Reinstate passage in totals</option>
                            ) : (
                                <option value="void">Void passage from totals</option>
                            )}
                        </Select>
                        <div className="form-field">
                            <label htmlFor="reason">Reason *</label>
                            <textarea
                                id="reason"
                                name="reason"
                                rows={4}
                                required
                                minLength={5}
                                maxLength={1000}
                                value={form.data.reason}
                                onChange={(e) => form.setData('reason', e.target.value)}
                                aria-describedby="reason-hint"
                            />
                            <p id="reason-hint" className="field-hint">
                                Explain the correction. Keep passwords and NFC references out of
                                notes.
                            </p>
                        </div>
                        <Button type="submit" variant="primary" disabled={form.processing}>
                            {form.processing ? 'Saving correction…' : 'Save correction'}
                        </Button>
                    </form>
                </Panel>
            )}
            {corrections && (
                <Panel
                    title="Correction history"
                    subtitle="Newest first. Each entry retains its administrator, reason and time."
                >
                    {corrections.data.length ? (
                        corrections.data.map((c) => (
                            <article className="correction-entry" key={c.id}>
                                <strong>
                                    {c.action} · {c.actor}
                                </strong>
                                <span>{manilaTime(c.at)}</span>
                                <p>{c.reason}</p>
                            </article>
                        ))
                    ) : (
                        <p className="report-empty">No corrections have been appended.</p>
                    )}
                    <Pagination records={corrections} />
                </Panel>
            )}
        </ManagementPage>
    );
}
