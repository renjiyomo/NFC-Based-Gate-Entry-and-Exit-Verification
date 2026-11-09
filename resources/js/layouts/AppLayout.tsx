import { Link } from '@inertiajs/react';
import { ChevronDown, ChevronRight, LogOut, Menu, KeyRound, X } from 'lucide-react';
import { useEffect, useRef, type ReactNode } from 'react';
import { type PageId } from '../lib/navigation';
import { useWorkspace } from '../lib/workspace';
import { clearVirtualTag } from '../lib/nfc';
import { Button } from '../components/ui';
import ThemeToggle from '../components/ThemeToggle';
import { useNavigationState } from '../lib/navigationState';
import PageSkeleton from '../components/PageSkeleton';

function Brand() {
    const { home } = useWorkspace();
    return (
        <Link href={home} className="brand" aria-label="NFCheck workspace">
            <img src="/images/nfcheck-logo.png" alt="" width="36" height="36" />
            <span>
                <strong>NFCheck</strong>
                <small>Campus access</small>
            </span>
        </Link>
    );
}
function Navigation({ page, close }: { page: PageId; close?: () => void }) {
    const { navigation, pageUrl } = useWorkspace();
    const items = navigation.filter((item) => item.id !== 'guards');
    return (
        <nav aria-label="Main navigation" className="main-navigation">
            {[...new Set(items.map((item) => item.group))].map((group) => (
                <div className="nav-group" key={group}>
                    <p className="nav-group-label">{group}</p>
                    {items
                        .filter((item) => item.group === group)
                        .map(({ id, label, icon: Icon }) => (
                            <Link
                                href={pageUrl(id)}
                                key={id}
                                className={`nav-item ${page === id ? 'nav-active' : ''}`}
                                aria-current={page === id ? 'page' : undefined}
                                onClick={close}
                            >
                                <Icon size={17} strokeWidth={1.6} aria-hidden="true" />
                                <span>{label}</span>
                            </Link>
                        ))}
                </div>
            ))}
        </nav>
    );
}
export default function AppLayout({ page, children }: { page: PageId; children: ReactNode }) {
    const { navigation, preview, user, flash, testWorkspace, hardwareLab } = useWorkspace();
    const drawer = useRef<HTMLDialogElement>(null);
    const menu = useRef<HTMLDetailsElement>(null);
    const { loading } = useNavigationState();
    const current = navigation.find((item) => item.id === page) ?? navigation[0];
    const role = user?.role === 'admin' ? 'Administrator' : 'Gate account';
    const initials = (user?.name ?? 'NF')
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
    useEffect(() => {
        drawer.current?.close();
        if (menu.current) menu.current.open = false;
    }, [page]);
    useEffect(() => {
        const outside = (e: PointerEvent) => {
            if (menu.current && !menu.current.contains(e.target as Node)) menu.current.open = false;
        };
        const escape = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && menu.current?.open) {
                menu.current.open = false;
                menu.current.querySelector('summary')?.focus();
            }
        };
        document.addEventListener('pointerdown', outside);
        document.addEventListener('keydown', escape);
        return () => {
            document.removeEventListener('pointerdown', outside);
            document.removeEventListener('keydown', escape);
        };
    }, []);
    return (
        <div className="app-shell">
            <a className="skip-link" href="#main-content">
                Skip to main content
            </a>
            <aside className="sidebar">
                <div className="sidebar-brand">
                    <Brand />
                </div>
                <Navigation page={page} />
                <div className="institution">
                    <img src="/images/bup-seal.png" width="28" height="28" alt="" />
                    <div>
                        <strong>Bicol University</strong>
                        <span>Polangui campus</span>
                    </div>
                </div>
            </aside>
            <div className="workspace">
                <header className="topbar">
                    <div className="topbar-left">
                        <Button
                            variant="quiet"
                            className="mobile-menu icon-button"
                            onClick={() => {
                                drawer.current?.showModal();
                                drawer.current
                                    ?.querySelector<HTMLButtonElement>(
                                        'button[aria-label="Close navigation"]',
                                    )
                                    ?.focus();
                            }}
                            aria-label="Open navigation"
                            aria-haspopup="dialog"
                            aria-controls="mobile-navigation"
                        >
                            <Menu size={20} aria-hidden="true" />
                        </Button>
                        <nav aria-label="Breadcrumb">
                            <ol className="breadcrumb">
                                <li>Workspace</li>
                                <li aria-hidden="true">
                                    <ChevronRight size={13} />
                                </li>
                                <li>{current.label}</li>
                            </ol>
                        </nav>
                    </div>
                    <div className="topbar-right">
                        <ThemeToggle />
                        {preview ? (
                            <span className="preview-label">Preview</span>
                        ) : (
                            <details className="account-menu" ref={menu}>
                                <summary aria-label={`${user?.name ?? 'Your'} account menu`}>
                                    <span className="account-avatar" aria-hidden="true">
                                        {initials}
                                    </span>
                                    <span className="account-summary-copy">
                                        <strong>{user?.name}</strong>
                                        <small>{role}</small>
                                    </span>
                                    <ChevronDown size={14} aria-hidden="true" />
                                </summary>
                                <div className="account-popover">
                                    <div className="account-popover-heading">
                                        <strong>{user?.name}</strong>
                                        <span>{role}</span>
                                    </div>
                                    <Link
                                        href="/account/password"
                                        className="account-action"
                                        onClick={() => {
                                            if (menu.current) menu.current.open = false;
                                        }}
                                    >
                                        <KeyRound size={16} aria-hidden="true" />
                                        Change password
                                    </Link>
                                    <Link
                                        href="/logout"
                                        method="post"
                                        as="button"
                                        className="account-action account-signout"
                                        onClick={clearVirtualTag}
                                    >
                                        <LogOut size={16} aria-hidden="true" />
                                        Sign out
                                    </Link>
                                </div>
                            </details>
                        )}
                    </div>
                </header>
                <main
                    id="main-content"
                    tabIndex={-1}
                    aria-label={`${current.label} content`}
                    aria-busy={loading}
                >
                    <div className="content-container">
                        {testWorkspace && (
                            <div className="test-workspace-notice">
                                <strong>{hardwareLab ? 'Hardware lab' : 'Simulator'}</strong>
                                <span>Synthetic records · Separate test workspace</span>
                            </div>
                        )}
                        {flash?.status && (
                            <p className="auth-status" role="status">
                                {flash.status}
                            </p>
                        )}
                        {loading && <PageSkeleton page={page} />}
                        <div className="workspace-page" hidden={loading} inert={loading}>
                            {children}
                        </div>
                        <footer className="content-footer">
                            <span>NFCheck · Bicol University Polangui</span>
                            <span>Asia/Manila</span>
                        </footer>
                    </div>
                </main>
            </div>
            <dialog
                ref={drawer}
                id="mobile-navigation"
                className="navigation-dialog"
                aria-labelledby="navigation-title"
            >
                <div className="drawer-heading">
                    <h2 id="navigation-title" className="sr-only">
                        Workspace navigation
                    </h2>
                    <Brand />
                    <Button
                        variant="quiet"
                        className="icon-button"
                        autoFocus
                        onClick={() => drawer.current?.close()}
                        aria-label="Close navigation"
                    >
                        <X size={20} aria-hidden="true" />
                    </Button>
                </div>
                <Navigation page={page} close={() => drawer.current?.close()} />
                <div className="drawer-footer">Bicol University · Polangui</div>
            </dialog>
        </div>
    );
}
