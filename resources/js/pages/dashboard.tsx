import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Circle, Plus } from 'lucide-react';
import ApplicationController from '@/actions/App/Http/Controllers/ApplicationController';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/hooks/use-trans';
import { formatDate } from '@/lib/format';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as aiCredentials } from '@/routes/ai-credentials';
import { edit as candidateProfile } from '@/routes/candidate-profile';
import { index as masterDocuments } from '@/routes/master-documents';
import type { StatusOption } from '@/types';

type ApplicationRow = {
    id: string;
    company_name: string;
    role_title: string;
    location: string | null;
    work_mode: string | null;
    language: string;
    status: StatusOption;
    applied_at: string | null;
    updated_at: string;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    setup: {
        profile: boolean;
        masterCv: boolean;
        aiKey: boolean;
        complete: boolean;
    };
    filters: { status: string | null };
    statuses: StatusOption[];
    counts: Record<string, number>;
    applications: Paginated<ApplicationRow>;
};

export default function Dashboard({
    setup,
    filters,
    statuses,
    counts,
    applications,
}: Props) {
    const { t } = useTrans();
    const total = Object.values(counts).reduce((sum, count) => sum + count, 0);

    return (
        <>
            <Head title={t('Dashboard')} />
            <div className="w-full space-y-8 py-8 lg:py-10">
                {!setup.complete && <SetupChecklist setup={setup} />}

                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="text-[1.75rem] leading-tight font-semibold tracking-[-0.015em]">
                            {t('Applications')}
                        </h1>
                        <p className="text-muted-foreground">
                            {t(
                                'Every job you are pursuing, and where it stands.',
                            )}
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={ApplicationController.create()}>
                            <Plus className="size-4" />
                            {t('New application')}
                        </Link>
                    </Button>
                </div>

                {total > 0 && (
                    <nav
                        aria-label={t('Filter by status')}
                        className="flex flex-wrap gap-2"
                    >
                        <FilterChip
                            href={dashboard()}
                            active={filters.status === null}
                            label={t('All')}
                            count={total}
                        />
                        {statuses
                            .filter((status) => (counts[status.value] ?? 0) > 0)
                            .map((status) => (
                                <FilterChip
                                    key={status.value}
                                    href={dashboard({
                                        query: { status: status.value },
                                    })}
                                    active={filters.status === status.value}
                                    label={status.label}
                                    count={counts[status.value]}
                                />
                            ))}
                    </nav>
                )}

                {applications.data.length === 0 ? (
                    <EmptyState filtered={filters.status !== null} />
                ) : (
                    <ul className="divide-y rounded-md border bg-card">
                        {applications.data.map((application) => (
                            <ApplicationListItem
                                key={application.id}
                                application={application}
                            />
                        ))}
                    </ul>
                )}

                {applications.last_page > 1 && (
                    <nav
                        className="flex items-center justify-between text-sm"
                        aria-label={t('Pagination')}
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!applications.prev_page_url}
                        >
                            {applications.prev_page_url ? (
                                <Link
                                    href={applications.prev_page_url}
                                    preserveScroll
                                >
                                    {t('Previous')}
                                </Link>
                            ) : (
                                <span>{t('Previous')}</span>
                            )}
                        </Button>
                        <span className="text-muted-foreground">
                            {t('Page :current of :last', {
                                current: applications.current_page,
                                last: applications.last_page,
                            })}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!applications.next_page_url}
                        >
                            {applications.next_page_url ? (
                                <Link
                                    href={applications.next_page_url}
                                    preserveScroll
                                >
                                    {t('Next')}
                                </Link>
                            ) : (
                                <span>{t('Next')}</span>
                            )}
                        </Button>
                    </nav>
                )}
            </div>
        </>
    );
}

function SetupChecklist({ setup }: { setup: Props['setup'] }) {
    const { t } = useTrans();
    const steps = [
        {
            done: setup.profile,
            label: t('Fill in your candidate profile'),
            href: candidateProfile(),
        },
        {
            done: setup.aiKey,
            label: t('Add your AI key'),
            href: aiCredentials(),
        },
        {
            done: setup.masterCv,
            label: t('Add your master CV (paste it or upload a PDF)'),
            href: masterDocuments(),
        },
    ];

    return (
        <section className="space-y-4 rounded-md border bg-card p-5 sm:p-6">
            <div className="space-y-1">
                <h2 className="text-lg font-semibold">
                    {t('Finish setting up')}
                </h2>
                <p className="text-sm text-muted-foreground">
                    {t(
                        'Do this once, and every application after that takes about a minute.',
                    )}
                </p>
            </div>
            <ol className="space-y-2">
                {steps.map((step) => (
                    <li
                        key={step.label}
                        className="flex items-center gap-3 text-sm"
                    >
                        {step.done ? (
                            <CheckCircle2
                                className="size-5 text-ink-green"
                                aria-label={t('Done')}
                            />
                        ) : (
                            <Circle
                                className="size-5 text-muted-foreground"
                                aria-label={t('To do')}
                            />
                        )}
                        {step.done ? (
                            <span className="text-muted-foreground line-through">
                                {step.label}
                            </span>
                        ) : (
                            <Link
                                href={step.href}
                                className="font-semibold text-primary underline-offset-4 hover:underline dark:text-ink-blue"
                            >
                                {step.label}
                            </Link>
                        )}
                    </li>
                ))}
            </ol>
        </section>
    );
}

function FilterChip({
    href,
    active,
    label,
    count,
}: {
    href: ReturnType<typeof dashboard>;
    active: boolean;
    label: string;
    count: number;
}) {
    return (
        <Link
            href={href}
            preserveScroll
            aria-current={active ? 'page' : undefined}
            className={cn(
                'inline-flex items-center gap-2 rounded-[3px] border px-3 py-1.5 text-sm font-medium transition-colors',
                active
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'border-input hover:bg-accent',
            )}
        >
            {label}
            <span
                className={cn(
                    'tabular-nums',
                    active ? 'opacity-80' : 'text-muted-foreground',
                )}
            >
                {count}
            </span>
        </Link>
    );
}

/** One entry: its stamp first (status and date), then the job, then where and in which language. */
function ApplicationListItem({ application }: { application: ApplicationRow }) {
    const { t, locale } = useTrans();
    const place = [application.location, application.work_mode]
        .filter(Boolean)
        .join(' · ');

    return (
        <li>
            <Link
                href={ApplicationController.show(application.id)}
                className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-2 px-4 py-4 transition-colors hover:bg-accent/60 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring sm:grid-cols-[9.5rem_minmax(0,1fr)_auto] sm:px-5"
            >
                <div className="col-span-2 flex items-center gap-2 sm:col-span-1 sm:flex-col sm:items-start sm:gap-1">
                    <StatusBadge status={application.status} />
                    <span className="text-xs text-muted-foreground tabular-nums">
                        {formatDate(
                            application.applied_at ?? application.updated_at,
                            locale.htmlLang,
                        )}
                    </span>
                </div>
                <div className="min-w-0">
                    <p className="truncate text-[0.9375rem] font-semibold">
                        {application.role_title}
                    </p>
                    <p className="truncate text-sm text-muted-foreground">
                        {application.company_name}
                        {place && ` · ${place}`}
                    </p>
                </div>
                <span
                    className="rounded-[3px] border border-ink-blue/50 px-1.5 py-0.5 text-xs font-semibold tracking-[0.04em] text-ink-blue"
                    lang={application.language}
                    title={t('Output language')}
                >
                    {application.language === 'pt-BR' ? 'PT-BR' : 'EN'}
                </span>
            </Link>
        </li>
    );
}

function EmptyState({ filtered }: { filtered: boolean }) {
    const { t } = useTrans();

    return (
        <div className="rounded-md border border-dashed border-input p-10 text-center">
            <p className="font-semibold">
                {filtered
                    ? t('No applications with this status')
                    : t('No applications yet')}
            </p>
            <p className="mt-1 text-sm text-muted-foreground">
                {filtered
                    ? t('Pick another status, or show all.')
                    : t(
                          'Paste your first job description to get a tailored CV.',
                      )}
            </p>
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: tk('Dashboard'), href: dashboard() }],
};
