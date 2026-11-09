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
            description="Inspect the evidence and record a reasoned human decision. An indicator does not establish wrongdoing."
            back="/admin/anomalies"
        >
            <Panel
                className="anomaly-panel"
                title="Detection explanation"
                subtitle={`${f.source} source · ${manilaTime(f.at)}`}
            >
                <Badge>{f.severity} priority</Badge> <Badge>{readable(f.status)}</Badge>
                <p>{f.explanation}</p>
                <p className="report-meaning">
                    {f.context === 'still_supported'
                        ? 'Current records still support the original rule and evidence. This does not confirm wrongdoing.'
                        : 'Evidence has changed: corrections or later review state no longer support the same evidence set. Original detection is retained; reassess this flag before deciding.'}
                </p>
                <p>
                    Latest reviewer: {f.reviewer ?? 'Not reviewed'}. Rules describe recording
                    patterns; missing scans, reader issues, shift work or authorized exceptions can
                    explain them.
                </p>
                <details className="chart-data">
                    <summary>Rule version and configuration</summary>
                    <p className="anomaly-version">{f.version}</p>
                    <dl className="record-facts">
                        {Object.entries(f.policy).map(([k, v]) => (
                            <div key={k}>
                                <dt>{readable(k)}</dt>
                                <dd>{String(v ?? 'not configured')}</dd>
                            </div>
                        ))}
                    </dl>
                </details>
            </Panel>
            <Panel
                className="anomaly-panel"
                title="Supporting records"
                subtitle="Original evidence remains linked even if a passage is later voided. Open each record to inspect the original facts."
            >
                <HistoryTable rows={evidence} />
            </Panel>
            <Panel
                className="anomaly-panel"
                title="Append a review decision"
                subtitle="All decisions retain administrator, time and reason. Resolve means review completed; it is not a disciplinary finding."
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
