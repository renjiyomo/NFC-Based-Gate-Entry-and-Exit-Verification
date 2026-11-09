import { useState } from 'react';
import { ManagementPage, Select } from '../../components/management/Common';
import ActivityTable, { useActivity } from '../../components/operations/Activity';
import type { Station } from '../../lib/operations';
import { useWorkspace } from '../../lib/workspace';
export default function Activity({ admin, stations }: { admin: boolean; stations: Station[] }) {
    const [gate, setGate] = useState('');
    const { simulator } = useWorkspace();
    const { feed, error, refresh } = useActivity(
        `${admin ? '/admin/history/feed' : '/guard/activity/feed'}${gate ? `?gate_id=${gate}` : ''}`,
    );
    return (
        <ManagementPage
            page="history"
            title={admin ? 'Gate activity' : 'Recent assigned-gate activity'}
            description="Valid NFC scans record automatically. Rejected and duplicate scans remain separate."
        >
            {simulator && (
                <p className="credential-notice">
                    Isolated simulator · All displayed activity is simulated.
                </p>
            )}
            <div className="activity-filter">
                <Select
                    name="activity_gate"
                    label={admin ? 'Gate filter' : 'Currently assigned gate'}
                    value={gate}
                    onChange={(e) => setGate(e.target.value)}
                >
                    <option value="">{admin ? 'All gates' : 'All currently assigned gates'}</option>
                    {stations.map((station) => (
                        <option key={station.id} value={station.id}>
                            {station.code} · {station.name}
                        </option>
                    ))}
                </Select>
            </div>
            <ActivityTable feed={feed} error={error} refresh={() => void refresh()} />
            <p className="availability-note">
                This recent activity view supports gate operations. Date-range reporting, exports
                and monitoring analytics are introduced in Phase 7.
            </p>
        </ManagementPage>
    );
}
