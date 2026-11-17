import { Form, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTrans } from '@/hooks/use-trans';
import type {
    Application,
    ApplicationFormOptions,
    LanguageValue,
} from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

type QuestionRow = { key: string; id: string | null; question: string };

let nextKey = 0;
const newKey = (): string => `q${nextKey++}`;

/** Radix Select can't hold an empty value, so "Not specified" is this sentinel and submits as empty. */
const NO_WORK_MODE = 'none';

export default function ApplicationForm({
    form,
    application,
    defaultLanguage,
    options,
    submitLabel,
}: {
    form: RouteFormDefinition<'post'>;
    application?: Application;
    defaultLanguage: LanguageValue;
    options: ApplicationFormOptions;
    submitLabel: string;
}) {
    const { t, locale } = useTrans();
    const { languages } = usePage().props;
    const [workMode, setWorkMode] = useState(
        application?.work_mode?.value ?? NO_WORK_MODE,
    );
    const [language, setLanguage] = useState<string>(
        application?.language.value ?? defaultLanguage,
    );
    const [jdLength, setJdLength] = useState(
        application?.job_description.length ?? 0,
    );
    const [questions, setQuestions] = useState<QuestionRow[]>(
        (application?.questions ?? []).map((question) => ({
            key: newKey(),
            id: question.id,
            question: question.question,
        })),
    );

    const move = (index: number, by: number): void =>
        setQuestions((rows) => {
            const next = [...rows];
            [next[index], next[index + by]] = [next[index + by], next[index]];

            return next;
        });

    return (
        <Form
            {...form}
            options={{ preserveScroll: true }}
            className="space-y-8"
        >
            {({ processing, errors }) => (
                <>
                    <section className="grid gap-6 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="company_name">{t('Company')}</Label>
                            <Input
                                id="company_name"
                                name="company_name"
                                defaultValue={application?.company_name}
                                required
                                maxLength={120}
                                autoFocus={!application}
                            />
                            <InputError message={errors.company_name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="role_title">{t('Role')}</Label>
                            <Input
                                id="role_title"
                                name="role_title"
                                defaultValue={application?.role_title}
                                required
                                maxLength={160}
                            />
                            <InputError message={errors.role_title} />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="job_url">
                                {t('Job posting URL')}
                            </Label>
                            <Input
                                id="job_url"
                                name="job_url"
                                type="url"
                                defaultValue={application?.job_url ?? ''}
                                placeholder="https://"
                            />
                            <InputError message={errors.job_url} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="location">{t('Location')}</Label>
                            <Input
                                id="location"
                                name="location"
                                defaultValue={application?.location ?? ''}
                                maxLength={120}
                            />
                            <InputError message={errors.location} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="work_mode">{t('Work mode')}</Label>
                            <input
                                type="hidden"
                                name="work_mode"
                                value={
                                    workMode === NO_WORK_MODE ? '' : workMode
                                }
                            />
                            <Select
                                value={workMode}
                                onValueChange={setWorkMode}
                            >
                                <SelectTrigger
                                    id="work_mode"
                                    className="w-full"
                                    aria-invalid={
                                        errors.work_mode ? true : undefined
                                    }
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NO_WORK_MODE}>
                                        {t('Not specified')}
                                    </SelectItem>
                                    {options.workModes.map((mode) => (
                                        <SelectItem
                                            key={mode.value}
                                            value={mode.value}
                                        >
                                            {mode.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.work_mode} />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="language">
                                {t('Output language')}
                            </Label>
                            <input
                                type="hidden"
                                name="language"
                                value={language}
                            />
                            <Select
                                value={language}
                                onValueChange={setLanguage}
                            >
                                <SelectTrigger
                                    id="language"
                                    className="w-full"
                                    aria-invalid={
                                        errors.language ? true : undefined
                                    }
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {languages.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                            lang={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <p className="text-xs text-muted-foreground">
                                {t(
                                    'The language the CV, letters and answers are written in. Your master CV can be in either language.',
                                )}
                            </p>
                            <InputError message={errors.language} />
                        </div>
                    </section>

                    <section className="grid gap-2">
                        <Label htmlFor="job_description">
                            {t('Job description')}
                        </Label>
                        <Textarea
                            id="job_description"
                            name="job_description"
                            defaultValue={application?.job_description}
                            required
                            rows={14}
                            maxLength={options.limits.jobDescription}
                            placeholder={t(
                                'Paste the full job description here.',
                            )}
                            onChange={(event) =>
                                setJdLength(event.target.value.length)
                            }
                        />
                        <div className="flex items-center justify-between gap-2">
                            <InputError message={errors.job_description} />
                            <span className="ml-auto text-xs text-muted-foreground tabular-nums">
                                {jdLength.toLocaleString(locale.htmlLang)} /{' '}
                                {options.limits.jobDescription.toLocaleString(
                                    locale.htmlLang,
                                )}
                            </span>
                        </div>
                    </section>

                    <section className="grid gap-2">
                        <Label htmlFor="company_notes">
                            {t('Company notes')}
                        </Label>
                        <Textarea
                            id="company_notes"
                            name="company_notes"
                            defaultValue={application?.company_notes ?? ''}
                            rows={3}
                            maxLength={5000}
                            placeholder={t(
                                'Optional. What you know about the company: product, mission, recent news, who referred you.',
                            )}
                        />
                        <InputError message={errors.company_notes} />
                    </section>

                    <fieldset className="space-y-3">
                        <legend className="mb-2 text-sm font-medium">
                            {t('Also write')}
                        </legend>
                        <p className="text-sm text-muted-foreground">
                            {t('A tailored CV is always generated.')}
                        </p>
                        {(
                            [
                                ['wants_cover_letter', t('Cover letter')],
                                [
                                    'wants_recruiter_message',
                                    t('Recruiter message'),
                                ],
                            ] as const
                        ).map(([name, label]) => (
                            <div key={name} className="flex items-center gap-3">
                                <Checkbox
                                    id={name}
                                    name={name}
                                    value="1"
                                    defaultChecked={
                                        application?.[name] ?? false
                                    }
                                />
                                <Label htmlFor={name}>{label}</Label>
                            </div>
                        ))}
                    </fieldset>

                    <fieldset className="space-y-3">
                        <legend className="mb-2 text-sm font-medium">
                            {t('Screening questions')}
                        </legend>
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'Questions the job form asks you, like “Why do you want to work here?”. Each gets a tailored answer.',
                            )}
                        </p>
                        <InputError message={errors.questions} />
                        {questions.map((row, index) => (
                            <div key={row.key} className="space-y-1">
                                <div className="flex items-start gap-2">
                                    <input
                                        type="hidden"
                                        name={`questions[${index}][id]`}
                                        value={row.id ?? ''}
                                    />
                                    <Label
                                        htmlFor={`question-${row.key}`}
                                        className="sr-only"
                                    >
                                        {t('Question :number', {
                                            number: index + 1,
                                        })}
                                    </Label>
                                    <Input
                                        id={`question-${row.key}`}
                                        name={`questions[${index}][question]`}
                                        value={row.question}
                                        required
                                        maxLength={1000}
                                        onChange={(event) =>
                                            setQuestions((rows) =>
                                                rows.map((current) =>
                                                    current.key === row.key
                                                        ? {
                                                              ...current,
                                                              question:
                                                                  event.target
                                                                      .value,
                                                          }
                                                        : current,
                                                ),
                                            )
                                        }
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        disabled={index === 0}
                                        onClick={() => move(index, -1)}
                                        aria-label={t('Move up')}
                                    >
                                        <ArrowUp className="size-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        disabled={
                                            index === questions.length - 1
                                        }
                                        onClick={() => move(index, 1)}
                                        aria-label={t('Move down')}
                                    >
                                        <ArrowDown className="size-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={() =>
                                            setQuestions((rows) =>
                                                rows.filter(
                                                    (current) =>
                                                        current.key !== row.key,
                                                ),
                                            )
                                        }
                                        aria-label={t('Remove question')}
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                                <InputError
                                    message={
                                        errors[`questions.${index}.question`] ??
                                        errors[`questions.${index}.id`]
                                    }
                                />
                            </div>
                        ))}
                        {questions.length < options.limits.questions && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    setQuestions((rows) => [
                                        ...rows,
                                        {
                                            key: newKey(),
                                            id: null,
                                            question: '',
                                        },
                                    ])
                                }
                            >
                                <Plus className="size-4" />
                                {t('Add question')}
                            </Button>
                        )}
                    </fieldset>

                    <Button disabled={processing}>{submitLabel}</Button>
                </>
            )}
        </Form>
    );
}
