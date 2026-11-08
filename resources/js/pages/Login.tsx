import { Head, Link } from '@inertiajs/react';
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
import { pageUrl } from '../lib/navigation';

export default function Login() {
    const [showPassword, setShowPassword] = useState(false);
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
                <Badge tone="blue">Interface preview</Badge>
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
                        Verify identity.
                        <br />
                        Record observed passage.
                    </h1>
                    <p className="login-description">
                        A dedicated workspace for reviewing campus credentials and recording
                        observed entry and exit.
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
                                title: 'Review the person’s identity',
                                description: 'Compare the visible identity information.',
                            },
                            {
                                icon: Check,
                                title: 'Confirm observed passage',
                                description: 'Keep verification and passage distinct.',
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
                        <ShieldCheck size={16} aria-hidden="true" /> Built around the security
                        guard’s review
                    </p>
                </section>
                <section className="login-form-panel" id="sign-in" aria-labelledby="sign-in-title">
                    <span className="login-lock">
                        <LockKeyhole size={25} strokeWidth={1.5} aria-hidden="true" />
                    </span>
                    <h2 id="sign-in-title">Welcome back</h2>
                    <p className="login-form-description">Sign in to your NFCheck workspace.</p>
                    <div className="login-preview-notice">
                        <Info size={16} aria-hidden="true" />
                        <p>
                            Sign-in is not connected. Use sample text only; do not enter real
                            credentials.
                        </p>
                    </div>
                    <form onSubmit={(event) => event.preventDefault()}>
                        <div className="form-field">
                            <label htmlFor="email">Email address</label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                autoComplete="off"
                                placeholder="Enter a sample email"
                            />
                        </div>
                        <div className="form-field">
                            <label htmlFor="password">Password</label>
                            <div className="password-input">
                                <input
                                    id="password"
                                    type={showPassword ? 'text' : 'password'}
                                    name="password"
                                    autoComplete="off"
                                    placeholder="Enter a sample password"
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
                        </div>
                        <button type="button" disabled className="recovery-link">
                            Forgot password?
                        </button>
                        <button
                            className="button button-primary login-submit"
                            type="submit"
                            disabled
                        >
                            <KeyRound size={17} aria-hidden="true" />
                            Sign in
                        </button>
                        <p className="login-no-submit">
                            No credentials are sent from this preview.
                        </p>
                    </form>
                    <div className="login-preview-link">
                        <span>Explore the interface</span>
                        <Link href={pageUrl('dashboard')}>
                            Open workspace preview <ArrowRight size={16} aria-hidden="true" />
                        </Link>
                    </div>
                </section>
            </main>
            <footer className="login-footer">
                <span>NFCheck · Campus gate verification & monitoring</span>
                <span>Bicol University Polangui</span>
            </footer>
        </div>
    );
}
