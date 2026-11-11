import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { ManagementPage } from '../../components/management/Common';
import { Badge, Button, Panel } from '../../components/ui';
import { NdefAdapter, NfcError, nfcCapabilities } from '../../lib/nfc';
import { useWorkspace } from '../../lib/workspace';

export default function Setup() {
    const { user } = useWorkspace();
    const [capabilities, setCapabilities] = useState(nfcCapabilities);
    const [permission, setPermission] = useState('Not checked');
    const [busy, setBusy] = useState(false);
    const [result, setResult] = useState('Choose a check below.');
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
                if (mounted.current) setPermission('Not checked');
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
                        ? 'NFC access is available. The reader has stopped.'
                        : 'Coin format recognised. The reader has stopped. Use the gate scanner to check the credential and record a passage. No passage was recorded here.',
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
                title="Device connection"
                subtitle="NFC scanning requires Chrome on an NFC-enabled Android phone."
            >
                <dl className="nfc-status-grid">
                    <div>
                        <dt>Browser support</dt>
                        <dd>
                            <Badge tone={capabilities.api ? 'success' : 'warning'}>
                                {capabilities.api ? 'Available' : 'Not supported'}
                            </Badge>
                        </dd>
                    </div>
                    <div>
                        <dt>Connection</dt>
                        <dd>
                            <Badge
                                tone={
                                    capabilities.secure && capabilities.topLevel
                                        ? 'success'
                                        : 'warning'
                                }
                            >
                                {capabilities.secure && capabilities.topLevel
                                    ? 'Secure'
                                    : 'Secure browser tab required'}
                            </Badge>
                        </dd>
                    </div>
                    <div>
                        <dt>NFC permission</dt>
                        <dd>
                            {(
                                {
                                    granted: 'Allowed',
                                    denied: 'Blocked',
                                    prompt: 'Ask when scanning',
                                } as Record<string, string>
                            )[permission] ?? permission}
                        </dd>
                    </div>
                </dl>
                {!capabilities.api && (
                    <p className="nfc-device-copy">
                        You can manage records on this device. To read or write a coin, open NFCheck
                        on a compatible NFC phone.
                    </p>
                )}
                <div className="form-actions">
                    <Button
                        variant="primary"
                        disabled={!ready || busy}
                        onClick={() => void check('access')}
                    >
                        Check NFC access
                    </Button>
                    <Button disabled={!ready || busy} onClick={() => void check('read')}>
                        Check coin format
                    </Button>
                    {busy && (
                        <Button onClick={() => operation.current?.abort()}>Cancel check</Button>
                    )}
                </div>
                <p className="credential-feedback" role="status" aria-live="polite">
                    {result}
                </p>
            </Panel>
            <Link
                href={user?.role === 'admin' ? '/admin/credentials' : '/guard/scanner'}
                className="button button-secondary"
            >
                {user?.role === 'admin' ? 'Open NFC credentials' : 'Open gate scanner'}
            </Link>
        </ManagementPage>
    );
}
