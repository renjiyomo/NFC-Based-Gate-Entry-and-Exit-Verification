import { usePage } from '@inertiajs/react';
import { navigation, type PageId } from './navigation';

export interface WorkspaceProps {
    [key: string]: unknown;
    app: {
        name: string;
        preview: boolean;
        testWorkspace?: boolean;
        simulator?: boolean;
        hardwareLab?: boolean;
    };
    auth?: {
        user: {
            id: number;
            name: string;
            email: string;
            role: 'admin' | 'guard';
            mustChangePassword: boolean;
        } | null;
    };
    flash: { status?: string };
    station?: { id: number; name: string; code: string };
    stations?: { id: number; name: string; code: string }[];
}

export function useWorkspace() {
    const { app, auth, flash, station, stations = [] } = usePage<WorkspaceProps>().props;
    const user = auth?.user;
    const items = navigation.filter((item) =>
        app.preview
            ? !['nfc', 'anomalies', 'summaries'].includes(item.id)
            : user?.role === 'admin'
              ? item.id !== 'scanner'
              : ['scanner', 'history', 'nfc'].includes(item.id),
    );
    const pageUrl = (page: PageId) =>
        page === 'nfc'
            ? '/nfc/setup'
            : app.preview
              ? `/preview/${page}`
              : user?.role === 'guard'
                ? page === 'history'
                    ? '/guard/activity'
                    : '/guard/scanner'
                : `/admin/${page}`;
    return {
        preview: app.preview,
        testWorkspace: app.testWorkspace ?? false,
        simulator: app.simulator ?? false,
        hardwareLab: app.hardwareLab ?? false,
        user,
        flash,
        station,
        stations,
        navigation: items,
        pageUrl,
        home: pageUrl(user?.role === 'guard' ? 'scanner' : 'dashboard'),
    };
}
