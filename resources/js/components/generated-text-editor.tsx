import { useForm } from '@inertiajs/react';
import type { RouteDefinition } from '@/wayfinder';
import { Copy, Download, RotateCcw, Save } from 'lucide-react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import ConfirmDialog from '@/components/confirm-dialog';
import DocumentPreview from '@/components/document-preview';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTrans } from '@/hooks/use-trans';
import { notify } from '@/lib/notify';

/**
 * Edits text the AI wrote (a document or an answer): save, copy, and revert to the generated text.
 * Callers key it by id + `updated_at`, so a save or revert from the server resets the field.
 */
export default function GeneratedTextEditor({
    id,
    label,
    content,
    edited,
    maxLength,
    rows,
    update,
    revert,
    canEdit,
    pdfUrl,
    preview,
}: {
    id: string;
    label: ReactNode;
    content: string;
    edited: boolean;
    maxLength: number;
    rows: number;
    update: RouteDefinition<'patch'>;
    revert: RouteDefinition<'post'>;
    canEdit: boolean;
    /** Download link for documents that have a PDF; the PDF shows the saved text, so it waits for a save. */
    pdfUrl?: string;
    /** Server-rendered PDF-template preview of the current text (CV and cover letter). */
    preview?: { url: string; title: string };
}) {
    const { t, locale } = useTrans();
    const form = useForm({ content });
    const fieldId = `content-${id}`;

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(form.data.content);
            toast.success(t('Copied to the clipboard.'));
        } catch {
            notify(
                'error',
                t('Could not copy. Select the text and copy it yourself.'),
            );
        }
    };

    return (
        <form
            className="space-y-3"
            onSubmit={(event) => {
                event.preventDefault();
                form.submit(update, {
                    preserveScroll: true,
                    preserveState: true,
                });
            }}
        >
            <div className="flex flex-wrap items-center gap-2">
                <Label htmlFor={fieldId} className="font-medium">
                    {label}
                </Label>
                {edited && <Badge variant="secondary">{t('Edited')}</Badge>}
                {form.isDirty && (
                    <span className="text-xs text-muted-foreground">
                        {t('Unsaved changes')}
                    </span>
                )}
            </div>

            <Textarea
                id={fieldId}
                value={form.data.content}
                onChange={(event) =>
                    form.setData('content', event.target.value)
                }
                readOnly={!canEdit}
                required
                maxLength={maxLength}
                rows={rows}
                aria-invalid={form.errors.content ? true : undefined}
                className="font-mono text-sm"
            />

            <div className="flex items-center justify-between gap-2">
                <InputError message={form.errors.content} />
                <span className="ml-auto text-xs text-muted-foreground tabular-nums">
                    {form.data.content.length.toLocaleString(locale.htmlLang)} /{' '}
                    {maxLength.toLocaleString(locale.htmlLang)}
                </span>
            </div>

            <div className="flex flex-wrap gap-2">
                {canEdit && (
                    <Button
                        type="submit"
                        size="sm"
                        disabled={form.processing || !form.isDirty}
                    >
                        <Save className="size-4" />
                        {t('Save')}
                    </Button>
                )}
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={copy}
                >
                    <Copy className="size-4" />
                    {t('Copy')}
                </Button>
                {preview && (
                    <DocumentPreview
                        url={preview.url}
                        title={preview.title}
                        getContent={() => form.data.content}
                    />
                )}
                {pdfUrl &&
                    (form.isDirty ? (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled
                            title={t(
                                'Save your changes to download them as a PDF.',
                            )}
                        >
                            <Download className="size-4" />
                            {t('Download PDF')}
                        </Button>
                    ) : (
                        <Button size="sm" variant="outline" asChild>
                            <a href={pdfUrl}>
                                <Download className="size-4" />
                                {t('Download PDF')}
                            </a>
                        </Button>
                    ))}
                {canEdit && edited && (
                    <ConfirmDialog
                        title={t(
                            'Discard your edits and bring back the generated text?',
                        )}
                        description={t('Your edits to this text are lost.')}
                        confirmLabel={t('Revert')}
                        action={revert}
                        visitOptions={{ preserveState: true }}
                        trigger={
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                className="ml-auto"
                                disabled={form.processing}
                            >
                                <RotateCcw className="size-4" />
                                {t('Revert')}
                            </Button>
                        }
                    />
                )}
            </div>
        </form>
    );
}
