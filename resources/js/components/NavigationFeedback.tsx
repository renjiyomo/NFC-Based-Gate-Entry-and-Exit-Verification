import { router } from '@inertiajs/react';
import { CircleAlert, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button, LoadingState } from './ui';

export default function NavigationFeedback() {
    const [loading, setLoading] = useState(false);
    const [announcement, setAnnouncement] = useState('');
    const [error, setError] = useState<string | null>(null);
    const retryUrl = useRef<string | null>(null);

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
                if (visit.async) return;
                activeVisits.add(visit.id);
                retryUrl.current = visit.url.href;
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
                    main?.focus({ preventScroll: true });
                    main?.setAttribute('aria-busy', String(activeVisits.size > 0));
                    const title = main?.querySelector('h1, h2')?.textContent?.trim();
                    setAnnouncement(title ? `${title} loaded.` : 'Workspace view loaded.');
                    focusFrame = null;
                });
            }),
            router.on('error', () => {
                navigationFailed('The requested view reported an error. Try opening it again.');
            }),
            router.on('networkError', () => {
                navigationFailed(
                    'The next view could not be loaded. Check your connection and try again.',
                );
                return false;
            }),
            router.on('httpException', () => {
                navigationFailed(
                    'The next view is unavailable. Try again or stay on the current page.',
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
        <>
            <div className="sr-only" role="status" aria-live="polite" aria-atomic="true">
                {announcement}
            </div>
            {loading && (
                <div className="fixed right-4 bottom-4 z-50 max-w-[calc(100vw-2rem)]">
                    <LoadingState compact message="Opening workspace view…" />
                </div>
            )}
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
        </>
    );
}
