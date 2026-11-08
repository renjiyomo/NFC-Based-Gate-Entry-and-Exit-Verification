import { Link, useForm } from '@inertiajs/react';
import { AuthPanel, PasswordField } from '../../components/AuthPanel';

export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const form = useForm({ token, email, password: '', password_confirmation: '' });
    return (
        <AuthPanel
            title="Choose a new password"
            description="Use 15–128 characters. A unique passphrase works well."
        >
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/reset-password', {
                        onFinish: () => form.reset('password', 'password_confirmation'),
                    });
                }}
            >
                <div className="form-field">
                    <label htmlFor="email">Account email</label>
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
                    {form.errors.token && (
                        <p className="field-error" role="alert">
                            {form.errors.token}
                        </p>
                    )}
                </div>
                <PasswordField
                    id="password"
                    label="New password"
                    value={form.data.password}
                    onChange={(value) => form.setData('password', value)}
                    error={form.errors.password}
                />
                <PasswordField
                    id="password_confirmation"
                    label="Confirm new password"
                    value={form.data.password_confirmation}
                    onChange={(value) => form.setData('password_confirmation', value)}
                    error={form.errors.password_confirmation}
                />
                <button className="button button-primary login-submit" disabled={form.processing}>
                    {form.processing ? 'Updating password…' : 'Reset password'}
                </button>
            </form>
            <Link href="/forgot-password" className="auth-back">
                Request a new link
            </Link>
        </AuthPanel>
    );
}
