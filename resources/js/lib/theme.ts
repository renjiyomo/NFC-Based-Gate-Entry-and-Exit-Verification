export type Theme = 'dark' | 'light';
const key = 'nfcheck.theme';
const duration = 620;
const canvas = { dark: '#0B0D0E', light: '#F4F2EC' };
const event = 'nfcheck:theme';
let running: Promise<void> | null = null;

export function currentTheme(): Theme {
    return document.documentElement.classList.contains('light') ? 'light' : 'dark';
}
export function watchTheme(listener: () => void): () => void {
    const storage = (e: StorageEvent) => {
        if (e.key === key) paint(e.newValue === 'light' ? 'light' : 'dark');
    };
    window.addEventListener(event, listener);
    window.addEventListener('storage', storage);
    return () => {
        window.removeEventListener(event, listener);
        window.removeEventListener('storage', storage);
    };
}
function paint(theme: Theme) {
    document.documentElement.classList.toggle('light', theme === 'light');
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', canvas[theme]);
    window.dispatchEvent(new Event(event));
}
export function themeOrigin(width: number, height: number, x: number, y: number) {
    const w = Math.max(width, 1),
        h = Math.max(height, 1);
    x = Math.min(Math.max(x, 0), w);
    y = Math.min(Math.max(y, 0), h);
    return {
        x: `${(x / w) * 100}%`,
        y: `${(y / h) * 100}%`,
        radius: `${(Math.hypot(Math.max(x, w - x), Math.max(y, h - y)) / (Math.hypot(w, h) / Math.SQRT2)) * 100 + 1}%`,
    };
}
export function changeTheme(mode: Theme, button: HTMLElement): Promise<void> {
    if (running) return running;
    try {
        localStorage.setItem(key, mode);
    } catch {
        /* Private browsing can deny storage. */
    }
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        paint(mode);
        return Promise.resolve();
    }
    const root = document.documentElement;
    const rect = button.getBoundingClientRect();
    const origin = themeOrigin(
        root.clientWidth,
        window.innerHeight,
        rect.left + rect.width / 2,
        rect.top + rect.height / 2,
    );
    root.style.setProperty('--tx', origin.x);
    root.style.setProperty('--ty', origin.y);
    root.style.setProperty('--tr', origin.radius);
    const cls = mode === 'light' ? 'sweep-in' : 'sweep-out';
    root.classList.add(cls);
    const operation = (async () => {
        try {
            if (typeof document.startViewTransition === 'function') {
                const transition = document.startViewTransition(() => paint(mode));
                void transition.ready.catch(() => {});
                void transition.updateCallbackDone.catch(() => {});
                await transition.finished.catch(() => {});
                if (currentTheme() !== mode) paint(mode);
            } else {
                root.classList.add('theming');
                paint(mode);
                const wave = document.createElement('div');
                wave.className = mode === 'light' ? 'theme-sweep' : 'theme-sweep theme-sweep-out';
                wave.setAttribute('aria-hidden', 'true');
                document.body.append(wave);
                await new Promise((resolve) => window.setTimeout(resolve, duration));
                wave.remove();
            }
        } catch {
            paint(mode);
        } finally {
            root.classList.remove(cls, 'theming');
        }
    })();
    running = operation;
    return operation.finally(() => {
        if (running === operation) running = null;
    });
}
