import { Form, Link, usePoll } from '@inertiajs/react';
import { AlertTriangle, Loader2, Sparkles } from 'lucide-react';
import { useEffect } from 'react';
import GenerationController from '@/actions/App/Http/Controllers/GenerationController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/hooks/use-trans';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { show } from '@/routes/applications';
import type { Generation, GenerationVersion } from '@/types';

/**
 * The Generate button, the version switcher and the state of the newest run. Polls the page while a run is
 * queued or running; the server then switches the view to the new version unless one was picked by hand.
 */
export default function GenerationPanel({
    applicationId,
    generation,
    versions,
    canGenerate,
}: {
    applicationId: string;
    generation: Generation | null;
    versions: GenerationVersion[];
    canGenerate: boolean;
}) {
    const { t, locale } = useTrans();
    const latest = versions[0] ?? null;
    const inProgress = latest !== null && !latest.status.finished;
    const { start, stop } = usePoll(
        2000,
        { only: ['generation', 'versions', 'application', 'can'] },
        { autoStart: false, mode: 'rest' },
    );

    useEffect(() => {
        if (inProgress) {
            start();
        } else {
            stop();
        }
    }, [inProgress, start, stop]);

    // Failures worth showing: the newest run, and the version being viewed if it is another failed one.
    const failures = versions.filter(
        (version) =>
            version.status.value === 'failed' &&
            (version === latest || version.version === generation?.version),
    );

    return (
        <section className="space-y-4 rounded-xl border p-4 sm:p-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="space-y-1">
                    <h2 className="font-medium">{t('Tailored documents')}</h2>
                    {generation?.finished_at && (
                        <p className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">
                            <span>
                                {t('Version :version', {
                                    version: generation.version,
                                })}
                            </span>
                            <Badge
                                variant="outline"
                                lang={generation.language.value}
                            >
                                {generation.language.label}
                            </Badge>
                            <span>
                                {generation.provider} · {generation.model}
                            </span>
                            <span>
                                {formatDateTime(
                                    generation.finished_at,
                                    locale.htmlLang,
                                )}
                            </span>
                        </p>
                    )}
                </div>

                <Form
                    {...GenerationController.store.form(applicationId)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <Button disabled={processing || !canGenerate}>
                            {inProgress || processing ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <Sparkles className="size-4" />
                            )}
                            {latest ? t('Generate again') : t('Generate')}
                        </Button>
                    )}
                </Form>
            </div>

            {versions.length > 1 && (
                <nav
                    aria-label={t('Versions')}
                    className="flex flex-wrap items-center gap-2"
                >
                    {[...versions].reverse().map((version) => {
                        const selected =
                            version.version === generation?.version;

                        return (
                            <Link
                                key={version.version}
                                href={show(applicationId, {
                                    query: { version: version.version },
                                })}
                                preserveScroll
                                preserveState
                                aria-current={selected ? 'page' : undefined}
                                title={version.status.label}
                                className={cn(
                                    'inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-sm transition-colors',
                                    selected
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'hover:bg-muted',
                                )}
                            >
                                <span className="font-medium">
                                    v{version.version}
                                </span>
                                <span
                                    lang={version.language.value}
                                    className={cn(
                                        'text-xs',
                                        selected
                                            ? 'opacity-80'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {version.language.value === 'pt-BR'
                                        ? 'PT'
                                        : 'EN'}
                                </span>
                                <VersionMark version={version} />
                            </Link>
                        );
                    })}
                </nav>
            )}

            {versions.length === 0 && (
                <p className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                    {t(
                        'Nothing generated yet. Generate to get a CV tailored to this job, plus the extras you asked for.',
                    )}
                </p>
            )}

            {inProgress && (
                <p
                    className="flex items-center gap-2 rounded-md bg-muted/50 p-4 text-sm"
                    role="status"
                >
                    <Loader2 className="size-4 animate-spin" />
                    {latest.status.value === 'queued'
                        ? t('Waiting for a free worker…')
                        : t(
                              'Writing version :version. This usually takes under a minute.',
                              { version: latest.version },
                          )}
                </p>
            )}

            {failures.map((failure) => (
                <p
                    key={failure.version}
                    className="flex gap-2 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200"
                    role="alert"
                >
                    <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                    <span>
                        <span className="font-medium">
                            {t('Version :version failed.', {
                                version: failure.version,
                            })}
                        </span>{' '}
                        {failure.error}
                    </span>
                </p>
            ))}
        </section>
    );
}

/** A dot for the version the application uses now, a spinner while running, a warning when it failed. */
function VersionMark({ version }: { version: GenerationVersion }) {
    const { t } = useTrans();

    if (!version.status.finished) {
        return <Loader2 className="size-3 animate-spin" aria-hidden />;
    }

    if (version.status.value === 'failed') {
        return (
            <AlertTriangle
                className="size-3"
                aria-label={version.status.label}
            />
        );
    }

    return version.current ? (
        <span
            className="size-1.5 rounded-full bg-current"
            aria-label={t('Current version')}
        />
    ) : null;
}
