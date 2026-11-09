import {
    Activity,
    BarChart3,
    BriefcaseBusiness,
    DoorOpen,
    FileText,
    GraduationCap,
    KeyRound,
    LayoutDashboard,
    ScanLine,
    Settings2,
    ShieldCheck,
    type LucideIcon,
} from 'lucide-react';

export type PageId =
    | 'dashboard'
    | 'students'
    | 'employees'
    | 'credentials'
    | 'guards'
    | 'gates'
    | 'scanner'
    | 'history'
    | 'reports'
    | 'analytics'
    | 'anomalies'
    | 'summaries'
    | 'settings'
    | 'nfc';

export interface NavigationItem {
    id: PageId;
    label: string;
    group: string;
    icon: LucideIcon;
}

export const navigation: NavigationItem[] = [
    { id: 'dashboard', label: 'Overview', group: 'Workspace', icon: LayoutDashboard },
    { id: 'students', label: 'Students', group: 'People', icon: GraduationCap },
    { id: 'employees', label: 'Employees', group: 'People', icon: BriefcaseBusiness },
    { id: 'credentials', label: 'NFC credentials', group: 'Access', icon: KeyRound },
    { id: 'guards', label: 'Gate accounts', group: 'Access', icon: ShieldCheck },
    { id: 'gates', label: 'Gates & accounts', group: 'Access', icon: DoorOpen },
    { id: 'scanner', label: 'Gate scanner', group: 'Monitoring', icon: ScanLine },
    { id: 'history', label: 'Entry & exit history', group: 'Monitoring', icon: Activity },
    { id: 'anomalies', label: 'Activity checks', group: 'Monitoring', icon: ShieldCheck },
    { id: 'reports', label: 'Reports', group: 'Insights', icon: FileText },
    { id: 'analytics', label: 'Analytics', group: 'Insights', icon: BarChart3 },
    { id: 'summaries', label: 'AI summaries', group: 'Insights', icon: FileText },
    { id: 'settings', label: 'Settings', group: 'System', icon: Settings2 },
    { id: 'nfc', label: 'NFC setup', group: 'System', icon: ScanLine },
];

export const pageUrl = (page: PageId) => `/preview/${page}`;
