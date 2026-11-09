import { router } from '@inertiajs/react';
import { ScanLine, Volume2, VolumeX, ArrowDownLeft, ArrowUpRight } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button, Panel, Badge } from '../../components/ui';
import { ManagementPage, FormErrors, Portrait } from '../../components/management/Common';
import { useWorkspace } from '../../lib/workspace';
import { NdefAdapter, nfcAvailable, readVirtualTag, acceptVirtualTagHandoff } from '../../lib/nfc';
import {
    operationBase,
    operationRequest,
    operationErrors,
    OperationError,
    reasonLabel,
    type Operation,
    type Station,
    type Direction,
} from '../../lib/operations';
import { manilaTime } from '../../lib/management';
import { useActivity } from '../../components/operations/Activity';
import { gateFeedback, playFeedback, sequenceLabel } from '../../lib/gateFeedback';
import { SkeletonRows } from '../../components/PageSkeleton';

export default function Scanner({
    station,
    duplicateSeconds,
    requestId,
    requestDirection,
}: {
    station: Station | null;
    duplicateSeconds: number;
    requestId: string | null;
    requestDirection: Direction | null;
}) {
    const { simulator } = useWorkspace();
    const [direction, setDirection] = useState<Direction | null>(requestDirection);
    const [result, setResult] = useState<Operation | null>(null);
    const [busy, setBusy] = useState(false);
    const [reading, setReading] = useState(false);
    const [uncertain, setUncertain] = useState(Boolean(requestId));
    const [missing, setMissing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [status, setStatus] = useState('');
    const [sound, setSound] = useState(false);
    const [soundNote, setSoundNote] = useState('');
    const audio = useRef<AudioContext | null>(null);
    const active = useRef<AbortController | null>(null);
    const request = useRef<string | null>(requestId);
    const payload = useRef<string | null>(null);
    const frozen = useRef<Direction | null>(requestDirection);
    const locked = useRef(false);
    const mounted = useRef(true);
    const activity = useActivity(station ? `/guard/gates/${station.id}/activity` : null);
    const base = station ? operationBase(station.id, simulator) : null;
    function locate(id: string | null) {
        request.current = id;
        if (station)
            router.replace({
                url: `/guard/gates/${station.id}/scanner${id ? `?request_id=${id}&direction=${frozen.current}` : ''}`,
                preserveState: true,
                preserveScroll: true,
            });
    }
    async function prepareSound() {
        try {
            audio.current ??= new AudioContext();
            await audio.current.resume();
            setSoundNote(
                audio.current.state === 'running'
                    ? ''
                    : 'Sound is unavailable. Read the on-screen result.',
            );
        } catch {
            setSoundNote('Sound is unavailable. Read the on-screen result.');
        }
    }
    function accept(r: Operation, announceSound = false) {
        if (!mounted.current) return;
        setResult(r);
        setUncertain(false);
        setMissing(false);
        payload.current = null;
        setStatus('');
        if (announceSound && sound && audio.current)
            playFeedback(audio.current, gateFeedback(r).tone);
        if (simulator) acceptVirtualTagHandoff();
        void activity.refresh();
    }
    async function recover() {
        if (!base || !request.current || locked.current) return;
        locked.current = true;
        setBusy(true);
        setErrors({});
        try {
            accept(await operationRequest<Operation>(`${base}/requests/${request.current}`));
        } catch (e) {
            setErrors(operationErrors(e));
            if (e instanceof OperationError && e.status === 404) setMissing(true);
        } finally {
            locked.current = false;
            if (mounted.current) setBusy(false);
        }
    }
    async function send() {
        if (!base || !payload.current || !request.current || !frozen.current) return;
        setUncertain(true);
        try {
            accept(
                await operationRequest<Operation>(`${base}/scan`, {
                    request_id: request.current,
                    payload: payload.current,
                    direction: frozen.current,
                }),
                true,
            );
        } catch (e) {
            setErrors(operationErrors(e));
            if (e instanceof OperationError && [403, 419, 422, 429].includes(e.status)) {
                setStatus('Request rejected. Recover its status before starting another scan.');
            }
        }
    }
    async function scan(reuse = false) {
        if (!base || !direction || locked.current || (uncertain && !reuse)) return;
        locked.current = true;
        setBusy(true);
        setReading(true);
        setErrors({});
        setResult(null);
        setStatus('Present one NFC coin.');
        if (sound) void prepareSound();
        active.current = new AbortController();
        try {
            const ref = simulator
                ? readVirtualTag()
                : await new NdefAdapter().read(active.current.signal);
            if (!ref) throw new Error('No virtual tag available');
            payload.current = ref;
            if (!reuse) {
                frozen.current = direction;
                locate(crypto.randomUUID());
            }
            setReading(false);
            await send();
        } catch {
            setStatus('Reader stopped. No scan request was sent.');
            setErrors({ nfc: 'The coin could not be read. Start a fresh scan when ready.' });
        } finally {
            locked.current = false;
            if (mounted.current) {
                setBusy(false);
                setReading(false);
            }
        }
    }
    async function retry() {
        if (locked.current) return;
        locked.current = true;
        setBusy(true);
        setErrors({});
        try {
            await send();
        } finally {
            locked.current = false;
            if (mounted.current) setBusy(false);
        }
    }
    useEffect(() => {
        mounted.current = true;
        const hide = () => {
            if (document.hidden) {
                active.current?.abort();
                payload.current = null;
                setReading(false);
                void audio.current?.suspend().catch(() => {});
            }
        };
        document.addEventListener('visibilitychange', hide);
        if (requestId && station) void recover();
        return () => {
            mounted.current = false;
            active.current?.abort();
            payload.current = null;
            void audio.current?.close().catch(() => {});
            document.removeEventListener('visibilitychange', hide);
        };
    }, []);
    const feedback = result ? gateFeedback(result) : null;
    return (
        <ManagementPage
            page="scanner"
            title="Gate scanner"
            description="Select a direction, then scan one NFC coin. Valid scans record automatically."
        >
            <FormErrors errors={errors} />
            <Panel
                className="gate-scanner"
                title={station ? station.name : 'Gate not configured'}
                subtitle={
                    station ? station.code : 'Ask an administrator to configure this gate account.'
                }
                action={
                    <Button
                        className="sound-control"
                        aria-label={sound ? 'Turn scan sound off' : 'Turn scan sound on'}
                        aria-pressed={sound}
                        onClick={() => {
                            if (!sound) void prepareSound();
                            setSound(!sound);
                        }}
                    >
                        {sound ? (
                            <Volume2 size={17} aria-hidden="true" />
                        ) : (
                            <VolumeX size={17} aria-hidden="true" />
                        )}
                        <span>Sound {sound ? 'on' : 'off'}</span>
                    </Button>
                }
            >
                <div className="scan-workspace">
                    <div className="scan-controls">
                        <span className="control-label">Passage direction</span>
                        <div
                            className="scan-directions"
                            role="group"
                            aria-label="Passage direction"
                        >
                            {(['entry', 'exit'] as const).map((d) => (
                                <Button
                                    key={d}
                                    variant={direction === d ? 'primary' : 'secondary'}
                                    disabled={busy || uncertain}
                                    aria-pressed={direction === d}
                                    onClick={() => setDirection(d)}
                                >
                                    {d === 'entry' ? (
                                        <ArrowDownLeft size={19} aria-hidden="true" />
                                    ) : (
                                        <ArrowUpRight size={19} aria-hidden="true" />
                                    )}
                                    {d === 'entry' ? 'Entry' : 'Exit'}
                                </Button>
                            ))}
                        </div>
                        <div className="scan-prompt">
                            <ScanLine size={32} strokeWidth={1.4} aria-hidden="true" />
                            <h2>
                                {reading
                                    ? 'Hold the coin near the phone'
                                    : direction
                                      ? 'Ready for ' + (direction === 'entry' ? 'Entry' : 'Exit')
                                      : 'Choose Entry or Exit'}
                            </h2>
                            <p>
                                {reading
                                    ? 'Keep one coin against the back of the phone.'
                                    : 'The selected direction stays ready for the next person.'}
                            </p>
                        </div>
                        <Button
                            variant="primary"
                            className="scan-main-action"
                            disabled={
                                !station ||
                                !direction ||
                                busy ||
                                uncertain ||
                                (!simulator && !nfcAvailable())
                            }
                            onClick={() => void scan()}
                        >
                            <ScanLine size={18} aria-hidden="true" />
                            {reading
                                ? 'Reading coin…'
                                : busy
                                  ? 'Checking…'
                                  : result
                                    ? 'Scan next coin'
                                    : 'Scan NFC coin'}
                        </Button>
                        {reading && (
                            <Button onClick={() => active.current?.abort()}>Cancel NFC read</Button>
                        )}
                        {!simulator && !nfcAvailable() && (
                            <p className="scanner-device-note">
                                Scanning is available in Chrome on a compatible Android NFC phone.
                            </p>
                        )}
                        {soundNote && <p className="scanner-device-note">{soundNote}</p>}
                        <details className="compact-help">
                            <summary>Scan guidance</summary>
                            <p>
                                Present one coin at a time. Repeated scans within {duplicateSeconds}{' '}
                                seconds are suppressed. A warning may indicate a missing earlier
                                scan; it does not block an otherwise valid passage.
                            </p>
                        </details>
                    </div>
                    <div className="scan-feedback-area">
                        {status && (
                            <p className="scan-live-status" role="status" aria-live="polite">
                                {status}
                            </p>
                        )}
                        {result && feedback ? (
                            <div
                                className={'scan-result scan-result-' + feedback.tone}
                                role={
                                    feedback.tone === 'warning' || feedback.tone === 'denied'
                                        ? 'alert'
                                        : 'status'
                                }
                            >
                                <div className="scan-result-heading">
                                    <h2>{feedback.title}</h2>
                                    <Badge
                                        tone={
                                            feedback.tone === 'success'
                                                ? 'success'
                                                : feedback.tone === 'duplicate'
                                                  ? 'blue'
                                                  : 'warning'
                                        }
                                    >
                                        {result.direction === 'entry' ? 'Entry' : 'Exit'}
                                    </Badge>
                                </div>
                                {result.identity && (
                                    <div className="scan-person">
                                        <Portrait
                                            src={result.identity.photo_url}
                                            name={result.identity.name}
                                        />
                                        <div>
                                            <h3>{result.identity.name}</h3>
                                            <p>{result.identity.number}</p>
                                            <span>
                                                {result.identity.kind === 'student'
                                                    ? 'Student'
                                                    : 'Employee'}
                                            </span>
                                        </div>
                                    </div>
                                )}
                                <p className="scan-result-message">{feedback.message}</p>
                                {result.sequence_context && (
                                    <p className="scan-context">
                                        Previous record:{' '}
                                        {result.sequence_context.direction === 'entry'
                                            ? 'Entry'
                                            : 'Exit'}{' '}
                                        · {manilaTime(result.sequence_context.confirmed_at)} ·{' '}
                                        {result.sequence_context.gate}
                                    </p>
                                )}
                                {result.duplicate_of && (
                                    <p className="scan-context">
                                        Original{' '}
                                        {result.duplicate_of.direction === 'entry'
                                            ? 'Entry'
                                            : 'Exit'}{' '}
                                        · {manilaTime(result.duplicate_of.confirmed_at)}
                                    </p>
                                )}
                                {result.passage ? (
                                    <p className="scan-receipt-time">
                                        Recorded {manilaTime(result.passage.confirmed_at)}
                                    </p>
                                ) : (
                                    !result.duplicate_of && (
                                        <p className="scan-context">
                                            {reasonLabel(result.verification.reason)}
                                        </p>
                                    )
                                )}
                            </div>
                        ) : (
                            !uncertain && (
                                <div className="scan-empty-result">
                                    <span className="scan-empty-mark" />
                                    <h3>Scan result</h3>
                                    <p>The person and Entry or Exit result will appear here.</p>
                                </div>
                            )
                        )}
                        {uncertain && (
                            <div className="scan-recovery">
                                <h2>Check the previous request</h2>
                                <p>
                                    The connection was interrupted. Recover this request before
                                    scanning another coin to avoid an extra record.
                                </p>
                                <div className="form-actions">
                                    <Button
                                        variant="primary"
                                        disabled={busy}
                                        onClick={() => void recover()}
                                    >
                                        Recover request status
                                    </Button>
                                    {missing && payload.current && (
                                        <Button disabled={busy} onClick={() => void retry()}>
                                            Retry the same request
                                        </Button>
                                    )}
                                    {missing && !payload.current && (
                                        <Button
                                            disabled={busy || !frozen.current}
                                            onClick={() => void scan(true)}
                                        >
                                            Read coin for same request
                                        </Button>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </Panel>
            <Panel
                title="Recent scans at this gate"
                subtitle="Latest scans from the last 30 minutes"
                action={
                    <Button onClick={() => void activity.refresh()} disabled={busy}>
                        Refresh
                    </Button>
                }
            >
                {!activity.feed && !activity.error && (
                    <div role="status" aria-label="Loading gate activity">
                        <SkeletonRows rows={3} />
                    </div>
                )}
                {activity.error && (
                    <p role="alert" className="form-errors">
                        {activity.error}
                    </p>
                )}
                {activity.feed?.records.length === 0 && (
                    <p className="panel-message">No scans in the last 30 minutes.</p>
                )}
                <ul className="scanner-recent-list">
                    {activity.feed?.records.slice(0, 10).map((r) => (
                        <li key={r.id}>
                            <div>
                                <strong>{r.person ?? 'Unidentified coin'}</strong>
                                <span>
                                    {r.direction === 'entry' ? 'Entry' : 'Exit'} ·{' '}
                                    {manilaTime(r.received_at)}
                                </span>
                                {r.sequence_notice && (
                                    <span className="recent-warning">
                                        {sequenceLabel(r.sequence_notice)}
                                    </span>
                                )}
                            </div>
                            <Badge
                                tone={
                                    r.duplicate
                                        ? 'blue'
                                        : r.sequence_notice
                                          ? 'warning'
                                          : r.confirmed_at
                                            ? 'success'
                                            : 'warning'
                                }
                            >
                                {r.duplicate
                                    ? 'Duplicate'
                                    : r.confirmed_at
                                      ? 'Recorded'
                                      : 'Rejected'}
                            </Badge>
                        </li>
                    ))}
                </ul>
            </Panel>
        </ManagementPage>
    );
}
