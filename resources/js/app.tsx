import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { Component, StrictMode, type ComponentType, type ReactNode } from 'react';
import { createRoot } from 'react-dom/client';
import { Button, ErrorState } from './components/ui';
import NavigationFeedback from './components/NavigationFeedback';

class AppErrorBoundary extends Component<{ children: ReactNode }, { failed: boolean }> {
    state = { failed: false };
    static getDerivedStateFromError() {
        return { failed: true };
    }
    render() {
        if (this.state.failed)
            return (
                <main id="main-content" tabIndex={-1} className="standalone-error">
                    <ErrorState
                        description="The interface encountered an unexpected problem. Reload the page to try again."
                        action={
                            <Button onClick={() => window.location.reload()}>
                                Reload workspace
                            </Button>
                        }
                    />
                </main>
            );
        return this.props.children;
    }
}

const pages = import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx');

void createInertiaApp({
    title: (title) => (title ? `${title} · NFCheck` : 'NFCheck'),
    resolve: async (name) => {
        const load = pages[`./pages/${name}.tsx`];
        if (!load) throw new Error('Workspace page is unavailable.');
        return (await load()).default;
    },
    setup({ el, App, props }) {
        createRoot(el).render(
            <StrictMode>
                <AppErrorBoundary>
                    <NavigationFeedback />
                    <App {...props} />
                </AppErrorBoundary>
            </StrictMode>,
        );
    },
    progress: { color: '#a8c7fa', showSpinner: false },
}).catch(() => {
    const mount = document.getElementById('app');
    if (mount && !mount.hasChildNodes())
        createRoot(mount).render(
            <main id="main-content" tabIndex={-1} className="standalone-error">
                <ErrorState
                    description="The workspace could not start. Check the connection and reload the page."
                    action={
                        <Button onClick={() => window.location.reload()}>Reload workspace</Button>
                    }
                />
            </main>,
        );
});
