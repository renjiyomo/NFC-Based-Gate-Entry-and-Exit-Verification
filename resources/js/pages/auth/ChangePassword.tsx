import { Link, useForm } from '@inertiajs/react';
import { AuthPanel, PasswordField } from '../../components/AuthPanel';
import { useWorkspace } from '../../lib/workspace';

export default function ChangePassword() {
    const { user, home } = useWorkspace();
    const form = useForm({ current_password: '', password: '', password_confirmation: '' });
    return (
        <AuthPanel
            title={user?.mustChangePassword ? 'Set your own password' : 'Change your password'}
            description={`${user?.name ?? 'Your account'} · Use 15–128 characters and a unique passphrase. Other sessions will be signed out.`}
        >
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put('/account/password', { onFinish: () => form.reset() });
                }}
            >
                <PasswordField
                    id="current_password"
                    label="Current password"
                    current
                    value={form.data.current_password}
                    onChange={(value) => form.setData('current_password', value)}
                    error={form.errors.current_password}
                />
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
                    {form.processing ? 'Saving password…' : 'Save password'}
                </button>
            </form>
            <div className="auth-actions">
                {!user?.mustChangePassword && <Link href={home}>Back to workspace</Link>}
                <Link href="/logout" method="post" as="button">
                    Sign out
                </Link>
            </div>
        </AuthPanel>
    );
}
