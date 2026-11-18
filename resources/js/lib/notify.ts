import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

/** Roughly how long it takes to read it: at least 10 s, more for long ones (provider details), at most 30 s. */
function readingDuration(message: string): number {
    return Math.min(30_000, Math.max(10_000, message.length * 60));
}

/**
 * Errors and warnings stay up long enough to read, pause on hover (Sonner's default) and have an X to close them
 * early; successes and info stay short.
 */
export function notify(type: FlashToast['type'], message: string): void {
    if (type === 'error' || type === 'warning') {
        toast[type](message, {
            duration: readingDuration(message),
            closeButton: true,
            // Sonner puts the X half outside the top-left corner; keep it inside, top right, where people look for it.
            classNames: {
                toast: '!pr-10',
                closeButton:
                    '!top-3 !right-3 !left-auto !transform-none !size-6 !border-0 !bg-transparent !text-muted-foreground hover:!text-foreground !opacity-100',
            },
        });

        return;
    }

    toast[type](message);
}
