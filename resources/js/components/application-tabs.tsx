import { AlertTriangle } from 'lucide-react';
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

/** The tabs this version has, in order: its documents, the answers, the report, and always the job itself. */
export function applicationTabValues(generation: Generation | null): string[] {
    const result = generation?.status.value === 'succeeded' ? generation : null;

    return [
        ...(result?.documents.map((document) => document.type) ?? []),
        ...(result && result.answers.length > 0 ? ['questions'] : []),
        ...(result?.report ? ['report'] : []),
        'job',
    ];
}

/**
 * The viewed version's documents on their sheets, its answers and report, plus the job itself. Documents and
 * answers only exist for a version that succeeded; the Job tab is always there. The page owns the picked tab, so
 * the package list beside it can open a document.
 */
export default function ApplicationTabs({
    application,
    generation,
    limits,
    canEdit,
    active,
    onActiveChange,
}: {
    application: Application;
    generation: Generation | null;
    limits: { document: number; answer: number };
    canEdit: boolean;
    active: string;
    onActiveChange: (value: string) => void;
}) {
    const { t } = useTrans();
    const result = generation?.status.value === 'succeeded' ? generation : null;

    const tabs = [
        ...(result?.documents.map((document) => ({
            value: document.type,
            label: (
                <TabLabel
                    label={document.label}
                    count={
                        new Set(document.todos).size + document.warnings.length
                    }
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

    return (
        <section
            id="documents"
            className="min-w-0 scroll-mt-6"
            aria-labelledby="documents-heading"
        >
            {/* Names the section for the outline (h1 role → h2 here → h3 documents and report groups). */}
            <h2 id="documents-heading" className="sr-only">
                {t('Documents')}
            </h2>
            <TabList
                id="application"
                label={t('Application sections')}
                tabs={tabs}
                value={active}
                onValueChange={onActiveChange}
            />

            {result?.documents
                .filter((document) => document.type === active)
                .map((document) => (
                    <TabPanel
                        key={document.id}
                        id="application"
                        value={document.type}
                        className="space-y-5"
                    >
                        <GeneratedTextEditor
                            key={`${document.id}:${document.updated_at}`}
                            id={document.id}
                            label={document.label}
                            content={document.content}
                            html={document.html}
                            edited={document.edited}
                            maxLength={limits.document}
                            rows={document.type === 'cv' ? 30 : 16}
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
                        <AtsNotes warnings={document.warnings} />
                    </TabPanel>
                ))}

            {result && active === 'questions' && (
                <TabPanel
                    id="application"
                    value="questions"
                    className="space-y-10 pt-6"
                >
                    {result.answers.map((answer, index) => (
                        <GeneratedTextEditor
                            key={`${answer.id}:${answer.updated_at}`}
                            id={answer.id}
                            label={
                                <>
                                    <span className="text-muted-foreground tabular-nums">
                                        {index + 1}.
                                    </span>{' '}
                                    {answer.question}
                                </>
                            }
                            content={answer.content}
                            edited={answer.edited}
                            maxLength={limits.answer}
                            rows={8}
                            update={GeneratedAnswerController.update(answer.id)}
                            revert={GeneratedAnswerRevertController.store(
                                answer.id,
                            )}
                            canEdit={canEdit}
                            compact
                        />
                    ))}
                </TabPanel>
            )}

            {result?.report && active === 'report' && (
                <TabPanel id="application" value="report" className="pt-6">
                    <GenerationReport report={result.report} />
                </TabPanel>
            )}

            {active === 'job' && (
                <TabPanel id="application" value="job" className="pt-6">
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
                    className="rounded-full bg-todo px-1.5 text-xs font-bold text-todo-foreground tabular-nums"
                    title={t('Needs your review')}
                >
                    {count}
                    <span className="sr-only"> {t('Needs your review')}</span>
                </span>
            )}
        </>
    );
}

/**
 * CV structure warnings, under the sheet so the document comes first. Its `[TODO: …]` placeholders are already
 * highlighted on the sheet and counted on the tab and in the package. Never blocks anything.
 */
function AtsNotes({ warnings }: { warnings: string[] }) {
    const { t } = useTrans();

    if (warnings.length === 0) {
        return null;
    }

    return (
        <div className="flex gap-2 border-t pt-4 text-sm text-muted-foreground">
            <AlertTriangle className="mt-0.5 size-4 shrink-0 text-todo-foreground dark:text-todo" />
            <div>
                <p className="font-semibold text-foreground">
                    {t(
                        'Worth fixing so applicant tracking systems read the CV well:',
                    )}
                </p>
                <ul className="mt-1 list-disc pl-5 break-words">
                    {warnings.map((warning) => (
                        <li key={warning}>{warning}</li>
                    ))}
                </ul>
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
