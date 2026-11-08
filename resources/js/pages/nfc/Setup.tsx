import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { ManagementPage } from '../../components/management/Common';
import { Badge, Button, Panel } from '../../components/ui';
import { NdefAdapter, NfcError, nfcCapabilities } from '../../lib/nfc';
import { useWorkspace } from '../../lib/workspace';

export default function Setup() {
    const { user, hardwareLab } = useWorkspace();
    const [capabilities, setCapabilities] = useState(nfcCapabilities);
    const [permission, setPermission] = useState('Not checked');
    const [busy, setBusy] = useState(false);
    const [result, setResult] = useState('No hardware check has run in this tab.');
    const operation = useRef<AbortController | null>(null);
    const mounted = useRef(true);
    const running = useRef(false);
    useEffect(() => {
        mounted.current = true;
        let subscription: PermissionStatus | undefined;
        const reportPermission = () => {
            if (mounted.current && subscription) setPermission(subscription.state);
        };
        // Querying state never requests access; scan/write starts only from a button.
        void navigator.permissions
            ?.query({ name: 'nfc' as PermissionName })
            .then((value) => {
                if (!mounted.current) return;
                subscription = value;
                reportPermission();
                subscription.addEventListener('change', reportPermission);
            })
            .catch(() => {
                if (mounted.current) setPermission('State unavailable; use the access check');
            });
        const visibility = () => {
            setCapabilities(nfcCapabilities());
            if (document.visibilityState === 'hidden') operation.current?.abort();
        };
        const cancel = () => operation.current?.abort();
        document.addEventListener('visibilitychange', visibility);
        window.addEventListener('pagehide', cancel);
        return () => {
            mounted.current = false;
            operation.current?.abort();
            subscription?.removeEventListener('change', reportPermission);
            document.removeEventListener('visibilitychange', visibility);
            window.removeEventListener('pagehide', cancel);
        };
    }, []);
    async function check(mode: 'access' | 'read') {
        if (running.current) return;
        running.current = true;
        setBusy(true);
        const controller = new AbortController();
        operation.current = controller;
        setResult(
            mode === 'access'
                ? 'Approve Chrome’s NFC prompt if shown…'
                : 'Hold the intended NFCheck tag against the back of the phone…',
        );
        try {
            const adapter = new NdefAdapter();
            if (mode === 'access') await adapter.probe(controller.signal);
            else await adapter.read(controller.signal); // Discard the reference; never render, store or submit it.
            if (mounted.current && !controller.signal.aborted)
                setResult(
                    mode === 'access'
                        ? 'Chrome started NFC access successfully. The reader has stopped. Tag reading and writing still require separate physical tests.'
                        : 'One valid NFCheck-format text record was read. The reader has stopped. Registration and identity were not checked; no passage was recorded.',
                );
        } catch (error) {
            if (mounted.current)
                setResult(
                    error instanceof NfcError
                        ? error.message
                        : 'The NFC check could not finish. Check the phone settings and retry.',
                );
        } finally {
            controller.abort();
            running.current = false;
            if (mounted.current) {
                setBusy(false);
                setCapabilities(nfcCapabilities());
            }
        }
    }
    const ready =
        capabilities.secure && capabilities.topLevel && capabilities.api && capabilities.visible;
    return (
        <ManagementPage
            page="nfc"
            title="NFC setup"
            description="Check this browser before writing or scanning a credential."
        >
            <Panel
                className="nfc-setup-panel"
                title="This device and browser"
                subtitle="API detection alone does not establish working NFC hardware."
            >
                <dl className="record-facts">
                    <div>
                        <dt>Secure connection</dt>
                        <dd>
                            <Badge tone={capabilities.secure ? 'success' : 'warning'}>
                                {capabilities.secure ? 'Available' : 'Required'}
                            </Badge>
                        </dd>
                    </div>
                    <div>
                        <dt>Direct browser tab</dt>
                        <dd>{capabilities.topLevel ? 'Yes' : 'Open outside the embedded page'}</dd>
                    </div>
                    <div>
                        <dt>Web NFC API</dt>
                        <dd>
                            {capabilities.api
                                ? 'Detected · physical test required'
                                : 'Unavailable in this browser'}
                        </dd>
                    </div>
                    <div>
                        <dt>Page visible</dt>
                        <dd>
                            {capabilities.visible
                                ? 'Yes · keep phone unlocked'
                                : 'Return to this tab'}
                        </dd>
                    </div>
                    <div>
                        <dt>NFC permission</dt>
                        <dd>{permission}</dd>
                    </div>
                </dl>
                <div className="form-actions">
                    <Button
                        variant="primary"
                        disabled={!ready || busy}
                        onClick={() => void check('access')}
                    >
                        Check NFC access
                    </Button>
                    <Button disabled={!ready || busy} onClick={() => void check('read')}>
                        Read reference format
                    </Button>
                    {busy && (
                        <Button onClick={() => operation.current?.abort()}>Cancel NFC check</Button>
                    )}
                </div>
                <p className="credential-feedback" role="status" aria-live="polite">
                    {result}
                </p>
                <p className="field-hint">
                    These checks do not write tags, validate registration or create gate events.
                    Each operation stops after 30 seconds, on cancellation, or when this tab is
                    hidden. Retry with a new button tap.
                </p>
            </Panel>
            <Panel
                className="nfc-setup-panel"
                title="Prepare your NFC phone"
                subtitle="Use an updated Chrome browser on Android."
            >
                <ol className="nfc-instructions">
                    <li>Open the phone’s Settings, search for NFC and turn it on.</li>
                    <li>
                        For USB testing, enable USB debugging and approve this laptop. Open{' '}
                        <strong>http://localhost:8003/login</strong> in phone Chrome after
                        forwarding is connected. File Transfer alone is insufficient.
                    </li>
                    <li>
                        Keep the phone unlocked and this Chrome tab visible. Allow NFC when the
                        browser asks. If blocked, review this site’s Chrome permissions.
                    </li>
                    <li>
                        Use one NTAG215 tag at a time. Hold it against the back of the phone and
                        slowly adjust its position if needed. Remove it between write and read-back.
                    </li>
                    <li>
                        Keep test tags writable. NFCheck does not permanently lock them. Writing
                        replaces the intended tag’s NDEF contents; do not tap an unrelated tag.
                    </li>
                </ol>
                {!capabilities.secure && (
                    <p className="credential-notice">
                        Ordinary Wi-Fi HTTP is not a secure context for Web NFC. Use the prepared
                        USB connection or a separately configured trusted HTTPS site.
                    </p>
                )}
                <p className="field-hint">
                    {hardwareLab
                        ? 'This workspace uses synthetic identities and test passage records.'
                        : 'Use the separate hardware test workspace for synthetic acceptance tests.'}{' '}
                    A readable NFC reference can be copied. The guard must still compare identity
                    and confirm observed passage.
                </p>
                <Link
                    href={user?.role === 'admin' ? '/admin/credentials' : '/guard/scanner'}
                    className="button button-secondary"
                >
                    {user?.role === 'admin' ? 'Open credential registry' : 'Open gate scanner'}
                </Link>
            </Panel>
        </ManagementPage>
    );
}
