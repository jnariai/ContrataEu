import { cn } from '@/lib/utils';
import type { StatusOption } from '@/types';

/** Server sends the color name with the status; this maps it to badge classes (light and dark). */
const colors: Record<string, string> = {
    gray: 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300',
    blue: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
    indigo: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300',
    amber: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    green: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
    red: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    slate: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
};

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
                'inline-flex shrink-0 items-center rounded-md px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                colors[status.color] ?? colors.gray,
                className,
            )}
        >
            {status.label}
        </span>
    );
}
