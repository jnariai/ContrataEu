import { AlertTriangle } from 'lucide-react';
import { useState } from 'react';
import GeneratedAnswerController from '@/actions/App/Http/Controllers/GeneratedAnswerController';
import GeneratedAnswerRevertController from '@/actions/App/Http/Controllers/GeneratedAnswerRevertController';
import GeneratedDocumentController from '@/actions/App/Http/Controllers/GeneratedDocumentController';
import GeneratedDocumentPreviewController from '@/actions/App/Http/Controllers/GeneratedDocumentPreviewController';
import GeneratedDocumentPdfController from '@/actions/App/Http/Controllers/GeneratedDocumentPdfController';
import GeneratedDocumentRevertController from '@/actions/App/Http/Controllers/GeneratedDocumentRevertController';
import GeneratedTextEditor from '@/components/generated-text-editor';
import GenerationReport from '@/components/generation-report';
import { TabList, TabPanel } from '@/components/ui/tabs';
import { useTrans } from '@/hooks/use-trans';
import type { Application, Generation } from '@/types';

/**
 * The viewed version's documents, answers and report, plus the job itself. Documents and answers only exist for
 * a version that succeeded; the Job tab is always there.
 */
export default function ApplicationTabs({
    application,
    generation,
    limits,
    canEdit,
}: {
    application: Application;
    generation: Generation | null;
    limits: { document: number; answer: number };
    canEdit: boolean;
}) {
    const { t } = useTrans();
    const result = generation?.status.value === 'succeeded' ? generation : null;

    const tabs = [
        ...(result?.documents.map((document) => ({
            value: document.type,
            label: (
                <TabLabel
                    label={document.label}
                    count={document.todos.length + document.warnings.length}
                />
            ),
        })) ?? []),
        ...(result && result.answers.length > 0
            ? [
                  {
                      value: 'questions',
                      label: (
                          <TabLabel
                              label={t('Questions')}
                              count={result.answers.reduce(
                                  (sum, answer) => sum + answer.todos.length,
                                  0,
                              )}
                          />
                      ),
                  },
              ]
            : []),
        ...(result?.report ? [{ value: 'report', label: t('Report') }] : []),
        { value: 'job', label: t('Job') },
    ];

    const [picked, setPicked] = useState(tabs[0].value);
    // A version without the picked tab (no cover letter, say) falls back to the first one.
    const active = tabs.some((tab) => tab.value === picked)
        ? picked
        : tabs[0].value;

    return (
        <section
            id="documents"
            className="scroll-mt-20 space-y-6 rounded-xl border p-4 sm:p-6"
        >
            {result && <ReviewBanner generation={result} />}

            <TabList
                id="application"
                label={t('Application sections')}
                tabs={tabs}
                value={active}
                onValueChange={setPicked}
            />

            {result?.documents
                .filter((document) => document.type === active)
                .map((document) => (
                    <TabPanel
                        key={document.id}
                        id="application"
                        value={document.type}
                    >
                        <GeneratedTextEditor
                            key={`${document.id}:${document.updated_at}`}
                            id={document.id}
                            label={document.label}
                            content={document.content}
                            edited={document.edited}
                            maxLength={limits.document}
                            rows={document.type === 'cv' ? 28 : 14}
                            update={GeneratedDocumentController.update(
                                document.id,
                            )}
                            revert={GeneratedDocumentRevertController.store(
                                document.id,
                            )}
                            canEdit={canEdit}
                            preview={
                                document.has_pdf
                                    ? {
                                          url: GeneratedDocumentPreviewController.store.url(
                                              document.id,
                                          ),
                                          title: document.label,
                                      }
                                    : undefined
                            }
                            pdfUrl={
                                document.has_pdf
                                    ? GeneratedDocumentPdfController.show.url(
                                          document.id,
                                      )
                                    : undefined
                            }
                        />
                    </TabPanel>
                ))}

            {result && active === 'questions' && (
                <TabPanel
                    id="application"
                    value="questions"
                    className="space-y-8"
                >
                    {result.answers.map((answer, index) => (
                        <GeneratedTextEditor
                            key={`${answer.id}:${answer.updated_at}`}
                            id={answer.id}
                            label={
                                <span className="font-normal">
                                    <span className="font-medium">
                                        {index + 1}.
                                    </span>{' '}
                                    {answer.question}
                                </span>
                            }
                            content={answer.content}
                            edited={answer.edited}
                            maxLength={limits.answer}
                            rows={6}
                            update={GeneratedAnswerController.update(answer.id)}
                            revert={GeneratedAnswerRevertController.store(
                                answer.id,
                            )}
                            canEdit={canEdit}
                        />
                    ))}
                </TabPanel>
            )}

            {result?.report && active === 'report' && (
                <TabPanel id="application" value="report">
                    <GenerationReport report={result.report} />
                </TabPanel>
            )}

            {active === 'job' && (
                <TabPanel id="application" value="job">
                    <JobDetails application={application} />
                </TabPanel>
            )}
        </section>
    );
}

function TabLabel({ label, count }: { label: string; count: number }) {
    const { t } = useTrans();

    return (
        <>
            {label}
            {count > 0 && (
                <span
                    className="rounded-full bg-amber-100 px-1.5 text-xs text-amber-900 tabular-nums dark:bg-amber-950 dark:text-amber-200"
                    title={t('Needs your review')}
                >
                    {count}
                </span>
            )}
        </>
    );
}

/** `[TODO: …]` placeholders and CV structure warnings across the version. Never blocks anything. */
function ReviewBanner({ generation }: { generation: Generation }) {
    const { t } = useTrans();
    const todos = [
        ...new Set([
            ...generation.documents.flatMap((document) => document.todos),
            ...generation.answers.flatMap((answer) => answer.todos),
        ]),
    ];
    const warnings = generation.documents.flatMap((document) =>
        document.warnings.map((warning) => `${document.label}: ${warning}`),
    );

    if (todos.length === 0 && warnings.length === 0) {
        return null;
    }

    return (
        <div
            className="flex gap-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
            role="status"
        >
            <AlertTriangle className="mt-0.5 size-4 shrink-0" />
            <div className="min-w-0 space-y-2">
                {todos.length > 0 && (
                    <div className="space-y-1">
                        <p className="font-medium">
                            {t(
                                'Before you send it, fill in these placeholders:',
                            )}
                        </p>
                        <ul className="list-disc pl-5 break-words">
                            {todos.map((todo) => (
                                <li key={todo}>{todo}</li>
                            ))}
                        </ul>
                    </div>
                )}
                {warnings.length > 0 && (
                    <div className="space-y-1">
                        <p className="font-medium">
                            {t(
                                'Worth fixing so applicant tracking systems read the CV well:',
                            )}
                        </p>
                        <ul className="list-disc pl-5 break-words">
                            {warnings.map((warning) => (
                                <li key={warning}>{warning}</li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        </div>
    );
}

function JobDetails({ application }: { application: Application }) {
    const { t } = useTrans();
    const documents = [
        t('CV'),
        application.wants_cover_letter && t('Cover letter'),
        application.wants_recruiter_message && t('Recruiter message'),
    ].filter(Boolean);

    return (
        <div className="grid gap-6 lg:grid-cols-[1fr_16rem]">
            <div className="space-y-2">
                <h3 className="font-medium">{t('Job description')}</h3>
                <div className="max-h-[40rem] overflow-auto text-sm break-words whitespace-pre-wrap">
                    {application.job_description}
                </div>
            </div>

            <aside className="space-y-6 text-sm">
                <div className="space-y-1">
                    <h3 className="font-medium">{t('Documents')}</h3>
                    <p className="text-muted-foreground">
                        {documents.join(' · ')}
                    </p>
                </div>

                <div className="space-y-1">
                    <h3 className="font-medium">{t('Screening questions')}</h3>
                    {application.questions.length === 0 ? (
                        <p className="text-muted-foreground">{t('None')}</p>
                    ) : (
                        <ol className="list-decimal space-y-1 pl-5">
                            {application.questions.map((question) => (
                                <li key={question.id}>{question.question}</li>
                            ))}
                        </ol>
                    )}
                </div>

                {application.company_notes && (
                    <div className="space-y-1">
                        <h3 className="font-medium">{t('Company notes')}</h3>
                        <p className="whitespace-pre-wrap text-muted-foreground">
                            {application.company_notes}
                        </p>
                    </div>
                )}
            </aside>
        </div>
    );
}
