import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { useTrans } from '@/hooks/use-trans';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { GenerationReport as Report } from '@/types';

type Strength = Report['matches'][number]['strength'];

const strengths: Record<Strength, { label: string; className: string }> = {
    strong: {
        label: tk('Strong'),
        className:
            'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    },
    partial: {
        label: tk('Partial'),
        className:
            'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
    },
    missing: {
        label: tk('Missing'),
        className: 'bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-200',
    },
};

/** The AI's analysis of how the master CV fits the job: matches, keyword coverage, gaps and interview prep. */
export default function GenerationReport({ report }: { report: Report }) {
    const { t } = useTrans();
    const coverage = report.keyword_coverage;
    const analysis = report.jd_analysis;

    return (
        <div className="space-y-8">
            <div className="grid gap-3 sm:grid-cols-4">
                <Stat
                    label={t('Keyword coverage')}
                    value={`${coverage.percentage}%`}
                    hint={t(':matched of :total', {
                        matched: coverage.matched,
                        total: coverage.total,
                    })}
                />
                <Stat
                    label={t('Strong matches')}
                    value={String(
                        report.matches.filter((m) => m.strength === 'strong')
                            .length,
                    )}
                    hint={t(':count requirements', {
                        count: report.matches.length,
                    })}
                />
                <Stat label={t('Gaps')} value={String(report.gaps.length)} />
                <Stat
                    label={t('Red flags')}
                    value={String(report.red_flags.length)}
                />
            </div>

            <Section title={t('Requirements')}>
                {report.matches.length === 0 ? (
                    <Empty />
                ) : (
                    <div className="overflow-x-auto rounded-md border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-xs text-muted-foreground">
                                <tr>
                                    <th className="p-3 font-medium">
                                        {t('Requirement')}
                                    </th>
                                    <th className="p-3 font-medium">
                                        {t('Evidence from your CV')}
                                    </th>
                                    <th className="p-3 font-medium">
                                        {t('Match')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {report.matches.map((match, index) => (
                                    <tr key={index} className="align-top">
                                        <td className="p-3 font-medium">
                                            {match.requirement}
                                        </td>
                                        <td className="p-3 text-muted-foreground">
                                            {match.evidence || '—'}
                                        </td>
                                        <td className="p-3">
                                            <span
                                                className={cn(
                                                    'inline-flex rounded-md px-2 py-0.5 text-xs font-medium',
                                                    strengths[match.strength]
                                                        ?.className,
                                                )}
                                            >
                                                {t(
                                                    strengths[match.strength]
                                                        ?.label ??
                                                        match.strength,
                                                )}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Section>

            {coverage.missing.length > 0 && (
                <Section title={t('Keywords missing from the CV')}>
                    <Chips items={coverage.missing} />
                </Section>
            )}

            <div className="grid gap-8 md:grid-cols-2">
                <Section title={t('Gaps')}>
                    <List items={report.gaps} />
                </Section>
                <Section title={t('Red flags')}>
                    <List items={report.red_flags} />
                </Section>
                <Section title={t('Questions for you')}>
                    <List items={report.todos} />
                </Section>
                <Section title={t('Interview prep')}>
                    <List items={report.interview_questions} ordered />
                </Section>
            </div>

            <Section title={t('Job analysis')}>
                <dl className="grid gap-4 text-sm sm:grid-cols-2">
                    <Fact label={t('Seniority')}>
                        {analysis.seniority || '—'}
                    </Fact>
                    <Fact label={t('Domain')}>{analysis.domain || '—'}</Fact>
                    <Fact label={t('Must-haves')}>
                        <List items={analysis.must_haves} />
                    </Fact>
                    <Fact label={t('Nice-to-haves')}>
                        <List items={analysis.nice_to_haves} />
                    </Fact>
                    <Fact label={t('Knockout criteria')}>
                        <List items={analysis.knockouts} />
                    </Fact>
                    <Fact label={t('Keywords')}>
                        <Chips items={analysis.keywords} />
                    </Fact>
                </dl>
            </Section>
        </div>
    );
}

function Stat({
    label,
    value,
    hint,
}: {
    label: string;
    value: string;
    hint?: string;
}) {
    return (
        <div className="rounded-md bg-muted/50 p-3">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="text-lg font-semibold tabular-nums">{value}</p>
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
        </div>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="space-y-3">
            <h3 className="font-medium">{title}</h3>
            {children}
        </section>
    );
}

function Fact({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="space-y-1">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd>{children}</dd>
        </div>
    );
}

function List({
    items,
    ordered = false,
}: {
    items: string[];
    ordered?: boolean;
}) {
    if (items.length === 0) {
        return <Empty />;
    }

    const Tag = ordered ? 'ol' : 'ul';

    return (
        <Tag
            className={cn(
                'space-y-1 pl-5 text-sm',
                ordered ? 'list-decimal' : 'list-disc',
            )}
        >
            {items.map((item, index) => (
                <li key={index}>{item}</li>
            ))}
        </Tag>
    );
}

function Chips({ items }: { items: string[] }) {
    if (items.length === 0) {
        return <Empty />;
    }

    return (
        <div className="flex flex-wrap gap-1.5">
            {items.map((item, index) => (
                <Badge key={index} variant="outline">
                    {item}
                </Badge>
            ))}
        </div>
    );
}

function Empty() {
    const { t } = useTrans();

    return <p className="text-sm text-muted-foreground">{t('None')}</p>;
}
