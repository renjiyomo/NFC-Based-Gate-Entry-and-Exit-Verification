import { Link, useForm } from '@inertiajs/react';
import { AuthPanel } from '../../components/AuthPanel';

export default function ForgotPassword() {
    const form = useForm({ email: '' });
    return (
        <AuthPanel
            title="Reset your password"
            description="Enter your account email to request a password reset link."
        >
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/forgot-password');
                }}
            >
                <div className="form-field">
                    <label htmlFor="email">Email address</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        autoComplete="username"
                        required
                        maxLength={255}
                        value={form.data.email}
                        onChange={(event) => form.setData('email', event.target.value)}
                        aria-invalid={!!form.errors.email}
                        aria-describedby={form.errors.email ? 'email-error' : undefined}
                    />
                    {form.errors.email && (
                        <p id="email-error" className="field-error" role="alert">
                            {form.errors.email}
                        </p>
                    )}
                </div>
                <button className="button button-primary login-submit" disabled={form.processing}>
                    {form.processing ? 'Requesting link…' : 'Send reset link'}
                </button>
            </form>
            <Link href="/login" className="auth-back">
                Back to sign in
            </Link>
        </AuthPanel>
    );
}
