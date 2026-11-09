import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { CredentialBadge, SimulatorNotice } from '../../components/credentials/Shared';
import PersonPicker from '../../components/credentials/PersonPicker';
import NfcStatus from '../../components/credentials/NfcStatus';
import { Field, FormErrors, ManagementPage, Select } from '../../components/management/Common';
import { Button, Panel } from '../../components/ui';
import {
    credentialErrors,
    credentialPost,
    type CredentialRecord,
    type IssuanceReceipt,
    type PersonChoice,
    type DestinationCheck,
} from '../../lib/credentials';
import { manilaTime } from '../../lib/management';
import {
    NdefAdapter,
    ReadBackConfirmation,
    nfcAvailable,
    readVirtualTag,
    writeVirtualTag,
    sameDestination,
    type TagSnapshot,
    NfcError,
} from '../../lib/nfc';

export default function Issue({
    selectedPerson,
    mode: initialMode,
    replacesId,
    simulated,
}: {
    selectedPerson: PersonChoice | null;
    mode: string;
    replacesId: string | null;
    simulated: boolean;
}) {
    const [person, setPerson] = useState(selectedPerson);
    const [mode, setMode] = useState(
        initialMode === 'new' && selectedPerson?.current ? 'replace' : initialMode,
    );
    const [label, setLabel] = useState('');
    const [until, setUntil] = useState('');
    const [record, setRecord] = useState<CredentialRecord | null>(null);
    const [busy, setBusy] = useState(false);
    const [radio, setRadio] = useState(false);
    const [written, setWritten] = useState(false);
    const [receiptLost, setReceiptLost] = useState(false);
    const [message, setMessage] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const receipt = useRef<IssuanceReceipt | null>(null);
    const requestId = useRef(crypto.randomUUID());
    const operation = useRef<AbortController | null>(null);
    const mounted = useRef(true);
    const readBack = useRef(new ReadBackConfirmation());
    const destinationRef = useRef<TagSnapshot | null>(null);
    const [destination, setDestination] = useState<DestinationCheck | null>(null);
    const base = simulated ? '/simulator' : '/admin/credentials';
    const endpoint = (action: string, id: string) =>
        simulated ? `/simulator/credentials/${id}/${action}` : `/admin/credentials/${id}/${action}`;
    useEffect(() => {
        mounted.current = true;
        const discard = () => {
            destinationRef.current = null;
            setDestination(null);
            readBack.current.invalidate();
            operation.current?.abort();
            receipt.current = null;
            if (mounted.current) {
                setReceiptLost(true);
                setMessage(
                    'The one-time issuance receipt was cleared. Cancel this pending issuance in the registry and start again.',
                );
            }
        };
        const hidden = () => {
            if (document.visibilityState === 'hidden') discard();
        };
        window.addEventListener('pagehide', discard);
        document.addEventListener('visibilitychange', hidden);
        return () => {
            mounted.current = false;
            destinationRef.current = null;
            readBack.current.invalidate();
            operation.current?.abort();
            receipt.current = null;
            window.removeEventListener('pagehide', discard);
            document.removeEventListener('visibilitychange', hidden);
        };
    }, []);
    function virtualSnapshot(): TagSnapshot {
        const reference = readVirtualTag();
        return { kind: reference ? 'reference' : 'empty', reference, serial: null };
    }
    async function checkCoin() {
        if (busy || record) return;
        setBusy(true);
        setErrors({});
        setDestination(null);
        destinationRef.current = null;
        const controller = new AbortController();
        operation.current = controller;
        setRadio(!simulated);
        setMessage('Present the coin you intend to write. This check does not change it.');
        try {
            const snapshot = simulated
                ? virtualSnapshot()
                : await new NdefAdapter().inspect(controller.signal);
            setRadio(false);
            const result = await credentialPost<DestinationCheck>(
                `${base}/destination`,
                {
                    kind: snapshot.kind,
                    ...(snapshot.reference ? { payload: snapshot.reference } : {}),
                },
                controller.signal,
            );
            if (controller.signal.aborted || !mounted.current) return;
            setDestination(result);
            if (result.allowed) destinationRef.current = snapshot;
            setMessage(result.message);
        } catch (error) {
            if (mounted.current)
                setErrors(
                    error instanceof NfcError ? { nfc: error.message } : credentialErrors(error),
                );
        } finally {
            controller.abort();
            if (mounted.current) {
                setBusy(false);
                setRadio(false);
            }
        }
    }
    async function prepare() {
        readBack.current.invalidate();
        if (!destination?.allowed || !destinationRef.current) {
            setErrors({ coin: 'Check an empty or retired coin before preparing an issuance.' });
            return;
        }
        if (!person) {
            setErrors({ person_id: 'Choose an active person.' });
            return;
        }
        setBusy(true);
        setErrors({});
        setMessage('');
        const controller = new AbortController();
        operation.current = controller;
        try {
            const result = await credentialPost<IssuanceReceipt>(
                `${base}/issue`,
                {
                    person_id: person.id,
                    record_version: person.record_version,
                    label,
                    mode,
                    replaces_id:
                        mode === 'replace'
                            ? person.current?.id
                            : mode === 'lost'
                              ? replacesId
                              : null,
                    valid_until: until || null,
                    request_id: requestId.current,
                },
                controller.signal,
            );
            if (controller.signal.aborted || !mounted.current) return;
            receipt.current = result;
            setRecord(result.credential);
            setReceiptLost(false);
            setMessage(
                'Pending reference prepared. Write and read back the intended tag within 10 minutes.',
            );
        } catch (error) {
            if (mounted.current) setErrors(credentialErrors(error));
        } finally {
            if (mounted.current) setBusy(false);
        }
    }
    async function operate(action: 'write' | 'read-back' | 'activate') {
        const current = receipt.current;
        if (!current || !record) {
            setErrors({ issuance: 'The one-time receipt is unavailable. Cancel and start again.' });
            return;
        }
        if (action === 'activate' && (!readBack.current.verified || !record.read_back_at)) {
            setErrors({ nfc: 'Complete a fresh matching read-back before activation.' });
            return;
        }
        if (action !== 'activate') readBack.current.invalidate();
        setBusy(true);
        setErrors({});
        setMessage(
            action === 'write'
                ? 'Hold only the intended writable tag against the phone…'
                : action === 'read-back'
                  ? 'Remove the tag, then hold it against the phone again…'
                  : 'Activating the reviewed assignment…',
        );
        operation.current?.abort();
        const controller = new AbortController();
        operation.current = controller;
        const signal = AbortSignal.any([controller.signal, AbortSignal.timeout(30000)]);
        try {
            // Hardware calls start directly from this button gesture, before a network await.
            if (action === 'write') {
                setRadio(!simulated);
                const checked = destinationRef.current;
                if (!checked) throw new NfcError('wrong_destination');
                if (simulated) {
                    if (!sameDestination(checked, virtualSnapshot(), current.payload))
                        throw new NfcError('wrong_destination');
                    writeVirtualTag(current.payload, current.credential.label);
                } else await new NdefAdapter().write(current.payload, signal, checked);
                setRadio(false);
                if (signal.aborted || !mounted.current) return;
                setWritten(true);
                setMessage(
                    simulated
                        ? 'Reference written to the virtual tag in this tab’s memory.'
                        : 'Write finished. Remove the tag, then use the separate read-back step.',
                );
            } else {
                let updated: CredentialRecord;
                if (action === 'read-back') {
                    setRadio(!simulated);
                    updated = await readBack.current.confirm(
                        current.payload,
                        () =>
                            simulated ? (readVirtualTag() ?? '') : new NdefAdapter().read(signal),
                        async (scanned) => {
                            setRadio(false);
                            const result = await credentialPost<{ credential: CredentialRecord }>(
                                endpoint('read-back', record.id),
                                { payload: scanned, completion_nonce: current.completion_nonce },
                                signal,
                            );
                            return result.credential;
                        },
                        signal,
                    );
                } else {
                    const result = await credentialPost<{ credential: CredentialRecord }>(
                        endpoint('activate', record.id),
                        { payload: current.payload, completion_nonce: current.completion_nonce },
                        signal,
                    );
                    updated = result.credential;
                }
                if (signal.aborted || !mounted.current) return;
                setRecord(updated);
                if (action === 'activate') {
                    receipt.current = null;
                    destinationRef.current = null;
                    readBack.current.invalidate();
                    setMessage('Credential activated. Its permanent assignment is now selected.');
                } else
                    setMessage('Matching read-back saved. Review the assignment, then activate.');
            }
        } catch (error) {
            if (mounted.current)
                setErrors(
                    error instanceof Error && !(error && 'errors' in error)
                        ? { nfc: error.message }
                        : credentialErrors(error),
                );
        } finally {
            controller.abort();
            if (mounted.current) {
                setBusy(false);
                setRadio(false);
            }
        }
    }
    return (
        <ManagementPage
            page="credentials"
            title={simulated ? 'Issue a simulated credential' : 'Issue an NFC credential'}
            description="Check coin → choose person → write checked coin → read back → activate"
            back="/admin/credentials"
        >
            {simulated && <SimulatorNotice />}
            {!simulated && <NfcStatus />}
            {!simulated && (
                <p className="credential-notice">
                    Use the intended writable coin. Writing replaces whatever reference is on that
                    coin. If it already holds an NFCheck credential, retire that credential before
                    reusing the coin; writing alone does not revoke the old database record.
                </p>
            )}
            <FormErrors errors={errors} />
            {!record && (
                <Panel
                    className="credential-panel"
                    title="1. Check the destination coin"
                    subtitle="Read the coin first. Active or pending references cannot be overwritten through this flow."
                >
                    <div className="form-actions">
                        <Button
                            disabled={busy || (!simulated && !nfcAvailable())}
                            onClick={() => void checkCoin()}
                        >
                            {simulated ? 'Check virtual coin' : 'Check NFC coin'}
                        </Button>
                        {radio && (
                            <Button onClick={() => operation.current?.abort()}>
                                Cancel NFC check
                            </Button>
                        )}
                    </div>
                    {destination && (
                        <div className="credential-notice" role="status">
                            <strong>
                                {destination.allowed
                                    ? 'Coin ready for issuance'
                                    : 'Coin not ready for overwrite'}
                            </strong>
                            <p>{destination.message}</p>
                            {destination.credential && (
                                <p>
                                    {destination.credential.label} ·{' '}
                                    {destination.credential.person.name} ·{' '}
                                    {destination.credential.person.number} ·{' '}
                                    {destination.credential.status}
                                </p>
                            )}
                            {!destination.allowed && destination.credential && (
                                <Link href={`/admin/credentials/${destination.credential.id}`}>
                                    Open credential to retire it before reuse
                                </Link>
                            )}
                        </div>
                    )}
                    <p className="field-hint">
                        Remove the checked coin and keep it separate. Only that checked coin should
                        be presented when writing starts. Keep other coins away from the phone.
                    </p>
                </Panel>
            )}
            {!record ? (
                <Panel
                    className="credential-panel"
                    title="2. Choose the person and prepare"
                    subtitle="Fields marked * are required."
                >
                    <div className="management-form">
                        <PersonPicker
                            value={person}
                            onChange={(p) => {
                                setPerson(p);
                                setMode(p?.current ? 'replace' : 'new');
                            }}
                        />
                        <Field
                            name="label"
                            label="Credential label"
                            required
                            maxLength={64}
                            value={label}
                            onChange={(e) => setLabel(e.target.value)}
                            placeholder="Printed inventory label, e.g. TAG-001"
                        />
                        <Select
                            name="mode"
                            label="Issuance purpose"
                            value={mode}
                            onChange={(e) => setMode(e.target.value)}
                        >
                            <option value="new" disabled={Boolean(person?.current)}>
                                New credential
                            </option>
                            <option value="replace" disabled={!person?.current}>
                                Replace the current credential
                            </option>
                            {initialMode === 'lost' && (
                                <option value="lost">Replace a reported lost credential</option>
                            )}
                        </Select>
                        <Field
                            name="valid_until"
                            label="Credential valid until (optional, Asia/Manila)"
                            type="datetime-local"
                            value={until}
                            onChange={(e) => setUntil(e.target.value)}
                        />
                        <p className="field-hint">
                            The person must be active. An activated, selected credential enables
                            gate scanning. This reference is delivered once; leaving or hiding this
                            page clears the issuance receipt.
                        </p>
                        {mode === 'replace' && (
                            <p className="credential-notice">
                                The current credential stays active until the replacement activates.
                                For a lost tag, revoke it as lost from its detail page first.
                            </p>
                        )}
                        <div className="form-actions">
                            <Button
                                variant="primary"
                                disabled={busy || !person || !destination?.allowed}
                                onClick={() => void prepare()}
                            >
                                {busy ? 'Preparing…' : 'Prepare pending credential'}
                            </Button>
                            <Link href="/admin/credentials" className="button button-secondary">
                                Cancel
                            </Link>
                        </div>
                    </div>
                </Panel>
            ) : (
                <>
                    <Panel
                        className="credential-panel"
                        title={record.label}
                        subtitle={`Assigned to ${record.person?.name ?? 'person'} · ${record.person?.number ?? ''}`}
                    >
                        <CredentialBadge record={record} />
                        <dl className="record-facts">
                            <div>
                                <dt>Pending deadline</dt>
                                <dd>{manilaTime(record.pending_expires_at)}</dd>
                            </div>
                            <div>
                                <dt>Read-back</dt>
                                <dd>
                                    {record.status === 'pending' &&
                                    record.read_back_at &&
                                    !readBack.current.verified
                                        ? 'Earlier match recorded; repeat matching read-back in this tab.'
                                        : record.read_back_at
                                          ? `Matched ${manilaTime(record.read_back_at)}`
                                          : 'Not matched'}
                                </dd>
                            </div>
                            <div>
                                <dt>Validity end</dt>
                                <dd>
                                    {record.valid_until
                                        ? manilaTime(record.valid_until)
                                        : 'No scheduled expiry'}
                                </dd>
                            </div>
                        </dl>
                    </Panel>
                    {record.status === 'pending' && !receiptLost && (
                        <Panel
                            className="credential-panel"
                            title={
                                simulated
                                    ? '3. Write and read the virtual tag'
                                    : '3. Write and read the checked NFC coin'
                            }
                        >
                            {!simulated && !nfcAvailable() && (
                                <p className="credential-notice">
                                    Web NFC is unavailable in this browser. A supported NFC device
                                    and secure top-level browser are required. You can cancel this
                                    pending issuance from the registry.
                                </p>
                            )}
                            <div className="form-actions">
                                {radio && (
                                    <Button onClick={() => operation.current?.abort()}>
                                        Cancel NFC operation
                                    </Button>
                                )}
                                <Button
                                    disabled={busy || (!simulated && !nfcAvailable())}
                                    onClick={() => void operate('write')}
                                >
                                    {simulated ? 'Simulate write' : 'Write checked NFC coin'}
                                </Button>
                                <Button
                                    disabled={busy || !written}
                                    onClick={() => void operate('read-back')}
                                >
                                    {simulated
                                        ? 'Simulate matching read-back'
                                        : 'Read back NFC tag'}
                                </Button>
                                <Button
                                    variant="primary"
                                    disabled={
                                        busy || !record.read_back_at || !readBack.current.verified
                                    }
                                    onClick={() => void operate('activate')}
                                >
                                    {busy
                                        ? 'Working…'
                                        : simulated
                                          ? 'Activate simulated credential'
                                          : 'Activate credential'}
                                </Button>
                            </div>
                            <p className="field-hint">
                                Writing replaces the intended tag’s NDEF contents. Keep the tag
                                writable. Keep this page visible. Each NFC operation stops after 30
                                seconds. A read-back is software evidence of the reference; it does
                                not attest hardware identity or prevent copying a tag. A new write
                                or failed read-back requires another matching read-back before
                                activation.
                            </p>
                        </Panel>
                    )}
                    <div className="form-actions">
                        <Link
                            href={`/admin/credentials/${record.id}`}
                            className="button button-secondary"
                        >
                            View credential / cancel issuance
                        </Link>
                        {simulated && record.status === 'active' && (
                            <Link href="/simulator" className="button button-primary">
                                Check simulated tag
                            </Link>
                        )}
                    </div>
                </>
            )}
            <p className="credential-feedback" role="status" aria-live="polite">
                {message}
            </p>
        </ManagementPage>
    );
}
