import { Link } from '@inertiajs/react';
import { Badge } from '../ui';
import type { CredentialRecord } from '../../lib/credentials';

export function CredentialBadge({
    record,
}: {
    record: Pick<CredentialRecord, 'status' | 'source'>;
}) {
    return (
        <span className="credential-badges">
            <Badge
                tone={
                    record.status === 'active'
                        ? 'success'
                        : record.status === 'pending'
                          ? 'warning'
                          : 'neutral'
                }
            >
                {record.status}
            </Badge>
            <Badge tone={record.source === 'simulated' ? 'warning' : 'blue'}>
                {record.source === 'simulated' ? 'Simulated' : 'Physical tag'}
            </Badge>
        </span>
    );
}
export function SimulatorNotice() {
    return (
        <div className="credential-notice" role="note">
            <strong>Isolated NFC simulator</strong>
            <p>
                Uses synthetic lab people and the shared credential verifier. Credential inspection
                records no gate decision or passage. The separate guard workflow records labeled
                simulated passages after explicit confirmation.
            </p>
            <Link href="/simulator">Open simulator</Link>
        </div>
    );
}
