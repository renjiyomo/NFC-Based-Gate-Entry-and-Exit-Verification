import { useId } from 'react';
import type { Summary } from '../../lib/monitoring';
import { number } from '../../lib/monitoring';

export function Trend({ rows }: { rows: Summary['trend'] }) {
    const id = useId();
    const max = Math.max(2, ...rows.flatMap((r) => [r.entries, r.exits]));
    const x = (i: number) => 42 + (rows.length === 1 ? 325 : (i * 640) / (rows.length - 1));
    const y = (v: number) => 204 - (v / max) * 170;
    const label = (d: string) =>
        new Date(d + 'T00:00:00+08:00').toLocaleDateString('en-PH', {
            month: 'short',
            day: 'numeric',
            timeZone: 'Asia/Manila',
        });
    const total = rows.reduce((s, r) => s + r.entries + r.exits, 0);
    return (
        <div className="report-chart">
            <div className="chart-legend">
                <span className="entry-key">Entries</span>
                <span className="exit-key">Exits</span>
            </div>
            {total ? (
                <svg viewBox="0 0 720 245" role="img" aria-labelledby={id}>
                    <title id={id}>
                        Effective recorded entries and exits by Manila date. Data table follows.
                    </title>
                    {[0, Math.ceil(max / 2), max].map((t) => (
                        <g key={t}>
                            <line x1="42" x2="694" y1={y(t)} y2={y(t)} stroke="#29384c" />
                            <text x="30" y={y(t) + 4} textAnchor="end">
                                {number(t)}
                            </text>
                        </g>
                    ))}
                    {(['entries', 'exits'] as const).map((key, i) => (
                        <g key={key} className={i ? 'exit-series' : 'entry-series'}>
                            <polyline
                                fill="none"
                                strokeWidth="2.5"
                                points={rows.map((r, n) => `${x(n)},${y(r[key])}`).join(' ')}
                            />
                            {rows.length <= 31 &&
                                rows.map((r, n) => (
                                    <circle key={r.date} cx={x(n)} cy={y(r[key])} r="3">
                                        <title>{`${r.date}: ${r[key]} ${key}`}</title>
                                    </circle>
                                ))}
                        </g>
                    ))}
                    {[...new Set([0, Math.floor((rows.length - 1) / 2), rows.length - 1])].map(
                        (i) => (
                            <text key={i} x={x(i)} y="232" textAnchor="middle">
                                {label(rows[i].date)}
                            </text>
                        ),
                    )}
                </svg>
            ) : (
                <p className="report-empty">No effective recorded passages in this period.</p>
            )}
            <details className="chart-data">
                <summary>View daily data table</summary>
                <div className="report-scroll">
                    <table className="data-table">
                        <thead>
                            <tr>
                                <th>Date · Manila</th>
                                <th>Entries</th>
                                <th>Exits</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr key={r.date}>
                                    <td>{r.date}</td>
                                    <td>{number(r.entries)}</td>
                                    <td>{number(r.exits)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </details>
        </div>
    );
}
export function Bars({ rows, label }: { rows: { label: string; value: number }[]; label: string }) {
    const max = Math.max(1, ...rows.map((r) => r.value));
    return (
        <div className="report-bars" role="list" aria-label={label}>
            {rows.some((r) => r.value) ? (
                rows.map((r, i) => (
                    <div role="listitem" className="report-bar-row" key={`${r.label}-${i}`}>
                        <div>
                            <span>{r.label.replaceAll('_', ' ')}</span>
                            <strong>{number(r.value)}</strong>
                        </div>
                        <div className="report-bar-track" aria-hidden="true">
                            <span style={{ width: `${(r.value / max) * 100}%` }} />
                        </div>
                    </div>
                ))
            ) : (
                <p className="report-empty">No matching activity in this period.</p>
            )}
        </div>
    );
}
