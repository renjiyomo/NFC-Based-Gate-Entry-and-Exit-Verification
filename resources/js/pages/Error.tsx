import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { ErrorState } from '../components/ui';

export default function Error({ status = 500 }: { status?: number }) {
    const title =
        status === 404
            ? 'This page could not be found'
            : status === 403
              ? 'This view is not available'
              : status === 503
                ? 'The workspace is temporarily unavailable'
                : 'Something interrupted this view';
    return (
        <main id="main-content" tabIndex={-1} className="standalone-error">
            <Head title={`${status} · Unavailable`} />
            <img src="/images/nfcheck-logo.png" width="58" height="58" alt="NFCheck" />
            <ErrorState
                title={title}
                description="Return to the workspace or try again in a moment."
                action={
                    <Link href="/" className="button button-secondary">
                        <ArrowLeft size={16} aria-hidden="true" />
                        Return to workspace
                    </Link>
                }
            />
        </main>
    );
}
