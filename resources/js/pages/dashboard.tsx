import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Circle, Plus } from 'lucide-react';
import ApplicationController from '@/actions/App/Http/Controllers/ApplicationController';
import Heading from '@/components/heading';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/hooks/use-trans';
import { formatDateTime } from '@/lib/format';
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
            <div className="mx-auto w-full max-w-5xl space-y-8 px-4 py-6">
                {!setup.complete && <SetupChecklist setup={setup} />}

                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={t('Applications')}
                        description={t(
                            'Every job you are pursuing, and where it stands.',
                        )}
                    />
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
                    <ul className="divide-y rounded-xl border">
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
        <section className="space-y-4 rounded-xl border bg-muted/30 p-4 sm:p-6">
            <div>
                <h2 className="font-medium">{t('Finish setting up')}</h2>
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
                                className="size-5 text-green-600"
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
                                className="font-medium underline-offset-4 hover:underline"
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
                'inline-flex items-center gap-2 rounded-full border px-3 py-1 text-sm transition-colors',
                active
                    ? 'border-foreground bg-foreground text-background'
                    : 'hover:bg-muted',
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

function ApplicationListItem({ application }: { application: ApplicationRow }) {
    const { t, locale } = useTrans();
    const meta = [
        application.location,
        application.work_mode,
        application.language.toUpperCase(),
    ].filter(Boolean);

    return (
        <li>
            <Link
                href={ApplicationController.show(application.id)}
                className="flex flex-col gap-2 p-4 transition-colors hover:bg-muted/50 sm:flex-row sm:items-center sm:gap-4"
            >
                <div className="min-w-0 flex-1">
                    <p className="truncate font-medium">
                        {application.role_title}
                    </p>
                    <p className="truncate text-sm text-muted-foreground">
                        {application.company_name}
                        {meta.length > 0 && ` · ${meta.join(' · ')}`}
                    </p>
                </div>
                <div className="flex items-center gap-3 sm:flex-col sm:items-end sm:gap-1">
                    <StatusBadge status={application.status} />
                    <span className="text-xs text-muted-foreground">
                        {t('Updated :date', {
                            date: formatDateTime(
                                application.updated_at,
                                locale.htmlLang,
                            ),
                        })}
                    </span>
                </div>
            </Link>
        </li>
    );
}

function EmptyState({ filtered }: { filtered: boolean }) {
    const { t } = useTrans();

    return (
        <div className="rounded-xl border border-dashed p-8 text-center">
            <p className="font-medium">
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
