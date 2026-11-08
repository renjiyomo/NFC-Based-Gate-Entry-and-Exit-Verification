import { Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { ArrowDownLeft, ArrowUpRight, ShieldAlert, Users, RefreshCw, Download } from 'lucide-react';
import {
    ManagementPage,
    Pagination,
    CurrentErrors,
    Select,
} from '../../components/management/Common';
import { Badge, Button, Panel } from '../../components/ui';
import Filters from '../../components/monitoring/Filters';
import HistoryTable from '../../components/monitoring/HistoryTable';
import { Bars, Trend } from '../../components/monitoring/Charts';
import {
    number,
    reportUrl,
    type HistoryRow,
    type Period,
    type ReportFilters,
    type Summary,
} from '../../lib/monitoring';
import { manilaTime, type Paginated } from '../../lib/management';

interface Props {
    section: 'dashboard' | 'history' | 'analytics' | 'reports';
    filters: ReportFilters;
    period: Period;
    source: string;
    gates: { id: number; code: string; name: string }[];
    guards: { id: number; name: string }[];
    summary?: Summary;
    directory?: { students: number; employees: number; active_credentials: number };
    recent?: HistoryRow[];
    records?: Paginated<HistoryRow>;
    exports?: {
        at: string;
        actor: string;
        details: {
            format: string;
            dataset: string;
            period_start: string;
            period_end: string;
            row_count: number;
        };
    }[];
}
const titles = {
    dashboard: 'Gate monitoring overview',
    history: 'Entry & exit history',
    analytics: 'Campus access analytics',
    reports: 'Activity reports',
};
export default function Index(props: Props) {
    const { section, filters, period, source, summary: s, directory, records, recent } = props;
    const [dataset, setDataset] = useState(section === 'history' ? filters.view : 'summary');
    const [refreshing, setRefreshing] = useState(false);
    const active = useRef(false);
    function refresh() {
        if (active.current) return;
        active.current = true;
        setRefreshing(true);
        router.reload({
            only: ['summary', 'recent', 'records', 'directory', 'period', 'exports'],
            onFinish: () => {
                active.current = false;
                setRefreshing(false);
            },
        });
    }
    useEffect(() => {
        if (section !== 'dashboard') return;
        const timer = setInterval(() => {
            if (document.visibilityState === 'visible') refresh();
        }, 30000);
        return () => clearInterval(timer);
    }, [section]);
    return (
        <ManagementPage
            page={section}
            title={titles[section]}
            description="Confirmed passages, verification attempts and estimates from saved records."
            action={
                <Button onClick={refresh} disabled={refreshing}>
                    <RefreshCw size={16} />
                    {refreshing ? 'Refreshing…' : 'Refresh data'}
                </Button>
            }
        >
            <CurrentErrors />
            <div className="report-period">
                <div>
                    <strong>
                        {period.from} — {period.to}
                    </strong>
                    <span>
                        Asia/Manila · {source} source · snapshot {manilaTime(period.as_of)}
                    </span>
                </div>
                <Badge>
                    {section === 'dashboard' ? 'Refreshes every 30 seconds' : 'Database snapshot'}
                </Badge>
            </div>
            {section === 'dashboard' ? (
                <details className="report-filter-disclosure">
                    <summary>Change period and filters</summary>
                    <Filters
                        section={section}
                        filters={filters}
                        gates={props.gates}
                        guards={props.guards}
                    />
                </details>
            ) : (
                <Filters
                    section={section}
                    filters={filters}
                    gates={props.gates}
                    guards={props.guards}
                />
            )}
            {s && (
                <>
                    <section className="metric-grid" aria-label="Filtered gate statistics">
                        {[
                            {
                                label: 'Confirmed entries',
                                value: s.entries,
                                icon: ArrowDownLeft,
                                color: 'blue',
                                note: 'Effective Entry passages in this period',
                            },
                            {
                                label: 'Confirmed exits',
                                value: s.exits,
                                icon: ArrowUpRight,
                                color: 'teal',
                                note: 'Effective Exit passages in this period',
                            },
                            {
                                label: 'Denied verifications',
                                value: s.denied,
                                icon: ShieldAlert,
                                color: 'orange',
                                note: 'Rejected attempts, not passages',
                            },
                            {
                                label: 'Last recorded inside',
                                value: s.presence.inside,
                                icon: Users,
                                color: 'lavender',
                                note: 'Campus-wide estimate as of period cutoff',
                            },
                        ].map(({ label, value, icon: Icon, color, note }) => (
                            <div className="metric-card" key={label}>
                                <div className="metric-label">
                                    <span>{label}</span>
                                    <span className={`metric-icon icon-${color}`}>
                                        <Icon size={18} />
                                    </span>
                                </div>
                                <div className="metric-value">{number(value)}</div>
                                <p className="metric-note">{note}</p>
                            </div>
                        ))}
                    </section>
                    <div className="report-facts">
                        {directory && (
                            <>
                                <span>
                                    <strong>{number(directory.students)}</strong> registered
                                    students
                                </span>
                                <span>
                                    <strong>{number(directory.employees)}</strong> registered
                                    employees
                                </span>
                                <span>
                                    <strong>{number(directory.active_credentials)}</strong> selected
                                    active credentials
                                </span>
                            </>
                        )}
                        <span>
                            <strong>{number(s.attempts)}</strong> attempts
                        </span>
                        <span>
                            <strong>{number(s.duplicates)}</strong> duplicates suppressed
                        </span>
                        <span>
                            <strong>{number(s.voided)}</strong> voided originals excluded
                        </span>
                    </div>
                    <p className="report-meaning">
                        Presence follows the last effective passage across all gates through{' '}
                        {manilaTime(s.presence.as_of)}; it ignores the gate/guard and period-start
                        filters. {number(s.presence.outside)} last recorded outside;{' '}
                        {number(s.presence.unknown)} retained people have no effective passage.
                        Missing scans and guard observations limit this estimate. Gate activity is
                        not classroom attendance.
                    </p>
                </>
            )}
            {section === 'history' && records && (
                <Panel
                    title={
                        filters.view === 'passages'
                            ? 'Passage history'
                            : 'Verification attempt history'
                    }
                    subtitle="Original records remain intact. Voids and reinstatements are traceable administrative corrections."
                    action={
                        <div className="report-export-actions">
                            {['csv', 'xlsx', 'pdf'].map((format) => (
                                <a
                                    className="text-link"
                                    key={format}
                                    href={reportUrl('/admin/reports/export', filters, {
                                        format,
                                        dataset: filters.view,
                                    })}
                                >
                                    Export {format === 'xlsx' ? 'Excel' : format.toUpperCase()}
                                </a>
                            ))}
                        </div>
                    }
                >
                    <HistoryTable rows={records.data} />
                    <Pagination records={records} />
                </Panel>
            )}
            {s && (
                <>
                    <div className="report-two-columns">
                        <Panel
                            title="Entry & exit trends"
                            subtitle="Effective confirmed passages by Manila date"
                        >
                            <Trend rows={s.trend} />
                        </Panel>
                        <Panel
                            title="Activity by gate"
                            subtitle={
                                s.gates.length > 10
                                    ? 'Top 10 gates by effective passages'
                                    : 'Effective confirmed passages across stations'
                            }
                        >
                            <Bars
                                label="Gate comparison"
                                rows={[...s.gates]
                                    .sort((a, b) => b.entries + b.exits - (a.entries + a.exits))
                                    .slice(0, 10)
                                    .map((g) => ({
                                        label: `${g.label} · ${g.entries} Entry / ${g.exits} Exit`,
                                        value: g.entries + g.exits,
                                    }))}
                            />
                            {s.gates.length > 10 && (
                                <details className="chart-data">
                                    <summary>View every gate</summary>
                                    <ul>
                                        {s.gates.map((g) => (
                                            <li key={g.label}>
                                                {g.label}: {g.entries} Entries / {g.exits} Exits
                                            </li>
                                        ))}
                                    </ul>
                                </details>
                            )}
                        </Panel>
                    </div>
                    {section !== 'dashboard' && (
                        <div className="report-two-columns">
                            <Panel
                                title="Student and employee activity"
                                subtitle="Confirmed passages, not unique people"
                            >
                                <Bars
                                    label="Person type comparison"
                                    rows={s.kinds.map((k) => ({
                                        label: `${k.label} · ${k.entries} Entry / ${k.exits} Exit`,
                                        value: k.entries + k.exits,
                                    }))}
                                />
                                <p className="report-meaning">
                                    {number(s.unique_people)} distinct people with effective passage
                                    in this period.
                                </p>
                            </Panel>
                            <Panel
                                title="Denied verification reasons"
                                subtitle="Unidentified references remain separate from person records"
                            >
                                <Bars rows={s.denied_reasons} label="Denied reasons" />
                            </Panel>
                            <Panel
                                title="Activity by time of day"
                                subtitle="Confirmed passages grouped by Manila hour"
                            >
                                <Bars rows={s.hourly} label="Hourly distribution" />
                            </Panel>
                            <Panel title="How totals are calculated">
                                <p className="report-meaning">
                                    Counts include effective confirmed passage records only.
                                    Cancelled, expired and rejected reviews do not count.
                                    Administrative voids exclude an original passage; reinstatement
                                    includes it again. Duplicate scans remain in attempt history
                                    without creating another passage.
                                </p>
                                <p className="report-meaning">
                                    Directory counts are current profile counts. Historical names
                                    are current directory descriptions. Report corrections reflect
                                    the state when this snapshot was generated.
                                </p>
                            </Panel>
                        </div>
                    )}
                </>
            )}
            {section === 'dashboard' && recent && (
                <Panel
                    title="Recent verification activity"
                    subtitle="Latest eight attempts matching this period and filters"
                    action={
                        <Link
                            className="text-link"
                            href={reportUrl('/admin/history', filters, { view: 'attempts' })}
                        >
                            View complete history
                        </Link>
                    }
                >
                    <HistoryTable rows={recent} />
                </Panel>
            )}
            {section === 'reports' && (
                <>
                    <Panel
                        title="Download a report"
                        subtitle="The applied filters above control every format. Downloads are private and audited."
                    >
                        <div className="report-downloads">
                            <Select
                                name="dataset"
                                label="Export records"
                                value={dataset}
                                onChange={(e) => setDataset(e.target.value)}
                            >
                                <option value="summary">Activity summary and breakdowns</option>
                                <option value="passages">Confirmed passage records</option>
                                <option value="attempts">Verification attempt records</option>
                            </Select>
                            <div className="form-actions">
                                {['csv', 'xlsx', 'pdf'].map((format) => (
                                    <a
                                        key={format}
                                        className="button button-primary"
                                        href={reportUrl('/admin/reports/export', filters, {
                                            dataset,
                                            format,
                                        })}
                                    >
                                        <Download size={16} />
                                        Download{' '}
                                        {format === 'xlsx' ? 'Excel' : format.toUpperCase()}
                                    </a>
                                ))}
                            </div>
                            <p className="field-hint">
                                Exports: up to 10,000 rows in CSV/Excel or 500 in PDF. Larger
                                selections require narrower filters; rows are never silently
                                omitted. Files are generated on demand.
                            </p>
                        </div>
                    </Panel>
                    <Panel
                        title="Recent prepared exports"
                        subtitle="Audit history records preparation, not proof that a file was opened."
                    >
                        <div className="report-scroll">
                            <table className="data-table">
                                <thead>
                                    <tr>
                                        <th>Prepared · Manila</th>
                                        <th>Administrator</th>
                                        <th>Dataset / format</th>
                                        <th>Period</th>
                                        <th>Rows</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {props.exports?.length ? (
                                        props.exports.map((r, i) => (
                                            <tr key={i}>
                                                <td>{manilaTime(r.at)}</td>
                                                <td>{r.actor}</td>
                                                <td>
                                                    {r.details.dataset} ·{' '}
                                                    {r.details.format.toUpperCase()}
                                                </td>
                                                <td>
                                                    {r.details.period_start} —{' '}
                                                    {r.details.period_end}
                                                </td>
                                                <td>{number(r.details.row_count)}</td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={5}>No exports prepared yet.</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </Panel>
                </>
            )}
        </ManagementPage>
    );
}
