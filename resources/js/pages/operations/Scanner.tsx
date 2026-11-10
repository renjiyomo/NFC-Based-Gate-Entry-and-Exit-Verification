import { router } from '@inertiajs/react';
import { ScanLine, Volume2, VolumeX } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button, Panel, Badge } from '../../components/ui';
import { ManagementPage, FormErrors, Portrait } from '../../components/management/Common';
import { useWorkspace } from '../../lib/workspace';
import { NdefAdapter, nfcAvailable, readVirtualTag, acceptVirtualTagHandoff } from '../../lib/nfc';
import { ScanLatch } from '../../lib/scanLatch';
import {
    operationBase,
    operationRequest,
    operationErrors,
    OperationError,
    reasonLabel,
    type Operation,
    type Station,
} from '../../lib/operations';
import { manilaTime } from '../../lib/management';
import { useActivity } from '../../components/operations/Activity';
import { gateFeedback, playFeedback, sequenceLabel } from '../../lib/gateFeedback';
import { SkeletonRows } from '../../components/PageSkeleton';

export default function Scanner({
    station,
    duplicateSeconds,
    requestId,
}: {
    station: Station | null;
    duplicateSeconds: number;
    requestId: string | null;
}) {
    const { simulator } = useWorkspace();
    const [result, setResult] = useState<Operation | null>(null);
    const [busy, setBusy] = useState(false);
    const [running, setRunning] = useState(false);
    const [starting, setStarting] = useState(false);
    const [uncertain, setUncertain] = useState(Boolean(requestId));
    const [missing, setMissing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [status, setStatus] = useState('Scanner stopped');
    const [sound, setSound] = useState(false);
    const [soundNote, setSoundNote] = useState('');
    const audio = useRef<AudioContext | null>(null);
    const active = useRef<AbortController | null>(null);
    const request = useRef<string | null>(requestId);
    const payload = useRef<string | null>(null);
    const pending = useRef(Boolean(requestId));
    const locked = useRef(false);
    const mounted = useRef(true);
    const enabledSound = useRef(sound);
    enabledSound.current = sound;
    const clearResult = useRef<ReturnType<typeof setTimeout> | null>(null);
    const latch = useRef(new ScanLatch(duplicateSeconds * 1000));
    const activity = useActivity(station ? `/guard/gates/${station.id}/activity` : null);
    const base = station ? operationBase(station.id, simulator) : null;

    function locate(id: string | null) {
        request.current = id;
        if (station)
            router.replace({
                url: `/guard/gates/${station.id}/scanner${id ? '?request_id=' + id : ''}`,
                preserveState: true,
                preserveScroll: true,
            });
    }
    function stop(message = 'Scanner stopped') {
        active.current?.abort();
        active.current = null;
        latch.current.clear();
        if (mounted.current) {
            setRunning(false);
            setStarting(false);
            setStatus(message);
        }
    }
    async function prepareSound() {
        try {
            audio.current ??= new AudioContext();
            await audio.current.resume();
            setSoundNote(
                audio.current.state === 'running'
                    ? ''
                    : 'Sound unavailable. Read the on-screen result.',
            );
        } catch {
            setSoundNote('Sound unavailable. Read the on-screen result.');
        }
    }
    function accept(r: Operation) {
        if (!mounted.current || document.hidden) return;
        setResult(r);
        setErrors({});
        setUncertain(false);
        setMissing(false);
        pending.current = false;
        payload.current = null;
        locate(null);
        if (enabledSound.current && audio.current)
            playFeedback(audio.current, gateFeedback(r).tone);
        if (simulator) acceptVirtualTagHandoff();
        setStatus(active.current ? 'Ready to Scan' : 'Scanner stopped');
        if (clearResult.current) clearTimeout(clearResult.current);
        clearResult.current = setTimeout(() => {
            if (mounted.current) setResult(null);
        }, 5000);
        void activity.refresh();
    }
    async function send() {
        if (!base || !payload.current || !request.current) return;
        pending.current = true;
        setUncertain(true);
        const id = request.current;
        try {
            const r = await operationRequest<Operation>(`${base}/automatic-scan`, {
                request_id: id,
                payload: payload.current,
            });
            if (request.current === id) accept(r);
        } catch (e) {
            stop('Connection or session interrupted');
            if (mounted.current) setErrors(operationErrors(e));
        }
    }
    async function onReference(reference: string) {
        if (locked.current || pending.current || document.hidden || !active.current) return;
        if (!latch.current.accept(reference)) {
            setStatus('Already Scanned — remove the coin before presenting it again');
            return;
        }
        locked.current = true;
        setBusy(true);
        setErrors({});
        setResult(null);
        setStatus('Processing');
        payload.current = reference;
        locate(crypto.randomUUID());
        try {
            await send();
        } finally {
            locked.current = false;
            if (mounted.current) setBusy(false);
        }
    }
    async function start() {
        if (!base || locked.current || pending.current || active.current) return;
        setErrors({});
        active.current = new AbortController();
        setStatus('Starting scanner');
        if (sound) void prepareSound();
        if (simulator) {
            setRunning(true);
            setStatus('Ready to Scan');
            return;
        }
        const controller = active.current;
        setStarting(true);
        try {
            await new NdefAdapter().watch(
                controller.signal,
                (reference) => void onReference(reference),
                (error, fatal) => {
                    if (!mounted.current || (controller.signal.aborted && !fatal)) return;
                    if (!fatal && locked.current) return;
                    if (fatal) stop('Reader stopped');
                    setResult(null);
                    setErrors({ nfc: error.message });
                    if (!fatal) setStatus('Scan Rejected — ready for another coin');
                },
            );
            if (!controller.signal.aborted && mounted.current) {
                setRunning(true);
                if (!locked.current) setStatus('Ready to Scan');
            }
        } catch (e) {
            if (!controller.signal.aborted) {
                stop('Scanner unavailable');
                if (mounted.current)
                    setErrors({ nfc: e instanceof Error ? e.message : 'Could not start NFC.' });
            }
        } finally {
            if (mounted.current) setStarting(false);
        }
    }
    async function recover() {
        if (!base || !request.current || locked.current) return;
        locked.current = true;
        setBusy(true);
        setErrors({});
        try {
            accept(await operationRequest<Operation>(`${base}/requests/${request.current}`));
        } catch (e) {
            if (mounted.current) {
                setErrors(operationErrors(e));
                if (e instanceof OperationError && e.status === 404) setMissing(true);
            }
        } finally {
            locked.current = false;
            if (mounted.current) setBusy(false);
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
    async function readForRecovery() {
        if (locked.current || !missing) return;
        locked.current = true;
        setBusy(true);
        const controller = new AbortController();
        active.current = controller;
        try {
            payload.current = simulator
                ? readVirtualTag()
                : await new NdefAdapter().read(controller.signal);
            if (!payload.current) throw new Error('No simulated coin available.');
            await send();
        } catch (e) {
            if (mounted.current)
                setErrors({ nfc: e instanceof Error ? e.message : 'Read failed.' });
        } finally {
            active.current = null;
            locked.current = false;
            if (mounted.current) {
                setBusy(false);
                if (!pending.current) setStatus('Scanner stopped — tap Start Scanner when ready');
            }
        }
    }
    useEffect(() => {
        mounted.current = true;
        const hide = () => {
            if (document.hidden) {
                stop('Scanner paused — tap Start Scanner when ready');
                payload.current = null;
                setResult(null);
                void audio.current?.suspend().catch(() => {});
            }
        };
        const pagehide = () => {
            stop();
            payload.current = null;
            setResult(null);
        };
        document.addEventListener('visibilitychange', hide);
        window.addEventListener('pagehide', pagehide);
        if (requestId && station) void recover();
        return () => {
            mounted.current = false;
            active.current?.abort();
            active.current = null;
            payload.current = null;
            latch.current.clear();
            if (clearResult.current) clearTimeout(clearResult.current);
            void audio.current?.close().catch(() => {});
            audio.current = null;
            document.removeEventListener('visibilitychange', hide);
            window.removeEventListener('pagehide', pagehide);
        };
    }, []);
    const feedback = result ? gateFeedback(result) : null;
    return (
        <ManagementPage
            page="scanner"
            title="Gate scanner"
            description="Entry and Exit are detected automatically. Present one coin, then remove it."
        >
            <FormErrors errors={errors} />
            <Panel
                className="gate-scanner"
                title={station?.name ?? 'Gate not configured'}
                subtitle={station?.code ?? 'Ask an administrator to configure this gate account.'}
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
                        <div className="scan-prompt">
                            <ScanLine size={32} strokeWidth={1.4} aria-hidden="true" />
                            <h2 role="status" aria-live="polite">
                                {busy
                                    ? 'Processing'
                                    : starting
                                      ? 'Starting scanner'
                                      : uncertain
                                        ? 'Request needs recovery'
                                        : running
                                          ? 'Ready to Scan'
                                          : 'Scanner stopped'}
                            </h2>
                            <p>
                                {running
                                    ? 'Hold one coin near the NFC antenna, then remove it.'
                                    : 'Start once to allow NFC access. Keep this page visible and the phone unlocked.'}
                            </p>
                        </div>
                        {!running ? (
                            <Button
                                variant="primary"
                                className="scan-main-action"
                                disabled={
                                    !station ||
                                    starting ||
                                    busy ||
                                    uncertain ||
                                    (!simulator && !nfcAvailable())
                                }
                                onClick={() => void start()}
                            >
                                <ScanLine size={18} aria-hidden="true" />
                                Start Scanner
                            </Button>
                        ) : (
                            <Button onClick={() => stop()} disabled={busy}>
                                Stop Scanner
                            </Button>
                        )}
                        {simulator && running && (
                            <Button
                                disabled={busy || uncertain}
                                onClick={() => {
                                    const ref = readVirtualTag();
                                    if (ref) void onReference(ref);
                                    else setErrors({ nfc: 'No simulated coin is available.' });
                                }}
                            >
                                Present simulated coin
                            </Button>
                        )}
                        {!simulator && !nfcAvailable() && (
                            <p className="scanner-device-note">
                                NFC scanning is unavailable in Safari/iOS. Use Chrome on a
                                compatible NFC Android phone over HTTPS or the local USB connection.
                            </p>
                        )}
                        {soundNote && <p className="scanner-device-note">{soundNote}</p>}
                        <details className="compact-help">
                            <summary>Scan guidance</summary>
                            <p>
                                Repeat scans within {duplicateSeconds} seconds are ignored. Remove
                                each coin after its result. The browser cannot confirm physical
                                movement or who holds a coin.
                            </p>
                        </details>
                    </div>
                    <div className="scan-feedback-area">
                        <p className="scan-live-status" role="status" aria-live="polite">
                            {status}
                        </p>
                        {result && feedback ? (
                            <div
                                className={'scan-result scan-result-' + feedback.tone}
                                role="status"
                            >
                                <div className="scan-result-heading">
                                    <h2>
                                        {result.passage?.voided
                                            ? 'Record voided'
                                            : result.duplicate_of
                                              ? 'Already Scanned'
                                              : result.passage
                                                ? result.direction === 'entry'
                                                    ? 'Entry Successful'
                                                    : 'Exit Successful'
                                                : 'Scan Rejected'}
                                    </h2>
                                    {(result.passage || result.duplicate_of) && (
                                        <Badge tone={result.duplicate_of ? 'blue' : 'success'}>
                                            {result.direction === 'entry' ? 'Entry' : 'Exit'}
                                        </Badge>
                                    )}
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
                                        · {manilaTime(result.sequence_context.confirmed_at)}
                                    </p>
                                )}
                                <p className="scan-receipt-time">
                                    {manilaTime(
                                        result.passage?.confirmed_at ??
                                            result.verification.received_at,
                                    )}
                                </p>
                                {!result.passage && !result.duplicate_of && (
                                    <p>{reasonLabel(result.verification.reason)}</p>
                                )}
                            </div>
                        ) : (
                            !uncertain && (
                                <div className="scan-empty-result">
                                    <span className="scan-empty-mark" />
                                    <h3>Latest result</h3>
                                    <p>The verified person and detected action appear here.</p>
                                </div>
                            )
                        )}
                        {uncertain && !busy && (
                            <div className="scan-recovery">
                                <h2>Recover the previous request</h2>
                                <p>
                                    No new scans will be submitted until this request is resolved.
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
                                    {missing && (
                                        <Button
                                            disabled={busy}
                                            onClick={() => void readForRecovery()}
                                        >
                                            Read original coin for same request
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
                                    {r.confirmed_at || r.duplicate
                                        ? (r.direction === 'entry' ? 'Entry' : 'Exit') + ' · '
                                        : ''}
                                    {manilaTime(r.received_at)}
                                </span>
                                {r.sequence_notice && (
                                    <span className="recent-warning">
                                        {sequenceLabel(r.sequence_notice)}
                                    </span>
                                )}
                            </div>
                            <Badge
                                tone={r.duplicate ? 'blue' : r.confirmed_at ? 'success' : 'warning'}
                            >
                                {r.duplicate
                                    ? 'Already Scanned'
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
