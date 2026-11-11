import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { ManagementPage, Select, FormErrors, Pagination } from '../../components/management/Common';
import HistoryTable from '../../components/monitoring/HistoryTable';
import { Badge, Button, Panel } from '../../components/ui';
import { manilaTime, type Paginated } from '../../lib/management';
import type { HistoryRow } from '../../lib/monitoring';
import { type Flag, readable } from './types';

export default function Show({
    flag: f,
    evidence,
    revision,
    reviews,
}: {
    flag: Flag & {
        explanation: string;
        policy: Record<string, unknown>;
        version: string;
        context: string;
    };
    evidence: HistoryRow[];
    revision: number;
    reviews: Paginated<{ status: string; reason: string; actor: string; at: string }>;
}) {
    const form = useForm({
        status: ['resolved', 'false_alarm'].includes(f.status) ? 'open' : 'in_review',
        reason: '',
        expected_revision: revision,
    });
    useEffect(() => {
        form.setData('expected_revision', revision);
        form.setData('reason', '');
        form.setData(
            'status',
            ['resolved', 'false_alarm'].includes(f.status) ? 'open' : 'in_review',
        );
    }, [revision]);
    return (
        <ManagementPage
            page="anomalies"
            title={f.title}
            description="Check the supporting scans and record your decision. A pattern may be explained by missed scans or an exception."
            back="/admin/anomalies"
        >
            <Panel
                className="anomaly-panel"
                title="Why this was flagged"
                subtitle={`${f.source} source · ${manilaTime(f.at)}`}
            >
                <Badge>{f.severity} priority</Badge> <Badge>{readable(f.status)}</Badge>
                <p>{f.explanation}</p>
                <p className="report-meaning">
                    {f.context === 'still_supported'
                        ? 'Current records still match this pattern.'
                        : 'The supporting records have changed since this check. Review the original evidence and any corrections before deciding.'}
                </p>
                <p>Latest reviewer: {f.reviewer ?? 'Not reviewed'}.</p>
            </Panel>
            <Panel
                className="anomaly-panel"
                title="Supporting records"
                subtitle="Scans used for this check. Open a record for the full result."
            >
                <HistoryTable rows={evidence} />
            </Panel>
            <Panel
                className="anomaly-panel"
                title="Record your review"
                subtitle="Save a decision and reason. Resolving a record means the review is complete."
            >
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(`/admin/anomalies/${f.id}/reviews`);
                    }}
                >
                    <FormErrors errors={form.errors} />
                    <Select
                        name="status"
                        label="Decision"
                        value={form.data.status}
                        onChange={(e) => form.setData('status', e.target.value)}
                    >
                        {(['resolved', 'false_alarm'].includes(f.status)
                            ? ['open']
                            : ['in_review', 'resolved', 'false_alarm', 'open']
                        ).map((v) => (
                            <option key={v} value={v}>
                                {v === 'open' ? 'Open / reopen' : readable(v)}
                            </option>
                        ))}
                    </Select>
                    <div className="form-field">
                        <label htmlFor="reason">Review reason</label>
                        <textarea
                            id="reason"
                            required
                            minLength={5}
                            maxLength={1000}
                            rows={4}
                            value={form.data.reason}
                            onChange={(e) => form.setData('reason', e.target.value)}
                        />
                        <p className="field-hint">
                            Explain evidence, exceptions and follow-up. Keep NFC references and
                            passwords out of notes.
                        </p>
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Saving review…' : 'Save review decision'}
                    </Button>
                </form>
            </Panel>
            <Panel
                className="anomaly-panel"
                title="Review history"
                subtitle="Newest first; previous decisions cannot be overwritten."
            >
                {reviews.data.length ? (
                    reviews.data.map((r, i) => (
                        <div className="correction-entry" key={i}>
                            <strong>
                                {readable(r.status)} · {r.actor}
                            </strong>
                            <p>{manilaTime(r.at)}</p>
                            <p>{r.reason}</p>
                        </div>
                    ))
                ) : (
                    <p>No review decisions yet.</p>
                )}
                <Pagination records={reviews} />
            </Panel>
        </ManagementPage>
    );
}
