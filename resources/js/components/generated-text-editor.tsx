import { router, useForm } from '@inertiajs/react';
import type { RouteDefinition } from '@/wayfinder';
import { Copy, RotateCcw, Save } from 'lucide-react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTrans } from '@/hooks/use-trans';

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
    actions,
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
    actions?: ReactNode;
}) {
    const { t, locale } = useTrans();
    const form = useForm({ content });
    const fieldId = `content-${id}`;

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(form.data.content);
            toast.success(t('Copied to the clipboard.'));
        } catch {
            toast.error(
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
                {actions}
                {canEdit && edited && (
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        className="ml-auto"
                        disabled={form.processing}
                        onClick={() =>
                            router.visit(revert, {
                                preserveScroll: true,
                                preserveState: true,
                                onBefore: () =>
                                    confirm(
                                        t(
                                            'Discard your edits and bring back the generated text?',
                                        ),
                                    ),
                            })
                        }
                    >
                        <RotateCcw className="size-4" />
                        {t('Revert')}
                    </Button>
                )}
            </div>
        </form>
    );
}
