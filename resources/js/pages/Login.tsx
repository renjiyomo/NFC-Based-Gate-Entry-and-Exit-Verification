import { Head, Link, useForm } from '@inertiajs/react';
import { Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';
import { useWorkspace } from '../lib/workspace';
import { virtualTagLabel } from '../lib/nfc';
import ThemeToggle from '../components/ThemeToggle';

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
                    <img src="/images/nfcheck-logo.png" width="36" height="36" alt="" />
                    <span>
                        <strong>NFCheck</strong>
                        <small>Tap. Check. Verify.</small>
                    </span>
                </Link>
                <ThemeToggle />
            </header>
            <main id="main-content" tabIndex={-1} className="login-content">
                <section className="login-introduction" aria-labelledby="login-introduction-title">
                    <div className="login-institution">
                        <img
                            src="/images/bup-seal.png"
                            width="42"
                            height="42"
                            alt="Bicol University Polangui seal"
                        />
                        <div>
                            <span>Bicol University</span>
                            <strong>Polangui campus</strong>
                        </div>
                    </div>
                    <h1 id="login-introduction-title">
                        Campus entry
                        <br /> and exit.
                    </h1>
                    <p className="login-description">
                        A clear record of the people passing through your gates.
                    </p>
                    <div className="login-register" aria-label="How NFCheck works">
                        <div>
                            <span>01</span>
                            <p>Registered identities</p>
                        </div>
                        <div>
                            <span>02</span>
                            <p>NFC credentials</p>
                        </div>
                        <div>
                            <span>03</span>
                            <p>Entry &amp; exit records</p>
                        </div>
                    </div>
                </section>
                <section className="login-form-panel" id="sign-in" aria-labelledby="sign-in-title">
                    <p className="login-form-overline">Your workspace</p>
                    <h2 id="sign-in-title">Sign in</h2>
                    <p className="login-form-description">
                        Use your administrator or gate account.
                    </p>
                    {(hardwareLab || simulator) && (
                        <p className="login-lab-label">
                            {hardwareLab ? 'Hardware lab' : 'Simulator'} · Separate test accounts
                        </p>
                    )}
                    {simulator && virtualTagLabel() && (
                        <p className="auth-status">
                            Virtual tag {virtualTagLabel()} is held in this tab for two minutes.
                            Sign in with the lab gate account.
                        </p>
                    )}
                    {preview && (
                        <p className="login-preview-notice">
                            Interface preview. Use sample text only; sign-in is disabled.
                        </p>
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
                                autoCapitalize="none"
                                spellCheck={false}
                                placeholder="Your username"
                                value={form.data.username}
                                onChange={(e) => form.setData('username', e.target.value)}
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
                                    placeholder="Your password"
                                    value={form.data.password}
                                    onChange={(e) => form.setData('password', e.target.value)}
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
                                    onClick={() => setShowPassword(!showPassword)}
                                >
                                    {showPassword ? (
                                        <EyeOff size={17} aria-hidden="true" />
                                    ) : (
                                        <Eye size={17} aria-hidden="true" />
                                    )}
                                </button>
                            </div>
                            {form.errors.password && (
                                <p id="password-error" className="field-error" role="alert">
                                    {form.errors.password}
                                </p>
                            )}
                        </div>
                        <button
                            className="button button-primary login-submit"
                            type="submit"
                            disabled={preview || form.processing}
                        >
                            {form.processing ? 'Signing in…' : 'Sign in'}
                        </button>
                        <p className="login-help">Need help signing in? Ask your administrator.</p>
                    </form>
                    {preview && (
                        <Link className="row-link" href="/preview/dashboard">
                            Open workspace preview
                        </Link>
                    )}
                </section>
            </main>
            <footer className="login-footer">
                <span>NFCheck</span>
                <span>Bicol University Polangui</span>
            </footer>
        </div>
    );
}
