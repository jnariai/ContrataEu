import { Form, Link, router, usePoll } from '@inertiajs/react';
import {
    AlertTriangle,
    Check,
    CheckCircle2,
    ChevronRight,
    Circle,
    Download,
    History,
    KeyRound,
    Loader2,
    Settings2,
    Sparkles,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import GeneratedDocumentPdfController from '@/actions/App/Http/Controllers/GeneratedDocumentPdfController';
import GenerationController from '@/actions/App/Http/Controllers/GenerationController';
import ActiveAiCredentialController from '@/actions/App/Http/Controllers/Settings/ActiveAiCredentialController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTrans } from '@/hooks/use-trans';
import { notify } from '@/lib/notify';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index as aiKeysIndex } from '@/routes/ai-credentials';
import { show } from '@/routes/applications';
import type {
    AiKeyOption,
    Generation,
    GenerationFailure,
    GenerationRun,
    GenerationVersion,
} from '@/types';

/** "1 min 05 s" / "42 s" from a number of seconds. */
function formatDuration(seconds: number): string {
    const safe = Math.max(0, Math.round(seconds));
    const minutes = Math.floor(safe / 60);
    const rest = safe % 60;

    return minutes > 0
        ? `${minutes} min ${String(rest).padStart(2, '0')} s`
        : `${rest} s`;
}

function secondsBetween(from: string, to: string | number): number {
    return (new Date(to).getTime() - new Date(from).getTime()) / 1000;
}

/**
 * The application's generation area, newest event first: the run in progress, or the failure of the last attempt,
 * then the version on screen as a clear "ready" card (download the CV, what to review), the version switcher,
 * "Generate again" with the key it will use, and, last and quiet, the log of earlier failures. Polls the page while
 * a run is queued or running; the server then shows the new version unless one was picked by hand.
 */
export default function GenerationPanel({
    applicationId,
    generation,
    versions,
    run,
    failures,
    lastAttemptFailed,
    aiKeys,
    canGenerate,
}: {
    applicationId: string;
    generation: Generation | null;
    versions: GenerationVersion[];
    run: GenerationRun | null;
    failures: GenerationFailure[];
    lastAttemptFailed: boolean;
    aiKeys: AiKeyOption[];
    canGenerate: boolean;
}) {
    const { t } = useTrans();
    const { start, stop } = usePoll(
        2000,
        {
            only: [
                'generation',
                'versions',
                'run',
                'failures',
                'lastAttemptFailed',
                'application',
                'can',
            ],
        },
        { autoStart: false, mode: 'rest' },
    );

    useEffect(() => {
        if (run) {
            start();
        } else {
            stop();
        }
    }, [run, start, stop]);

    // A run that just finished while the page was open: say how it went, so a success is never missed.
    const hadRun = useRef(run !== null);
    const [justFinished, setJustFinished] = useState(false);

    useEffect(() => {
        if (hadRun.current && run === null) {
            setJustFinished(true);

            if (lastAttemptFailed) {
                notify('error', t('The generation failed. Details are below.'));
            } else if (generation) {
                toast.success(
                    t('Version :version is ready.', {
                        version: generation.version,
                    }),
                );
            }
        }

        hadRun.current = run !== null;
    }, [run, lastAttemptFailed, generation, t]);

    const lastFailure = lastAttemptFailed ? (failures[0] ?? null) : null;
    const hasVersion = generation !== null;
    const viewingCurrent =
        hasVersion &&
        versions.some(
            (version) =>
                version.current && version.version === generation.version,
        );

    return (
        <section className="space-y-4 rounded-xl border p-4 sm:p-6">
            <h2 className="font-medium">{t('Tailored documents')}</h2>

            {run && <RunProgress run={run} />}

            {!run && lastFailure && (
                <div
                    className="flex gap-2 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200"
                    role="alert"
                >
                    <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                    <div className="space-y-1">
                        <p className="font-medium">
                            {generation
                                ? t(
                                      'Your last attempt failed. Nothing changed: you are still seeing version :version.',
                                      { version: generation.version },
                                  )
                                : t('Your last attempt failed.')}
                        </p>
                        <p>{lastFailure.error}</p>
                    </div>
                </div>
            )}

            {generation ? (
                <VersionCard
                    generation={generation}
                    current={viewingCurrent}
                    afterFailure={!run && lastFailure !== null}
                    highlight={justFinished && !lastAttemptFailed}
                    currentVersionUrl={
                        viewingCurrent ? null : show.url(applicationId)
                    }
                />
            ) : (
                !run &&
                !lastFailure && (
                    <p className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                        {t(
                            'Nothing generated yet. Generate to get a CV tailored to this job, plus the extras you asked for.',
                        )}
                    </p>
                )
            )}

            {versions.length > 1 && (
                <nav
                    aria-label={t('Versions')}
                    className="flex flex-wrap items-center gap-2"
                >
                    <span className="text-sm text-muted-foreground">
                        {t('Versions')}
                    </span>
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
                                {version.current && (
                                    <span
                                        className="size-1.5 rounded-full bg-current"
                                        aria-label={t('Current version')}
                                    />
                                )}
                            </Link>
                        );
                    })}
                </nav>
            )}

            <div className="flex flex-wrap items-center gap-2 border-t pt-4">
                <Form
                    {...GenerationController.store.form(applicationId)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <Button
                            variant={hasVersion ? 'outline' : 'default'}
                            disabled={processing || !canGenerate}
                        >
                            {run || processing ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <Sparkles className="size-4" />
                            )}
                            {run
                                ? t('Generating...')
                                : hasVersion || failures.length > 0
                                  ? t('Generate again')
                                  : t('Generate')}
                        </Button>
                    )}
                </Form>
                {aiKeys.length > 0 && (
                    <AiKeySwitcher aiKeys={aiKeys} disabled={!!run} />
                )}
                {hasVersion && !run && (
                    <p className="w-full text-xs text-muted-foreground">
                        {t(
                            'Generating again makes a new version; this one stays available.',
                        )}
                    </p>
                )}
            </div>

            {failures.length > 0 && (
                <FailureLog failures={failures} quiet={!lastFailure} />
            )}
        </section>
    );
}

/**
 * The version on screen, stated plainly: ready (or an older version), when and with what, a direct PDF download of
 * the CV, and how many placeholders and warnings to review below before sending.
 */
function VersionCard({
    generation,
    current,
    afterFailure,
    highlight,
    currentVersionUrl,
}: {
    generation: Generation;
    current: boolean;
    /** Shown under a failure notice: still the version in use, but not news, so not green. */
    afterFailure: boolean;
    highlight: boolean;
    currentVersionUrl: string | null;
}) {
    const { t, locale } = useTrans();
    const cv = generation.documents.find((document) => document.type === 'cv');
    const toReview =
        new Set([
            ...generation.documents.flatMap((document) => document.todos),
            ...generation.answers.flatMap((answer) => answer.todos),
        ]).size +
        generation.documents.reduce(
            (sum, document) => sum + document.warnings.length,
            0,
        );
    const documents = [
        ...generation.documents.map((document) => document.label),
        ...(generation.answers.length > 0
            ? [
                  t(':count answers', {
                      count: generation.answers.length,
                  }),
              ]
            : []),
    ];

    return (
        <div
            className={cn(
                'space-y-3 rounded-md border p-4 transition-shadow',
                current && !afterFailure
                    ? 'border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-950/40'
                    : 'bg-muted/50',
                highlight && 'ring-2 ring-green-500',
            )}
            role="status"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex gap-2">
                    {current ? (
                        <CheckCircle2
                            className={cn(
                                'mt-0.5 size-5 shrink-0',
                                afterFailure
                                    ? 'text-muted-foreground'
                                    : 'text-green-600 dark:text-green-400',
                            )}
                        />
                    ) : (
                        <History className="mt-0.5 size-5 shrink-0 text-muted-foreground" />
                    )}
                    <div className="space-y-1">
                        <p className="font-medium">
                            {!current
                                ? t(
                                      'You are viewing version :version, an older version',
                                      { version: generation.version },
                                  )
                                : afterFailure
                                  ? t(
                                        'Version :version, your latest working version',
                                        { version: generation.version },
                                    )
                                  : t('Version :version is ready', {
                                        version: generation.version,
                                    })}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {documents.join(' · ')}
                        </p>
                        <p className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                            <Badge
                                variant="outline"
                                lang={generation.language.value}
                            >
                                {generation.language.label}
                            </Badge>
                            <span>
                                {generation.provider} · {generation.model}
                            </span>
                            {generation.finished_at && (
                                <span>
                                    {formatDateTime(
                                        generation.finished_at,
                                        locale.htmlLang,
                                    )}
                                </span>
                            )}
                        </p>
                    </div>
                </div>
                {cv?.has_pdf && (
                    <Button size="sm" asChild>
                        <a
                            href={GeneratedDocumentPdfController.show.url(
                                cv.id,
                            )}
                        >
                            <Download className="size-4" />
                            {t('Download CV (PDF)')}
                        </a>
                    </Button>
                )}
            </div>
            <p className="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                {currentVersionUrl && (
                    <Link
                        href={currentVersionUrl}
                        preserveScroll
                        className="font-medium underline-offset-4 hover:underline"
                    >
                        {t('Back to the current version')}
                    </Link>
                )}
                {toReview > 0 ? (
                    <a
                        href="#documents"
                        className="text-amber-800 underline-offset-4 hover:underline dark:text-amber-300"
                    >
                        {t('Review :count item(s) below before you send it.', {
                            count: toReview,
                        })}
                    </a>
                ) : (
                    <a
                        href="#documents"
                        className="text-muted-foreground underline-offset-4 hover:underline"
                    >
                        {t('Read and edit the documents below.')}
                    </a>
                )}
            </p>
        </div>
    );
}

/** "Work OpenRouter · model", or "OpenRouter · model" for a key without a name. */
function aiKeyLabel(key: AiKeyOption): string {
    return `${key.name} · ${key.model}`;
}

/**
 * Which of the user's keys the next generation uses. Picking one makes it the active key everywhere (same as on the
 * AI keys page), so the switch sticks for the next applications too.
 */
function AiKeySwitcher({
    aiKeys,
    disabled,
}: {
    aiKeys: AiKeyOption[];
    disabled: boolean;
}) {
    const { t } = useTrans();
    const [switching, setSwitching] = useState(false);
    const active = aiKeys.find((key) => key.active);

    function activate(id: string) {
        router.post(
            ActiveAiCredentialController.store.url(id),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setSwitching(true),
                onFinish: () => setSwitching(false),
            },
        );
    }

    return (
        <div className="flex items-center gap-1">
            <Select
                value={active?.id}
                onValueChange={activate}
                disabled={disabled || switching}
            >
                <SelectTrigger
                    size="sm"
                    className="max-w-64"
                    aria-label={t('AI key for the next generation')}
                >
                    <KeyRound className="size-4 text-muted-foreground" />
                    <SelectValue placeholder={t('Pick an AI key')} />
                </SelectTrigger>
                <SelectContent align="end">
                    {aiKeys.map((key) => (
                        <SelectItem key={key.id} value={key.id}>
                            {aiKeyLabel(key)}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Button
                variant="ghost"
                size="icon"
                className="size-8"
                asChild
                title={t('Manage AI keys')}
            >
                <Link href={aiKeysIndex()} aria-label={t('Manage AI keys')}>
                    <Settings2 className="size-4" />
                </Link>
            </Button>
        </div>
    );
}

/** The steps of the run in progress, from its status and timestamps, with the elapsed time. */
function RunProgress({ run }: { run: GenerationRun }) {
    const { t } = useTrans();
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), 1000);

        return () => window.clearInterval(timer);
    }, []);

    const elapsed = secondsBetween(run.queued_at, now);
    const running = run.status.value === 'running';
    const steps = [
        {
            label: running ? t('Started') : t('Getting started'),
            state: running ? 'done' : 'active',
        },
        {
            label: t('Writing your documents with :provider (:model)', {
                provider: run.provider,
                model: run.model,
            }),
            state: running ? 'active' : 'pending',
        },
        { label: t('Saving the new version'), state: 'pending' },
    ] as const;

    return (
        <div
            className="space-y-3 rounded-md bg-muted/50 p-4 text-sm"
            role="status"
        >
            <ol className="space-y-2">
                {steps.map((step) => (
                    <li key={step.label} className="flex items-center gap-2">
                        {step.state === 'done' ? (
                            <Check className="size-4 text-green-600" />
                        ) : step.state === 'active' ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : (
                            <Circle className="size-4 text-muted-foreground" />
                        )}
                        <span
                            className={cn(
                                step.state === 'pending' &&
                                    'text-muted-foreground',
                                step.state === 'active' && 'font-medium',
                            )}
                        >
                            {step.label}
                        </span>
                    </li>
                ))}
            </ol>
            <p className="text-xs text-muted-foreground tabular-nums">
                {t(
                    'Elapsed: :time. This usually takes a minute or two; slow models can take up to five.',
                    {
                        time: formatDuration(elapsed),
                    },
                )}
            </p>
        </div>
    );
}

/**
 * The application's last failed runs: when, with what, how long, and why. `quiet` once a later attempt worked:
 * kept for reference but out of the way, so old failures never read as the current state.
 */
function FailureLog({
    failures,
    quiet,
}: {
    failures: GenerationFailure[];
    quiet: boolean;
}) {
    const { t, locale } = useTrans();

    return (
        <details
            className={cn(
                'group text-sm',
                quiet ? 'text-muted-foreground' : 'rounded-md border',
            )}
        >
            <summary
                className={cn(
                    'flex cursor-pointer list-none items-center gap-2',
                    quiet ? 'text-xs hover:text-foreground' : 'p-3 font-medium',
                )}
            >
                <ChevronRight className="size-4 transition-transform group-open:rotate-90" />
                {quiet
                    ? t('Earlier failed attempts (:count)', {
                          count: failures.length,
                      })
                    : t('Recent failures (:count)', { count: failures.length })}
            </summary>
            <ol
                className={cn(
                    'divide-y',
                    quiet ? 'mt-2 rounded-md border' : 'border-t',
                )}
            >
                {failures.map((failure) => (
                    <li key={failure.id} className="space-y-1 p-3">
                        <p className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                            {failure.finished_at && (
                                <span>
                                    {formatDateTime(
                                        failure.finished_at,
                                        locale.htmlLang,
                                    )}
                                </span>
                            )}
                            <span>
                                {failure.provider} · {failure.model}
                            </span>
                            <span lang={failure.language.value}>
                                {failure.language.label}
                            </span>
                            {failure.started_at && failure.finished_at && (
                                <span className="tabular-nums">
                                    {t('ran for :time', {
                                        time: formatDuration(
                                            secondsBetween(
                                                failure.started_at,
                                                failure.finished_at,
                                            ),
                                        ),
                                    })}
                                </span>
                            )}
                        </p>
                        <p className="break-words">{failure.error}</p>
                    </li>
                ))}
            </ol>
        </details>
    );
}
