import { Form, Link, usePoll } from '@inertiajs/react';
import { AlertTriangle, FileUp, Loader2, X } from 'lucide-react';
import { useEffect } from 'react';
import CvImportController from '@/actions/App/Http/Controllers/Settings/CvImportController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/hooks/use-trans';
import { formatBytes } from '@/lib/format';

export type CvImportState = {
    id: string;
    status: {
        value: 'queued' | 'running' | 'succeeded' | 'failed';
        finished: boolean;
    };
    markdown: string | null;
    error: string | null;
    file_name: string | null;
    provider: string;
};

/**
 * "Import from PDF" for the master CV: upload, a live status while the AI reads it (the page polls), and the
 * failure message. A finished result fills the CV editor (see the documents page); nothing is saved until Save.
 */
export default function CvImport({
    cvImport,
    canImport,
    maxUploadKilobytes,
}: {
    cvImport: CvImportState | null;
    canImport: { allowed: boolean; reason: string | null };
    maxUploadKilobytes: number;
}) {
    const { t, locale } = useTrans();
    const inProgress = cvImport !== null && !cvImport.status.finished;
    const { start, stop } = usePoll(
        2000,
        { only: ['cvImport', 'documents'] },
        { autoStart: false, mode: 'rest' },
    );

    useEffect(() => {
        if (inProgress) {
            start();
        } else {
            stop();
        }
    }, [inProgress, start, stop]);

    return (
        <div className="space-y-3 rounded-md border border-dashed p-4">
            <div>
                <p className="text-sm font-medium">{t('Import from a PDF')}</p>
                <p className="text-xs text-muted-foreground">
                    {t(
                        'Upload your current CV (a LinkedIn “Save to PDF” works too, max :size). Your AI key reads it into the editor below; check it, then Save.',
                        {
                            size: formatBytes(
                                maxUploadKilobytes * 1024,
                                locale.htmlLang,
                            ),
                        },
                    )}
                </p>
            </div>

            {inProgress ? (
                <p className="flex items-center gap-2 text-sm" role="status">
                    <Loader2 className="size-4 animate-spin" />
                    {t(
                        ':provider is reading :file. This usually takes under a minute.',
                        {
                            provider: cvImport.provider,
                            file: cvImport.file_name ?? 'PDF',
                        },
                    )}
                </p>
            ) : canImport.allowed ? (
                <Form
                    {...CvImportController.store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="flex flex-wrap items-center gap-3"
                >
                    {({ processing, errors }) => (
                        <>
                            <Label htmlFor="cv-import-file" className="sr-only">
                                {t('CV as PDF')}
                            </Label>
                            <input
                                id="cv-import-file"
                                type="file"
                                name="file"
                                accept=".pdf,application/pdf"
                                required
                                className="max-w-full text-sm file:mr-3 file:rounded-md file:border file:bg-background file:px-3 file:py-1.5 file:text-sm"
                            />
                            <Button size="sm" disabled={processing}>
                                {processing ? (
                                    <Loader2 className="size-4 animate-spin" />
                                ) : (
                                    <FileUp className="size-4" />
                                )}
                                {t('Read PDF')}
                            </Button>
                            <InputError
                                message={errors.file}
                                className="w-full"
                            />
                        </>
                    )}
                </Form>
            ) : (
                <p className="text-sm text-muted-foreground">
                    {canImport.reason}
                </p>
            )}

            {cvImport?.status.value === 'failed' && (
                <div
                    className="flex gap-2 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200"
                    role="alert"
                >
                    <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                    <span className="flex-1">{cvImport.error}</span>
                    <Link
                        href={CvImportController.destroy(cvImport.id)}
                        as="button"
                        preserveScroll
                        aria-label={t('Dismiss')}
                    >
                        <X className="size-4" />
                    </Link>
                </div>
            )}
        </div>
    );
}
