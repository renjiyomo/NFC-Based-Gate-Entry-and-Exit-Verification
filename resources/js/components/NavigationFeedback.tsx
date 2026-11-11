import { router } from '@inertiajs/react';
import { CircleAlert, X } from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Button } from './ui';
import { NavigationState } from '../lib/navigationState';

export default function NavigationFeedback({ children }: { children: ReactNode }) {
    const [loading, setLoading] = useState(false);
    const [announcement, setAnnouncement] = useState('');
    const [error, setError] = useState<string | null>(null);
    const retryUrl = useRef<string | null>(null);
    const [showSkeleton, setShowSkeleton] = useState(false);
    useEffect(() => {
        if (!loading) {
            setShowSkeleton(false);
            return;
        }
        const timer = window.setTimeout(() => setShowSkeleton(true), 180);
        return () => window.clearTimeout(timer);
    }, [loading]);

    useEffect(() => {
        const activeVisits = new Set<string>();
        let focusFrame: number | null = null;
        const updateLoading = () => {
            const busy = activeVisits.size > 0;
            setLoading(busy);
            document.getElementById('main-content')?.setAttribute('aria-busy', String(busy));
        };
        const navigationFailed = (message: string) => {
            setError(message);
            setAnnouncement('');
        };
        const unsubscribe = [
            router.on('start', ({ detail: { visit } }) => {
                if (visit.async || visit.method !== 'get') return;
                activeVisits.add(visit.id);
                retryUrl.current = visit.method === 'get' ? visit.url.href : null;
                setError(null);
                setAnnouncement('');
                updateLoading();
            }),
            router.on('finish', ({ detail: { visit } }) => {
                activeVisits.delete(visit.id);
                updateLoading();
            }),
            router.on('navigate', ({ detail: { type } }) => {
                if (type === 'initial') return;
                if (focusFrame !== null) window.cancelAnimationFrame(focusFrame);
                focusFrame = window.requestAnimationFrame(() => {
                    const main = document.getElementById('main-content');
                    const target = document.getElementById(window.location.hash.slice(1));
                    if (
                        target instanceof HTMLElement &&
                        main?.contains(target) &&
                        target.matches('input, select, textarea, button, a[href], [tabindex]')
                    ) {
                        target.focus();
                    } else {
                        main?.focus({ preventScroll: true });
                    }
                    main?.setAttribute('aria-busy', String(activeVisits.size > 0));
                    const title = main?.querySelector('h1, h2')?.textContent?.trim();
                    setAnnouncement(title ? `${title} loaded.` : 'Workspace view loaded.');
                    focusFrame = null;
                });
            }),
            router.on('error', () => {
                // Inertia's error event carries form validation, rendered beside its fields.
                setError(null);
                setAnnouncement('Review the highlighted form fields.');
                window.requestAnimationFrame(() =>
                    document.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus(),
                );
            }),
            router.on('networkError', () => {
                navigationFailed(
                    'The request could not be completed. Check your connection and try again.',
                );
                return false;
            }),
            router.on('httpException', () => {
                navigationFailed(
                    'The request is unavailable. Reload the page and try again in a moment.',
                );
                return false;
            }),
        ];
        return () => {
            unsubscribe.forEach((removeListener) => removeListener());
            if (focusFrame !== null) window.cancelAnimationFrame(focusFrame);
            document.getElementById('main-content')?.removeAttribute('aria-busy');
        };
    }, []);

    return (
        <NavigationState.Provider value={{ loading: showSkeleton }}>
            {children}
            <div className="sr-only" role="status" aria-live="polite" aria-atomic="true">
                {announcement}
            </div>
            {error && (
                <div
                    className="fixed right-4 bottom-4 z-50 w-96 max-w-[calc(100vw-2rem)] rounded-lg border border-[var(--border)] bg-[var(--surface)] p-4 shadow-lg"
                    role="alert"
                >
                    <div className="flex items-start gap-3">
                        <CircleAlert
                            size={19}
                            className="text-[var(--warning)]"
                            aria-hidden="true"
                        />
                        <p className="flex-1 text-sm leading-relaxed">{error}</p>
                        <Button
                            variant="quiet"
                            onClick={() => setError(null)}
                            aria-label="Dismiss navigation error"
                        >
                            <X size={17} aria-hidden="true" />
                        </Button>
                    </div>
                    <div className="mt-3 flex justify-end">
                        <Button
                            onClick={() => router.visit(retryUrl.current ?? window.location.href)}
                        >
                            Try again
                        </Button>
                    </div>
                </div>
            )}
        </NavigationState.Provider>
    );
}
