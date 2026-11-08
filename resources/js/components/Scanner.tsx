import {
    ArrowDownLeft,
    ArrowUpRight,
    Activity,
    CircleAlert,
    ClipboardCheck,
    DoorOpen,
    Info,
    ScanLine,
    ShieldCheck,
    UserRound,
    Wifi,
    WifiOff,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { useWorkspace } from '../lib/workspace';
import { Badge, Button, ChoiceGroup, EmptyTable, Notice, PageHeading, Panel } from './ui';

export default function Scanner() {
    const { preview, user, station, stations } = useWorkspace();
    const [direction, setDirection] = useState<'entry' | 'exit'>('entry');
    const [browser, setBrowser] = useState<{ online: boolean; hasNfcApi: boolean } | null>(null);

    useEffect(() => {
        const update = () =>
            setBrowser({ online: navigator.onLine, hasNfcApi: 'NDEFReader' in window });
        update();
        window.addEventListener('online', update);
        window.addEventListener('offline', update);
        return () => {
            window.removeEventListener('online', update);
            window.removeEventListener('offline', update);
        };
    }, []);

    const ConnectionIcon = browser?.online ? Wifi : WifiOff;
    return (
        <>
            <PageHeading
                eyebrow="Gate operations"
                title="Gate scanner"
                description="Read a credential. Review identity. Confirm observed passage."
                action={
                    <Badge tone={browser?.online ? 'neutral' : 'warning'}>
                        <ConnectionIcon size={14} aria-hidden="true" />
                        {browser === null
                            ? 'Checking browser'
                            : browser.online
                              ? 'Browser online'
                              : 'Browser offline'}
                    </Badge>
                }
            />
            <Notice icon={Info} title="Scanner interface preview">
                Credential verification and passage recording are unavailable. No NFC permission is
                requested and no access is authorized in this preview.
            </Notice>
            <div className="scanner-station">
                <div className="station-select">
                    <label htmlFor="gate-station">
                        <DoorOpen size={16} aria-hidden="true" /> Gate station
                    </label>
                    <select
                        id="gate-station"
                        disabled={preview || !stations.length}
                        value={station?.id ?? ''}
                        onChange={(event) => {
                            if (event.target.value)
                                router.get(`/guard/gates/${event.target.value}/scanner`);
                        }}
                    >
                        <option value="">
                            {stations.length
                                ? 'Choose an assigned station'
                                : 'No current gate assignments'}
                        </option>
                        {stations.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.code} · {item.name}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="station-session">
                    <ShieldCheck size={18} aria-hidden="true" />
                    <div>
                        <strong>
                            {preview ? 'Guard session required' : `${user?.name} · Guard signed in`}
                        </strong>
                        <span>
                            {station
                                ? 'Station assignment verified'
                                : 'Choose a current assigned station to view its context'}
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
                    <ChoiceGroup
                        label="Passage direction"
                        value={direction}
                        onChange={setDirection}
                        className="direction-choices"
                        choices={[
                            { value: 'entry', label: 'Entry', icon: ArrowDownLeft },
                            { value: 'exit', label: 'Exit', icon: ArrowUpRight },
                        ]}
                    />
                    <div className="scan-target">
                        <div className="scan-frame">
                            <ScanLine size={64} strokeWidth={1.1} aria-hidden="true" />
                        </div>
                        <Badge tone="warning">
                            <CircleAlert size={13} aria-hidden="true" />
                            Verification unavailable
                        </Badge>
                        <h3>Scanning is not connected</h3>
                        <p>
                            Connect secure sign-in, gate assignments, and credential verification
                            before scanning.
                        </p>
                        <Button
                            disabled
                            variant="primary"
                            className="scan-button"
                            aria-describedby="scanner-availability"
                        >
                            <ScanLine size={19} aria-hidden="true" /> Start NFC scan
                        </Button>
                        <p className="scan-direction-note" role="status">
                            Selected direction:{' '}
                            <strong>
                                {direction === 'entry' ? 'Entry into campus' : 'Exit from campus'}
                            </strong>
                        </p>
                    </div>
                    <div className="browser-capability" id="scanner-availability">
                        <Info size={15} aria-hidden="true" />
                        <span>
                            {browser === null
                                ? 'Checking browser capability…'
                                : browser.hasNfcApi
                                  ? 'Web NFC API detected. Physical device compatibility is untested.'
                                  : 'Web NFC API unavailable in this browser. Use compatible Android Chrome for NFC.'}
                        </span>
                    </div>
                </Panel>
                <Panel
                    title="Identity review"
                    subtitle="Shown after an authorized credential check"
                    action={<Badge>Awaiting credential</Badge>}
                >
                    <div className="identity-empty">
                        <div className="identity-photo">
                            <UserRound size={49} strokeWidth={1.2} aria-hidden="true" />
                        </div>
                        <h3>No identity to review</h3>
                        <p>
                            A person's photograph and necessary identity details will appear after
                            verification.
                        </p>
                    </div>
                    <div className="identity-explanation">
                        <ClipboardCheck size={20} aria-hidden="true" />
                        <div>
                            <strong>Every passage needs a guard's review</strong>
                            <p>
                                Compare the person with the identity record, then confirm the
                                passage you observed.
                            </p>
                        </div>
                    </div>
                    <div className="identity-actions">
                        <Button disabled variant="primary">
                            <ShieldCheck size={17} aria-hidden="true" /> Confirm observed passage
                        </Button>
                        <div>
                            <Button disabled>Identity mismatch</Button>
                            <Button disabled>Cancel</Button>
                        </div>
                    </div>
                </Panel>
            </div>
            <Panel
                title="Recent station activity"
                subtitle="Only permitted activity from the assigned gate"
                action={
                    <Button disabled variant="quiet">
                        Report concern
                    </Button>
                }
            >
                <EmptyTable
                    columns={['Person', 'Direction', 'Review result', 'Time']}
                    caption="Recent activity at the assigned station"
                    icon={Activity}
                    title="Station activity is not connected"
                    description="Confirmed passages and verification outcomes will appear here during gate operations."
                />
            </Panel>
        </>
    );
}
