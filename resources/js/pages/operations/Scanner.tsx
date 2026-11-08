import { Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import {
    ArrowDownLeft,
    ArrowUpRight,
    ScanLine,
    ShieldCheck,
    UserRound,
    Wifi,
    WifiOff,
} from 'lucide-react';
import { FormErrors, ManagementPage, Select } from '../../components/management/Common';
import Activity, { useActivity } from '../../components/operations/Activity';
import NfcStatus from '../../components/credentials/NfcStatus';
import { Badge, Button, Panel } from '../../components/ui';
import { manilaTime } from '../../lib/management';
import {
    NdefAdapter,
    acceptVirtualTagHandoff,
    nfcAvailable,
    readVirtualTag,
    unknownReference,
    virtualTagLabel,
} from '../../lib/nfc';
import {
    OperationError,
    operationBase,
    operationErrors,
    operationRequest,
    reasonLabel,
    reviewStatusLabel,
    sequenceLabel,
    type Direction,
    type Operation,
    type Station,
} from '../../lib/operations';
import { useWorkspace } from '../../lib/workspace';

export default function Scanner({
    stations,
    station,
    requestId,
    reviewSeconds,
    duplicateSeconds,
}: {
    stations: Station[];
    station: Station | null;
    requestId: string | null;
    reviewSeconds: number;
    duplicateSeconds: number;
}) {
    const { simulator } = useWorkspace();
    const [direction, setDirection] = useState<Direction | null>(null);
    const [operation, setOperation] = useState<Operation | null>(null);
    const [busy, setBusy] = useState(false);
    const [reading, setReading] = useState(false);
    const busyRef = useRef(false);
    const [online, setOnline] = useState(navigator.onLine);
    const [uncertain, setUncertain] = useState(Boolean(requestId));
    const [notFound, setNotFound] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [status, setStatus] = useState('');
    const [identityChecked, setIdentityChecked] = useState(false);
    const [identityMethod, setIdentityMethod] = useState('campus_document');
    const [photoFailed, setPhotoFailed] = useState(false);
    const [remaining, setRemaining] = useState(0);
    const payload = useRef<string | null>(null);
    const lastRequest = useRef<string | null>(requestId);
    const frozenDirection = useRef<Direction | null>(null);
    const active = useRef<AbortController | null>(null);
    const clock = useRef<{ expires: number; server: number; received: number } | null>(null);
    const mounted = useRef(true);
    const base = station ? operationBase(station.id, simulator) : null;
    const activity = useActivity(station ? `/guard/gates/${station.id}/activity` : null);
    const pending = operation?.review.status === 'pending';
    const blocked = busy || pending || uncertain;
    function working(value: boolean) {
        busyRef.current = value;
        setBusy(value);
    }
    function locate(id: string | null) {
        lastRequest.current = id;
        if (station)
            router.replace({
                url: `/guard/gates/${station.id}/scanner${id ? `?request_id=${id}` : ''}`,
                preserveState: true,
                preserveScroll: true,
            });
    }
    function accept(result: Operation, keepReview = false) {
        if (result.source === 'simulated') acceptVirtualTagHandoff();
        setOperation(result);
        setDirection(result.direction);
        frozenDirection.current = result.direction;
        setUncertain(false);
        setNotFound(false);
        payload.current = null;
        if (!keepReview || !result.identity) {
            setIdentityChecked(false);
            setPhotoFailed(false);
            setIdentityMethod(result.identity?.photo_url ? 'photo' : 'campus_document');
        }
        clock.current = result.review.expires_at
            ? {
                  expires: Date.parse(result.review.expires_at),
                  server: Date.parse(result.server_time),
                  received: performance.now(),
              }
            : null;
        setRemaining(
            clock.current
                ? Math.max(0, Math.ceil((clock.current.expires - clock.current.server) / 1000))
                : 0,
        );
        setStatus(
            result.passage?.voided
                ? 'The original passage is retained but was voided by an administrator.'
                : result.passage
                  ? `${result.source === 'simulated' ? 'Simulated passage' : 'Observed passage'} recorded: ${result.passage.direction}.`
                  : result.duplicate_of
                    ? 'Duplicate suppressed. The earlier passage is linked; no second passage was recorded.'
                    : result.review.status === 'pending'
                      ? 'Credential authorized. Review the person before confirming passage.'
                      : reasonLabel(result.review.reason ?? result.verification.reason),
        );
        void activity.refresh();
    }
    async function send(id: string, selected: Direction, value: string) {
        if (!base) return;
        const controller = new AbortController();
        active.current = controller;
        try {
            const result = await operationRequest<Operation>(
                `${base}/scan`,
                { request_id: id, direction: selected, payload: value },
                controller.signal,
            );
            if (mounted.current && !controller.signal.aborted) accept(result);
        } catch (error) {
            if (mounted.current) {
                setErrors(operationErrors(error));
                setUncertain(true);
            }
        }
    }
    async function scan(probe?: 'unknown' | 'malformed') {
        if (!station || !direction || busyRef.current || pending || uncertain) return;
        working(true);
        setErrors({});
        setOperation(null);
        setIdentityChecked(false);
        setStatus(
            simulator ? 'Reading simulated input…' : 'Hold the intended tag near the NFC reader…',
        );
        active.current?.abort();
        const controller = new AbortController();
        active.current = controller;
        let submitted = false;
        try {
            setReading(!simulator);
            const value = simulator
                ? probe === 'unknown'
                    ? unknownReference()
                    : probe === 'malformed'
                      ? 'invalid-NDEF-record'
                      : readVirtualTag()
                : await new NdefAdapter().read(
                      AbortSignal.any([controller.signal, AbortSignal.timeout(30000)]),
                  );
            setReading(false);
            controller.abort();
            if (!value)
                throw new Error(
                    'No virtual tag is available. Ask the lab administrator to issue and hand off one in this tab.',
                );
            if (!mounted.current) return;
            payload.current = value;
            frozenDirection.current = direction;
            const id = crypto.randomUUID();
            locate(id);
            submitted = true;
            setStatus('Checking the reference with the server…');
            await send(id, direction, value);
        } catch (error) {
            if (mounted.current) {
                setErrors({
                    nfc:
                        error instanceof Error
                            ? error.message
                            : 'The tag could not be read. Tap again to retry.',
                });
                if (!submitted) {
                    payload.current = null;
                    setStatus('No server verification was submitted.');
                }
            }
        } finally {
            controller.abort();
            if (mounted.current) {
                working(false);
                setReading(false);
            }
        }
    }
    async function recover(poll = false) {
        if (
            !base ||
            !lastRequest.current ||
            busyRef.current ||
            !navigator.onLine ||
            document.visibilityState === 'hidden'
        )
            return;
        working(true);
        if (!poll) setErrors({});
        const controller = new AbortController();
        active.current = controller;
        try {
            const result = await operationRequest<Operation>(
                `${base}/requests/${lastRequest.current}`,
                undefined,
                controller.signal,
            );
            if (mounted.current && !controller.signal.aborted) accept(result, poll);
        } catch (error) {
            if (mounted.current && !controller.signal.aborted) {
                setErrors(operationErrors(error));
                setUncertain(true);
                setNotFound(error instanceof OperationError && error.status === 404);
                setOperation((old) => (old ? { ...old, identity: null } : null));
            }
        } finally {
            if (mounted.current) working(false);
        }
    }
    async function review(action: 'confirm' | 'cancel' | 'identity_mismatch') {
        if (!base || !operation || busyRef.current || !online) return;
        working(true);
        setErrors({});
        const controller = new AbortController();
        active.current = controller;
        try {
            const result = await operationRequest<Operation>(
                `${base}/attempts/${operation.id}/review`,
                {
                    action,
                    ...(action === 'confirm'
                        ? { identity_checked: identityChecked, identity_method: identityMethod }
                        : {}),
                },
                controller.signal,
            );
            if (mounted.current && !controller.signal.aborted) accept(result);
        } catch (error) {
            if (mounted.current) {
                setErrors(operationErrors(error));
                if (!(error instanceof OperationError && error.status === 422)) {
                    setUncertain(true);
                    setOperation((old) => (old ? { ...old, identity: null } : null));
                }
            }
        } finally {
            if (mounted.current) working(false);
        }
    }
    function clear() {
        payload.current = null;
        locate(null);
        setOperation(null);
        setUncertain(false);
        setNotFound(false);
        setErrors({});
        setStatus('Ready for an explicit new scan.');
        setIdentityChecked(false);
    }
    useEffect(() => {
        mounted.current = true;
        const interrupt = () => {
            active.current?.abort();
            payload.current = null;
            setOperation((old) => (old ? { ...old, identity: null } : null));
            setIdentityChecked(false);
            if (lastRequest.current) setUncertain(true);
        };
        const visibility = () => {
            if (document.visibilityState === 'hidden') interrupt();
        };
        const connection = () => {
            setOnline(navigator.onLine);
            if (!navigator.onLine) interrupt();
        };
        window.addEventListener('pagehide', interrupt);
        document.addEventListener('visibilitychange', visibility);
        window.addEventListener('online', connection);
        window.addEventListener('offline', connection);
        if (requestId && station) void recover();
        return () => {
            mounted.current = false;
            active.current?.abort();
            payload.current = null;
            window.removeEventListener('pagehide', interrupt);
            document.removeEventListener('visibilitychange', visibility);
            window.removeEventListener('online', connection);
            window.removeEventListener('offline', connection);
        };
        // This page remounts when the station changes; operation IDs are metadata, never a credential.
    }, []);
    useEffect(() => {
        const timer = setInterval(() => {
            const c = clock.current;
            if (c)
                setRemaining(
                    Math.max(
                        0,
                        Math.ceil((c.expires - c.server - (performance.now() - c.received)) / 1000),
                    ),
                );
        }, 250);
        return () => clearInterval(timer);
    }, []);
    useEffect(() => {
        if (!pending) return;
        const timer = setInterval(() => void recover(true), 5000);
        return () => clearInterval(timer);
    }, [operation?.id, pending]);
    const identity = !uncertain && remaining > 0 ? operation?.identity : null;
    const ConnectionIcon = online ? Wifi : WifiOff;
    return (
        <ManagementPage
            page="scanner"
            title="Gate scanner"
            description="Read a credential. Review identity. Confirm observed passage."
            action={
                <Badge tone={online ? 'neutral' : 'warning'}>
                    <ConnectionIcon size={14} aria-hidden="true" />
                    {online ? 'Browser online' : 'Browser offline'}
                </Badge>
            }
        >
            {simulator && (
                <p className="credential-notice">
                    <strong>Isolated guard simulator</strong>
                    <br />
                    Every scan and passage here is simulated. Virtual tag:{' '}
                    {virtualTagLabel() ?? 'not available in this tab'}.
                </p>
            )}
            {!online && (
                <p className="form-errors" role="alert">
                    Offline. A server response is required before any passage can be confirmed.
                    Recover the current request after reconnecting.
                </p>
            )}
            <FormErrors errors={errors} />
            <div className="scanner-station">
                <div className="station-select">
                    <label htmlFor="gate-station">Assigned gate station</label>
                    <select
                        id="gate-station"
                        value={station?.id ?? ''}
                        disabled={blocked || !stations.length}
                        onChange={(e) => {
                            if (e.target.value)
                                router.get(`/guard/gates/${e.target.value}/scanner`);
                        }}
                    >
                        <option value="">
                            {stations.length
                                ? 'Choose an assigned station'
                                : 'No current gate assignments'}
                        </option>
                        {stations.map((gate) => (
                            <option key={gate.id} value={gate.id}>
                                {gate.code} · {gate.name}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="station-session">
                    <ShieldCheck size={20} />
                    <div>
                        <strong>
                            {station
                                ? `${station.code} · ${station.name}`
                                : 'Station selection required'}
                        </strong>
                        <span>
                            {reviewSeconds}-second review · {duplicateSeconds}-second duplicate
                            window · server checks every action
                        </span>
                    </div>
                </div>
            </div>
            <div className="scanner-grid">
                <Panel className="scan-panel">
                    <div className="scan-panel-heading">
                        <h2>Passage direction</h2>
                        <span className="small-muted">Choose before scanning</span>
                    </div>
                    <div className="direction-choices" role="group" aria-label="Passage direction">
                        {(['entry', 'exit'] as const).map((value) => {
                            const Icon = value === 'entry' ? ArrowDownLeft : ArrowUpRight;
                            return (
                                <Button
                                    key={value}
                                    className={
                                        direction === value
                                            ? 'operation-direction selected'
                                            : 'operation-direction'
                                    }
                                    aria-pressed={direction === value}
                                    disabled={blocked}
                                    onClick={() => setDirection(value)}
                                >
                                    <Icon size={22} />
                                    {value === 'entry' ? 'Entry' : 'Exit'}
                                </Button>
                            );
                        })}
                    </div>
                    <div className="scan-target">
                        <div className="scan-frame">
                            <ScanLine size={60} strokeWidth={1.2} />
                        </div>
                        <h3>
                            {direction
                                ? `Ready for ${direction === 'entry' ? 'Entry' : 'Exit'}`
                                : 'Select Entry or Exit'}
                        </h3>
                        <p>
                            A credential match opens a separate identity review. It does not record
                            passage.
                        </p>
                        <Button
                            variant="primary"
                            className="scan-button"
                            disabled={
                                !station ||
                                !direction ||
                                blocked ||
                                !online ||
                                (!simulator && !nfcAvailable())
                            }
                            onClick={() => void scan()}
                        >
                            {busy ? 'Working…' : simulator ? 'Scan virtual tag' : 'Start NFC scan'}
                        </Button>
                        {reading && (
                            <Button onClick={() => active.current?.abort()}>Cancel NFC scan</Button>
                        )}
                        {!simulator && <NfcStatus />}
                    </div>
                    {simulator && (
                        <div className="operation-probes">
                            <Button
                                disabled={!station || !direction || blocked || !online}
                                onClick={() => void scan('unknown')}
                            >
                                Simulate unknown tag
                            </Button>
                            <Button
                                disabled={!station || !direction || blocked || !online}
                                onClick={() => void scan('malformed')}
                            >
                                Simulate malformed tag
                            </Button>
                        </div>
                    )}
                </Panel>
                <Panel
                    title="Identity review"
                    subtitle="Confirm only after comparing the person and observing the selected passage."
                    className="identity-operation-panel"
                    action={
                        <Badge
                            tone={
                                operation?.passage?.voided
                                    ? 'warning'
                                    : identity
                                      ? 'blue'
                                      : operation?.passage
                                        ? 'success'
                                        : 'neutral'
                            }
                        >
                            {identity
                                ? `${remaining}s remaining`
                                : operation?.passage?.voided
                                  ? 'Passage voided'
                                  : operation?.passage
                                    ? 'Passage recorded'
                                    : uncertain
                                      ? 'Recover status'
                                      : operation
                                        ? reviewStatusLabel(operation.review.status)
                                        : 'Awaiting credential'}
                        </Badge>
                    }
                >
                    {identity ? (
                        <div className="operation-identity">
                            <div className="operation-portrait">
                                {identity.photo_url && !photoFailed ? (
                                    <img
                                        src={identity.photo_url}
                                        alt={`${identity.name}, verification photograph`}
                                        onError={() => {
                                            setPhotoFailed(true);
                                            setIdentityMethod('campus_document');
                                            setIdentityChecked(false);
                                        }}
                                    />
                                ) : (
                                    <UserRound
                                        size={70}
                                        aria-label="No verification photograph available"
                                    />
                                )}
                            </div>
                            <h3>{identity.name}</h3>
                            <p>
                                {identity.number} · {identity.kind}
                            </p>
                            <Badge tone="blue">Credential authorized · passage not recorded</Badge>
                            {identity.sequence_notice && (
                                <p className="credential-notice">
                                    {sequenceLabel(identity.sequence_notice)}
                                </p>
                            )}
                            <Select
                                name="identity_method"
                                label="Identity review method"
                                value={identityMethod}
                                onChange={(e) => {
                                    setIdentityMethod(e.target.value);
                                    setIdentityChecked(false);
                                }}
                            >
                                <option value="photo" disabled={!identity.photo_url || photoFailed}>
                                    Compare with the photograph
                                </option>
                                <option value="campus_document">
                                    Review a trusted campus identity document
                                </option>
                            </Select>
                            <label className="operation-confirm-check">
                                <input
                                    type="checkbox"
                                    checked={identityChecked}
                                    onChange={(e) => setIdentityChecked(e.target.checked)}
                                />
                                <span>
                                    I checked the person’s identity using the selected method and
                                    observed this {operation?.direction} passage.
                                </span>
                            </label>
                        </div>
                    ) : (
                        <div className="identity-empty">
                            <UserRound size={50} />
                            <h3>
                                {operation?.passage?.voided
                                    ? 'Original passage voided by an administrator'
                                    : operation?.passage
                                      ? `${operation.passage.direction === 'entry' ? 'Entry' : 'Exit'} passage recorded`
                                      : operation?.duplicate_of
                                        ? 'Duplicate passage suppressed'
                                        : uncertain
                                          ? 'Server outcome needs recovery'
                                          : operation?.review.status === 'expired'
                                            ? 'Review expired'
                                            : operation?.verification.outcome === 'denied'
                                              ? 'Credential denied'
                                              : 'No identity to review'}
                            </h3>
                            <p>
                                {operation?.passage
                                    ? manilaTime(operation.passage.confirmed_at)
                                    : operation?.review.reason
                                      ? reasonLabel(operation.review.reason)
                                      : operation
                                        ? reasonLabel(operation.verification.reason)
                                        : 'Choose your assigned station and direction, then scan a credential.'}
                            </p>
                            {operation?.duplicate_of && (
                                <p>
                                    Earlier {operation.duplicate_of.direction}:{' '}
                                    {manilaTime(operation.duplicate_of.confirmed_at)}. No second
                                    passage was created.
                                </p>
                            )}
                            {pending && remaining <= 0 && (
                                <p>Recover server status or cancel before a new scan.</p>
                            )}
                        </div>
                    )}
                    {pending && !uncertain && (
                        <div className="identity-actions">
                            <Button
                                variant="primary"
                                disabled={
                                    busy ||
                                    !online ||
                                    !identity ||
                                    !identityChecked ||
                                    remaining <= 0
                                }
                                onClick={() => void review('confirm')}
                            >
                                Confirm observed{' '}
                                {operation.direction === 'entry' ? 'Entry' : 'Exit'}
                            </Button>
                            <div>
                                <Button
                                    disabled={busy || !online}
                                    onClick={() => void review('identity_mismatch')}
                                >
                                    Identity mismatch
                                </Button>
                                <Button
                                    disabled={busy || !online}
                                    onClick={() => void review('cancel')}
                                >
                                    Cancel review
                                </Button>
                            </div>
                        </div>
                    )}
                    {lastRequest.current && (
                        <div className="operation-recovery">
                            <Button disabled={busy || !online} onClick={() => void recover()}>
                                Recover request status
                            </Button>
                            {notFound && payload.current && frozenDirection.current && (
                                <Button
                                    disabled={busy || !online}
                                    onClick={() => {
                                        working(true);
                                        void send(
                                            lastRequest.current!,
                                            frozenDirection.current!,
                                            payload.current!,
                                        ).finally(() => {
                                            if (mounted.current) working(false);
                                        });
                                    }}
                                >
                                    Retry the same request
                                </Button>
                            )}
                            {(!pending && !uncertain) || notFound ? (
                                <Button disabled={busy} onClick={clear}>
                                    {notFound ? 'Discard local request' : 'Start another check'}
                                </Button>
                            ) : null}
                        </div>
                    )}
                </Panel>
            </div>
            <p role="status" aria-live="polite" className="credential-feedback">
                {status}
            </p>
            {station ? (
                <Activity
                    station
                    feed={activity.feed}
                    error={activity.error}
                    refresh={() => void activity.refresh()}
                />
            ) : (
                <p className="panel-message">
                    Select an assigned station to load permitted recent activity.
                </p>
            )}
            <p className="availability-note">
                Recorded direction never changes automatically. Activity reflects guard observations
                and missing scans; it does not prove continued campus presence.{' '}
                <Link href="/guard/activity">View assigned-gate activity</Link>
            </p>
        </ManagementPage>
    );
}
