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
                        'Search by person name or campus number. NFC references cannot be searched.',
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
            <div className="report-filter-grid">
                <Select
                    name="period"
                    label="Reporting period"
                    value={form.data.period}
                    onChange={(e) => form.setData('period', e.target.value)}
                >
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly · Monday–Sunday</option>
                    <option value="monthly">Monthly</option>
                    <option value="custom">Custom range</option>
                </Select>
                {form.data.period === 'custom' ? (
                    <>
                        <Field
                            name="from"
                            label="From · Manila"
                            type="date"
                            value={form.data.from}
                            onChange={(e) => form.setData('from', e.target.value)}
                            required
                        />
                        <Field
                            name="to"
                            label="Through · Manila"
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
                                ? 'Date · Manila'
                                : 'Date within period · Manila'
                        }
                        type="date"
                        value={form.data.date}
                        onChange={(e) => form.setData('date', e.target.value)}
                    />
                )}
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
                    label="Guard"
                    value={form.data.guard_id}
                    onChange={(e) => form.setData('guard_id', e.target.value)}
                >
                    <option value="">All guards</option>
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
                    label="Person or campus number"
                    type="search"
                    maxLength={100}
                    value={form.data.q}
                    onChange={(e) => form.setData('q', e.target.value)}
                    placeholder="Search retained identity records"
                />
                {section === 'history' && (
                    <>
                        <Select
                            name="view"
                            label="Record type"
                            value={form.data.view}
                            onChange={(e) =>
                                form.setData('view', e.target.value as ReportFilters['view'])
                            }
                        >
                            <option value="passages">Confirmed passages</option>
                            <option value="attempts">Verification attempts</option>
                        </Select>
                        {form.data.view === 'attempts' ? (
                            <>
                                <Select
                                    name="outcome"
                                    label="Verification"
                                    value={form.data.outcome}
                                    onChange={(e) => form.setData('outcome', e.target.value)}
                                >
                                    <option value="">All outcomes</option>
                                    <option value="allowed">Allowed for review</option>
                                    <option value="denied">Denied</option>
                                </Select>
                                <Select
                                    name="review"
                                    label="Review state"
                                    value={form.data.review}
                                    onChange={(e) => form.setData('review', e.target.value)}
                                >
                                    <option value="">All review states</option>
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
                                            {s.replaceAll('_', ' ')}
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
                                <option value="effective">Effective passages</option>
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
                <span className="field-hint">
                    Manila dates · up to 366 days · search describes current directory names
                </span>
            </div>
        </form>
    );
}
