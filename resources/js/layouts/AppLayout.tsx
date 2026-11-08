import { Link } from '@inertiajs/react';
import { ArrowLeft, ChevronRight, CircleHelp, Menu, ShieldCheck, X } from 'lucide-react';
import { useEffect, useRef, type ReactNode } from 'react';
import { navigation, pageUrl, type PageId } from '../lib/navigation';
import { Badge, Button } from '../components/ui';

function Brand() {
    return (
        <Link href={pageUrl('dashboard')} className="brand" aria-label="NFCheck overview">
            <img src="/images/nfcheck-logo.png" alt="" width="44" height="44" />
            <span>
                <strong>NFCheck</strong>
                <small>Campus gate workspace</small>
            </span>
        </Link>
    );
}

function Navigation({ page, close }: { page: PageId; close?: () => void }) {
    const groups = [...new Set(navigation.map((item) => item.group))];
    return (
        <nav aria-label="Main navigation" className="main-navigation">
            {groups.map((group) => (
                <div className="nav-group" key={group}>
                    <p className="nav-group-label">{group}</p>
                    {navigation
                        .filter((item) => item.group === group)
                        .map(({ id, label, icon: Icon }) => (
                            <Link
                                href={pageUrl(id)}
                                key={id}
                                className={`nav-item ${page === id ? 'nav-active' : ''}`}
                                aria-current={page === id ? 'page' : undefined}
                                onClick={close}
                            >
                                <Icon size={18} strokeWidth={1.7} aria-hidden="true" />
                                <span>{label}</span>
                                {page === id && (
                                    <span className="nav-active-dot" aria-hidden="true" />
                                )}
                            </Link>
                        ))}
                </div>
            ))}
        </nav>
    );
}

export default function AppLayout({ page, children }: { page: PageId; children: ReactNode }) {
    const drawer = useRef<HTMLDialogElement>(null);
    const information = useRef<HTMLDialogElement>(null);
    const current = navigation.find((item) => item.id === page) ?? navigation[0];

    useEffect(() => {
        drawer.current?.close();
    }, [page]);

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
                    <img src="/images/bup-seal.png" width="34" height="34" alt="" />
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
                            className="mobile-menu"
                            onClick={() => drawer.current?.showModal()}
                            aria-label="Open navigation"
                            aria-haspopup="dialog"
                            aria-controls="mobile-navigation"
                        >
                            <Menu size={21} aria-hidden="true" />
                        </Button>
                        <nav aria-label="Breadcrumb">
                            <ol className="breadcrumb">
                                <li>Campus security</li>
                                <li aria-hidden="true">
                                    <ChevronRight size={14} />
                                </li>
                                <li>{current.label}</li>
                            </ol>
                        </nav>
                    </div>
                    <div className="topbar-right">
                        <Badge tone="blue">
                            <span className="status-dot" />
                            <span className="preview-label">Interface preview</span>
                            <span className="preview-short">Preview</span>
                        </Badge>
                        <Button
                            variant="quiet"
                            onClick={() => information.current?.showModal()}
                            aria-label="About this preview"
                            aria-haspopup="dialog"
                        >
                            <CircleHelp size={20} aria-hidden="true" />
                        </Button>
                        <span className="workspace-avatar" aria-hidden="true">
                            <ShieldCheck size={19} />
                        </span>
                    </div>
                </header>
                <main id="main-content" tabIndex={-1} aria-label={`${current.label} content`}>
                    <div className="content-container">
                        {children}
                        <footer className="content-footer">
                            <span>NFCheck · Campus gate verification & monitoring</span>
                            <span>Asia/Manila · UTC+8</span>
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
                        autoFocus
                        onClick={() => drawer.current?.close()}
                        aria-label="Close navigation"
                    >
                        <X size={21} aria-hidden="true" />
                    </Button>
                </div>
                <Navigation page={page} close={() => drawer.current?.close()} />
                <div className="drawer-footer">
                    <Link href="/login" onClick={() => drawer.current?.close()}>
                        <ArrowLeft size={16} aria-hidden="true" /> View sign-in screen
                    </Link>
                </div>
            </dialog>
            <dialog
                ref={information}
                className="information-dialog"
                aria-labelledby="preview-title"
            >
                <div className="dialog-heading">
                    <span className="dialog-symbol">
                        <ShieldCheck size={22} aria-hidden="true" />
                    </span>
                    <Button
                        variant="quiet"
                        autoFocus
                        onClick={() => information.current?.close()}
                        aria-label="Close preview information"
                    >
                        <X size={20} aria-hidden="true" />
                    </Button>
                </div>
                <h2 id="preview-title">A preview of your workspace</h2>
                <p>
                    Explore the navigation, layouts, and gate direction controls. Identity records
                    and gate activity are not connected, and no operational data is saved.
                </p>
                <div className="dialog-note">
                    <strong>Before gate operations</strong>
                    <p>
                        Secure sign-in, assigned gates, and credential verification must be
                        connected. This preview cannot authorize campus access.
                    </p>
                </div>
                <Link
                    href="/login"
                    className="button button-secondary"
                    onClick={() => information.current?.close()}
                >
                    <ArrowLeft size={16} aria-hidden="true" /> View sign-in screen
                </Link>
            </dialog>
        </div>
    );
}
