import { Link } from '@inertiajs/react';
import {
    Activity,
    ArrowDownLeft,
    ArrowRight,
    ArrowUpRight,
    BarChart3,
    ChevronRight,
    DoorOpen,
    GraduationCap,
    KeyRound,
    ScanLine,
    ShieldAlert,
    Users,
} from 'lucide-react';
import { useWorkspace } from '../lib/workspace';
import { ActionLink, Badge, EmptyState, EmptyTable, PageHeading, Panel } from './ui';

const metrics = [
    {
        label: 'Entries today',
        icon: ArrowDownLeft,
        note: 'Confirmed campus entries',
        color: 'blue',
    },
    { label: 'Exits today', icon: ArrowUpRight, note: 'Confirmed campus exits', color: 'teal' },
    {
        label: 'Denied verifications',
        icon: ShieldAlert,
        note: 'Unsuccessful credential checks',
        color: 'orange',
    },
    {
        label: 'Estimated on campus',
        icon: Users,
        note: 'Based on recorded passages',
        color: 'lavender',
    },
];

export default function Dashboard() {
    const { pageUrl, preview } = useWorkspace();
    return (
        <>
            <PageHeading
                eyebrow="Campus overview"
                title="Gate monitoring overview"
                description="A clear view of campus access and recorded gate activity."
                action={
                    <ActionLink href={pageUrl(preview ? 'scanner' : 'gates')}>
                        <ScanLine size={17} aria-hidden="true" />{' '}
                        {preview ? 'Open gate scanner' : 'View gate stations'}
                        <ArrowRight size={16} aria-hidden="true" />
                    </ActionLink>
                }
            />
            <div className="workspace-notice">
                <span className="workspace-notice-icon">
                    <Activity size={20} aria-hidden="true" />
                </span>
                <div>
                    <strong>
                        {preview ? 'Gate operations preview' : 'Guard gate workflow connected'}
                    </strong>
                    <p>
                        {preview
                            ? 'This interface preview is ready to explore.'
                            : 'Gate accounts record valid NFC scans automatically.'}
                    </p>
                </div>
                <Badge>{preview ? 'Preview mode' : 'Modules pending'}</Badge>
            </div>
            <section className="metric-grid" aria-label="Gate activity statistics">
                {metrics.map(({ label, icon: Icon, note, color }) => (
                    <div className="metric-card" key={label}>
                        <div className="metric-label">
                            <span>{label}</span>
                            <span className={`metric-icon icon-${color}`}>
                                <Icon size={18} strokeWidth={1.7} aria-hidden="true" />
                            </span>
                        </div>
                        <div className="metric-value" aria-label="Not connected">
                            —
                        </div>
                        <p>{note}</p>
                        <span className="metric-unavailable">Not connected</span>
                    </div>
                ))}
            </section>
            <div className="dashboard-middle">
                <Panel
                    title="Gate activity"
                    subtitle="Recorded entries and exits across campus gates"
                    action={<Badge>Today</Badge>}
                >
                    <div className="chart-empty">
                        <div className="chart-key">
                            <span>
                                <i className="legend-entry" />
                                Entries
                            </span>
                            <span>
                                <i className="legend-exit" />
                                Exits
                            </span>
                        </div>
                        <EmptyState
                            icon={BarChart3}
                            title="Activity trends will appear here"
                            description="Connect verified passage records to see how gate activity changes throughout the day."
                        />
                    </div>
                    <div className="panel-bottom-note">
                        Only confirmed passages will contribute to these trends.
                    </div>
                </Panel>
                <Panel title="Station readiness" subtitle="What gate operations will need">
                    <div className="readiness-list">
                        <div>
                            <span className="readiness-icon">
                                <DoorOpen size={18} aria-hidden="true" />
                            </span>
                            <span>
                                <strong>Gate assignments</strong>
                                <small>Assigned stations and security personnel</small>
                            </span>
                            <span className="readiness-status">
                                {preview ? 'Pending' : 'Available'}
                            </span>
                        </div>
                        <div>
                            <span className="readiness-icon">
                                <Users size={18} aria-hidden="true" />
                            </span>
                            <span>
                                <strong>Identity directory</strong>
                                <small>Students, employees, and photographs</small>
                            </span>
                            <span className="readiness-status">
                                {preview ? 'Pending' : 'Available'}
                            </span>
                        </div>
                        <div>
                            <span className="readiness-icon">
                                <KeyRound size={18} aria-hidden="true" />
                            </span>
                            <span>
                                <strong>NFC credentials</strong>
                                <small>Issuance, read-back and lifecycle</small>
                            </span>
                            <span className="readiness-status">
                                {preview ? 'Pending' : 'Available'}
                            </span>
                        </div>
                    </div>
                    <div className="readiness-foot">
                        <ShieldAlert size={16} aria-hidden="true" />
                        <p>
                            {preview
                                ? 'Gate operations are unavailable in preview.'
                                : 'Guard verification and separate passage confirmation are available.'}
                        </p>
                    </div>
                    <Link className="text-link readiness-link" href={pageUrl('gates')}>
                        View gate stations <ArrowRight size={15} aria-hidden="true" />
                    </Link>
                </Panel>
            </div>
            <Panel
                title="Recent gate activity"
                subtitle="Credential checks and confirmed passages"
                action={
                    <Link href={pageUrl('history')} className="text-link">
                        View history <ArrowRight size={15} aria-hidden="true" />
                    </Link>
                }
            >
                <EmptyTable
                    columns={['Person', 'Gate station', 'Direction', 'Result', 'Time']}
                    icon={Activity}
                    caption="Recent gate activity, currently unavailable"
                    title="No activity to display yet"
                    description="Gate records will appear here when verification and passage recording are connected."
                />
            </Panel>
            <section className="quick-links" aria-label="Explore workspace">
                <Link href={pageUrl('students')}>
                    <span className="quick-link-icon">
                        <GraduationCap size={21} aria-hidden="true" />
                    </span>
                    <div>
                        <strong>People directory</strong>
                        <p>Student and employee access records</p>
                    </div>
                    <ChevronRight size={18} aria-hidden="true" />
                </Link>
                <Link href={pageUrl('credentials')}>
                    <span className="quick-link-icon">
                        <KeyRound size={21} aria-hidden="true" />
                    </span>
                    <div>
                        <strong>Credential management</strong>
                        <p>Issue, review, and revoke NFC references</p>
                    </div>
                    <ChevronRight size={18} aria-hidden="true" />
                </Link>
            </section>
        </>
    );
}
