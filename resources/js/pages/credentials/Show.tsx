import { Link } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { CredentialBadge } from '../../components/credentials/Shared';
import { FormErrors, ManagementPage, Select } from '../../components/management/Common';
import { Button, Panel } from '../../components/ui';
import { credentialErrors, credentialPost, type CredentialRecord } from '../../lib/credentials';
import { manilaTime } from '../../lib/management';

export default function Show({ record: initial }: { record: CredentialRecord }) {
    const [record, setRecord] = useState(initial);
    const [reason, setReason] = useState('lost');
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [message, setMessage] = useState('');
    const dialog = useRef<HTMLDialogElement>(null);
    const open = record.status === 'active' || record.status === 'pending';
    const prefix = record.source === 'simulated' ? '/simulator/issue' : '/admin/credentials/create';
    const person = record.person;
    const replaceMode =
        record.status === 'revoked' && record.close_reason === 'lost' ? 'lost' : 'replace';
    const replacement = `${prefix}?person_id=${person?.id}&mode=${replaceMode}&replaces_id=${record.id}`;
    async function close() {
        setBusy(true);
        setErrors({});
        try {
            const result = await credentialPost<{ credential: CredentialRecord }>(
                `/admin/credentials/${record.id}/${record.status === 'pending' ? 'cancel' : 'revoke'}`,
                { reason },
            );
            setRecord(result.credential);
            setMessage(
                result.credential.close_reason === 'lost' && result.credential.status === 'revoked'
                    ? 'Lost coin revoked. It can no longer record Entry or Exit. Issue a replacement after checking the person’s identity.'
                    : 'Credential closed. Its assignment and audit history are retained.',
            );
            dialog.current?.close();
        } catch (error) {
            setErrors(credentialErrors(error));
        } finally {
            setBusy(false);
        }
    }
    return (
        <ManagementPage
            page="credentials"
            title={record.label}
            description="Credential lifecycle and permanent assignment"
            back="/admin/credentials"
        >
            <Panel className="credential-panel" title="Credential details">
                <CredentialBadge record={record} />
                <dl className="record-facts">
                    <div>
                        <dt>Assigned person</dt>
                        <dd>
                            {person ? (
                                <Link
                                    href={`/admin/${person.kind === 'student' ? 'students' : 'employees'}/${person.id}`}
                                >
                                    {person.name} · {person.number}
                                </Link>
                            ) : (
                                'Unavailable'
                            )}
                        </dd>
                    </div>
                    <div>
                        <dt>Selected for verification</dt>
                        <dd>{record.selected ? 'Yes' : 'No'}</dd>
                    </div>
                    <div>
                        <dt>Created</dt>
                        <dd>{manilaTime(record.created_at)}</dd>
                    </div>
                    <div>
                        <dt>Pending deadline</dt>
                        <dd>{manilaTime(record.pending_expires_at)}</dd>
                    </div>
                    <div>
                        <dt>Matching read-back</dt>
                        <dd>
                            {record.read_back_at ? manilaTime(record.read_back_at) : 'Not recorded'}
                        </dd>
                    </div>
                    <div>
                        <dt>Activated</dt>
                        <dd>
                            {record.activated_at
                                ? manilaTime(record.activated_at)
                                : 'Not activated'}
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
                    <div>
                        <dt>Closed</dt>
                        <dd>
                            {record.closed_at
                                ? `${manilaTime(record.closed_at)} · ${record.close_reason?.replaceAll('_', ' ')}`
                                : 'Not closed'}
                        </dd>
                    </div>
                    {record.replaces_id && (
                        <div>
                            <dt>Replaces</dt>
                            <dd>
                                <Link href={`/admin/credentials/${record.replaces_id}`}>
                                    View earlier credential
                                </Link>
                            </dd>
                        </div>
                    )}
                </dl>
                <p className="field-hint">
                    Raw references and completion receipts are not recoverable. A lost pending
                    receipt must be cancelled and issued again. Closed credentials remain closed.
                </p>
                <div className="form-actions">
                    {open && (
                        <Button
                            className="button-danger"
                            onClick={() => dialog.current?.showModal()}
                        >
                            {record.status === 'pending'
                                ? 'Cancel pending issuance'
                                : 'Report lost or revoke coin'}
                        </Button>
                    )}
                    {person &&
                        !person.archived &&
                        person.status === 'active' &&
                        ((record.selected && record.status === 'active') ||
                            replaceMode === 'lost') && (
                            <Link href={replacement} className="button button-primary">
                                Issue replacement
                            </Link>
                        )}
                </div>
            </Panel>
            <p role="status" className="credential-feedback">
                {message}
            </p>
            <dialog ref={dialog} className="management-dialog" aria-label={`Close ${record.label}`}>
                <h2>
                    {record.status === 'pending'
                        ? 'Cancel pending issuance?'
                        : 'Revoke credential?'}
                </h2>
                <p>
                    {record.label} for {person?.name}. This credential will stop being eligible; its
                    history is retained. For a lost coin, revocation takes effect when this request
                    succeeds. You do not need the missing coin. A replacement stays unusable until
                    its matching read-back and activation succeed.
                </p>
                <FormErrors errors={errors} />
                {record.status === 'active' && (
                    <Select
                        name="reason"
                        label="Revocation reason"
                        value={reason}
                        onChange={(e) => setReason(e.target.value)}
                    >
                        <option value="lost">Lost tag — revoke immediately</option>
                        <option value="damaged">Damaged</option>
                        <option value="withdrawn">Withdrawn</option>
                        <option value="security">Security concern</option>
                    </Select>
                )}
                <div className="form-actions">
                    <Button autoFocus disabled={busy} onClick={() => dialog.current?.close()}>
                        Keep credential
                    </Button>
                    <Button variant="primary" disabled={busy} onClick={() => void close()}>
                        {busy
                            ? 'Saving…'
                            : record.status === 'pending'
                              ? 'Confirm cancellation'
                              : 'Confirm revocation'}
                    </Button>
                </div>
            </dialog>
        </ManagementPage>
    );
}
