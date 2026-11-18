import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useTrans } from '@/hooks/use-trans';

type VisitOptions = NonNullable<Parameters<typeof router.visit>[1]>;

type Props = {
    /** The button that opens the dialog. */
    trigger: ReactNode;
    title: string;
    description?: string;
    confirmLabel: string;
    /** The request to send once confirmed, from Wayfinder (e.g. `AiCredentialController.destroy(id)`). */
    action: {
        url: string;
        method: 'get' | 'post' | 'put' | 'patch' | 'delete';
    };
    destructive?: boolean;
    visitOptions?: Omit<VisitOptions, 'method' | 'onStart' | 'onFinish'>;
};

/** Asks before a destructive or hard-to-undo request, then sends it; stays open while it runs. */
export default function ConfirmDialog({
    trigger,
    title,
    description,
    confirmLabel,
    action,
    destructive = true,
    visitOptions,
}: Props) {
    const { t } = useTrans();
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const confirm = () =>
        router.visit(action.url, {
            preserveScroll: true,
            ...visitOptions,
            method: action.method,
            onStart: () => setProcessing(true),
            onSuccess: (page) => {
                setOpen(false);
                visitOptions?.onSuccess?.(page);
            },
            onFinish: () => setProcessing(false),
        });

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => !processing && setOpen(next)}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description && (
                        <DialogDescription>{description}</DialogDescription>
                    )}
                </DialogHeader>
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="outline" disabled={processing}>
                            {t('Cancel')}
                        </Button>
                    </DialogClose>
                    <Button
                        variant={destructive ? 'destructive' : 'default'}
                        disabled={processing}
                        onClick={confirm}
                    >
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
