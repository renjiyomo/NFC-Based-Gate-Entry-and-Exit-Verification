import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeCheck,
    Check,
    Eye,
    EyeOff,
    Info,
    KeyRound,
    LockKeyhole,
    ScanLine,
    ShieldCheck,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '../components/ui';
import { useWorkspace } from '../lib/workspace';
import { virtualTagLabel } from '../lib/nfc';

export default function Login({ preview = false }: { preview?: boolean }) {
    const [showPassword, setShowPassword] = useState(false);
    const { flash, simulator, hardwareLab } = useWorkspace();
    const form = useForm({ username: '', password: '' });
    return (
        <div className="login-page">
            <Head title="Sign in" />
            <a className="skip-link" href="#sign-in">
                Skip to sign-in form
            </a>
            <header className="login-header">
                <Link href="/login" className="brand">
                    <img src="/images/nfcheck-logo.png" width="44" height="44" alt="" />
                    <span>
                        <strong>NFCheck</strong>
                        <small>Tap. Check. Verify.</small>
                    </span>
                </Link>
                <Badge tone="blue">{preview ? 'Interface preview' : 'Account access'}</Badge>
            </header>
            <main id="main-content" tabIndex={-1} className="login-content">
                <section className="login-introduction" aria-labelledby="login-introduction-title">
                    <div className="login-institution">
                        <img
                            src="/images/bup-seal.png"
                            width="64"
                            height="64"
                            alt="Bicol University Polangui seal"
                        />
                        <div>
                            <span>Bicol University</span>
                            <strong>Polangui campus</strong>
                        </div>
                    </div>
                    <p className="eyebrow">Campus gate workspace</p>
                    <h1 id="login-introduction-title">
                        Tap your credential.
                        <br />
                        Record Entry or Exit.
                    </h1>
                    <p className="login-description">
                        A dedicated workspace for validating campus credentials and recording NFC
                        entry and exit scans.
                    </p>
                    <div className="login-workflow">
                        {[
                            {
                                icon: ScanLine,
                                title: 'Read a campus credential',
                                description: 'Check a registered NFC reference.',
                            },
                            {
                                icon: BadgeCheck,
                                title: 'Validate the credential',
                                description: 'Check the active person and NFC record.',
                            },
                            {
                                icon: Check,
                                title: 'Record the selected direction',
                                description: 'Valid scans are recorded automatically.',
                            },
                        ].map(({ icon: Icon, title, description }) => (
                            <div key={title}>
                                <span>
                                    <Icon size={19} strokeWidth={1.6} aria-hidden="true" />
                                </span>
                                <div>
                                    <strong>{title}</strong>
                                    <p>{description}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                    <p className="login-institution-note">
                        <ShieldCheck size={16} aria-hidden="true" /> Campus entry and exit
                        monitoring
                    </p>
                </section>
                <section className="login-form-panel" id="sign-in" aria-labelledby="sign-in-title">
                    <span className="login-lock">
                        <LockKeyhole size={25} strokeWidth={1.5} aria-hidden="true" />
                    </span>
                    <h2 id="sign-in-title">Welcome back</h2>
                    <p className="login-form-description">Sign in to your NFCheck workspace.</p>
                    {hardwareLab && (
                        <p className="login-preview-notice">
                            Hardware test workspace · Use the separate hardware test account.
                            Synthetic identities and test passages only.
                        </p>
                    )}
                    {simulator && virtualTagLabel() && (
                        <p className="login-preview-notice">
                            Virtual lab tag {virtualTagLabel()} is held only in this tab’s memory
                            for up to two minutes. Sign in with the separate lab guard account.
                            Reloading discards the tag.
                        </p>
                    )}
                    {preview && (
                        <div className="login-preview-notice">
                            <Info size={16} aria-hidden="true" />
                            <p>
                                Sign-in is not connected. Use sample text only; do not enter real
                                credentials.
                            </p>
                        </div>
                    )}
                    {flash?.status && (
                        <p className="auth-status" role="status">
                            {flash.status}
                        </p>
                    )}
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            if (!preview)
                                form.post('/login', { onFinish: () => form.reset('password') });
                        }}
                    >
                        <div className="form-field">
                            <label htmlFor="username">Username</label>
                            <input
                                id="username"
                                type="text"
                                name="username"
                                autoComplete={preview ? 'off' : 'username'}
                                placeholder={
                                    preview ? 'Enter a sample username' : 'e.g. alexi or gate1'
                                }
                                value={form.data.username}
                                onChange={(event) => form.setData('username', event.target.value)}
                                required
                                maxLength={255}
                                aria-invalid={!!form.errors.username}
                                aria-describedby={
                                    form.errors.username ? 'username-error' : undefined
                                }
                            />
                            {form.errors.username && (
                                <p id="username-error" className="field-error" role="alert">
                                    {form.errors.username}
                                </p>
                            )}
                        </div>
                        <div className="form-field">
                            <label htmlFor="password">Password</label>
                            <div className="password-input">
                                <input
                                    id="password"
                                    type={showPassword ? 'text' : 'password'}
                                    name="password"
                                    autoComplete={preview ? 'off' : 'current-password'}
                                    placeholder={
                                        preview ? 'Enter a sample password' : 'Enter your password'
                                    }
                                    value={form.data.password}
                                    onChange={(event) =>
                                        form.setData('password', event.target.value)
                                    }
                                    required
                                    maxLength={128}
                                    aria-invalid={!!form.errors.password}
                                    aria-describedby={
                                        form.errors.password ? 'password-error' : undefined
                                    }
                                />
                                <button
                                    type="button"
                                    aria-label={showPassword ? 'Hide password' : 'Show password'}
                                    aria-pressed={showPassword}
                                    onClick={() => setShowPassword((previous) => !previous)}
                                >
                                    {showPassword ? (
                                        <EyeOff size={18} aria-hidden="true" />
                                    ) : (
                                        <Eye size={18} aria-hidden="true" />
                                    )}
                                </button>
                            </div>
                            {form.errors.password && (
                                <p id="password-error" className="field-error" role="alert">
                                    {form.errors.password}
                                </p>
                            )}
                        </div>
                        {preview ? (
                            <button type="button" disabled className="recovery-link">
                                Forgot password?
                            </button>
                        ) : (
                            <span className="field-hint">
                                Ask an administrator if you need a password reset.
                            </span>
                        )}
                        <button
                            className="button button-primary login-submit"
                            type="submit"
                            disabled={preview || form.processing}
                        >
                            <KeyRound size={17} aria-hidden="true" />
                            {form.processing ? 'Signing in…' : 'Sign in'}
                        </button>
                        {preview && (
                            <p className="login-no-submit">
                                No credentials are sent from this preview.
                            </p>
                        )}
                    </form>
                    {preview && (
                        <div className="login-preview-link">
                            <span>Explore the interface</span>
                            <Link href="/preview/dashboard">
                                Open workspace preview <ArrowRight size={16} aria-hidden="true" />
                            </Link>
                        </div>
                    )}
                </section>
            </main>
            <footer className="login-footer">
                <span>NFCheck · Campus gate verification & monitoring</span>
                <span>Bicol University Polangui</span>
            </footer>
        </div>
    );
}
