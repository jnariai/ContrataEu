import { Form, Head, Link } from '@inertiajs/react';
import { FileText, Trash2, Upload } from 'lucide-react';
import { useState } from 'react';
import MasterCvPreviewController from '@/actions/App/Http/Controllers/Settings/MasterCvPreviewController';
import CvImportController from '@/actions/App/Http/Controllers/Settings/CvImportController';
import MasterDocumentController from '@/actions/App/Http/Controllers/Settings/MasterDocumentController';
import MasterDocumentFileController from '@/actions/App/Http/Controllers/Settings/MasterDocumentFileController';
import CvImport from '@/components/cv-import';
import DocumentPreview from '@/components/document-preview';
import type { CvImportState } from '@/components/cv-import';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTrans } from '@/hooks/use-trans';
import { formatBytes, formatDateTime } from '@/lib/format';
import { tk } from '@/lib/i18n';
import { show as showFile } from '@/routes/files';
import { index } from '@/routes/master-documents';

type MasterDocument = {
    type: 'cv' | 'cover_letter' | 'recruiter_message';
    label: string;
    required: boolean;
    content: string | null;
    updated_at: string | null;
    file: { id: string; name: string | null; size_bytes: number } | null;
};

type Props = {
    documents: MasterDocument[];
    cvImport: CvImportState | null;
    canImportCv: { allowed: boolean; reason: string | null };
    maxCharacters: number;
    maxUploadKilobytes: number;
};

const hints: Record<MasterDocument['type'], string> = {
    cv: tk(
        'Your full master CV in Markdown: every role, project, metric and skill. Generations pick and rewrite from it, and never add facts that are not here.',
    ),
    cover_letter: tk(
        'Optional. A cover letter you like, used as the base for new ones.',
    ),
    recruiter_message: tk(
        'Optional. A recruiter message whose voice and structure the AI should follow.',
    ),
};

export default function Documents({
    documents,
    cvImport,
    canImportCv,
    maxCharacters,
    maxUploadKilobytes,
}: Props) {
    const { t } = useTrans();

    return (
        <>
            <Head title={t('Documents')} />

            <div className="space-y-12">
                <Heading
                    variant="small"
                    title={t('Documents')}
                    description={t(
                        'The base documents every application is tailored from.',
                    )}
                />

                {documents.map((document) => (
                    <DocumentEditor
                        key={
                            document.type === 'cv' && cvImport?.markdown
                                ? `cv:${cvImport.id}`
                                : document.type
                        }
                        document={document}
                        cvImport={document.type === 'cv' ? cvImport : null}
                        canImportCv={canImportCv}
                        maxCharacters={maxCharacters}
                        maxUploadKilobytes={maxUploadKilobytes}
                    />
                ))}
            </div>
        </>
    );
}

function DocumentEditor({
    document,
    cvImport,
    canImportCv,
    maxCharacters,
    maxUploadKilobytes,
}: {
    document: MasterDocument;
    cvImport: CvImportState | null;
    canImportCv: { allowed: boolean; reason: string | null };
    maxCharacters: number;
    maxUploadKilobytes: number;
}) {
    const { t, locale } = useTrans();
    // A finished PDF import fills the CV editor (unsaved); the field remounts when it arrives or goes away.
    const imported = cvImport?.markdown ? cvImport : null;
    const content = imported?.markdown ?? document.content ?? '';
    const [length, setLength] = useState(content.length);
    const fieldId = `content-${document.type}`;

    return (
        <section className="space-y-4 rounded-xl border p-4 sm:p-6">
            <div className="flex flex-wrap items-center gap-2">
                <h3 className="font-medium">{document.label}</h3>
                <Badge variant={document.required ? 'default' : 'secondary'}>
                    {document.required ? t('Required') : t('Optional')}
                </Badge>
                {document.updated_at && (
                    <span className="ml-auto text-xs text-muted-foreground">
                        {t('Saved :date', {
                            date: formatDateTime(
                                document.updated_at,
                                locale.htmlLang,
                            ),
                        })}
                    </span>
                )}
            </div>

            {document.type === 'cv' && (
                <CvImport
                    cvImport={cvImport}
                    canImport={canImportCv}
                    maxUploadKilobytes={maxUploadKilobytes}
                />
            )}

            {imported && (
                <div
                    className="flex flex-wrap items-start gap-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
                    role="status"
                >
                    <p className="min-w-0 flex-1">
                        <span className="font-medium">
                            {t('Imported from :file, not saved yet.', {
                                file: imported.file_name ?? 'PDF',
                            })}
                        </span>{' '}
                        {t(
                            'Check every section against your PDF, then Save. Saving also keeps the PDF as your CV’s original file.',
                        )}
                    </p>
                    <Button variant="outline" size="sm" asChild>
                        <Link
                            href={CvImportController.destroy(imported.id)}
                            as="button"
                            preserveScroll
                            onBefore={() =>
                                confirm(
                                    t(
                                        'Discard the imported text and keep your saved CV?',
                                    ),
                                )
                            }
                        >
                            {t('Discard import')}
                        </Link>
                    </Button>
                </div>
            )}

            <Form
                {...MasterDocumentController.update.form(document.type)}
                options={{ preserveScroll: true }}
                className="space-y-3"
            >
                {({ processing, errors }) => (
                    <>
                        <Label htmlFor={fieldId} className="sr-only">
                            {document.label}
                        </Label>
                        <p className="text-sm text-muted-foreground">
                            {t(hints[document.type])}
                        </p>
                        {imported && (
                            <input
                                type="hidden"
                                name="import"
                                value={imported.id}
                            />
                        )}
                        <Textarea
                            id={fieldId}
                            name="content"
                            defaultValue={content}
                            required={document.required}
                            maxLength={maxCharacters}
                            rows={document.type === 'cv' ? 18 : 8}
                            className="font-mono text-sm"
                            onChange={(event) =>
                                setLength(event.target.value.length)
                            }
                        />
                        <div className="flex items-center justify-between gap-2">
                            <InputError message={errors.content} />
                            <span className="ml-auto text-xs text-muted-foreground tabular-nums">
                                {length.toLocaleString(locale.htmlLang)} /{' '}
                                {maxCharacters.toLocaleString(locale.htmlLang)}
                            </span>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button disabled={processing}>{t('Save')}</Button>
                            {document.type === 'cv' && (
                                <DocumentPreview
                                    url={MasterCvPreviewController.store.url()}
                                    title={document.label}
                                    getContent={() =>
                                        (
                                            window.document.getElementById(
                                                fieldId,
                                            ) as HTMLTextAreaElement | null
                                        )?.value ?? ''
                                    }
                                />
                            )}
                        </div>
                    </>
                )}
            </Form>

            {document.content !== null && (
                <OriginalFile
                    document={document}
                    maxUploadKilobytes={maxUploadKilobytes}
                />
            )}
        </section>
    );
}

function OriginalFile({
    document,
    maxUploadKilobytes,
}: {
    document: MasterDocument;
    maxUploadKilobytes: number;
}) {
    const { t, locale } = useTrans();
    const inputId = `file-${document.type}`;

    return (
        <div className="space-y-3 border-t pt-4">
            <div>
                <p className="text-sm font-medium">{t('Original file')}</p>
                <p className="text-xs text-muted-foreground">
                    {t(
                        'Optional. Keep the PDF or DOCX you started from, for reference (max :size).',
                        {
                            size: formatBytes(
                                maxUploadKilobytes * 1024,
                                locale.htmlLang,
                            ),
                        },
                    )}
                </p>
            </div>

            {document.file && (
                <div className="flex flex-wrap items-center gap-3 rounded-md bg-muted/50 p-3 text-sm">
                    <FileText className="size-4 text-muted-foreground" />
                    <a
                        href={showFile.url(document.file.id)}
                        className="font-medium underline-offset-4 hover:underline"
                    >
                        {document.file.name ?? t('Download')}
                    </a>
                    <span className="text-muted-foreground">
                        {formatBytes(document.file.size_bytes, locale.htmlLang)}
                    </span>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="ml-auto text-destructive hover:text-destructive"
                        asChild
                    >
                        <Link
                            href={MasterDocumentFileController.destroy(
                                document.type,
                            )}
                            as="button"
                            preserveScroll
                            onBefore={() =>
                                confirm(t('Remove the original file?'))
                            }
                        >
                            <Trash2 className="size-4" />
                            {t('Remove')}
                        </Link>
                    </Button>
                </div>
            )}

            <Form
                {...MasterDocumentFileController.store.form(document.type)}
                options={{ preserveScroll: true }}
                resetOnSuccess
                className="flex flex-wrap items-center gap-3"
            >
                {({ processing, errors }) => (
                    <>
                        <Label htmlFor={inputId} className="sr-only">
                            {t('Original file')}
                        </Label>
                        <input
                            id={inputId}
                            type="file"
                            name="file"
                            accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                            required
                            className="max-w-full text-sm file:mr-3 file:rounded-md file:border file:bg-background file:px-3 file:py-1.5 file:text-sm"
                        />
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={processing}
                        >
                            <Upload className="size-4" />
                            {document.file ? t('Replace') : t('Upload')}
                        </Button>
                        <InputError message={errors.file} className="w-full" />
                    </>
                )}
            </Form>
        </div>
    );
}

Documents.layout = {
    breadcrumbs: [{ title: tk('Documents'), href: index() }],
};
