import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import type { ReportFilters } from '../../lib/monitoring';
import { Field, Select, FormErrors } from '../management/Common';
import { Button } from '../ui';

export default function Filters({
    filters,
    section,
    gates,
    guards,
}: {
    filters: ReportFilters;
    section: string;
    gates: { id: number; code: string; name: string }[];
    guards: { id: number; name: string }[];
}) {
    const form = useForm({ ...filters });
    useEffect(() => {
        form.setData({ ...filters });
    }, [filters]);
    return (
        <form
            className="report-filters"
            onSubmit={(e) => {
                e.preventDefault();
                if (/nfcheck:v\d+:/i.test(form.data.q)) {
                    form.setError(
                        'q',
                        'Search by person name or university number. NFC references cannot be searched.',
                    );
                    return;
                }
                form.transform((data) => ({ ...data, page: 1 }));
                form.get(`/admin/${section}`, {
                    preserveState: true,
                    preserveScroll: true,
                });
            }}
        >
            <FormErrors errors={form.errors} />
            <div
                className={`period-fields ${form.data.period === 'custom' ? 'period-fields-custom' : ''}`}
            >
                <Select
                    name="period"
                    label="Period"
                    value={form.data.period}
                    onChange={(e) => form.setData('period', e.target.value)}
                >
                    <option value="daily">One day</option>
                    <option value="weekly">One week · Mon–Sun</option>
                    <option value="monthly">One month</option>
                    <option value="custom">Custom dates</option>
                </Select>
                {form.data.period === 'custom' ? (
                    <>
                        <Field
                            name="from"
                            label="Start date"
                            type="date"
                            value={form.data.from}
                            onChange={(e) => form.setData('from', e.target.value)}
                            required
                        />
                        <Field
                            name="to"
                            label="End date"
                            type="date"
                            value={form.data.to}
                            onChange={(e) => form.setData('to', e.target.value)}
                            required
                        />
                    </>
                ) : (
                    <Field
                        name="date"
                        label={
                            form.data.period === 'daily'
                                ? 'Date'
                                : form.data.period === 'weekly'
                                  ? 'Week containing'
                                  : 'Month containing'
                        }
                        type="date"
                        value={form.data.date}
                        onChange={(e) => form.setData('date', e.target.value)}
                    />
                )}
                <Button type="submit" variant="primary" disabled={form.processing}>
                    {form.processing ? 'Loading…' : 'Apply period & filters'}
                </Button>
            </div>
            <details className="advanced-filters">
                <summary>
                    More filters
                    {[
                        filters.gate_id,
                        filters.kind,
                        filters.guard_id,
                        filters.direction,
                        filters.q,
                    ].filter(Boolean).length
                        ? ' · Applied'
                        : ''}
                </summary>
                <div className="report-filter-grid">
                    <Select
                        name="gate_id"
                        label="Gate"
                        value={form.data.gate_id}
                        onChange={(e) => form.setData('gate_id', e.target.value)}
                    >
                        <option value="">All gates</option>
                        {gates.map((g) => (
                            <option key={g.id} value={g.id}>
                                {g.code} · {g.name}
                            </option>
                        ))}
                    </Select>
                    <Select
                        name="kind"
                        label="Person type"
                        value={form.data.kind}
                        onChange={(e) => form.setData('kind', e.target.value)}
                    >
                        <option value="">Students and employees</option>
                        <option value="student">Students</option>
                        <option value="employee">Employees</option>
                    </Select>
                    <Select
                        name="guard_id"
                        label="Gate account"
                        value={form.data.guard_id}
                        onChange={(e) => form.setData('guard_id', e.target.value)}
                    >
                        <option value="">All gate accounts</option>
                        {guards.map((g) => (
                            <option key={g.id} value={g.id}>
                                {g.name}
                            </option>
                        ))}
                    </Select>
                    <Select
                        name="direction"
                        label="Direction"
                        value={form.data.direction}
                        onChange={(e) => form.setData('direction', e.target.value)}
                    >
                        <option value="">Entry and Exit</option>
                        <option value="entry">Entry</option>
                        <option value="exit">Exit</option>
                    </Select>
                    <Field
                        name="q"
                        label="Person or university number"
                        type="search"
                        maxLength={100}
                        value={form.data.q}
                        onChange={(e) => form.setData('q', e.target.value)}
                        placeholder="Name or university number"
                    />
                    {section === 'history' && (
                        <>
                            {form.data.view === 'attempts' ? (
                                <>
                                    <Select
                                        name="outcome"
                                        label="Credential check"
                                        value={form.data.outcome}
                                        onChange={(e) => form.setData('outcome', e.target.value)}
                                    >
                                        <option value="">All outcomes</option>
                                        <option value="allowed">Accepted</option>
                                        <option value="denied">Rejected</option>
                                    </Select>
                                    <Select
                                        name="review"
                                        label="Scan status"
                                        value={form.data.review}
                                        onChange={(e) => form.setData('review', e.target.value)}
                                    >
                                        <option value="">All statuses</option>
                                        {[
                                            'pending',
                                            'confirmed',
                                            'cancelled',
                                            'expired',
                                            'identity_mismatch',
                                            'duplicate',
                                            'not_applicable',
                                        ].map((s) => (
                                            <option key={s} value={s}>
                                                {
                                                    {
                                                        pending: 'Awaiting review',
                                                        confirmed: 'Passage recorded',
                                                        cancelled: 'Cancelled',
                                                        expired: 'Expired',
                                                        identity_mismatch: 'Identity mismatch',
                                                        duplicate: 'Duplicate suppressed',
                                                        not_applicable: 'No review',
                                                    }[s]
                                                }
                                            </option>
                                        ))}
                                    </Select>
                                </>
                            ) : (
                                <Select
                                    name="validity"
                                    label="Passage state"
                                    value={form.data.validity}
                                    onChange={(e) => form.setData('validity', e.target.value)}
                                >
                                    <option value="effective">Current records</option>
                                    <option value="all">All originals and corrections</option>
                                    <option value="voided">Voided originals</option>
                                </Select>
                            )}
                            <Select
                                name="per_page"
                                label="Rows per page"
                                value={form.data.per_page}
                                onChange={(e) => form.setData('per_page', Number(e.target.value))}
                            >
                                {[25, 50, 100].map((n) => (
                                    <option key={n}>{n}</option>
                                ))}
                            </Select>
                        </>
                    )}
                </div>
                <div className="form-actions">
                    <Button type="submit" variant="primary" disabled={form.processing}>
                        {form.processing ? 'Loading records…' : 'Apply filters'}
                    </Button>
                    <a className="button button-quiet" href={`/admin/${section}`}>
                        Reset filters
                    </a>
                </div>
            </details>
        </form>
    );
}
