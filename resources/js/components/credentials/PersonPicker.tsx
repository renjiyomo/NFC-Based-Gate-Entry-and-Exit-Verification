import { useEffect, useState } from 'react';
import { Button } from '../ui';
import { Field } from '../management/Common';
import type { PersonChoice } from '../../lib/credentials';

export default function PersonPicker({
    value,
    onChange,
}: {
    value: PersonChoice | null;
    onChange: (person: PersonChoice | null) => void;
}) {
    const [query, setQuery] = useState('');
    const [page, setPage] = useState(1);
    const [people, setPeople] = useState<PersonChoice[]>([]);
    const [more, setMore] = useState(false);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    useEffect(() => {
        if (value) return;
        const controller = new AbortController();
        const timer = setTimeout(() => {
            setLoading(true);
            setFailed(false);
            void fetch(`/admin/credentials/people?q=${encodeURIComponent(query)}&page=${page}`, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            })
                .then(async (r) => {
                    if (!r.ok) throw new Error();
                    return r.json() as Promise<{ data: PersonChoice[]; has_more: boolean }>;
                })
                .then((data) => {
                    setPeople(data.data);
                    setMore(data.has_more);
                })
                .catch(() => {
                    if (!controller.signal.aborted) setFailed(true);
                })
                .finally(() => {
                    if (!controller.signal.aborted) setLoading(false);
                });
        }, 200);
        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [query, page, value]);
    return value ? (
        <div className="credential-selected">
            <strong>{value.name}</strong>
            <span>
                {value.number} · {value.kind}
            </span>
            {value.current && (
                <span>
                    Current: {value.current.label} ({value.current.status})
                </span>
            )}
            <Button onClick={() => onChange(null)}>Choose another person</Button>
        </div>
    ) : (
        <div className="credential-picker">
            <Field
                name="person_search"
                label="Find an active person"
                type="search"
                maxLength={100}
                value={query}
                onChange={(e) => {
                    setQuery(e.target.value);
                    setPage(1);
                }}
                placeholder="Search name or campus number"
            />
            <div aria-live="polite">
                {loading ? (
                    <p>Finding people…</p>
                ) : failed ? (
                    <p role="alert">People could not load. Change the search to retry.</p>
                ) : (
                    <>
                        <ul className="credential-choices">
                            {people.map((person) => (
                                <li key={person.id}>
                                    <Button onClick={() => onChange(person)}>
                                        <strong>{person.name}</strong>
                                        <span>
                                            {person.number} · {person.kind}
                                        </span>
                                    </Button>
                                </li>
                            ))}
                        </ul>
                        {!people.length && <p>No active people match this search.</p>}
                    </>
                )}
            </div>
            <div className="form-actions">
                <Button disabled={page === 1 || loading} onClick={() => setPage(page - 1)}>
                    Previous people
                </Button>
                <span>Page {page}</span>
                <Button disabled={!more || loading} onClick={() => setPage(page + 1)}>
                    More people
                </Button>
            </div>
        </div>
    );
}
