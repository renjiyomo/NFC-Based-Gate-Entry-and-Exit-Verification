import { Head, Link } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { useWorkspace } from '../lib/workspace';
import ThemeToggle from './ThemeToggle';
import { Eye, EyeOff } from 'lucide-react';

export function AuthPanel({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
}) {
    const { flash } = useWorkspace();
    return (
        <div className="auth-page">
            <Head title={title} />
            <a className="skip-link" href="#main-content">
                Skip to form
            </a>
            <header className="login-header">
                <Link href="/" className="brand">
                    <img src="/images/nfcheck-logo.png" width="44" height="44" alt="" />
                    <span>
                        <strong>NFCheck</strong>
                        <small>Campus gate workspace</small>
                    </span>
                </Link>
                <ThemeToggle />
            </header>
            <main id="main-content" tabIndex={-1} className="auth-main">
                <section className="login-form-panel" aria-labelledby="auth-title">
                    <h1 id="auth-title">{title}</h1>
                    <p className="login-form-description">{description}</p>
                    {flash?.status && (
                        <p className="auth-status" role="status">
                            {flash.status}
                        </p>
                    )}
                    {children}
                </section>
            </main>
            <footer className="login-footer">
                <span>NFCheck · Bicol University Polangui</span>
            </footer>
        </div>
    );
}

export function PasswordField({
    id,
    label,
    value,
    onChange,
    error,
    current = false,
}: {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    current?: boolean;
}) {
    const [visible, setVisible] = useState(false);
    return (
        <div className="form-field">
            <label htmlFor={id}>{label}</label>
            <div className="password-input">
                <input
                    id={id}
                    name={id}
                    type={visible ? 'text' : 'password'}
                    autoComplete={current ? 'current-password' : 'new-password'}
                    required
                    maxLength={128}
                    minLength={current ? undefined : 15}
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    aria-invalid={!!error}
                    aria-describedby={error ? `${id}-error` : undefined}
                />
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
            </div>
            {error && (
                <p id={`${id}-error`} className="field-error" role="alert">
                    {error}
                </p>
            )}
        </div>
    );
}
