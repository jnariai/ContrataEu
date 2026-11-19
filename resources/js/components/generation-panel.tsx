import { Form, Link, router, usePoll } from '@inertiajs/react';
import {
    AlertTriangle,
    Check,
    ChevronRight,
    Circle,
    KeyRound,
    Loader2,
    Settings2,
    Sparkles,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import GenerationController from '@/actions/App/Http/Controllers/GenerationController';
import ActiveAiCredentialController from '@/actions/App/Http/Controllers/Settings/ActiveAiCredentialController';
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
 * then the version on screen, the version switcher,
 * "Generate again" with the key it will use, and, last and quiet, the log of earlier failures. Polls the page while
 * a run is queued or running; the server then shows the new version unless one was picked by hand.
 */
export default function GenerationPanel({
    applicationId,
    generation,
    run,
    failures,
    lastAttemptFailed,
    aiKeys,
    canGenerate,
    emphasize,
}: {
    applicationId: string;
    generation: Generation | null;
    run: GenerationRun | null;
    failures: GenerationFailure[];
    lastAttemptFailed: boolean;
    aiKeys: AiKeyOption[];
    canGenerate: boolean;
    /** Generate is the page's primary action until the package is ready to send (then Download is). */
    emphasize: boolean;
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

    useEffect(() => {
        if (hadRun.current && run === null) {
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

    // Identical failures in a row are one problem, told once: the newest group is the notice when the last attempt
    // failed, and the log keeps only the groups before it.
    const groups = groupFailures(failures);
    const lastFailure = lastAttemptFailed ? (groups[0] ?? null) : null;
    const earlier = lastFailure ? groups.slice(1) : groups;
    const fixInAiKeys = lastFailure?.failure.fix_in_ai_keys ?? false;
    const hasVersion = generation !== null;

    return (
        <section className="space-y-4" aria-label={t('Tailored documents')}>
            {run && <RunProgress run={run} />}

            {!generation && !run && !lastFailure && (
                <p className="text-sm text-muted-foreground">
                    {t(
                        'Nothing generated yet. Generate to get a CV tailored to this job, plus the extras you asked for.',
                    )}
                </p>
            )}

            {!run && lastFailure && (
                <div className="flex gap-2 text-sm" role="alert">
                    <AlertTriangle className="mt-0.5 size-4 shrink-0 text-ink-red" />
                    <div className="min-w-0 flex-1 space-y-1">
                        {/* With a good version still on screen this is news, not an emergency: only the icon is red. */}
                        <p
                            className={cn(
                                'font-semibold text-pretty',
                                !generation && 'text-ink-red',
                            )}
                        >
                            {generation
                                ? t(
                                      'The last generation failed, so no new version was made.',
                                  )
                                : t('The last generation failed.')}
                        </p>
                        <p className="break-words text-muted-foreground">
                            {lastFailure.failure.error}
                        </p>
                        {lastFailure.count > 1 && (
                            <p className="text-xs text-muted-foreground tabular-nums">
                                {t(':count attempts in a row, same cause', {
                                    count: lastFailure.count,
                                })}
                            </p>
                        )}
                        {fixInAiKeys && (
                            <Button className="mt-2 w-full max-sm:h-11" asChild>
                                <Link href={aiKeysIndex()}>
                                    <KeyRound />
                                    {t('Check AI keys')}
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>
            )}

            <div className="space-y-2">
                <Form
                    {...GenerationController.store.form(applicationId)}
                    options={{ preserveScroll: true }}
                    className="w-full"
                >
                    {({ processing }) => (
                        <Button
                            variant={
                                emphasize && !fixInAiKeys
                                    ? 'default'
                                    : 'outline'
                            }
                            className="w-full max-sm:h-11"
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
                    <p className="text-xs text-pretty text-muted-foreground">
                        {t(
                            'Generating again makes a new version; this one stays available.',
                        )}
                    </p>
                )}
            </div>

            {earlier.length > 0 && <FailureLog groups={earlier} />}
        </section>
    );
}

/**
 * The version on screen, in one quiet line: which one, made with what and when, and the kept versions to switch
 * between. The sheet's caption says when it is an older one and links back.
 */
export function VersionLine({
    applicationId,
    generation,
    versions,
}: {
    applicationId: string;
    generation: Generation;
    versions: GenerationVersion[];
}) {
    const { t, locale } = useTrans();

    return (
        <div className="space-y-2.5">
            <p className="text-sm">
                <span className="font-semibold">
                    {t('Version :version', { version: generation.version })}
                </span>
                <span className="block text-muted-foreground tabular-nums">
                    {generation.provider} · {generation.model}
                    {generation.finished_at &&
                        ` · ${formatDateTime(generation.finished_at, locale.htmlLang)}`}
                </span>
            </p>
            {versions.length > 1 && (
                <nav
                    aria-label={t('Versions')}
                    className="flex flex-wrap items-center gap-2"
                >
                    <span className="text-sm text-muted-foreground">
                        {t('Versions')}
                    </span>
                    {[...versions].reverse().map((version) => {
                        const selected = version.version === generation.version;

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
                                    'inline-flex items-center gap-1.5 rounded-[3px] border px-2.5 py-1 text-sm tabular-nums transition-colors max-sm:min-h-11 max-sm:px-3.5',
                                    selected
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-input hover:bg-accent',
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
                                    <>
                                        <span
                                            className="size-1.5 rounded-full bg-current"
                                            aria-hidden
                                        />
                                        <span className="sr-only">
                                            {t('Current version')}
                                        </span>
                                    </>
                                )}
                            </Link>
                        );
                    })}
                </nav>
            )}
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
        <div className="flex w-full items-center gap-1">
            <Select
                value={active?.id}
                onValueChange={activate}
                disabled={disabled || switching}
            >
                <SelectTrigger
                    size="sm"
                    className="min-w-0 flex-1 max-sm:data-[size=sm]:h-11"
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
                className="size-11 sm:size-8"
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
            className="space-y-3 rounded-md border bg-card p-4 text-sm"
            role="status"
        >
            <ol className="space-y-2">
                {steps.map((step) => (
                    <li key={step.label} className="flex items-center gap-2">
                        {step.state === 'done' ? (
                            <Check className="size-4 text-ink-green" />
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

type FailureGroup = { failure: GenerationFailure; count: number };

/** Newest first, consecutive failures with the same message folded into one, keeping the newest of each. */
function groupFailures(failures: GenerationFailure[]): FailureGroup[] {
    const groups: FailureGroup[] = [];

    for (const failure of failures) {
        const last = groups.at(-1);

        if (last && last.failure.error === failure.error) {
            last.count += 1;
        } else {
            groups.push({ failure, count: 1 });
        }
    }

    return groups;
}

/**
 * Failures before the one being reported (or all of them once a later run worked): when, with what, how long, and
 * why. Closed by default and ruled like the package list, so old failures never read as the current state.
 */
function FailureLog({ groups }: { groups: FailureGroup[] }) {
    const { t, locale } = useTrans();
    const total = groups.reduce((sum, group) => sum + group.count, 0);

    return (
        <details className="group text-sm text-muted-foreground">
            <summary className="flex min-h-11 cursor-pointer list-none items-center gap-2 text-xs hover:text-foreground sm:min-h-0">
                <ChevronRight className="size-4 transition-transform group-open:rotate-90" />
                {t('Earlier failed attempts (:count)', { count: total })}
            </summary>
            <ol className="mt-2 divide-y border-y">
                {groups.map(({ failure, count }) => (
                    <li key={failure.id} className="space-y-1 py-3">
                        <p className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs tabular-nums">
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
                            {count > 1 ? (
                                <span>
                                    {t(':count attempts in a row, same cause', {
                                        count,
                                    })}
                                </span>
                            ) : (
                                failure.started_at &&
                                failure.finished_at && (
                                    <FailureDuration
                                        seconds={secondsBetween(
                                            failure.started_at,
                                            failure.finished_at,
                                        )}
                                    />
                                )
                            )}
                        </p>
                        <p className="break-words text-foreground">
                            {failure.error}
                        </p>
                    </li>
                ))}
            </ol>
        </details>
    );
}

/** How long a failed run lasted; one refused at once (a rejected key, say) reads as such, not as "0 s". */
function FailureDuration({ seconds }: { seconds: number }) {
    const { t } = useTrans();

    return seconds < 1
        ? t('stopped right away')
        : t('ran for :time', { time: formatDuration(seconds) });
}
