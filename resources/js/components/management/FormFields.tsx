import {
    useState,
    type InputHTMLAttributes,
    type SelectHTMLAttributes,
    type ReactNode,
} from 'react';
import { Eye, EyeOff } from 'lucide-react';

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
    const [visible, setVisible] = useState(false);
    const password = props.type === 'password';
    return (
        <div className="form-field">
            <label htmlFor={id}>
                {label}
                {props.required && <span className="required-mark"> *</span>}
            </label>
            <div className={password ? 'password-input' : undefined}>
                <input
                    {...props}
                    type={password && visible ? 'text' : props.type}
                    id={id}
                    aria-invalid={!!error}
                    aria-describedby={
                        [hint ? `${id}-hint` : null, error ? `${id}-error` : null]
                            .filter(Boolean)
                            .join(' ') || undefined
                    }
                />
                {password && (
                    <button
                        type="button"
                        aria-label={`${visible ? 'Hide' : 'Show'} ${label.toLowerCase()}`}
                        aria-pressed={visible}
                        onClick={() => setVisible(!visible)}
                    >
                        {visible ? (
                            <EyeOff size={17} aria-hidden="true" />
                        ) : (
                            <Eye size={17} aria-hidden="true" />
                        )}
                    </button>
                )}
            </div>
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
