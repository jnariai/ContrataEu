import { useForm } from '@inertiajs/react';
import type { RouteDefinition } from '@/wayfinder';
import {
    Check,
    Copy,
    Download,
    Pencil,
    RotateCcw,
    Save,
    X,
} from 'lucide-react';
import { Fragment, useEffect, useRef, useState, type ReactNode } from 'react';
import { toast } from 'sonner';
import ConfirmDialog from '@/components/confirm-dialog';
import DocumentPreview from '@/components/document-preview';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTrans } from '@/hooks/use-trans';
import { notify } from '@/lib/notify';
import { cn } from '@/lib/utils';

const TODO = /(\[TODO:[^\]]*\])/g;

/**
 * Text the AI wrote (a document or an answer), shown as it will be sent on the paper sheet, with `[TODO: …]`
 * placeholders highlighted. "Edit" swaps the sheet for the source in the same place: save, cancel, copy, preview,
 * and revert to the generated text. Callers key it by id + `updated_at`, so a save or revert resets it to reading.
 */
export default function GeneratedTextEditor({
    id,
    label,
    content,
    html,
    edited,
    maxLength,
    rows,
    update,
    revert,
    canEdit,
    pdfUrl,
    preview,
    compact = false,
}: {
    id: string;
    label: ReactNode;
    content: string;
    /** The document rendered as the PDF renders it (the CV); plain text is shown as paragraphs otherwise. */
    html?: string | null;
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
    /** Screening answers: a smaller sheet, the question as its heading. */
    compact?: boolean;
}) {
    const { t, locale } = useTrans();
    const form = useForm({ content });
    const [editing, setEditing] = useState(false);
    const [copied, setCopied] = useState(false);
    const fieldId = `content-${id}`;

    useEffect(() => {
        if (!copied) {
            return;
        }

        const timer = window.setTimeout(() => setCopied(false), 2000);

        return () => window.clearTimeout(timer);
    }, [copied]);
    const nearLimit = form.data.content.length > maxLength * 0.8;
    const notes = (edited || form.isDirty) && (
        <span className="flex flex-wrap items-baseline gap-x-3">
            {edited && (
                <span className="text-xs font-medium text-muted-foreground">
                    {t('Edited')}
                </span>
            )}
            {form.isDirty && (
                <span className="text-xs font-medium text-todo-foreground dark:text-todo">
                    {t('Unsaved changes')}
                </span>
            )}
        </span>
    );

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(form.data.content);
            setCopied(true);
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
            {/* A document is named by the tab it hangs from, so its heading is for screen readers only and the
                sheet stays joined to the tab in every state; an answer shows its question. */}
            <div
                className={cn(
                    'flex flex-wrap items-baseline gap-x-3 gap-y-1',
                    !compact && 'sr-only',
                )}
            >
                {editing ? (
                    <Label
                        htmlFor={fieldId}
                        className="text-[0.9375rem] font-semibold"
                    >
                        {label}
                    </Label>
                ) : (
                    <h3 className="text-[0.9375rem] font-semibold">{label}</h3>
                )}
                {compact && notes}
            </div>

            <div
                className={cn(
                    'rounded-[2px] bg-sheet text-sheet-foreground shadow-[0_1px_0_var(--border),0_14px_30px_-16px_rgb(20_32_46/0.32)]',
                    // Documents join the tab that opened them, like a sheet pulled from its folder.
                    !compact && '!mt-0 rounded-t-none',
                    editing
                        ? 'p-2 sm:p-3'
                        : compact
                          ? 'px-5 py-5 sm:px-8 sm:py-6'
                          : 'px-5 py-7 sm:px-12 sm:py-12 xl:px-16 xl:py-14',
                )}
            >
                {editing ? (
                    <Textarea
                        id={fieldId}
                        value={form.data.content}
                        onChange={(event) =>
                            form.setData('content', event.target.value)
                        }
                        required
                        maxLength={maxLength}
                        rows={rows}
                        autoFocus
                        aria-invalid={form.errors.content ? true : undefined}
                        className="border-transparent bg-transparent font-mono text-[0.8125rem] leading-relaxed text-sheet-foreground"
                    />
                ) : html ? (
                    <RenderedDocument html={html} />
                ) : (
                    <PlainDocument text={form.data.content} />
                )}
            </div>

            {editing && (
                <div className="flex items-center justify-between gap-2">
                    <InputError message={form.errors.content} />
                    {nearLimit && (
                        <span className="ml-auto text-xs text-muted-foreground tabular-nums">
                            {form.data.content.length.toLocaleString(
                                locale.htmlLang,
                            )}{' '}
                            / {maxLength.toLocaleString(locale.htmlLang)}
                        </span>
                    )}
                </div>
            )}

            {/* Touch-sized on phones. A document's bar sticks to the bottom while you read it; an answer's stays right
                under its own text, so with several answers on screen each Copy is unmistakably its own. */}
            <div
                className={cn(
                    'flex flex-wrap items-center gap-2 max-sm:[&_a]:h-11 max-sm:[&_button]:h-11',
                    !compact &&
                        'sticky bottom-0 z-10 border-t bg-background/95 py-3 backdrop-blur-sm sm:static sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none',
                )}
            >
                {editing ? (
                    <>
                        <Button
                            type="submit"
                            size="sm"
                            disabled={form.processing || !form.isDirty}
                        >
                            <Save />
                            {t('Save')}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => {
                                form.reset();
                                setEditing(false);
                            }}
                        >
                            <X />
                            {t('Cancel')}
                        </Button>
                    </>
                ) : (
                    canEdit && (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => setEditing(true)}
                        >
                            <Pencil />
                            {t('Edit')}
                        </Button>
                    )
                )}
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={copy}
                >
                    {copied ? <Check /> : <Copy />}
                    {copied ? t('Copied') : t('Copy')}
                </Button>
                {preview && editing && (
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
                            <Download />
                            {t('Download PDF')}
                        </Button>
                    ) : (
                        <Button size="sm" variant="outline" asChild>
                            <a href={pdfUrl}>
                                <Download />
                                {t('Download PDF')}
                            </a>
                        </Button>
                    ))}
                {!compact && notes && <span className="ml-auto">{notes}</span>}
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
                                className={cn(compact && 'ml-auto')}
                                disabled={form.processing}
                            >
                                <RotateCcw />
                                {t('Revert')}
                            </Button>
                        }
                    />
                )}
            </div>
        </form>
    );
}

/**
 * The server's rendering of the CV (raw HTML stripped, unsafe links dropped: see GenerationResource), with each
 * `[TODO: …]` wrapped in a highlight after it is mounted.
 */
function RenderedDocument({ html }: { html: string }) {
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const root = ref.current;

        if (!root) {
            return;
        }

        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        const matches: Text[] = [];

        while (walker.nextNode()) {
            const node = walker.currentNode as Text;

            if (node.data.includes('[TODO:')) {
                matches.push(node);
            }
        }

        for (const node of matches) {
            const fragment = document.createDocumentFragment();

            for (const part of node.data.split(TODO)) {
                if (part.startsWith('[TODO:')) {
                    const mark = document.createElement('mark');
                    mark.textContent = part;
                    fragment.append(mark);
                } else if (part !== '') {
                    fragment.append(part);
                }
            }

            node.replaceWith(fragment);
        }

        // The document's own headings (name, sections, roles) sit under the page's "Documents" heading in the outline,
        // as levels 4 to 6, while keeping their tags for the PDF-matching styles.
        for (const heading of root.querySelectorAll('h1, h2, h3')) {
            heading.setAttribute('role', 'heading');
            heading.setAttribute(
                'aria-level',
                String(Number(heading.tagName[1]) + 3),
            );
        }
    }, [html]);

    return (
        <div
            ref={ref}
            className="sheet-document"
            dangerouslySetInnerHTML={{ __html: html }}
        />
    );
}

/** Letters, messages and answers: their paragraphs as written, placeholders highlighted. */
function PlainDocument({ text }: { text: string }) {
    return (
        <div className="sheet-document space-y-3">
            {text.split(/\n\s*\n/).map((paragraph, index) => (
                <p key={index} className="whitespace-pre-wrap">
                    {paragraph
                        .split(TODO)
                        .map((part, partIndex) =>
                            part.startsWith('[TODO:') ? (
                                <mark key={partIndex}>{part}</mark>
                            ) : (
                                <Fragment key={partIndex}>{part}</Fragment>
                            ),
                        )}
                </p>
            ))}
        </div>
    );
}
