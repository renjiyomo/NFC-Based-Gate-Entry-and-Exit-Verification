import { useEffect, useState } from 'react';
import { Button } from '../ui';
import type { Choice } from '../../lib/management';
import { Field, Select } from './FormFields';

export default function ChoicePicker({
    kind,
    name,
    label,
    value,
    onChange,
    error,
    required = false,
    active = true,
}: {
    kind: 'gates' | 'guards';
    name: string;
    label: string;
    value: string | number;
    onChange: (value: string) => void;
    error?: string;
    required?: boolean;
    active?: boolean;
}) {
    const [query, setQuery] = useState('');
    const [page, setPage] = useState(1);
    const [options, setOptions] = useState<Choice[]>([]);
    const [selected, setSelected] = useState<Choice | null>(null);
    const [busy, setBusy] = useState(false);
    const [failure, setFailure] = useState(false);
    const [more, setMore] = useState(false);
    const [retry, setRetry] = useState(0);
    useEffect(() => {
        const controller = new AbortController();
        const timer = window.setTimeout(
            async () => {
                setBusy(true);
                setFailure(false);
                try {
                    const params = new URLSearchParams({
                        q: query,
                        page: String(page),
                        active: active ? '1' : '0',
                    });
                    if (value) params.set('selected', String(value));
                    const response = await fetch(`/admin/choices/${kind}?${params}`, {
                        signal: controller.signal,
                        headers: { Accept: 'application/json' },
                    });
                    if (!response.ok) throw new Error('Choices unavailable');
                    const data = (await response.json()) as {
                        data: Choice[];
                        selected: Choice | null;
                        has_more: boolean;
                    };
                    if (controller.signal.aborted) return;
                    setOptions((previous) =>
                        page === 1
                            ? data.data
                            : [
                                  ...new Map(
                                      [...previous, ...data.data].map((choice) => [
                                          choice.id,
                                          choice,
                                      ]),
                                  ).values(),
                              ],
                    );
                    setSelected(data.selected);
                    setMore(data.has_more);
                } catch {
                    if (!controller.signal.aborted) setFailure(true);
                } finally {
                    if (!controller.signal.aborted) setBusy(false);
                }
            },
            query ? 250 : 0,
        );
        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [query, page, kind, active, value, retry]);
    const choices =
        selected && !options.some((choice) => choice.id === selected.id)
            ? [selected, ...options]
            : options;
    return (
        <div className="choice-picker">
            <Field
                id={`${name}-search`}
                name={`${name}_search`}
                label={`Find ${kind === 'gates' ? 'a gate' : 'a security guard'}`}
                type="search"
                maxLength={100}
                value={query}
                onChange={(event) => {
                    setQuery(event.target.value);
                    setPage(1);
                }}
                placeholder={kind === 'gates' ? 'Name or gate code' : 'Name or account email'}
            />
            <Select
                name={name}
                label={label}
                required={required}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                error={error}
            >
                <option value="">{required ? 'Choose a record' : 'All records'}</option>
                {value && !choices.some((choice) => String(choice.id) === String(value)) && (
                    <option value={value}>Previously selected record is unavailable</option>
                )}
                {choices.map((choice) => (
                    <option key={choice.id} value={choice.id}>
                        {choice.code ? `${choice.code} · ` : ''}
                        {choice.name}
                        {choice.email ? ` · ${choice.email}` : ''}
                    </option>
                ))}
            </Select>
            <div className="choice-feedback" aria-live="polite">
                {busy ? (
                    'Loading choices…'
                ) : failure ? (
                    <>
                        <span>Choices could not be loaded.</span>
                        <Button onClick={() => setRetry((count) => count + 1)}>Retry</Button>
                    </>
                ) : (
                    <>
                        <span>
                            {choices.length
                                ? `${choices.length} choices loaded`
                                : 'No matching records'}
                        </span>
                        {more && (
                            <Button onClick={() => setPage((current) => current + 1)}>
                                Load more choices
                            </Button>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}
