import { Head } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';
import Dashboard from '../components/Dashboard';
import DirectoryPage from '../components/DirectoryPage';
import Scanner from '../components/Scanner';
import {
    AnalyticsPage,
    HistoryPage,
    ReportsPage,
    SettingsPage,
} from '../components/WorkspacePages';
import { navigation, type PageId } from '../lib/navigation';
import { ErrorState } from '../components/ui';

export default function Workspace({ page }: { page: string }) {
    const item = navigation.find((item) => item.id === page);
    const activePage: PageId = item?.id ?? 'dashboard';
    let content;
    switch (page) {
        case 'dashboard':
            content = <Dashboard />;
            break;
        case 'students':
        case 'employees':
        case 'credentials':
        case 'guards':
        case 'gates':
            content = <DirectoryPage page={page} />;
            break;
        case 'scanner':
            content = <Scanner />;
            break;
        case 'history':
            content = <HistoryPage />;
            break;
        case 'reports':
            content = <ReportsPage />;
            break;
        case 'analytics':
            content = <AnalyticsPage />;
            break;
        case 'settings':
            content = <SettingsPage />;
            break;
        default:
            content = (
                <ErrorState
                    title="This workspace view is unavailable"
                    description="Use the navigation to choose an available preview screen."
                />
            );
    }
    return (
        <AppLayout page={activePage}>
            <Head title={item?.label ?? 'Workspace'} />
            {content}
        </AppLayout>
    );
}
