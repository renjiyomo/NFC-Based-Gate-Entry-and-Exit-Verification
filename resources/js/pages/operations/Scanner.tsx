import { router } from '@inertiajs/react';
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
    function next() {
        payload.current = null;
        frozen.current = null;
        setResult(null);
        setErrors({});
        setStatus('');
        setMissing(false);
        setUncertain(false);
        locate(null);
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
            description="Choose Entry or Exit. Each valid NFC scan records automatically."
        >
            <FormErrors errors={errors} />
            <Panel
                title={
                    station ? `${station.code} · ${station.name}` : 'Gate account not configured'
                }
                subtitle={
                    station
                        ? `Gate session · ${duplicateSeconds}-second duplicate protection`
                        : 'Ask an administrator to link this account to an active gate.'
                }
            >
                <div className="automatic-scanner-body">
                    <div
                        className="automatic-direction"
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
                                {d === 'entry' ? 'Entry' : 'Exit'}
                            </Button>
                        ))}
                    </div>
                    <h2>
                        {direction
                            ? `Ready for ${direction === 'entry' ? 'Entry' : 'Exit'}`
                            : 'Select Entry or Exit'}
                    </h2>
                    <p className="panel-message">Direction stays selected for the next scan.</p>
                    <div className="scanner-options">
                        <Button
                            aria-pressed={sound}
                            onClick={() => {
                                if (!sound) void prepareSound();
                                setSound(!sound);
                            }}
                        >
                            Sound {sound ? 'on' : 'off'}
                        </Button>
                    </div>
                    {soundNote && <p className="form-note">{soundNote}</p>}
                    <Button
                        variant="primary"
                        disabled={
                            !station ||
                            !direction ||
                            busy ||
                            uncertain ||
                            (!simulator && !nfcAvailable())
                        }
                        onClick={() => void scan()}
                    >
                        {reading ? 'Reading coin…' : 'Scan NFC coin'}
                    </Button>
                    {reading && (
                        <Button onClick={() => active.current?.abort()}>Cancel NFC read</Button>
                    )}
                    {!simulator && !nfcAvailable() && (
                        <p className="form-note">
                            Web NFC is unavailable in this browser. Use the compatible Android NFC
                            phone.
                        </p>
                    )}
                    <p role="status" aria-live="polite">
                        {status}
                    </p>
                    {result && feedback && (
                        <div
                            className={`scan-result scan-result-${feedback.tone}`}
                            role={
                                feedback.tone === 'warning' || feedback.tone === 'denied'
                                    ? 'alert'
                                    : 'status'
                            }
                        >
                            <Badge
                                tone={
                                    feedback.tone === 'warning' || feedback.tone === 'denied'
                                        ? 'warning'
                                        : result.passage
                                          ? 'success'
                                          : result.duplicate_of
                                            ? 'neutral'
                                            : 'warning'
                                }
                            >
                                {feedback.title}
                            </Badge>
                            <p className="scan-result-message">{feedback.message}</p>
                            {result.sequence_context && (
                                <p>
                                    Previous effective record:{' '}
                                    {result.sequence_context.direction === 'entry'
                                        ? 'Entry'
                                        : 'Exit'}{' '}
                                    · {manilaTime(result.sequence_context.confirmed_at)} ·{' '}
                                    {result.sequence_context.gate}
                                </p>
                            )}
                            {result.identity && (
                                <>
                                    <Portrait
                                        src={result.identity.photo_url}
                                        name={result.identity.name}
                                    />
                                    <h2>{result.identity.name}</h2>
                                    <p>
                                        {result.identity.number} · {result.identity.kind}
                                    </p>
                                </>
                            )}
                            {result.duplicate_of && (
                                <p>
                                    Original{' '}
                                    {result.duplicate_of.direction === 'entry' ? 'Entry' : 'Exit'}:{' '}
                                    {manilaTime(result.duplicate_of.confirmed_at)}
                                </p>
                            )}
                            {result.passage ? (
                                <p>
                                    {result.direction === 'entry' ? 'Entry' : 'Exit'} ·{' '}
                                    {manilaTime(result.passage.confirmed_at)}
                                </p>
                            ) : (
                                !result.duplicate_of && (
                                    <p>{reasonLabel(result.verification.reason)}</p>
                                )
                            )}
                        </div>
                    )}
                    {uncertain && (
                        <div>
                            <p className="form-note">
                                The server may have saved this scan. Recover the same request before
                                continuing.
                            </p>
                            <Button disabled={busy} onClick={() => void recover()}>
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
                    )}
                    {result && !uncertain && (
                        <Button disabled={busy} onClick={next}>
                            Clear result
                        </Button>
                    )}
                </div>
            </Panel>
            <Panel title="Recent gate activity" subtitle="Latest permitted scans at this gate">
                {!activity.feed && !activity.error && (
                    <div role="status" aria-label="Loading gate activity">
                        <SkeletonRows rows={3} />
                    </div>
                )}
                <Button onClick={() => void activity.refresh()}>Refresh activity</Button>
                {activity.error && (
                    <p role="alert" className="form-errors">
                        {activity.error}
                    </p>
                )}
                {activity.feed?.records.length === 0 && (
                    <p className="panel-message">No recent scans at this gate.</p>
                )}
                {activity.feed?.records.slice(0, 10).map((r) => (
                    <p key={r.id}>
                        <strong>{r.person ?? 'Unidentified coin'}</strong> · {r.direction} ·{' '}
                        {r.duplicate
                            ? 'Duplicate suppressed'
                            : r.confirmed_at
                              ? 'Recorded'
                              : 'Rejected'}{' '}
                        · {manilaTime(r.received_at)}
                        {r.sequence_notice && (
                            <span className="table-secondary">
                                Warning: {sequenceLabel(r.sequence_notice)}
                            </span>
                        )}
                    </p>
                ))}
            </Panel>
        </ManagementPage>
    );
}
