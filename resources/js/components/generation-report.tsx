import type { ReactNode } from 'react';
import { useTrans } from '@/hooks/use-trans';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { GenerationReport as Report } from '@/types';

type Strength = Report['matches'][number]['strength'];

/** How well the CV meets a requirement, in stamp inks like the status badges: always readable as text. */
const strengths: Record<Strength, { label: string; ink: string }> = {
    strong: { label: tk('Strong'), ink: 'text-ink-green border-ink-green' },
    partial: {
        label: tk('Partial'),
        ink: 'text-todo-foreground border-todo-foreground dark:text-todo dark:border-todo',
    },
    missing: { label: tk('Missing'), ink: 'text-ink-red border-ink-red' },
};

/**
 * The AI's analysis of how the master CV fits the job, ordered for the two moments it is read: before sending (what
 * could sink the application, what it still needs from you) and before the interview (what to rehearse). The
 * requirement-by-requirement evidence, keywords and job breakdown follow as reference.
 */
export default function GenerationReport({ report }: { report: Report }) {
    const { t } = useTrans();
    const coverage = report.keyword_coverage;
    const analysis = report.jd_analysis;
    const strong = report.matches.filter(
        (match) => match.strength === 'strong',
    ).length;
    const missing = new Set(
        coverage.missing.map((keyword) => keyword.toLowerCase()),
    );
    // Every keyword the job asks for, the missing ones marked; keywords only reported as missing are kept too.
    const keywords = [
        ...analysis.keywords,
        ...coverage.missing.filter(
            (keyword) =>
                !analysis.keywords.some(
                    (known) => known.toLowerCase() === keyword.toLowerCase(),
                ),
        ),
    ];

    return (
        <div className="space-y-10 text-sm">
            <p className="text-muted-foreground tabular-nums">
                {[
                    t(':percent% keyword coverage (:matched of :total)', {
                        percent: coverage.percentage,
                        matched: coverage.matched,
                        total: coverage.total,
                    }),
                    t(':strong of :count requirements strong', {
                        strong,
                        count: report.matches.length,
                    }),
                    t('Gaps: :count', { count: report.gaps.length }),
                    t('Red flags: :count', { count: report.red_flags.length }),
                ].join(' · ')}
            </p>

            <Group title={t('Before you send')}>
                <Ledger>
                    <LedgerRow label={t('Knockout criteria')}>
                        <Items items={analysis.knockouts} />
                    </LedgerRow>
                    <LedgerRow label={t('Questions for you')}>
                        <Items items={report.todos} />
                    </LedgerRow>
                    <LedgerRow label={t('Red flags')}>
                        <Items items={report.red_flags} />
                    </LedgerRow>
                    <LedgerRow label={t('Gaps')}>
                        <Items items={report.gaps} />
                    </LedgerRow>
                </Ledger>
            </Group>

            <Group title={t('Interview prep')}>
                {report.interview_questions.length === 0 ? (
                    <Empty />
                ) : (
                    <ol className="border-t">
                        {report.interview_questions.map((question, index) => (
                            <li
                                key={index}
                                className="flex gap-3 border-b py-3"
                            >
                                <span className="w-5 shrink-0 text-right text-muted-foreground tabular-nums">
                                    {index + 1}.
                                </span>
                                <span className="text-pretty">{question}</span>
                            </li>
                        ))}
                    </ol>
                )}
            </Group>

            <Group title={t('Requirements')}>
                {report.matches.length === 0 ? (
                    <Empty />
                ) : (
                    <ul className="border-t">
                        {report.matches.map((match, index) => (
                            <li key={index} className="space-y-1 border-b py-3">
                                <div className="flex items-baseline justify-between gap-3">
                                    <span className="font-semibold text-pretty">
                                        {match.requirement}
                                    </span>
                                    <StrengthMark strength={match.strength} />
                                </div>
                                <p className="text-pretty text-muted-foreground">
                                    {match.evidence ||
                                        t('Nothing in your CV shows this.')}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </Group>

            <Group title={t('Keywords')}>
                {keywords.length === 0 ? (
                    <Empty />
                ) : (
                    <div className="space-y-2">
                        <ul className="flex flex-wrap gap-x-1.5 gap-y-2 leading-6">
                            {keywords.map((keyword, index) => {
                                const absent = missing.has(
                                    keyword.toLowerCase(),
                                );

                                return (
                                    <li key={index}>
                                        <span
                                            className={cn(
                                                'rounded-[2px] px-1 py-0.5',
                                                absent
                                                    ? 'bg-todo font-semibold text-todo-foreground shadow-[0_0_0_2px_var(--todo)]'
                                                    : 'bg-secondary',
                                            )}
                                        >
                                            {keyword}
                                            {absent && (
                                                <span className="sr-only">
                                                    {' '}
                                                    ({t('missing from the CV')})
                                                </span>
                                            )}
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                        {coverage.missing.length > 0 && (
                            <p className="text-xs text-muted-foreground">
                                {t('Highlighted: missing from the CV.')}
                            </p>
                        )}
                    </div>
                )}
            </Group>

            <Group title={t('Job analysis')}>
                <Ledger>
                    <LedgerRow label={t('Seniority')}>
                        {analysis.seniority || <Empty />}
                    </LedgerRow>
                    <LedgerRow label={t('Domain')}>
                        {analysis.domain || <Empty />}
                    </LedgerRow>
                    <LedgerRow label={t('Must-haves')}>
                        <Items items={analysis.must_haves} />
                    </LedgerRow>
                    <LedgerRow label={t('Nice-to-haves')}>
                        <Items items={analysis.nice_to_haves} />
                    </LedgerRow>
                </Ledger>
            </Group>
        </div>
    );
}

function Group({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="space-y-3">
            <h3 className="text-[1.125rem] font-semibold">{title}</h3>
            {children}
        </section>
    );
}

/** Field rows, as on a form: the label on the left (above it on phones), the entry beside it, ruled between. */
function Ledger({ children }: { children: ReactNode }) {
    return <dl className="border-t">{children}</dl>;
}

function LedgerRow({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-1 border-b py-3 sm:grid-cols-[10rem_minmax(0,1fr)] sm:gap-4">
            <dt className="field-label pt-0.5">{label}</dt>
            <dd className="min-w-0">{children}</dd>
        </div>
    );
}

function StrengthMark({ strength }: { strength: Strength }) {
    const { t } = useTrans();
    const mark = strengths[strength];

    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center rounded-[3px] border-[1.5px] px-1.5 py-px text-[0.6875rem] leading-4 font-bold tracking-[0.08em] whitespace-nowrap uppercase',
                mark?.ink ?? 'border-ink-grey text-ink-grey',
            )}
        >
            {mark ? t(mark.label) : strength}
        </span>
    );
}

/** A short list of findings: one line each, or "None". */
function Items({ items }: { items: string[] }) {
    if (items.length === 0) {
        return <Empty />;
    }

    if (items.length === 1) {
        return <p className="text-pretty">{items[0]}</p>;
    }

    return (
        <ul className="list-disc space-y-1 pl-4 marker:text-muted-foreground">
            {items.map((item, index) => (
                <li key={index} className="text-pretty">
                    {item}
                </li>
            ))}
        </ul>
    );
}

function Empty() {
    const { t } = useTrans();

    return <span className="text-muted-foreground">{t('None')}</span>;
}
