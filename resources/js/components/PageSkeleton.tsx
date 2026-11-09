export function SkeletonRows({ rows = 5 }: { rows?: number }) {
    return (
        <div className="skeleton-rows" aria-hidden="true">
            {Array.from({ length: rows }, (_, i) => (
                <div className="skeleton-row" key={i}>
                    <span className="skeleton skeleton-avatar" />
                    <span className="skeleton skeleton-name" />
                    <span className="skeleton skeleton-cell" />
                    <span className="skeleton skeleton-cell" />
                </div>
            ))}
        </div>
    );
}
export default function PageSkeleton({ page }: { page: string }) {
    return (
        <div className="page-skeleton" role="status" aria-label="Loading page">
            <span className="sr-only">Loading page…</span>
            <div className="skeleton-heading" aria-hidden="true">
                <div>
                    <span className="skeleton skeleton-title" />
                    <span className="skeleton skeleton-description" />
                </div>
                <span className="skeleton skeleton-action" />
            </div>
            {(page === 'dashboard' || page === 'analytics') && (
                <div className="skeleton-metrics" aria-hidden="true">
                    {[0, 1, 2, 3].map((i) => (
                        <div key={i}>
                            <span className="skeleton skeleton-description" />
                            <span className="skeleton skeleton-number" />
                        </div>
                    ))}
                </div>
            )}
            <div className="skeleton-panel">
                <div className="skeleton-filters" aria-hidden="true">
                    {[0, 1, 2].map((i) => (
                        <span className="skeleton" key={i} />
                    ))}
                </div>
                <SkeletonRows />
            </div>
        </div>
    );
}
