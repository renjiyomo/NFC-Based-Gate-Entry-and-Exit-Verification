import { Moon, Sun } from 'lucide-react';
import { useState, useSyncExternalStore } from 'react';
import { changeTheme, currentTheme, watchTheme } from '../lib/theme';
export default function ThemeToggle() {
    const theme = useSyncExternalStore(watchTheme, currentTheme, () => 'dark' as const);
    const [busy, setBusy] = useState(false);
    return (
        <button
            className="theme-toggle"
            type="button"
            role="switch"
            aria-checked={theme === 'light'}
            aria-label={`Switch to ${theme === 'dark' ? 'light' : 'dark'} theme`}
            title={`Switch to ${theme === 'dark' ? 'light' : 'dark'} theme`}
            disabled={busy}
            onClick={async (e) => {
                setBusy(true);
                try {
                    await changeTheme(theme === 'dark' ? 'light' : 'dark', e.currentTarget);
                } finally {
                    setBusy(false);
                }
            }}
        >
            {theme === 'dark' ? (
                <Sun size={18} strokeWidth={1.7} aria-hidden="true" />
            ) : (
                <Moon size={18} strokeWidth={1.7} aria-hidden="true" />
            )}
        </button>
    );
}
