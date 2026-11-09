import { Link, useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import {
    ManagementPage,
    Field,
    Select,
    FormErrors,
    Pagination,
} from '../../components/management/Common';
import { Badge, Button, Panel } from '../../components/ui';
import { manilaTime, type Paginated } from '../../lib/management';
import { type Facts, type SummaryRow, readable } from './types';

export default function Index({
    filters,
    period,
    source,
    facts,
    availability,
    requestKey,
    history,
}: {
    filters: { period: string; date: string };
    period: { from: string; to: string; as_of: string };
    source: string;
    facts: Facts;
    availability: {
        ready: boolean;
        provider: string;
        model: string | null;
        reason: string | null;
        daily_limit: number;
        billing: string;
    };
    requestKey: string;
    history: Paginated<SummaryRow>;
}) {
    const filter = useForm({ ...filters });
    const generation = useForm({ ...filters, request_key: requestKey });
    useEffect(() => {
        filter.setData({ ...filters });
        generation.setData({ ...filters, request_key: requestKey });
    }, [filters, requestKey]);
    return (
        <ManagementPage
            page="summaries"
            title="Activity summaries"
            description="Review activity summaries before using or sharing them."
        >
            <div className="report-period">
                <div>
                    <strong>
                        {period.from} through {period.to}
                    </strong>
                    <span>
                        Asia/Manila · {source} records · updated {manilaTime(period.as_of)}
                    </span>
                </div>
                <Badge>{availability.ready ? 'Free Tier available' : 'AI unavailable'}</Badge>
            </div>
            <section className="activity-section summary-period">
                <h2>Summary period</h2>
                <form
                    className="report-filters"
                    onSubmit={(e) => {
                        e.preventDefault();
                        filter.get('/admin/summaries');
                    }}
                >
                    <FormErrors errors={filter.errors} />
                    <div className="period-fields">
                        <Select
                            name="period"
                            label="Period"
                            value={filter.data.period}
                            onChange={(e) => filter.setData('period', e.target.value)}
                        >
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly · Monday–Sunday</option>
                        </Select>
                        <Field
                            name="date"
                            label="Date within period · Manila"
                            type="date"
                            required
                            value={filter.data.date}
                            onChange={(e) => filter.setData('date', e.target.value)}
                        />
                        <Button type="submit" variant="primary">
                            Apply period
                        </Button>
                    </div>
                </form>
            </section>
            <Panel
                className="anomaly-panel"
                title="Create an activity summary"
                subtitle="A draft highlights recorded numbers for administrator review."
            >
                <p className="summary-availability">
                    {availability.ready
                        ? 'Gemini Free Tier is available for synthetic lab summaries.'
                        : 'AI generation is unavailable in this workspace. ' +
                          readable(availability.reason ?? 'not configured') +
                          '. Recorded numbers remain available below.'}
                </p>
                <details className="compact-help">
                    <summary>Privacy, limits & review</summary>
                    <p>
                        Synthetic aggregate counts only. No names, university numbers or NFC
                        references are sent. Google’s Free Tier data-use terms apply. The project
                        must stay unlinked from billing; no paid fallback or automatic retry is
                        allowed.
                    </p>
                    <p>
                        Up to {availability.daily_limit} attempts per workspace per UTC day. Drafts
                        select existing facts and require administrator review before use.
                    </p>
                </details>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        generation.post('/admin/summaries');
                    }}
                >
                    <FormErrors errors={generation.errors} />
                    <Button
                        type="submit"
                        variant="primary"
                        disabled={!availability.ready || generation.processing}
                    >
                        {generation.processing ? 'Saving request…' : 'Create draft summary'}
                    </Button>
                </form>
            </Panel>
            <details className="insight-details">
                <summary>Numbers included in this period</summary>{' '}
                <Panel
                    className="anomaly-panel"
                    title="Recorded numbers"
                    subtitle="Counts from saved records; available without AI."
                >
                    <div className="report-scroll management-table">
                        <table className="data-table">
                            <thead>
                                <tr>
                                    <th scope="col">Measure</th>
                                    <th scope="col">Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                {Object.entries(facts.facts).map(([id, f]) => (
                                    <tr key={id}>
                                        <td>{readable(id)}</td>
                                        <td>{f.count.toLocaleString()}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <p>{facts.gate_coverage} Gate aliases are anonymous within this snapshot.</p>
                    <details className="chart-data">
                        <summary>Read measurement limits</summary>
                        {Object.values(facts.limitations).map((v) => (
                            <p key={v}>{v}</p>
                        ))}
                    </details>
                </Panel>
            </details>
            <Panel
                className="anomaly-panel"
                title="Saved summaries"
                subtitle="Open a summary to read it and record your review. Check existing requests before requesting again after a connection error."
            >
                <div className="report-scroll management-table">
                    <table className="data-table">
                        <thead>
                            <tr>
                                {[
                                    'Period · Manila',
                                    'Snapshot',
                                    'Generation',
                                    'Human review',
                                    'Requested by',
                                    'Details',
                                ].map((v) => (
                                    <th key={v} scope="col">
                                        {v}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {history.data.length ? (
                                history.data.map((r) => (
                                    <tr key={r.id}>
                                        <td>
                                            {r.from} — {r.to}
                                            <br />
                                            {r.period}
                                        </td>
                                        <td>{manilaTime(r.at)}</td>
                                        <td>
                                            <Badge>{readable(r.status)}</Badge>
                                        </td>
                                        <td>{readable(r.review)}</td>
                                        <td>{r.actor}</td>
                                        <td>
                                            <Link
                                                className="text-link"
                                                href={`/admin/summaries/${r.id}`}
                                            >
                                                Open summary
                                            </Link>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={6}>No summary requests have been saved.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination records={history} />
            </Panel>
        </ManagementPage>
    );
}
