import type { InputHTMLAttributes, SelectHTMLAttributes, ReactNode } from 'react';

export function Field({
    label,
    error,
    hint,
    ...props
}: InputHTMLAttributes<HTMLInputElement> & {
    name: string;
    label: string;
    error?: string;
    hint?: string;
}) {
    const id = props.id ?? props.name;
    return (
        <div className="form-field">
            <label htmlFor={id}>
                {label}
                {props.required && <span className="required-mark"> *</span>}
            </label>
            <input
                {...props}
                id={id}
                aria-invalid={!!error}
                aria-describedby={
                    [hint ? `${id}-hint` : null, error ? `${id}-error` : null]
                        .filter(Boolean)
                        .join(' ') || undefined
                }
            />
            {hint && (
                <p className="field-hint" id={`${id}-hint`}>
                    {hint}
                </p>
            )}
            {error && (
                <p className="field-error" id={`${id}-error`}>
                    {error}
                </p>
            )}
        </div>
    );
}
export function Select({
    label,
    error,
    children,
    ...props
}: SelectHTMLAttributes<HTMLSelectElement> & {
    name: string;
    label: string;
    error?: string;
    children: ReactNode;
}) {
    const id = props.id ?? props.name;
    return (
        <div className="form-field">
            <label htmlFor={id}>
                {label}
                {props.required && <span className="required-mark"> *</span>}
            </label>
            <select
                {...props}
                id={id}
                aria-invalid={!!error}
                aria-describedby={error ? `${id}-error` : undefined}
            >
                {children}
            </select>
            {error && (
                <p className="field-error" id={`${id}-error`}>
                    {error}
                </p>
            )}
        </div>
    );
}
