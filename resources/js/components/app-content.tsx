import type * as React from 'react';
import { cn } from '@/lib/utils';

/** The one page frame: every signed-in page shares the band's width and gutters, so content edges never move. */
export function AppContent({
    children,
    className,
    ...props
}: React.ComponentProps<'main'>) {
    return (
        <main
            className={cn(
                'mx-auto flex w-full max-w-[90rem] flex-1 flex-col px-4 sm:px-6 lg:px-12',
                className,
            )}
            {...props}
        >
            {children}
        </main>
    );
}
