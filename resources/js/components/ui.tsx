import { Link } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    CircleAlert,
    LoaderCircle,
    Search,
    type LucideIcon,
} from 'lucide-react';
import {
    useEffect,
    useId,
    useRef,
    useState,
    type ButtonHTMLAttributes,
    type ReactNode,
} from 'react';

export function Button({
    children,
    variant = 'secondary',
    className = '',
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?: 'primary' | 'secondary' | 'quiet';
    children: ReactNode;
}) {
    return (
        <button type="button" className={`button button-${variant} ${className}`} {...props}>
            {children}
        </button>
    );
}

export function ActionLink({
    href,
    children,
    secondary = false,
}: {
    href: string;
    children: ReactNode;
    secondary?: boolean;
}) {
    return (
        <Link href={href} className={`button button-${secondary ? 'secondary' : 'primary'}`}>
            {children}
        </Link>
    );
}

export function Badge({
    children,
    tone = 'neutral',
}: {
    children: ReactNode;
    tone?: 'neutral' | 'blue' | 'warning' | 'success';
}) {
    return <span className={`badge badge-${tone}`}>{children}</span>;
}

export function Panel({
    title,
    subtitle,
    action,
    children,
    className = '',
}: {
    title?: string;
    subtitle?: string;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section className={`panel ${className}`}>
            {title && (
                <div className="panel-heading">
                    <div>
                        <h2>{title}</h2>
                        {subtitle && <p>{subtitle}</p>}
                    </div>
                    {action}
                </div>
            )}
            {children}
        </section>
    );
}

export function PageHeading({
    title,
    description,
    action,
}: {
    eyebrow?: string;
    title: string;
    description: string;
    action?: ReactNode;
}) {
    return (
        <div className="page-heading">
            <div>
                <h1>{title}</h1>
                {description && <p className="page-description">{description}</p>}
            </div>
            {action && <div className="heading-action">{action}</div>}
        </div>
    );
}

export function EmptyState({
    icon: Icon,
    title,
    description,
    compact = false,
}: {
    icon?: LucideIcon;
    title: string;
    description: string;
    compact?: boolean;
}) {
    return (
        <div className={`empty-state ${compact ? 'empty-compact' : ''}`}>
            {Icon && (
                <span className="empty-icon">
                    <Icon size={22} strokeWidth={1.5} aria-hidden="true" />
                </span>
            )}
            <h3>{title}</h3>
            <p>{description}</p>
        </div>
    );
}

export function LoadingState({
    message = 'Loading workspace…',
    compact = false,
}: {
    message?: string;
    compact?: boolean;
}) {
    return (
        <div
            className={
                compact
                    ? 'flex items-center gap-2 rounded-lg border border-[var(--border-soft)] bg-[var(--surface)] px-4 py-3 text-sm shadow-lg'
                    : 'state-message'
            }
            role="status"
            aria-live="polite"
        >
            <LoaderCircle className="spin" size={compact ? 17 : 24} aria-hidden="true" />
            <p>{message}</p>
        </div>
    );
}

export function ErrorState({
    title = 'This view could not be loaded',
    description,
    action,
}: {
    title?: string;
    description: string;
    action?: ReactNode;
}) {
    return (
        <div className="empty-state" role="alert">
            <span className="empty-icon error-icon">
                <CircleAlert size={25} aria-hidden="true" />
            </span>
            <h2>{title}</h2>
            <p>{description}</p>
            {action}
        </div>
    );
}

export function Notice({
    children,
    title,
    icon: Icon = CircleAlert,
}: {
    title?: string;
    children: ReactNode;
    icon?: LucideIcon;
}) {
    return (
        <div className="notice">
            <Icon size={18} aria-hidden="true" />
            <div>
                {title && <strong>{title}</strong>}
                <p>{children}</p>
            </div>
        </div>
    );
}

export function SearchField({
    label,
    placeholder,
    disabled = false,
}: {
    label: string;
    placeholder: string;
    disabled?: boolean;
}) {
    const id = useId();
    return (
        <div className="search-field">
            <label className="sr-only" htmlFor={id}>
                {label}
            </label>
            <Search size={17} aria-hidden="true" />
            <input id={id} type="search" placeholder={placeholder} disabled={disabled} />
        </div>
    );
}

export function SelectField({
    label,
    children,
    disabled = false,
}: {
    label: string;
    children: ReactNode;
    disabled?: boolean;
}) {
    const id = useId();
    return (
        <div className="select-field">
            <label className="sr-only" htmlFor={id}>
                {label}
            </label>
            <select id={id} disabled={disabled}>
                {children}
            </select>
        </div>
    );
}

export function EmptyTable({
    columns,
    icon,
    title,
    description,
    caption,
}: {
    columns: string[];
    icon?: LucideIcon;
    title: string;
    description: string;
    caption: string;
}) {
    const container = useRef<HTMLDivElement>(null);
    const [scrollable, setScrollable] = useState(false);

    useEffect(() => {
        const element = container.current;
        if (!element) return;
        const updateScrollable = () => setScrollable(element.scrollWidth > element.clientWidth + 1);
        updateScrollable();
        const observer = new ResizeObserver(updateScrollable);
        observer.observe(element);
        if (element.firstElementChild) observer.observe(element.firstElementChild);
        return () => observer.disconnect();
    }, [caption]);

    return (
        <div
            ref={container}
            className="data-table-wrap"
            tabIndex={scrollable ? 0 : undefined}
            role={scrollable ? 'region' : undefined}
            aria-label={scrollable ? `${caption}; horizontally scrollable` : undefined}
        >
            <table className="data-table">
                <caption className="sr-only">{caption}</caption>
                <thead>
                    <tr>
                        {columns.map((column) => (
                            <th scope="col" key={column}>
                                {column}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colSpan={columns.length}>
                            <EmptyState icon={icon} title={title} description={description} />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    );
}

export function EmptyPagination() {
    return (
        <div className="pagination">
            <p>Records are not connected</p>
            <div>
                <Button disabled aria-label="Previous page">
                    <ChevronLeft size={16} aria-hidden="true" />
                </Button>
                <span>—</span>
                <Button disabled aria-label="Next page">
                    <ChevronRight size={16} aria-hidden="true" />
                </Button>
            </div>
        </div>
    );
}

export function ChoiceGroup<T extends string>({
    label,
    value,
    onChange,
    choices,
    className = '',
}: {
    label: string;
    value: T;
    onChange: (value: T) => void;
    choices: { value: T; label: string; icon?: LucideIcon }[];
    className?: string;
}) {
    const name = useId();
    return (
        <fieldset className={`choice-group ${className}`}>
            <legend className="sr-only">{label}</legend>
            {choices.map(({ value: option, label: text, icon: Icon }) => (
                <label key={option} className={value === option ? 'choice-selected' : ''}>
                    <input
                        type="radio"
                        name={name}
                        value={option}
                        checked={value === option}
                        onChange={() => onChange(option)}
                    />
                    {Icon && <Icon size={18} aria-hidden="true" />}
                    <span>{text}</span>
                </label>
            ))}
        </fieldset>
    );
}
