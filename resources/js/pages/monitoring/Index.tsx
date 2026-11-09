import { Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Download, RefreshCw } from 'lucide-react';
import {
    ManagementPage,
    Pagination,
    CurrentErrors,
    DataTable,
} from '../../components/management/Common';
import { Button, Panel } from '../../components/ui';
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
import { reasonLabel } from '../../lib/operations';

interface Props {
    section: 'dashboard' | 'history' | 'analytics' | 'reports';
    filters: ReportFilters;
    period: Period;
    source: string;
    gates: { id: number; code: string; name: string }[];
    guards: { id: number; name: string }[];
    summary?: Summary;
    directory?: {
        students: number;
        employees: number;
        active_credentials: number;
        active_gates: number;
    };
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
const headings = {
    dashboard: ['Overview', 'Campus records and recent gate activity.'],
    history: ['Entry & exit history', 'Find recorded passages or review individual scan results.'],
    analytics: [
        'Activity analytics',
        'Compare Entry and Exit activity across dates, gates and people.',
    ],
    reports: ['Reports', 'Choose a date range and download the records you need.'],
};
const datasets = [
    {
        value: 'summary',
        label: 'Activity summary',
        note: 'Totals and breakdowns by date, gate and person type.',
    },
    {
        value: 'passages',
        label: 'Entry & exit records',
        note: 'Individual recorded passages and their correction status.',
    },
    {
        value: 'attempts',
        label: 'All scan attempts',
        note: 'Accepted, rejected and duplicate scans, including scans without a passage.',
    },
];
export default function Index(props: Props) {
    const { section, filters, period, summary: s, directory, records, recent } = props;
    const [dataset, setDataset] = useState('summary');
    const [live, setLive] = useState(true);
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
        if (section !== 'dashboard' || !live) return;
        const timer = setInterval(() => {
            if (document.visibilityState === 'visible') refresh();
        }, 30000);
        return () => clearInterval(timer);
    }, [section, live]);
    return (
        <ManagementPage
            page={section}
            title={headings[section][0]}
            description={headings[section][1]}
            action={
                <Button onClick={refresh} disabled={refreshing}>
                    <RefreshCw size={16} aria-hidden="true" />
                    {refreshing ? 'Updating…' : 'Refresh'}
                </Button>
            }
        >
            <CurrentErrors />
            {section === 'history' && (
                <nav className="history-tabs" aria-label="History record type">
                    {[
                        ['passages', 'Recorded Entry & Exit'],
                        ['attempts', 'All scan attempts'],
                    ].map(([view, label]) => (
                        <Link
                            key={view}
                            href={reportUrl('/admin/history', filters, { view, page: '1' })}
                            aria-current={filters.view === view ? 'page' : undefined}
                        >
                            {label}
                        </Link>
                    ))}
                </nav>
            )}
            {section === 'dashboard' && directory && (
                <section className="registry-section" aria-labelledby="registry-title">
                    <div className="section-caption">
                        <h2 id="registry-title">Campus records</h2>
                        <span>Current totals · not affected by date filters</span>
                    </div>
                    <div className="registry-grid">
                        {[
                            ['Students', directory.students, '/admin/students'],
                            ['Employees', directory.employees, '/admin/employees'],
                            [
                                'Active NFC coins',
                                directory.active_credentials,
                                '/admin/credentials',
                            ],
                            ['Active gates', directory.active_gates, '/admin/gates'],
                        ].map(([label, value, href]) => (
                            <Link key={label} href={String(href)} className="registry-card">
                                <span>{label}</span>
                                <strong>{number(Number(value))}</strong>
                                <span className="registry-link">View records →</span>
                            </Link>
                        ))}
                    </div>
                </section>
            )}
            <section className="activity-section" aria-label="Activity period and filters">
                <div className="section-caption">
                    <h2>{section === 'reports' ? 'Report period' : 'Activity period'}</h2>
                    {section === 'dashboard' && (
                        <label className="live-control">
                            <input
                                type="checkbox"
                                checked={live}
                                onChange={(e) => setLive(e.target.checked)}
                            />
                            Live updates <span>{live ? 'Every 30 sec' : 'Paused'}</span>
                        </label>
                    )}
                </div>
                <Filters
                    section={section}
                    filters={filters}
                    gates={props.gates}
                    guards={props.guards}
                />
                <div className="period-caption">
                    <span>
                        {period.from === period.to ? period.from : `${period.from} — ${period.to}`}{' '}
                        · Asia/Manila
                    </span>
                    <span>Updated {manilaTime(period.as_of)}</span>
                </div>
            </section>
            {s && section !== 'reports' && (
                <>
                    <section className="metric-grid" aria-label="Activity totals">
                        {[
                            ['Entries', s.entries, 'Recorded in this period'],
                            ['Exits', s.exits, 'Recorded in this period'],
                            ['Rejected scans', s.denied, 'No passage recorded'],
                            section === 'dashboard'
                                ? [
                                      'Estimated inside',
                                      s.presence.inside,
                                      'Based on the latest recorded direction',
                                  ]
                                : [
                                      'People with passages',
                                      s.unique_people,
                                      'Each person counted once',
                                  ],
                        ].map(([label, value, note]) => (
                            <div className="metric-card" key={String(label)}>
                                <div className="metric-label">{label}</div>
                                <strong className="metric-value">{number(Number(value))}</strong>
                                <p className="metric-note">{note}</p>
                            </div>
                        ))}
                    </section>
                    <details className="compact-help">
                        <summary>What do these numbers mean?</summary>
                        <p>
                            Entries and Exits count saved passages in the selected period. Rejected
                            and duplicate scans do not add a passage. {number(s.attempts)} scans
                            were checked; {number(s.duplicates)} duplicates were suppressed. Voided
                            passages are excluded.
                        </p>
                        {section === 'dashboard' && (
                            <p>
                                Estimated inside uses each person’s last effective passage across
                                all gates up to {manilaTime(s.presence.as_of)}, regardless of the
                                gate/account filter or start date. Missing scans can make this
                                estimate incomplete. It is not a live headcount or classroom
                                attendance.
                            </p>
                        )}
                        {section === 'dashboard' && (
                            <p>
                                Live updates check for new records without reloading the whole page.
                                They never create a scan or passage.
                            </p>
                        )}
                        {directory && (
                            <p>
                                Student and employee totals include current active and inactive
                                records; archived people are excluded. Active NFC coins are
                                selected, unexpired credentials in this workspace. These registry
                                totals do not change with the activity period.
                            </p>
                        )}
                    </details>
                    <div className="report-two-columns">
                        <Panel
                            title="Entries & exits over time"
                            subtitle="Recorded passages by date"
                        >
                            <Trend rows={s.trend} />
                        </Panel>
                        <Panel
                            title="Activity by gate"
                            subtitle={
                                s.gates.length > 10
                                    ? 'Ten busiest gates in this period'
                                    : 'Recorded passages in this period'
                            }
                        >
                            <Bars
                                label="Gate activity"
                                rows={[...s.gates]
                                    .sort((a, b) => b.entries + b.exits - a.entries - a.exits)
                                    .slice(0, 10)
                                    .map((g) => ({
                                        label: `${g.label} · ${g.entries} Entry / ${g.exits} Exit`,
                                        value: g.entries + g.exits,
                                    }))}
                            />
                            {s.gates.length > 10 && (
                                <details className="chart-data">
                                    <summary>All gates</summary>
                                    {s.gates.map((g) => (
                                        <p key={g.label}>
                                            {g.label}: {g.entries} Entry / {g.exits} Exit
                                        </p>
                                    ))}
                                </details>
                            )}
                        </Panel>
                    </div>
                    {section === 'analytics' && (
                        <div className="report-two-columns">
                            <Panel
                                title="Student & employee activity"
                                subtitle="Passages, including repeat visits"
                            >
                                <Bars
                                    label="Activity by person type"
                                    rows={s.kinds.map((k) => ({
                                        label: `${k.label} · ${k.entries} Entry / ${k.exits} Exit`,
                                        value: k.entries + k.exits,
                                    }))}
                                />
                            </Panel>
                            <Panel
                                title="Reasons scans were rejected"
                                subtitle="Rejected scans do not create Entry or Exit records"
                            >
                                <Bars
                                    rows={s.denied_reasons.map((r) => ({
                                        ...r,
                                        label: reasonLabel(r.label),
                                    }))}
                                    label="Rejection reasons"
                                />
                            </Panel>
                            <Panel
                                title="Busiest hours"
                                subtitle="Up to eight busiest hours · Asia/Manila"
                            >
                                <Bars
                                    rows={s.hourly
                                        .filter((r) => r.value > 0)
                                        .sort((a, b) => b.value - a.value)
                                        .slice(0, 8)}
                                    label="Passages by hour"
                                />
                                <details className="chart-data">
                                    <summary>All hours</summary>
                                    <dl className="hourly-data">
                                        {s.hourly.map((r) => (
                                            <div key={r.label}>
                                                <dt>{r.label}</dt>
                                                <dd>{number(r.value)}</dd>
                                            </div>
                                        ))}
                                    </dl>
                                </details>
                            </Panel>
                            <Panel title="Reading the charts">
                                <div className="panel-copy">
                                    <p>
                                        Use the same date range and filters to compare activity. A
                                        person may have several passages during one day.
                                    </p>
                                    <p>
                                        Charts include effective records only. Missing scans and
                                        incomplete gate coverage affect the results.
                                    </p>
                                    <Link
                                        className="text-link"
                                        href={reportUrl('/admin/reports', filters)}
                                    >
                                        Download these results →
                                    </Link>
                                </div>
                            </Panel>
                        </div>
                    )}
                </>
            )}
            {section === 'history' && records && (
                <Panel
                    title={filters.view === 'passages' ? 'Recorded passages' : 'Scan attempts'}
                    action={
                        <Link className="text-link" href={reportUrl('/admin/reports', filters)}>
                            Download report →
                        </Link>
                    }
                >
                    <HistoryTable rows={records.data} />
                    <Pagination records={records} />
                </Panel>
            )}
            {section === 'dashboard' && recent && (
                <Panel
                    title="Recent scans"
                    subtitle="Latest eight scans in the selected period"
                    action={
                        <Link
                            className="text-link"
                            href={reportUrl('/admin/history', filters, { view: 'attempts' })}
                        >
                            View history →
                        </Link>
                    }
                >
                    <HistoryTable rows={recent} />
                </Panel>
            )}
            {section === 'reports' && (
                <>
                    <Panel title="Choose a report">
                        <div className="report-builder">
                            <fieldset className="report-datasets">
                                <legend className="sr-only">Report contents</legend>
                                {datasets.map((d) => (
                                    <label
                                        className={`dataset-choice ${dataset === d.value ? 'is-selected' : ''}`}
                                        key={d.value}
                                    >
                                        <input
                                            type="radio"
                                            name="dataset"
                                            value={d.value}
                                            checked={dataset === d.value}
                                            onChange={() => setDataset(d.value)}
                                        />
                                        <span>
                                            <strong>{d.label}</strong>
                                            <span>{d.note}</span>
                                        </span>
                                    </label>
                                ))}
                            </fieldset>
                            <div className="report-download-bar">
                                <div>
                                    <h3>Download</h3>
                                    <p>Uses the applied period and filters above.</p>
                                </div>
                                <div className="form-actions">
                                    {['csv', 'xlsx', 'pdf'].map((format) => (
                                        <a
                                            key={format}
                                            className={`button button-${format === 'xlsx' ? 'primary' : 'secondary'}`}
                                            href={reportUrl('/admin/reports/export', filters, {
                                                dataset,
                                                format,
                                            })}
                                        >
                                            <Download size={16} aria-hidden="true" />
                                            {format === 'xlsx' ? 'Excel' : format.toUpperCase()}
                                        </a>
                                    ))}
                                </div>
                            </div>
                            <p className="field-hint">
                                Up to 10,000 rows in CSV or Excel; 500 rows in PDF. Narrow the
                                period for larger reports.
                            </p>
                        </div>
                    </Panel>
                    <Panel title="Recent downloads">
                        <DataTable
                            caption="Report download history"
                            columns={['Downloaded', 'Requested by', 'Report', 'Format', 'Rows']}
                            count={props.exports?.length ?? 0}
                            empty="No reports downloaded yet"
                            emptyDescription="Your recent downloads will appear here."
                        >
                            {props.exports?.map((row, i) => (
                                <tr key={`${row.at}-${i}`}>
                                    <td>{manilaTime(row.at)}</td>
                                    <td>{row.actor}</td>
                                    <td>
                                        {datasets.find((d) => d.value === row.details.dataset)
                                            ?.label ?? row.details.dataset}
                                    </td>
                                    <td>
                                        {row.details.format === 'xlsx'
                                            ? 'Excel'
                                            : row.details.format.toUpperCase()}
                                    </td>
                                    <td>{number(row.details.row_count)}</td>
                                </tr>
                            ))}
                        </DataTable>
                    </Panel>
                </>
            )}
        </ManagementPage>
    );
}
