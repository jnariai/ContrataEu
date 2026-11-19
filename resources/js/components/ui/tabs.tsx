import { useEffect, useRef, useState, type KeyboardEvent, type ReactNode } from 'react';
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
    const listRef = useRef<HTMLDivElement>(null);
    // Which edges have more tabs past them, so a narrow strip fades only on the side that scrolls.
    const [overflow, setOverflow] = useState({ start: false, end: false });

    const measure = () => {
        const list = listRef.current;

        if (!list) {
            return;
        }

        setOverflow({
            start: list.scrollLeft > 1,
            end: list.scrollLeft + list.clientWidth < list.scrollWidth - 1,
        });
    };

    useEffect(() => {
        measure();
        window.addEventListener('resize', measure);

        return () => window.removeEventListener('resize', measure);
    }, []);

    // Keep the picked tab in view (opened from elsewhere on the page, or by keyboard).
    useEffect(() => {
        const list = listRef.current;
        const tab = document.getElementById(`${id}-tab-${value}`);

        // Horizontal only: scrollIntoView would also move the page.
        if (list && tab) {
            const left = tab.offsetLeft - list.offsetLeft;

            if (left < list.scrollLeft) {
                list.scrollLeft = left - 24;
            } else if (left + tab.offsetWidth > list.scrollLeft + list.clientWidth) {
                list.scrollLeft = left + tab.offsetWidth - list.clientWidth + 24;
            }
        }

        measure();
    }, [id, value]);

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
            ref={listRef}
            role="tablist"
            aria-label={label}
            onKeyDown={move}
            onScroll={measure}
            style={{
                maskImage: `linear-gradient(to right, ${overflow.start ? 'transparent, black 2rem' : 'black, black'}, ${overflow.end ? 'black calc(100% - 2rem), transparent' : 'black'})`,
            }}
            className={cn(
                'flex gap-0.5 overflow-x-auto overflow-y-hidden border-b [scrollbar-width:none] [&::-webkit-scrollbar]:hidden',
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
                            '-mb-px flex min-h-11 shrink-0 items-center gap-1.5 rounded-t-[3px] border border-b-0 px-3.5 py-2 text-sm sm:min-h-0 font-medium whitespace-nowrap transition-colors outline-none focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring',
                            selected
                                ? 'border-border bg-sheet font-semibold text-sheet-foreground shadow-[0_1px_0_var(--sheet)]'
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
