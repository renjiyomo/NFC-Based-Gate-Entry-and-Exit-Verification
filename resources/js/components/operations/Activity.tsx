import { useCallback, useEffect, useRef, useState } from 'react';
import { Badge, Button, Panel } from '../ui';
import { DataTable } from '../management/Common';
import {
    operationRequest,
    reasonLabel,
    reviewStatusLabel,
    type ActivityFeed,
} from '../../lib/operations';
import { manilaTime } from '../../lib/management';

export function useActivity(url: string | null) {
    const [feed, setFeed] = useState<ActivityFeed | null>(null);
    const [error, setError] = useState('');
    const active = useRef<AbortController | null>(null);
    const refresh = useCallback(async () => {
        if (!url || document.visibilityState === 'hidden') return;
        active.current?.abort();
        const controller = new AbortController();
        active.current = controller;
        try {
            const result = await operationRequest<ActivityFeed>(url, undefined, controller.signal);
            if (!controller.signal.aborted) {
                setFeed(result);
                setError('');
            }
        } catch {
            if (!controller.signal.aborted) {
                setFeed(null);
                setError(
                    'Activity could not refresh. Check your connection and current station permission.',
                );
            }
        }
    }, [url]);
    useEffect(() => {
        setFeed(null);
        setError('');
        void refresh();
        const timer = setInterval(() => void refresh(), 8000);
        const visible = () => {
            if (document.visibilityState === 'visible') void refresh();
            else active.current?.abort();
        };
        document.addEventListener('visibilitychange', visible);
        return () => {
            clearInterval(timer);
            active.current?.abort();
            document.removeEventListener('visibilitychange', visible);
        };
    }, [refresh]);
    return { feed, error, refresh };
}
export default function Activity({
    feed,
    error,
    refresh,
    station = false,
}: {
    feed: ActivityFeed | null;
    error: string;
    refresh: () => void;
    station?: boolean;
}) {
    return (
        <Panel
            title={station ? 'Recent station activity' : 'Verification and passage activity'}
            subtitle="Refreshes every 8 seconds while visible. Only confirmed passage is a recorded entry or exit."
            action={<Button onClick={refresh}>Refresh activity</Button>}
        >
            {error && (
                <p className="form-errors" role="alert">
                    {error}
                </p>
            )}
            {!feed && !error ? (
                <p className="panel-message" role="status">
                    Loading permitted activity…
                </p>
            ) : (
                <>
                    <DataTable
                        caption="Verification attempts and their passage outcomes"
                        columns={[
                            'Person / gate',
                            'Direction',
                            'Credential check',
                            'Review / passage',
                            'Time (Manila)',
                        ]}
                        count={feed?.records.length ?? 0}
                        empty="No permitted activity yet"
                        emptyDescription="Credential checks and their separate review outcomes will appear here."
                    >
                        {feed?.records.map((record) => (
                            <tr key={record.id}>
                                <td>
                                    <strong>{record.person ?? 'Identity not disclosed'}</strong>
                                    <span className="table-secondary">{record.gate}</span>
                                    <span className="table-secondary">{record.guard}</span>
                                    {record.source === 'simulated' && (
                                        <Badge tone="warning">Simulated</Badge>
                                    )}
                                </td>
                                <td className="capitalize">{record.direction}</td>
                                <td>
                                    <Badge tone={record.outcome === 'allowed' ? 'blue' : 'warning'}>
                                        {record.outcome === 'allowed'
                                            ? 'Authorized at scan'
                                            : 'Denied'}
                                    </Badge>
                                    <span className="table-secondary">
                                        {reasonLabel(record.reason)}
                                    </span>
                                </td>
                                <td>
                                    <Badge
                                        tone={
                                            record.voided
                                                ? 'warning'
                                                : record.confirmed_at
                                                  ? 'success'
                                                  : record.review_status === 'pending'
                                                    ? 'blue'
                                                    : 'neutral'
                                        }
                                    >
                                        {record.voided
                                            ? 'Passage voided'
                                            : record.confirmed_at
                                              ? 'Passage recorded'
                                              : reviewStatusLabel(record.review_status)}
                                    </Badge>
                                    {record.review_reason && (
                                        <span className="table-secondary">
                                            {reasonLabel(record.review_reason)}
                                        </span>
                                    )}
                                </td>
                                <td>
                                    {manilaTime(record.received_at)}
                                    {record.confirmed_at && (
                                        <span className="table-secondary">
                                            Confirmed {manilaTime(record.confirmed_at)}
                                        </span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </DataTable>
                    {feed && (
                        <p className="panel-bottom-note">
                            Showing up to {feed.limit} latest attempts
                            {feed.minutes
                                ? ` from the last ${feed.minutes} minutes at this active gate`
                                : ''}
                            . Last refreshed {manilaTime(feed.server_time)}.
                        </p>
                    )}
                </>
            )}
        </Panel>
    );
}
