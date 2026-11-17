import type { KeyboardEvent, ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type TabItem = { value: string; label: ReactNode };

/**
 * A minimal accessible tab list (WAI-ARIA tabs pattern): arrow keys, Home and End move between tabs.
 * Pair each tab with a `TabPanel` of the same `id` and `value`.
 */
export function TabList({
    id,
    tabs,
    value,
    onValueChange,
    label,
    className,
}: {
    id: string;
    tabs: TabItem[];
    value: string;
    onValueChange: (value: string) => void;
    label: string;
    className?: string;
}) {
    const move = (event: KeyboardEvent<HTMLDivElement>) => {
        const index = tabs.findIndex((tab) => tab.value === value);
        const next = {
            ArrowRight: (index + 1) % tabs.length,
            ArrowLeft: (index - 1 + tabs.length) % tabs.length,
            Home: 0,
            End: tabs.length - 1,
        }[event.key];

        if (next === undefined) {
            return;
        }

        event.preventDefault();
        onValueChange(tabs[next].value);
        document.getElementById(`${id}-tab-${tabs[next].value}`)?.focus();
    };

    return (
        <div
            role="tablist"
            aria-label={label}
            onKeyDown={move}
            className={cn(
                'flex gap-1 overflow-x-auto overflow-y-hidden border-b [scrollbar-width:thin]',
                className,
            )}
        >
            {tabs.map((tab) => {
                const selected = tab.value === value;

                return (
                    <button
                        key={tab.value}
                        id={`${id}-tab-${tab.value}`}
                        type="button"
                        role="tab"
                        aria-selected={selected}
                        aria-controls={`${id}-panel-${tab.value}`}
                        tabIndex={selected ? 0 : -1}
                        onClick={() => onValueChange(tab.value)}
                        className={cn(
                            '-mb-px flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                            selected
                                ? 'border-primary text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {tab.label}
                    </button>
                );
            })}
        </div>
    );
}

export function TabPanel({
    id,
    value,
    children,
    className,
}: {
    id: string;
    value: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            role="tabpanel"
            id={`${id}-panel-${value}`}
            aria-labelledby={`${id}-tab-${value}`}
            tabIndex={0}
            className={cn('outline-none', className)}
        >
            {children}
        </div>
    );
}
