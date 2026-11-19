import { useEffect, useRef, useState } from 'react';
import { statusInk } from '@/components/status-badge';
import { useTrans } from '@/hooks/use-trans';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { LanguageValue, StatusOption } from '@/types';

/**
 * The application's entry stamp: status, date and language inked together, like an entry in working papers.
 * It is pressed once when the status changes on this page (never on load; reduced motion just shows it).
 */
export default function EntryStamp({
    status,
    date,
    language,
    className,
}: {
    status: StatusOption;
    date: string;
    language: LanguageValue;
    className?: string;
}) {
    const { locale } = useTrans();
    const first = useRef(status.value);
    const [pressed, setPressed] = useState(false);

    useEffect(() => {
        if (status.value !== first.current) {
            setPressed(true);
            first.current = status.value;
        }
    }, [status.value]);

    return (
        <div
            className={cn(
                'inline-flex -rotate-[1.5deg] flex-col rounded-[3px] border-2 px-3 pt-2 pb-1.5',
                statusInk[status.value] ?? statusInk.draft,
                pressed && 'stamp-pressed',
                className,
            )}
            onAnimationEnd={() => setPressed(false)}
        >
            <span className="text-[1.375rem] leading-none font-bold tracking-[0.06em] uppercase">
                {status.label}
            </span>
            <span className="mt-1 text-[0.6875rem] font-semibold tracking-[0.1em] uppercase tabular-nums">
                {formatDate(date, locale.htmlLang)} ·{' '}
                <span lang={language}>
                    {language === 'pt-BR' ? 'PT-BR' : 'EN'}
                </span>
            </span>
        </div>
    );
}

/** The ordered path an application walks; rejected and withdrawn close it instead of continuing. */
const path = ['draft', 'ready', 'applied', 'interviewing', 'offer'];

export function StatusLine({
    statuses,
    current,
}: {
    statuses: StatusOption[];
    current: StatusOption;
}) {
    const { t } = useTrans();
    const steps = path
        .map((value) => statuses.find((status) => status.value === value))
        .filter((status): status is StatusOption => status !== undefined);
    const position = path.indexOf(current.value);
    const closed = position === -1;

    return (
        <div>
            <ol
                className="flex items-center"
                aria-label={t('Application progress')}
            >
                {steps.map((step, index) => {
                    const done = !closed && index < position;
                    const now = !closed && index === position;

                    return (
                        <li
                            key={step.value}
                            className="flex flex-1 items-center last:flex-none"
                            aria-current={now ? 'step' : undefined}
                        >
                            <span
                                title={step.label}
                                className={cn(
                                    'block shrink-0 rounded-full border-[1.5px]',
                                    now
                                        ? cn(
                                              'size-3.5 bg-current',
                                              statusInk[step.value],
                                          )
                                        : 'size-2.5 border-ink-blue',
                                    done && 'bg-ink-blue',
                                    closed && 'border-ink-grey/60',
                                )}
                            >
                                <span className="sr-only">
                                    {step.label}
                                    {now && `, ${t('current')}`}
                                </span>
                            </span>
                            {index < steps.length - 1 && (
                                <span
                                    className={cn(
                                        'h-px flex-1',
                                        closed
                                            ? 'bg-ink-grey/40'
                                            : 'bg-ink-blue/60',
                                    )}
                                />
                            )}
                        </li>
                    );
                })}
            </ol>
            {/* The current step is named under its own dot; a closed application says how it closed. */}
            <div
                className="mt-1.5 grid text-xs font-semibold"
                style={{
                    gridTemplateColumns: `repeat(${steps.length}, minmax(0, 1fr))`,
                }}
                aria-hidden
            >
                {closed ? (
                    <span
                        className={cn(
                            'col-span-full',
                            statusInk[current.value],
                        )}
                    >
                        {current.label}
                    </span>
                ) : (
                    <span
                        className={cn(
                            'whitespace-nowrap',
                            statusInk[current.value],
                            position === 0
                                ? 'justify-self-start'
                                : position === steps.length - 1
                                  ? 'justify-self-end'
                                  : 'justify-self-center',
                        )}
                        style={{ gridColumnStart: position + 1 }}
                    >
                        {current.label}
                    </span>
                )}
            </div>
        </div>
    );
}

/** English and Portuguese as the paired mark of every document: the one in use is inked. */
export function LanguagePair({ value }: { value: LanguageValue }) {
    const { t } = useTrans();

    return (
        <span
            className="inline-flex overflow-hidden rounded-[3px] border border-ink-blue/50 text-xs font-semibold tracking-[0.04em]"
            role="img"
            aria-label={t('Output language: :language', {
                language: value === 'pt-BR' ? 'Português (Brasil)' : 'English',
            })}
            title={t('Output language: :language', {
                language: value === 'pt-BR' ? 'Português (Brasil)' : 'English',
            })}
        >
            {(['en', 'pt-BR'] as const).map((language) => (
                <span
                    key={language}
                    lang={language}
                    className={cn(
                        'px-2 py-1',
                        language === value
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground',
                    )}
                >
                    {language === 'pt-BR' ? 'PT-BR' : 'EN'}
                </span>
            ))}
        </span>
    );
}
