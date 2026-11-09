import { Link } from '@inertiajs/react';
import { useWorkspace } from '../lib/workspace';
import {
    Activity,
    ArrowDownLeft,
    ArrowUpRight,
    BarChart3,
    CalendarDays,
    ChartNoAxesCombined,
    Clock3,
    Download,
    FileSpreadsheet,
    FileText,
    Info,
    LockKeyhole,
    Monitor,
    Settings2,
    ShieldAlert,
    ShieldCheck,
} from 'lucide-react';
import { useState } from 'react';
import {
    Badge,
    Button,
    ChoiceGroup,
    EmptyPagination,
    EmptyState,
    EmptyTable,
    Notice,
    PageHeading,
    Panel,
    SearchField,
    SelectField,
} from './ui';

export function HistoryPage() {
    const [view, setView] = useState<'passages' | 'attempts'>('passages');
    return (
        <>
            <PageHeading
                eyebrow="Monitoring"
                title="Entry & exit history"
                description="Review credential verification separately from confirmed university passage."
                action={
                    <Button disabled>
                        <Download size={17} aria-hidden="true" /> Export records
                    </Button>
                }
            />
            <Notice icon={Info}>
                History is unavailable until gate recording is connected. A credential check alone
                will never count as a confirmed passage.
            </Notice>
            <Panel>
                <div className="history-view">
                    <ChoiceGroup
                        label="History record type"
                        value={view}
                        onChange={setView}
                        choices={[
                            { value: 'passages', label: 'Confirmed passages' },
                            { value: 'attempts', label: 'Verification attempts' },
                        ]}
                    />
                    <Badge>Not connected</Badge>
                </div>
                <div className="table-toolbar">
                    <SearchField
                        label="Search person or university number"
                        placeholder="Search person or university number"
                        disabled
                    />
                    <div className="filter-controls">
                        <SelectField label="Filter by gate" disabled>
                            <option>All gates</option>
                        </SelectField>
                        <SelectField label="Filter by direction" disabled>
                            <option>All directions</option>
                        </SelectField>
                        <SelectField label="Filter by date" disabled>
                            <option>All dates</option>
                        </SelectField>
                    </div>
                </div>
                <EmptyTable
                    columns={
                        view === 'passages'
                            ? ['Person', 'Gate', 'Direction', 'Confirmed by', 'Time']
                            : [
                                  'Credential / person',
                                  'Gate',
                                  'Direction',
                                  'Verification result',
                                  'Review',
                              ]
                    }
                    caption={
                        view === 'passages'
                            ? 'Confirmed university passages'
                            : 'Credential verification attempts'
                    }
                    icon={Activity}
                    title={
                        view === 'passages'
                            ? 'No passage history is available'
                            : 'No verification history is available'
                    }
                    description={
                        view === 'passages'
                            ? 'Guard-confirmed entry and exit records will appear here when connected.'
                            : 'Authorized, denied, cancelled, and expired verification outcomes will appear here when connected.'
                    }
                />
                <EmptyPagination />
            </Panel>
            <p className="availability-note">
                Historical records will preserve original events. Administrative corrections will be
                traceable.
            </p>
        </>
    );
}

export function ReportsPage() {
    const [period, setPeriod] = useState<'daily' | 'weekly' | 'monthly'>('daily');
    return (
        <>
            <PageHeading
                eyebrow="Insights"
                title="Activity reports"
                description="Build a clear, traceable account of recorded university gate activity."
                action={
                    <Button disabled variant="primary">
                        <FileText size={17} aria-hidden="true" /> Generate report
                    </Button>
                }
            />
            <Notice title="Reports are not connected" icon={Info}>
                Generation and downloads will become available with verified activity records. No
                sample figures or reports are presented as real.
            </Notice>
            <Panel title="Report scope" subtitle="Choose the reporting interval">
                <div className="report-scope">
                    <ChoiceGroup
                        label="Reporting interval"
                        value={period}
                        onChange={setPeriod}
                        choices={[
                            { value: 'daily', label: 'Daily' },
                            { value: 'weekly', label: 'Weekly' },
                            { value: 'monthly', label: 'Monthly' },
                        ]}
                    />
                    <SelectField label="Reporting date" disabled>
                        <option>Select reporting date</option>
                    </SelectField>
                    <SelectField label="Gate scope" disabled>
                        <option>All gate stations</option>
                    </SelectField>
                </div>
                <div className="scope-footnote">
                    <CalendarDays size={16} aria-hidden="true" />
                    Reporting periods will follow Asia/Manila time.
                </div>
            </Panel>
            <div className="export-grid">
                {[
                    {
                        name: 'CSV file',
                        icon: FileText,
                        detail: 'A portable table of permitted activity records.',
                        extension: '.csv',
                    },
                    {
                        name: 'Excel workbook',
                        icon: FileSpreadsheet,
                        detail: 'Structured records for authorized analysis.',
                        extension: '.xlsx',
                    },
                    {
                        name: 'PDF report',
                        icon: FileText,
                        detail: 'A readable summary of the selected reporting period.',
                        extension: '.pdf',
                    },
                ].map(({ name, icon: Icon, detail, extension }) => (
                    <Panel key={name} className="export-card">
                        <span className="export-icon">
                            <Icon size={24} strokeWidth={1.5} aria-hidden="true" />
                        </span>
                        <span className="file-extension">{extension}</span>
                        <h2>{name}</h2>
                        <p>{detail}</p>
                        <Button disabled>
                            <Download size={16} aria-hidden="true" />
                            Download
                        </Button>
                    </Panel>
                ))}
            </div>
            <Panel
                title="Generated reports"
                subtitle="Permitted report history and download availability"
            >
                <EmptyState
                    icon={FileText}
                    title="No reports are available"
                    description="Generated reports will appear here once reporting is connected. Downloads will require authorized access."
                />
            </Panel>
        </>
    );
}

export function AnalyticsPage() {
    return (
        <>
            <PageHeading
                eyebrow="Insights"
                title="University access analytics"
                description="Understand activity patterns using confirmed records and explained measures."
                action={<Badge>Data not connected</Badge>}
            />
            <div className="analytics-filters">
                <span>
                    <CalendarDays size={17} aria-hidden="true" />
                    Analysis period
                </span>
                <SelectField label="Analysis period" disabled>
                    <option>Select date range</option>
                </SelectField>
                <SelectField label="Gate filter" disabled>
                    <option>All gate stations</option>
                </SelectField>
                <SelectField label="Person type filter" disabled>
                    <option>Students & employees</option>
                </SelectField>
            </div>
            <div className="analytics-grid">
                <Panel
                    title="Entry & exit trends"
                    subtitle="Effective confirmed passages over time"
                >
                    <EmptyState
                        icon={ChartNoAxesCombined}
                        title="Trends need recorded activity"
                        description="Connected passage records will support accurate daily, weekly, and monthly comparisons."
                    />
                </Panel>
                <Panel
                    title="Activity by gate"
                    subtitle="Compare recorded activity across stations"
                >
                    <EmptyState
                        icon={BarChart3}
                        title="Gate comparisons are not available"
                        description="No station data is connected. Charts will appear only when there are verified records to represent."
                    />
                </Panel>
            </div>
            <Panel
                title="Know what the measures mean"
                subtitle="Context matters when reviewing university access"
            >
                <div className="measure-grid">
                    <div>
                        <ArrowDownLeft size={21} aria-hidden="true" />
                        <h3>Entries & exits</h3>
                        <p>Count recorded NFC passages, with traceable corrections applied.</p>
                    </div>
                    <div>
                        <ShieldAlert size={21} aria-hidden="true" />
                        <h3>Denied checks</h3>
                        <p>Count unsuccessful verification attempts separately from passages.</p>
                    </div>
                    <div>
                        <ArrowUpRight size={21} aria-hidden="true" />
                        <h3>Estimated presence</h3>
                        <p>
                            Uses recorded passage sequences. Missing scans can affect the estimate.
                        </p>
                    </div>
                </div>
            </Panel>
            <div className="supporting-note">
                <Info size={18} aria-hidden="true" />
                <div>
                    <strong>Gate activity has a specific purpose</strong>
                    <p>
                        These records support university access monitoring. They do not establish
                        classroom attendance or an exact physical headcount.
                    </p>
                </div>
            </div>
        </>
    );
}

export function SettingsPage() {
    const { preview } = useWorkspace();
    return (
        <>
            <PageHeading
                eyebrow="System"
                title="Workspace settings"
                description="Review the workspace presentation and future administrative controls."
            />
            <Notice title="Workspace settings" icon={Info}>
                {preview
                    ? 'This preview does not save settings.'
                    : 'Account security is connected. Operational settings are awaiting later phases.'}
            </Notice>
            <div className="settings-grid">
                <Panel title="Presentation" subtitle="Current interface display">
                    <dl className="settings-list">
                        <div>
                            <dt>
                                <Monitor size={18} aria-hidden="true" />
                                <span>
                                    Appearance
                                    <small>The interface currently uses the dark theme</small>
                                </span>
                            </dt>
                            <dd>
                                <Badge>Dark</Badge>
                            </dd>
                        </div>
                        <div>
                            <dt>
                                <Clock3 size={18} aria-hidden="true" />
                                <span>
                                    Display timezone
                                    <small>Used for the university workspace presentation</small>
                                </span>
                            </dt>
                            <dd>Asia/Manila</dd>
                        </div>
                        <div>
                            <dt>
                                <Settings2 size={18} aria-hidden="true" />
                                <span>
                                    Workspace state
                                    <small>Navigation and interface controls are available</small>
                                </span>
                            </dt>
                            <dd>
                                <Badge tone="blue">
                                    {preview ? 'Preview' : 'Account connected'}
                                </Badge>
                            </dd>
                        </div>
                    </dl>
                </Panel>
                <Panel title="Access & accountability" subtitle="Account security">
                    <div className="settings-security">
                        <span className="empty-icon">
                            <LockKeyhole size={25} aria-hidden="true" />
                        </span>
                        <h3>{preview ? 'Account access preview' : 'Your account is protected'}</h3>
                        <p>
                            {preview
                                ? 'Sign-in is disabled in this preview.'
                                : 'Role permissions, encrypted sessions, password recovery, and account audit records are active. Sessions expire after 30 minutes of inactivity.'}
                        </p>
                        {!preview && (
                            <Link href="/account/password" className="button button-secondary">
                                <ShieldCheck size={17} aria-hidden="true" />
                                Change password
                            </Link>
                        )}
                    </div>
                </Panel>
            </div>
            <Panel
                title="Operational policies"
                subtitle="Institutional decisions guide gate operations"
            >
                <div className="policy-preview">
                    <div>
                        <h3>Gate and credential policies</h3>
                        <p>
                            Assignment schedules, credential validity, and duplicate handling need
                            an approved operational policy.
                        </p>
                    </div>
                    <div>
                        <h3>Privacy and retention</h3>
                        <p>
                            Identity fields, movement history, permitted exports, and retention
                            periods require institutional authorization.
                        </p>
                    </div>
                </div>
            </Panel>
        </>
    );
}
