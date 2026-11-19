import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { Download, ExternalLink, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ApplicationController from '@/actions/App/Http/Controllers/ApplicationController';
import ApplicationStatusController from '@/actions/App/Http/Controllers/ApplicationStatusController';
import GeneratedDocumentPdfController from '@/actions/App/Http/Controllers/GeneratedDocumentPdfController';
import ApplicationTabs, {
    applicationTabValues,
} from '@/components/application-tabs';
import ConfirmDialog from '@/components/confirm-dialog';
import EntryStamp, { LanguagePair, StatusLine } from '@/components/entry-stamp';
import GenerationPanel, { VersionLine } from '@/components/generation-panel';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTrans } from '@/hooks/use-trans';
import { formatDate } from '@/lib/format';
import { tk } from '@/lib/i18n';
import { dashboard } from '@/routes';
import { show } from '@/routes/applications';
import type {
    AiKeyOption,
    Application,
    Generation,
    GenerationFailure,
    GenerationRun,
    GenerationVersion,
    StatusOption,
} from '@/types';

type Props = {
    application: Application;
    generation: Generation | null;
    versions: GenerationVersion[];
    run: GenerationRun | null;
    failures: GenerationFailure[];
    lastAttemptFailed: boolean;
    aiKeys: AiKeyOption[];
    statuses: StatusOption[];
    limits: { document: number; answer: number };
    can: { update: boolean; delete: boolean; generate: boolean };
};

/**
 * One application as an entry in your working papers: who and what it is with its stamp on the left, the
 * documents on their sheets in the middle, and the package (what is ready, what to fix, generate) on the right.
 */
export default function ShowApplication({
    application,
    generation,
    versions,
    run,
    failures,
    lastAttemptFailed,
    aiKeys,
    statuses,
    limits,
    can,
}: Props) {
    const tabs = applicationTabValues(generation);
    const [picked, setPicked] = useState(tabs[0]);
    // A version without the picked tab (no cover letter, say) falls back to the first one.
    const active = tabs.includes(picked) ? picked : tabs[0];

    setLayoutProps({
        breadcrumbs: [
            { title: tk('Dashboard'), href: dashboard() },
            { title: application.company_name, href: show(application.id) },
        ],
    });

    const openDocument = (value: string) => {
        setPicked(value);
        document
            .getElementById('documents')
            ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    return (
        <>
            <Head
                title={`${application.company_name} · ${application.role_title}`}
            />
            {/* Source order is the keyboard order on wide screens (left column, documents, package); phones show the
                documents right after the entry with `order`, and wide screens place each block in its column. */}
            <div className="grid grid-cols-[minmax(0,1fr)] gap-x-10 gap-y-8 py-8 lg:grid-cols-[17rem_minmax(0,1fr)] lg:grid-rows-[auto_auto_1fr] lg:py-10 xl:grid-cols-[17rem_minmax(0,1fr)_18rem] xl:grid-rows-[auto_1fr]">
                <EntryHead
                    application={application}
                    statuses={statuses}
                    canUpdate={can.update}
                />

                <EntryRecord
                    application={application}
                    generation={generation}
                    versions={versions}
                    can={can}
                />
                <div className="min-w-0 max-lg:order-2 lg:col-start-2 lg:row-span-3 lg:row-start-1 xl:row-span-2">
                    {generation && (
                        <SheetCaption
                            application={application}
                            generation={generation}
                            versions={versions}
                        />
                    )}
                    <ApplicationTabs
                        application={application}
                        generation={generation}
                        limits={limits}
                        canEdit={can.update}
                        active={active}
                        onActiveChange={setPicked}
                    />
                </div>

                <aside className="space-y-8 max-lg:order-4 lg:col-start-1 lg:row-start-3 xl:col-start-3 xl:row-span-2 xl:row-start-1">
                    <Package
                        application={application}
                        generation={generation}
                        onOpen={openDocument}
                    />
                    <GenerationPanel
                        applicationId={application.id}
                        generation={generation}
                        run={run}
                        failures={failures}
                        lastAttemptFailed={lastAttemptFailed}
                        aiKeys={aiKeys}
                        canGenerate={can.generate}
                        emphasize={!readyToSend(generation)}
                    />
                </aside>
            </div>
        </>
    );
}

/** A succeeded version whose CV has nothing left to fix: Download becomes the primary action. */
function readyToSend(generation: Generation | null): boolean {
    const cv =
        generation?.status.value === 'succeeded'
            ? generation.documents.find((document) => document.type === 'cv')
            : undefined;

    return cv !== undefined && cv.todos.length + cv.warnings.length === 0;
}

/** Top of the left column: the job, its entry stamp, where it stands on the line, and the control to move it. */
function EntryHead({
    application,
    statuses,
    canUpdate,
}: {
    application: Application;
    statuses: StatusOption[];
    canUpdate: boolean;
}) {
    const { t, locale } = useTrans();
    const place = [application.location, application.work_mode?.label]
        .filter(Boolean)
        .join(' · ');

    return (
        <section className="min-w-0 space-y-6 lg:col-start-1 lg:row-start-1">
            <div className="space-y-1.5">
                <h1 className="text-[1.75rem] leading-tight font-semibold tracking-[-0.015em] text-balance break-words">
                    {application.role_title}
                </h1>
                <p className="text-base font-medium">
                    {application.company_name}
                </p>
                <p className="text-sm text-muted-foreground tabular-nums">
                    {place && <>{place} · </>}
                    {t('Opened :date', {
                        date: formatDate(
                            application.created_at,
                            locale.htmlLang,
                        ),
                    })}
                </p>
            </div>

            <EntryStamp
                status={application.status}
                date={application.applied_at ?? application.updated_at}
                language={application.language.value}
            />

            <StatusLine statuses={statuses} current={application.status} />

            {canUpdate && (
                <div className="space-y-1.5">
                    <span className="field-label" id="status-label">
                        {t('Status')}
                    </span>
                    <Select
                        value={application.status.value}
                        onValueChange={(status) =>
                            router.patch(
                                ApplicationStatusController.update.url(
                                    application.id,
                                ),
                                { status },
                                { preserveScroll: true },
                            )
                        }
                    >
                        <SelectTrigger
                            className="w-full max-sm:data-[size=default]:h-11"
                            aria-labelledby="status-label"
                        >
                            <SelectValue>
                                {application.status.label}
                            </SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                            {statuses.map((status) => (
                                <SelectItem
                                    key={status.value}
                                    value={status.value}
                                >
                                    <StatusBadge status={status} />
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            )}
        </section>
    );
}

/** The rest of the record: the output language, the version on screen, and record actions. */
function EntryRecord({
    application,
    generation,
    versions,
    can,
}: {
    application: Application;
    generation: Generation | null;
    versions: GenerationVersion[];
    can: Props['can'];
}) {
    const { t } = useTrans();

    return (
        <section className="min-w-0 space-y-6 max-lg:order-3 lg:col-start-1 lg:row-start-2">
            <div className="space-y-2 border-t pt-5">
                <span className="field-label block">
                    {t('Output language')}
                </span>
                <LanguagePair value={application.language.value} />
                {generation && (
                    <div className="pt-2">
                        <VersionLine
                            applicationId={application.id}
                            generation={generation}
                            versions={versions}
                        />
                    </div>
                )}
            </div>

            <div className="flex flex-wrap gap-x-5 border-t pt-3 text-sm sm:gap-y-2 sm:pt-5 max-sm:[&>*]:min-h-11">
                {application.job_url && (
                    <a
                        href={application.job_url}
                        target="_blank"
                        rel="noreferrer noopener"
                        className="inline-flex items-center gap-1.5 font-medium text-primary hover:underline dark:text-ink-blue"
                    >
                        <ExternalLink className="size-4" />
                        {t('Job posting')}
                    </a>
                )}
                {can.update && (
                    <Link
                        href={ApplicationController.edit(application.id)}
                        className="inline-flex items-center gap-1.5 font-medium text-primary hover:underline dark:text-ink-blue"
                    >
                        <Pencil className="size-4" />
                        {t('Edit application')}
                    </Link>
                )}
                {can.delete && (
                    <ConfirmDialog
                        title={t('Delete this application?')}
                        description={t(
                            'Every version generated for it is deleted too. This can’t be undone.',
                        )}
                        confirmLabel={t('Delete application')}
                        action={ApplicationController.destroy(application.id)}
                        visitOptions={{ preserveScroll: false }}
                        trigger={
                            <button
                                type="button"
                                className="inline-flex items-center gap-1.5 font-medium text-destructive hover:underline"
                            >
                                <Trash2 className="size-4" />
                                {t('Delete application')}
                            </button>
                        }
                    />
                )}
            </div>
        </section>
    );
}

/**
 * Says which version is on the sheet and in what language, right above it, so what you read is never mistaken for
 * what you asked for: an older version links back to the current one, and a version in another language than the
 * application's says so.
 */
function SheetCaption({
    application,
    generation,
    versions,
}: {
    application: Application;
    generation: Generation;
    versions: GenerationVersion[];
}) {
    const { t } = useTrans();
    const current = versions.some(
        (version) => version.current && version.version === generation.version,
    );
    const otherLanguage =
        generation.language.value !== application.language.value;
    const line = {
        version: generation.version,
        language: generation.language.label,
    };

    return (
        <div className="mb-3 space-y-1 text-sm">
            <p className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span className="text-muted-foreground tabular-nums">
                    {current
                        ? t('Version :version · :language', line)
                        : t(
                              'Version :version · :language · an older version',
                              line,
                          )}
                </span>
                {!current && (
                    <Link
                        href={show.url(application.id)}
                        preserveScroll
                        className="font-semibold text-primary underline-offset-4 hover:underline dark:text-ink-blue"
                    >
                        {t('Back to the current version')}
                    </Link>
                )}
            </p>
            {otherLanguage && (
                <p className="text-pretty text-todo-foreground dark:text-todo">
                    {t(
                        'This application asks for :target; generate again to get it.',
                        { target: application.language.label },
                    )}
                </p>
            )}
        </div>
    );
}

/**
 * The package for this job: each document of the version on screen with what is left to fix, and the CV's PDF.
 * Download stays secondary, with the count, while the CV still has placeholders.
 */
function Package({
    application,
    generation,
    onOpen,
}: {
    application: Application;
    generation: Generation | null;
    onOpen: (tab: string) => void;
}) {
    const { t } = useTrans();
    const result = generation?.status.value === 'succeeded' ? generation : null;
    const cv = result?.documents.find((document) => document.type === 'cv');
    const cvToFix = cv ? new Set(cv.todos).size + cv.warnings.length : 0;
    const answersToFix =
        result?.answers.reduce((sum, answer) => sum + answer.todos.length, 0) ??
        0;

    const rows = result
        ? [
              ...result.documents.map((document) => ({
                  tab: document.type,
                  label: document.label,
                  toFix:
                      new Set(document.todos).size + document.warnings.length,
                  note: null as string | null,
              })),
              ...(result.answers.length > 0
                  ? [
                        {
                            tab: 'questions',
                            label: t('Questions'),
                            toFix: answersToFix,
                            note: t(':count answers', {
                                count: result.answers.length,
                            }),
                        },
                    ]
                  : []),
              ...(result.report
                  ? [
                        {
                            tab: 'report',
                            label: t('Report'),
                            toFix: 0,
                            note: t(':percent% keyword match', {
                                percent:
                                    result.report.keyword_coverage.percentage,
                            }),
                        },
                    ]
                  : []),
          ]
        : [
              t('CV'),
              application.wants_cover_letter && t('Cover letter'),
              application.wants_recruiter_message && t('Recruiter message'),
          ]
              .filter((label): label is string => Boolean(label))
              .map((label) => ({
                  tab: null,
                  label,
                  toFix: 0,
                  note: t('Not generated yet'),
              }));

    return (
        <section className="space-y-3" aria-labelledby="package-heading">
            <h2 id="package-heading" className="field-label">
                {t('Your package')}
            </h2>
            <ul className="border-t">
                {rows.map((row) => {
                    const status =
                        row.toFix > 0 ? (
                            <span className="rounded-full bg-todo px-2 py-0.5 text-xs font-bold text-todo-foreground tabular-nums">
                                {t(':count to fix', { count: row.toFix })}
                            </span>
                        ) : row.note ? (
                            <span className="text-xs text-muted-foreground tabular-nums">
                                {row.note}
                            </span>
                        ) : (
                            <span className="text-xs font-semibold text-ink-green">
                                {t('Ready')}
                            </span>
                        );

                    return (
                        <li key={row.label} className="border-b">
                            {row.tab ? (
                                <button
                                    type="button"
                                    onClick={() => onOpen(row.tab)}
                                    className="flex w-full items-center justify-between gap-3 py-3 text-left text-[0.9375rem] hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring dark:hover:text-ink-blue"
                                >
                                    {row.label}
                                    {status}
                                </button>
                            ) : (
                                <div className="flex items-center justify-between gap-3 py-3 text-[0.9375rem] text-muted-foreground">
                                    {row.label}
                                    {status}
                                </div>
                            )}
                        </li>
                    );
                })}
            </ul>
            {rows.some((row) => row.toFix > 0) && (
                <p className="text-xs text-pretty text-muted-foreground">
                    {t(
                        '“To fix” counts [TODO] placeholders and the CV’s ATS notes.',
                    )}
                </p>
            )}
            {cv?.has_pdf && (
                <Button
                    variant={cvToFix > 0 ? 'outline' : 'default'}
                    className="w-full max-sm:h-11"
                    asChild
                >
                    <a href={GeneratedDocumentPdfController.show.url(cv.id)}>
                        <Download />
                        {cvToFix > 0
                            ? t('Download CV · :count to fix', {
                                  count: cvToFix,
                              })
                            : t('Download CV')}
                    </a>
                </Button>
            )}
        </section>
    );
}
