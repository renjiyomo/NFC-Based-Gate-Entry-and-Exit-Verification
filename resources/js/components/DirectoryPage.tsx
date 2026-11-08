import {
    BriefcaseBusiness,
    DoorOpen,
    GraduationCap,
    Info,
    KeyRound,
    Plus,
    ShieldCheck,
    type LucideIcon,
} from 'lucide-react';
import {
    Badge,
    Button,
    EmptyPagination,
    EmptyTable,
    Notice,
    PageHeading,
    Panel,
    SearchField,
    SelectField,
} from './ui';

type DirectoryId = 'students' | 'employees' | 'credentials' | 'guards' | 'gates';
interface DirectoryDefinition {
    title: string;
    description: string;
    action: string;
    panel: string;
    subtitle: string;
    search: string;
    filter: string;
    columns: string[];
    emptyTitle: string;
    emptyDescription: string;
    icon: LucideIcon;
}

const definitions: Record<DirectoryId, DirectoryDefinition> = {
    students: {
        title: 'Student directory',
        description: 'Manage student identities, campus access, and assigned NFC credentials.',
        action: 'Add student',
        panel: 'Students',
        subtitle: 'Registered campus identities and access status',
        search: 'Search name or student number',
        filter: 'All programs',
        columns: ['Student', 'Student number', 'Program / year', 'Access status', 'NFC credential'],
        emptyTitle: 'Student records are not connected',
        emptyDescription:
            'The student directory will appear here when identity management is available.',
        icon: GraduationCap,
    },
    employees: {
        title: 'Employee directory',
        description: 'Manage employee identities, departments, and campus access.',
        action: 'Add employee',
        panel: 'Employees',
        subtitle: 'Employee identities and authorization details',
        search: 'Search name or employee number',
        filter: 'All departments',
        columns: ['Employee', 'Employee number', 'Department', 'Access status', 'NFC credential'],
        emptyTitle: 'Employee records are not connected',
        emptyDescription:
            'Employee records and access details will appear here when identity management is available.',
        icon: BriefcaseBusiness,
    },
    credentials: {
        title: 'NFC credentials',
        description: 'Keep campus credentials assigned, traceable, and ready for identity review.',
        action: 'Issue credential',
        panel: 'Credential registry',
        subtitle: 'Issuance, ownership, and credential lifecycle',
        search: 'Search credential label or person',
        filter: 'All person types',
        columns: ['Credential', 'Assigned person', 'Person type', 'Status', 'Issued'],
        emptyTitle: 'The credential registry is not connected',
        emptyDescription:
            'Registered credentials and their status will appear here. No credentials can be issued in this preview.',
        icon: KeyRound,
    },
    guards: {
        title: 'Security personnel',
        description: 'Organize security accounts and their assigned gate stations.',
        action: 'Add personnel',
        panel: 'Security personnel',
        subtitle: 'Operator accounts and gate assignments',
        search: 'Search name or account',
        filter: 'All gate stations',
        columns: ['Security personnel', 'Account', 'Gate assignments', 'Account status'],
        emptyTitle: 'Security personnel management is pending',
        emptyDescription:
            'Security personnel and their permitted stations will appear here after account management is available.',
        icon: ShieldCheck,
    },
    gates: {
        title: 'Gate stations',
        description: 'Manage campus entry points and their security personnel assignments.',
        action: 'Add gate station',
        panel: 'Campus gates',
        subtitle: 'Station configuration and assignment overview',
        search: 'Search gate name or code',
        filter: 'All assignment states',
        columns: ['Gate station', 'Gate code', 'Security assignments', 'Status'],
        emptyTitle: 'Gate stations are not connected',
        emptyDescription:
            'Configured campus gates and authorized assignments will appear here when gate management is available.',
        icon: DoorOpen,
    },
};

export default function DirectoryPage({ page }: { page: DirectoryId }) {
    const definition = definitions[page];
    return (
        <>
            <PageHeading
                eyebrow={
                    page === 'students' || page === 'employees' ? 'People' : 'Access management'
                }
                title={definition.title}
                description={definition.description}
                action={
                    <Button disabled variant="primary" aria-describedby="directory-availability">
                        <Plus size={17} aria-hidden="true" />
                        {definition.action}
                    </Button>
                }
            />
            <Notice title="Directory management pending" icon={Info}>
                Record management, search, and filters will become available in the later management
                phases.
            </Notice>
            <Panel
                title={definition.panel}
                subtitle={definition.subtitle}
                action={<Badge>Not connected</Badge>}
            >
                <div className="table-toolbar">
                    <SearchField
                        label={definition.search}
                        placeholder={definition.search}
                        disabled
                    />
                    <div className="filter-controls">
                        <SelectField label={definition.filter} disabled>
                            <option>{definition.filter}</option>
                        </SelectField>
                        <SelectField label="Filter by status" disabled>
                            <option>All statuses</option>
                        </SelectField>
                    </div>
                </div>
                <EmptyTable
                    columns={definition.columns}
                    icon={definition.icon}
                    caption={`${definition.panel} directory preview`}
                    title={definition.emptyTitle}
                    description={definition.emptyDescription}
                />
                <EmptyPagination />
            </Panel>
            <p className="availability-note" id="directory-availability">
                Adding and changing records is unavailable in this interface preview. No operational
                records are loaded.
            </p>
            {page === 'credentials' && (
                <div className="supporting-note">
                    <ShieldCheck size={18} aria-hidden="true" />
                    <div>
                        <strong>A reference to identity, not identity itself</strong>
                        <p>
                            NFC credentials will store a random reference. Guards still need to
                            review the person's identity and confirm observed passage.
                        </p>
                    </div>
                </div>
            )}
        </>
    );
}
