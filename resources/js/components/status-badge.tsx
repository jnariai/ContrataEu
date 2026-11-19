import { cn } from '@/lib/utils';
import type { StatusOption } from '@/types';

/**
 * Status inks, as the office stamps (carimbos) that mark working papers: violet is the classic stamp ink for
 * "applied"; rejected and withdrawn are voids. Keyed by the status value, so the server's colour name is not needed.
 */
export const statusInk: Record<string, string> = {
    draft: 'text-ink-grey border-ink-grey border-dashed',
    ready: 'text-ink-grey border-ink-grey',
    applied: 'text-ink-violet border-ink-violet',
    interviewing: 'text-ink-blue border-ink-blue',
    offer: 'text-ink-green border-ink-green',
    rejected: 'text-ink-red border-ink-red',
    withdrawn: 'text-ink-red border-ink-red border-dashed',
};

/** The compact stamp: a status in its ink, readable as text (never colour alone). Used in lists and menus. */
export default function StatusBadge({
    status,
    className,
}: {
    status: StatusOption;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center rounded-[3px] border-[1.5px] px-1.5 py-px text-[0.6875rem] leading-4 font-bold tracking-[0.08em] whitespace-nowrap uppercase',
                statusInk[status.value] ?? statusInk.draft,
                className,
            )}
        >
            {status.label}
        </span>
    );
}
