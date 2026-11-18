import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ExternalLink, Pencil, Trash2 } from 'lucide-react';
import ApplicationController from '@/actions/App/Http/Controllers/ApplicationController';
import ApplicationStatusController from '@/actions/App/Http/Controllers/ApplicationStatusController';
import ApplicationTabs from '@/components/application-tabs';
import GenerationPanel from '@/components/generation-panel';
import StatusBadge from '@/components/status-badge';
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
import { formatDateTime } from '@/lib/format';
import { tk } from '@/lib/i18n';
import { dashboard } from '@/routes';
import { show } from '@/routes/applications';
import type {
    Application,
    Generation,
    GenerationFailure,
    GenerationRun,
    GenerationVersion,
    StatusOption,
    AiKeyOption,
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
    const { t, locale } = useTrans();

    setLayoutProps({
        breadcrumbs: [
            { title: tk('Dashboard'), href: dashboard() },
            { title: application.company_name, href: show(application.id) },
        ],
    });

    return (
        <>
            <Head
                title={`${application.company_name} · ${application.role_title}`}
            />
            <div className="mx-auto w-full max-w-4xl space-y-6 px-4 py-6">
                <header className="space-y-4 rounded-xl border p-4 sm:p-6">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div className="min-w-0 space-y-1">
                            <h1 className="text-xl font-semibold tracking-tight break-words">
                                {application.role_title}
                            </h1>
                            <p className="text-muted-foreground">
                                {application.company_name}
                            </p>
                        </div>
                        {can.update ? (
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
                                    className="w-48"
                                    aria-label={t('Status')}
                                >
                                    <SelectValue />
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
                        ) : (
                            <StatusBadge status={application.status} />
                        )}
                    </div>

                    <div className="flex flex-wrap items-center gap-2 text-sm">
                        {application.location && (
                            <Badge variant="secondary">
                                {application.location}
                            </Badge>
                        )}
                        {application.work_mode && (
                            <Badge variant="secondary">
                                {application.work_mode.label}
                            </Badge>
                        )}
                        <Badge
                            variant="outline"
                            lang={application.language.value}
                        >
                            {application.language.label}
                        </Badge>
                        {application.applied_at && (
                            <span className="text-muted-foreground">
                                {t('Applied :date', {
                                    date: formatDateTime(
                                        application.applied_at,
                                        locale.htmlLang,
                                    ),
                                })}
                            </span>
                        )}
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {application.job_url && (
                            <Button variant="outline" size="sm" asChild>
                                <a
                                    href={application.job_url}
                                    target="_blank"
                                    rel="noreferrer noopener"
                                >
                                    <ExternalLink className="size-4" />
                                    {t('Job posting')}
                                </a>
                            </Button>
                        )}
                        {can.update && (
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={ApplicationController.edit(
                                        application.id,
                                    )}
                                >
                                    <Pencil className="size-4" />
                                    {t('Edit')}
                                </Link>
                            </Button>
                        )}
                        {can.delete && (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="text-destructive hover:text-destructive"
                                asChild
                            >
                                <Link
                                    href={ApplicationController.destroy(
                                        application.id,
                                    )}
                                    as="button"
                                    onBefore={() =>
                                        confirm(
                                            t(
                                                'Delete this application and every version generated for it? This cannot be undone.',
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 className="size-4" />
                                    {t('Delete')}
                                </Link>
                            </Button>
                        )}
                    </div>
                </header>

                <GenerationPanel
                    applicationId={application.id}
                    generation={generation}
                    versions={versions}
                    run={run}
                    failures={failures}
                    lastAttemptFailed={lastAttemptFailed}
                    aiKeys={aiKeys}
                    canGenerate={can.generate}
                />

                <ApplicationTabs
                    application={application}
                    generation={generation}
                    limits={limits}
                    canEdit={can.update}
                />
            </div>
        </>
    );
}
