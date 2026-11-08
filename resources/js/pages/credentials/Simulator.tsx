import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { SimulatorNotice } from '../../components/credentials/Shared';
import { FormErrors, ManagementPage } from '../../components/management/Common';
import { Badge, Button, Panel } from '../../components/ui';
import { credentialErrors, credentialPost, type Inspection } from '../../lib/credentials';
import {
    clearVirtualTag,
    holdVirtualTagForHandoff,
    readVirtualTag,
    unknownReference,
    virtualTagLabel,
} from '../../lib/nfc';

export default function Simulator() {
    const [tag, setTag] = useState(virtualTagLabel());
    const [result, setResult] = useState<Inspection | null>(null);
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    async function check(kind: 'tag' | 'unknown' | 'malformed') {
        const payload =
            kind === 'tag'
                ? readVirtualTag()
                : kind === 'unknown'
                  ? unknownReference()
                  : 'invalid-NDEF-reference';
        if (!payload) {
            setErrors({ tag: 'Issue and simulate writing a tag in this tab first.' });
            return;
        }
        setBusy(true);
        setErrors({});
        setResult(null);
        try {
            setResult(await credentialPost<Inspection>('/simulator/check', { payload }));
        } catch (error) {
            setErrors(credentialErrors(error));
        } finally {
            setBusy(false);
        }
    }
    return (
        <ManagementPage
            page="credentials"
            title="NFC simulator"
            description="Exercise the credential lifecycle using synthetic lab records."
            back="/admin/credentials"
            action={
                <Link href="/simulator/issue" className="button button-primary">
                    Issue simulated credential
                </Link>
            }
        >
            <SimulatorNotice />
            {tag && (
                <div className="credential-notice">
                    <strong>Try the guard workflow</strong>
                    <p>
                        Hand this simulated tag to the separate lab guard account. This signs out of
                        administration and retains only the virtual tag in this tab’s memory for up
                        to two minutes. Reloading discards it.
                    </p>
                    <Button
                        onClick={() => {
                            holdVirtualTagForHandoff();
                            router.post('/logout');
                        }}
                    >
                        Hand virtual tag to a lab guard
                    </Button>
                </div>
            )}
            <FormErrors errors={errors} />
            <div className="record-detail-grid">
                <Panel
                    className="credential-panel"
                    title="Virtual tag"
                    subtitle="Stored only in this tab’s memory; reload or closing the tab clears it."
                >
                    <p className="credential-tag-label">{tag ?? 'No virtual tag written yet'}</p>
                    <p className="panel-message">
                        Issue a simulated credential, simulate its write, complete the matching
                        read-back and activate it. Then return here to inspect it. Repeated checks
                        do not record passage.
                    </p>
                    <div className="form-actions">
                        <Button
                            variant="primary"
                            disabled={busy || !tag}
                            onClick={() => void check('tag')}
                        >
                            {busy ? 'Checking…' : 'Check virtual tag'}
                        </Button>
                        <Button
                            disabled={!tag || busy}
                            onClick={() => {
                                clearVirtualTag();
                                setTag(null);
                                setResult(null);
                            }}
                        >
                            Clear virtual tag
                        </Button>
                    </div>
                </Panel>
                <Panel className="credential-panel" title="Validation probes">
                    <p className="panel-message">
                        These probes reach the same server verifier. An unknown reference is
                        randomly generated for one request; it is never assigned or saved as a
                        credential.
                    </p>
                    <div className="form-actions">
                        <Button disabled={busy} onClick={() => void check('unknown')}>
                            Check unknown reference
                        </Button>
                        <Button disabled={busy} onClick={() => void check('malformed')}>
                            Check malformed record
                        </Button>
                    </div>
                </Panel>
            </div>
            {result && (
                <Panel className="credential-panel" title="Verification result">
                    <div className="credential-result" role="status">
                        <Badge tone={result.eligible ? 'success' : 'warning'}>
                            {result.eligible ? 'Eligible for review' : 'Rejected'}
                        </Badge>
                        <h2>{result.reason.replaceAll('_', ' ')}</h2>
                        {result.person && (
                            <p>
                                {result.person.name} · {result.person.number}
                            </p>
                        )}
                        {result.credential && (
                            <Link href={`/admin/credentials/${result.credential.id}`}>
                                {result.credential.label} · View / revoke credential
                            </Link>
                        )}
                        <p>No gate decision, identity confirmation or passage was recorded.</p>
                    </div>
                </Panel>
            )}
        </ManagementPage>
    );
}
