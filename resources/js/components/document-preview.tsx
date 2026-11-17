import { useHttp } from '@inertiajs/react';
import { Eye, Loader2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTrans } from '@/hooks/use-trans';

/**
 * "Preview": renders the editor's current (possibly unsaved) text in the PDF template on the server and shows it in
 * a sandboxed iframe (no scripts, no same-origin access), close to what the downloaded PDF looks like.
 */
export default function DocumentPreview({
    url,
    title,
    getContent,
}: {
    url: string;
    title: string;
    getContent: () => string;
}) {
    const { t } = useTrans();
    const [open, setOpen] = useState(false);
    const [html, setHtml] = useState<string | null>(null);
    const http = useHttp<{ content: string }, { html: string }>({
        content: '',
    });

    const show = () => {
        setOpen(true);
        setHtml(null);
        http.clearErrors();
        http.transform(() => ({ content: getContent() }));
        void http
            .post(url)
            .then((response) => setHtml(response.html))
            .catch(() => undefined);
    };

    return (
        <>
            <Button type="button" size="sm" variant="outline" onClick={show}>
                <Eye className="size-4" />
                {t('Preview')}
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="flex h-[90vh] max-w-[calc(100%-2rem)] flex-col gap-3 sm:max-w-4xl">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>
                            {t(
                                'How the PDF will look, including unsaved changes. The PDF splits long documents into A4 pages.',
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="min-h-0 flex-1 overflow-hidden rounded-md border bg-muted">
                        {html !== null ? (
                            <iframe
                                title={title}
                                srcDoc={html}
                                sandbox=""
                                className="size-full"
                            />
                        ) : http.errors.content ? (
                            <p className="p-4 text-sm text-destructive">
                                {http.errors.content}
                            </p>
                        ) : (
                            <p
                                className="flex h-full items-center justify-center gap-2 text-sm text-muted-foreground"
                                role="status"
                            >
                                {http.processing ? (
                                    <>
                                        <Loader2 className="size-4 animate-spin" />
                                        {t('Rendering the preview…')}
                                    </>
                                ) : (
                                    t(
                                        'The preview could not be rendered. Please try again.',
                                    )
                                )}
                            </p>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
