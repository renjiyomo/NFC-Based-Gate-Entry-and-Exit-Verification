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
            description="AI selects observations from validated aggregate facts. NFCheck renders the factual wording and retains human review."
        >
            <div className="report-period">
                <div>
                    <strong>
                        {period.from} through {period.to}
                    </strong>
                    <span>
                        Asia/Manila · {source} source · snapshot {manilaTime(period.as_of)}
                    </span>
                </div>
                <Badge>
                    {availability.ready ? 'Free Tier configured' : 'Live AI disabled / unavailable'}
                </Badge>
            </div>
            <Panel
                className="anomaly-panel"
                title="Validated facts"
                subtitle="These counts come from saved database records. They remain available without an AI service."
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
            <details className="report-filter-disclosure">
                <summary>Change the summary period</summary>
                <form
                    className="report-filters"
                    onSubmit={(e) => {
                        e.preventDefault();
                        filter.get('/admin/summaries');
                    }}
                >
                    <FormErrors errors={filter.errors} />
                    <div className="report-filter-grid">
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
                    </div>
                    <Button type="submit">Apply period</Button>
                </form>
            </details>
            <Panel
                className="anomaly-panel"
                title="Request AI-assisted observations"
                subtitle="Free Tier only. No paid fallback, automatic retries, real identities or raw NFC data."
            >
                <p>
                    {availability.ready
                        ? 'Configured for Gemini Free Tier; network availability is checked during generation.'
                        : 'Live generation is unavailable: ' +
                          readable(availability.reason ?? 'not configured') +
                          '. The validated facts above are available now.'}
                </p>
                <p>
                    Synthetic lab workspaces only. The Google project must remain unlinked from
                    billing. Free-tier prompts may be used by Google to improve its products. At
                    most 3 generation attempts per workspace per UTC day; quota errors stop
                    generation.
                </p>
                <p>
                    AI can select up to 12 existing facts; it cannot supply new figures, identities
                    or incident claims. Every draft needs administrator review. A separate worker
                    handles generation so gate requests can continue.
                </p>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        generation.post('/admin/summaries');
                    }}
                >
                    <FormErrors errors={generation.errors} />
                    <Button type="submit" disabled={!availability.ready || generation.processing}>
                        {generation.processing ? 'Saving request…' : 'Request AI summary'}
                    </Button>
                </form>
            </Panel>
            <Panel
                className="anomaly-panel"
                title="Generation history"
                subtitle="All saved requests in this workspace, newest first. Open an existing request before creating another after an uncertain response."
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
