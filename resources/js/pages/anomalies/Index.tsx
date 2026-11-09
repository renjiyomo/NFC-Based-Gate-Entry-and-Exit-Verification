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
import type { Period, ReportFilters } from '../../lib/monitoring';
import { type Flag, readable } from './types';

export default function Index({
    flags,
    filters,
    source,
    openCount,
    rules,
    policy,
}: {
    flags: Paginated<Flag>;
    filters: ReportFilters & { status?: string; rule?: string; severity?: string };
    period: Period;
    source: string;
    openCount: number;
    rules: Record<string, string>;
    policy: Record<string, string | number | boolean | null>;
}) {
    const filter = useForm({
        from: filters.from,
        to: filters.to,
        status: filters.status ?? '',
        rule: filters.rule ?? '',
        severity: filters.severity ?? '',
    });
    const detection = useForm({ from: filters.from, to: filters.to });
    useEffect(() => {
        filter.setData({
            from: filters.from,
            to: filters.to,
            status: filters.status ?? '',
            rule: filters.rule ?? '',
            severity: filters.severity ?? '',
        });
    }, [filters]);
    return (
        <ManagementPage
            page="anomalies"
            title="Activity checks"
            description="Review unusual scan patterns and possible missing records. A flag is a prompt to check, not a finding of wrongdoing."
        >
            <div className="report-period">
                <div>
                    <strong>{openCount} open or in review</strong>
                    <span>Across all dates · {source} records</span>
                </div>
                <Badge>Administrator review</Badge>
            </div>
            <section className="activity-section">
                <div className="section-caption">
                    <h2>Find activity to review</h2>
                </div>
                <form
                    className="report-filters"
                    onSubmit={(e) => {
                        e.preventDefault();
                        filter.transform((d) => ({ ...d, period: 'custom', page: 1 }));
                        filter.get('/admin/anomalies');
                    }}
                >
                    <FormErrors errors={filter.errors} />
                    <div className="report-filter-grid">
                        <Field
                            name="from"
                            label="Start date"
                            type="date"
                            value={filter.data.from}
                            onChange={(e) => filter.setData('from', e.target.value)}
                        />
                        <Field
                            name="to"
                            label="End date"
                            type="date"
                            value={filter.data.to}
                            onChange={(e) => filter.setData('to', e.target.value)}
                        />
                        <Select
                            name="status"
                            label="Review status"
                            value={filter.data.status}
                            onChange={(e) => filter.setData('status', e.target.value)}
                        >
                            <option value="">All statuses</option>
                            {['open', 'in_review', 'resolved', 'false_alarm'].map((v) => (
                                <option key={v} value={v}>
                                    {readable(v)}
                                </option>
                            ))}
                        </Select>
                        <Select
                            name="rule"
                            label="Rule"
                            value={filter.data.rule}
                            onChange={(e) => filter.setData('rule', e.target.value)}
                        >
                            <option value="">All rules</option>
                            {Object.entries(rules).map(([k, v]) => (
                                <option key={k} value={k}>
                                    {v}
                                </option>
                            ))}
                        </Select>
                        <Select
                            name="severity"
                            label="Review priority"
                            value={filter.data.severity}
                            onChange={(e) => filter.setData('severity', e.target.value)}
                        >
                            <option value="">All priorities</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                        </Select>
                    </div>
                    <Button type="submit">Apply filters</Button>
                </form>
            </section>
            <Panel
                className="anomaly-panel"
                title="Activity needing review"
                subtitle={`${filters.from} — ${filters.to} · Asia/Manila. Open a record to review the pattern and supporting scans.`}
            >
                <div className="report-scroll management-table">
                    <table className="data-table">
                        <caption className="sr-only">Anomaly review queue</caption>
                        <thead>
                            <tr>
                                {[
                                    'Time · Manila',
                                    'Indicator',
                                    'Person / gate',
                                    'Priority / status',
                                    'Latest reviewer',
                                    'Details',
                                ].map((v) => (
                                    <th key={v} scope="col">
                                        {v}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {flags.data.length ? (
                                flags.data.map((a) => (
                                    <tr key={a.id}>
                                        <td>{manilaTime(a.at)}</td>
                                        <td>{a.title}</td>
                                        <td>
                                            {a.person ?? 'Station-level / unidentified'}
                                            <br />
                                            {a.gate}
                                        </td>
                                        <td>
                                            <Badge>{a.severity}</Badge>
                                            <br />
                                            {readable(a.status)}
                                        </td>
                                        <td>{a.reviewer ?? 'Not reviewed'}</td>
                                        <td>
                                            <Link
                                                className="text-link"
                                                href={`/admin/anomalies/${a.id}`}
                                            >
                                                Review record
                                            </Link>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={6}>
                                        No flags match this selection. Run checks for the relevant
                                        dates to evaluate saved records.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination records={flags} />
            </Panel>
            <details className="insight-details">
                <summary>Check another period</summary>
                <Panel
                    className="anomaly-panel"
                    title="Check saved records"
                    subtitle="Find patterns that may need review. Entry and Exit records stay unchanged."
                >
                    <details className="compact-help">
                        <summary>Rules used for these checks</summary>{' '}
                        <p>
                            Sequence checks detect repeated Entry and Exit without a preceding
                            recorded Entry. Missing scans can explain both.{' '}
                            {policy.threshold_rules
                                ? 'Development threshold rules are enabled.'
                                : 'Threshold rules are disabled pending policy approval.'}{' '}
                            After-hours checks are{' '}
                            {policy.open_hour === null
                                ? 'disabled; campus hours have not been supplied'
                                : 'enabled for configured Manila hours'}
                            .
                        </p>
                        <details className="chart-data">
                            <summary>View current rule configuration</summary>
                            <dl className="record-facts">
                                {Object.entries(policy).map(([k, v]) => (
                                    <div key={k}>
                                        <dt>{readable(k)}</dt>
                                        <dd>{String(v ?? 'not configured')}</dd>
                                    </div>
                                ))}
                            </dl>
                            <p>
                                Denied bursts describe a station pattern, not an identified person.
                                Short intervals between gates do not establish impossible travel.
                                Each qualifying threshold anchor can produce a flag; rerunning the
                                same rule version does not duplicate it.
                            </p>
                        </details>
                    </details>
                    <form
                        className="report-filters"
                        onSubmit={(e) => {
                            e.preventDefault();
                            detection.post('/admin/anomalies/detect');
                        }}
                    >
                        <FormErrors errors={detection.errors} />
                        <div className="report-filter-grid">
                            <Field
                                name="detect_from"
                                label="Start date"
                                type="date"
                                required
                                value={detection.data.from}
                                onChange={(e) => detection.setData('from', e.target.value)}
                            />
                            <Field
                                name="detect_to"
                                label="End date"
                                type="date"
                                required
                                value={detection.data.to}
                                onChange={(e) => detection.setData('to', e.target.value)}
                            />
                        </div>
                        <Button type="submit" disabled={detection.processing}>
                            {detection.processing ? 'Checking records…' : 'Run checks'}
                        </Button>
                        <p className="field-hint">
                            Up to 31 days and 1,000 attempts per run. Earlier passages still provide
                            sequence context. Narrow dates if the selection exceeds the limit.
                        </p>
                    </form>
                </Panel>
            </details>
        </ManagementPage>
    );
}
