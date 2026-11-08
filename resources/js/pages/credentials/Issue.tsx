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
} from '../../lib/credentials';
import { manilaTime } from '../../lib/management';
import { NdefAdapter, nfcAvailable, readVirtualTag, writeVirtualTag } from '../../lib/nfc';

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
    const [mode, setMode] = useState(initialMode);
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
    const base = simulated ? '/simulator' : '/admin/credentials';
    const endpoint = (action: string, id: string) =>
        simulated ? `/simulator/credentials/${id}/${action}` : `/admin/credentials/${id}/${action}`;
    useEffect(() => {
        mounted.current = true;
        const discard = () => {
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
            operation.current?.abort();
            receipt.current = null;
            window.removeEventListener('pagehide', discard);
            document.removeEventListener('visibilitychange', hidden);
        };
    }, []);
    async function prepare() {
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
                if (simulated) writeVirtualTag(current.payload, current.credential.label);
                else await new NdefAdapter().write(current.payload, signal);
                setRadio(false);
                if (signal.aborted || !mounted.current) return;
                setWritten(true);
                setMessage(
                    simulated
                        ? 'Reference written to the virtual tag in this tab’s memory.'
                        : 'Write finished. Remove the tag, then use the separate read-back step.',
                );
            } else {
                let payload = current.payload;
                if (action === 'read-back') {
                    setRadio(!simulated);
                    payload = simulated
                        ? (readVirtualTag() ?? '')
                        : await new NdefAdapter().read(signal);
                    setRadio(false);
                    if (!payload) throw new Error('Write the virtual tag before reading it back.');
                }
                const result = await credentialPost<{ credential: CredentialRecord }>(
                    endpoint(action, record.id),
                    { payload, completion_nonce: current.completion_nonce },
                    signal,
                );
                if (signal.aborted || !mounted.current) return;
                setRecord(result.credential);
                if (action === 'activate') {
                    receipt.current = null;
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
            description="Prepare → write → matching read-back → activate"
            back="/admin/credentials"
        >
            {simulated && <SimulatorNotice />}
            {!simulated && <NfcStatus />}
            <FormErrors errors={errors} />
            {!record ? (
                <Panel
                    className="credential-panel"
                    title="1. Prepare the assignment"
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
                            <option value="new">New credential</option>
                            <option value="replace">Replace the current credential</option>
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
                            The person must be active. Campus access still needs a separate grant.
                            This reference is delivered once; leaving or hiding this page clears the
                            issuance receipt.
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
                                disabled={busy || !person}
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
                                    {record.read_back_at
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
                                    ? '2. Write and read the virtual tag'
                                    : '2. Write and read the NFC tag'
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
                                    {simulated ? 'Simulate write' : 'Write NFC tag'}
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
                                    disabled={busy || !record.read_back_at}
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
                                not attest hardware identity or prevent copying a tag.
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
