import { router, useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { ManagementPage, Select, FormErrors, Pagination } from '../../components/management/Common';
import { Badge, Button, Panel } from '../../components/ui';
import { manilaTime, type Paginated } from '../../lib/management';
import { type Facts, type SummaryRow, readable } from './types';

export default function Show({
    summary: s,
    revision,
    reviews,
}: {
    summary: SummaryRow & {
        snapshot: Facts;
        hash: string;
        contract: string;
        changed: boolean;
        sentences: string[] | null;
        error: string | null;
        provider: string | null;
        model: string | null;
        tokens: { input: number | null; output: number | null };
        recoverable: boolean;
    };
    revision: number;
    reviews: Paginated<{ decision: string; reason: string; actor: string; at: string }>;
}) {
    const form = useForm({ decision: 'approved', reason: '', expected_revision: revision });
    const recovery = useForm({});
    useEffect(() => {
        form.setData('expected_revision', revision);
        form.setData('reason', '');
    }, [revision]);
    useEffect(() => {
        if (!['queued', 'processing'].includes(s.status)) return;
        const t = setInterval(() => {
            if (document.visibilityState === 'visible')
                router.reload({ only: ['summary', 'revision', 'reviews'] });
        }, 5000);
        return () => clearInterval(t);
    }, [s.status]);
    return (
        <ManagementPage
            page="summaries"
            title="Summary details"
            description="Saved aggregate snapshot, generation outcome and administrator review."
            back="/admin/summaries"
            action={<Button onClick={() => router.reload()}>Refresh status</Button>}
        >
            <div className="report-period">
                <div>
                    <strong>
                        {s.from} through {s.to} · {s.period}
                    </strong>
                    <span>
                        {s.source} source · snapshot {manilaTime(s.at)} · requested by {s.actor}
                    </span>
                </div>
                <Badge>{readable(s.status)}</Badge>
            </div>
            {s.changed && (
                <p className="report-meaning">
                    Current records differ from this saved snapshot. New activity, corrections or
                    flag reviews may explain the change. The saved summary is retained; assess the
                    difference before approving it.
                </p>
            )}
            <Panel
                className="anomaly-panel"
                title={s.status === 'ready' ? 'AI-assisted draft' : 'Generation status'}
                subtitle="Figures and wording are rendered from the saved backend facts; model output is limited to selecting fact IDs."
            >
                {s.provider === 'test_fixture' && (
                    <p className="report-meaning">
                        Synthetic provider test result. No external AI request was made for this
                        draft.
                    </p>
                )}
                {s.status === 'ready' && s.sentences ? (
                    <>
                        <Badge>{readable(s.review)}</Badge>
                        <ul className="summary-sentences">
                            {s.sentences.map((v, i) => (
                                <li key={i}>{v}</li>
                            ))}
                        </ul>
                    </>
                ) : (
                    <p>
                        {s.status === 'queued'
                            ? 'Waiting for generation to start. Gate verification remains available.'
                            : s.status === 'processing'
                              ? 'Generation is in progress. An uncertain response will not be automatically retried.'
                              : 'No AI draft was accepted. ' +
                                readable(s.error ?? 'unavailable') +
                                '. The saved aggregate facts remain available below.'}
                    </p>
                )}
                {s.recoverable && (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            recovery.post(`/admin/summaries/${s.id}/recover`);
                        }}
                    >
                        <FormErrors errors={recovery.errors} />
                        <Button type="submit" disabled={recovery.processing}>
                            Close interrupted request without retry
                        </Button>
                    </form>
                )}
                <p>
                    Provider: {s.provider ?? 'Not processed'} · model: {s.model ?? 'Not processed'}.
                    Completed: {s.completed_at ? manilaTime(s.completed_at) : 'Not completed'}.
                </p>
            </Panel>
            <details className="report-filter-disclosure">
                <summary>Inspect saved facts and provenance</summary>
                <Panel className="anomaly-panel" title="Saved aggregate snapshot">
                    <p>
                        Contract {s.contract}. Gate aliases are scoped to this snapshot.{' '}
                        {s.snapshot.gate_coverage}
                    </p>
                    <p className="anomaly-version">Snapshot fingerprint: {s.hash}</p>
                    <p>
                        Provider-reported tokens: input {s.tokens.input ?? 'not reported'}, output
                        including thinking {s.tokens.output ?? 'not reported'}.
                    </p>
                    <div className="report-scroll management-table">
                        <table className="data-table">
                            <thead>
                                <tr>
                                    <th scope="col">Fact</th>
                                    <th scope="col">Validated statement</th>
                                </tr>
                            </thead>
                            <tbody>
                                {Object.entries(s.snapshot.facts).map(([k, v]) => (
                                    <tr key={k}>
                                        <td>{readable(k)}</td>
                                        <td>{v.sentence}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Panel>
            </details>
            {s.status === 'ready' && (
                <Panel
                    className="anomaly-panel"
                    title="Administrator review"
                    subtitle="Approval applies to this saved snapshot and selected observations. It does not establish wrongdoing or operational readiness."
                >
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(`/admin/summaries/${s.id}/reviews`);
                        }}
                    >
                        <FormErrors errors={form.errors} />
                        <Select
                            name="decision"
                            label="Review decision"
                            value={form.data.decision}
                            onChange={(e) => form.setData('decision', e.target.value)}
                        >
                            <option value="approved">Approve draft</option>
                            <option value="rejected">Reject draft</option>
                        </Select>
                        <div className="form-field">
                            <label htmlFor="reason">Review reason</label>
                            <textarea
                                id="reason"
                                rows={4}
                                minLength={5}
                                maxLength={1000}
                                required
                                value={form.data.reason}
                                onChange={(e) => form.setData('reason', e.target.value)}
                            />
                        </div>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Saving review…' : 'Save review'}
                        </Button>
                    </form>
                </Panel>
            )}
            <Panel
                className="anomaly-panel"
                title="Review history"
                subtitle="Previous administrator decisions cannot be overwritten."
            >
                {reviews.data.length ? (
                    reviews.data.map((r, i) => (
                        <div className="correction-entry" key={i}>
                            <strong>
                                {readable(r.decision)} · {r.actor}
                            </strong>
                            <p>{manilaTime(r.at)}</p>
                            <p>{r.reason}</p>
                        </div>
                    ))
                ) : (
                    <p>No human review has been recorded.</p>
                )}
                <Pagination records={reviews} />
            </Panel>
        </ManagementPage>
    );
}
