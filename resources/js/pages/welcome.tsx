import { Head, Link, usePage } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import LanguageSwitcher from '@/components/language-switcher';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/hooks/use-trans';
import { tk } from '@/lib/i18n';
import { dashboard, login, register } from '@/routes';

const features = [
    {
        title: tk('Tailored in about a minute'),
        description: tk(
            'Paste the job description and get a CV, cover letter, recruiter message and screening answers written for that job.',
        ),
    },
    {
        title: tk('Your rules, your prompts'),
        description: tk(
            'Start from a proven screening playbook or override any part of the prompt with your own instructions.',
        ),
    },
    {
        title: tk('ATS-friendly PDFs'),
        description: tk(
            'One clean, single-column template that parsers read correctly and recruiters enjoy.',
        ),
    },
    {
        title: tk('English or Portuguese'),
        description: tk(
            'Keep one master CV and generate each application in English or Brazilian Portuguese.',
        ),
    },
];

export default function Welcome() {
    const { auth } = usePage().props;
    const { t } = useTrans();

    return (
        <>
            <Head title={t('Hire me')} />
            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <header className="guilloche bg-band text-band-foreground">
                    <div className="mx-auto flex h-16 w-full max-w-[90rem] items-center gap-3 px-4 sm:px-6 lg:px-12">
                        <AppLogo />
                        <nav className="ml-auto flex items-center gap-2">
                            <LanguageSwitcher className="hidden h-9 border-band-muted/40 bg-transparent text-band-foreground hover:bg-white/10 sm:flex [&_svg]:!text-band-muted" />
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="rounded-md bg-band-foreground px-4 py-2 text-sm font-semibold text-band hover:bg-band-foreground/90"
                                >
                                    {t('Dashboard')}
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="rounded-md px-3 py-2 text-sm font-medium text-band-muted hover:text-band-foreground"
                                    >
                                        {t('Log in')}
                                    </Link>
                                    <Link
                                        href={register()}
                                        className="rounded-md bg-band-foreground px-4 py-2 text-sm font-semibold text-band hover:bg-band-foreground/90"
                                    >
                                        {t('Sign up')}
                                    </Link>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                <main className="mx-auto w-full max-w-[90rem] flex-1 px-4 sm:px-6 lg:px-12">
                    <section className="grid items-center gap-12 py-14 lg:grid-cols-[minmax(0,1fr)_minmax(0,34rem)] lg:gap-20 lg:py-24">
                        <div className="max-w-2xl space-y-7">
                            <h1 className="text-[2.5rem] leading-[1.05] font-semibold tracking-[-0.03em] text-balance sm:text-[3.5rem]">
                                {t(
                                    'From job description to ready-to-send application in a minute.',
                                )}
                            </h1>
                            <p className="max-w-xl text-lg text-muted-foreground">
                                {t(
                                    'Tailor your CV, cover letter and recruiter message for every job, with your own rules and your own AI key. Apply more, stress less.',
                                )}
                            </p>
                            {!auth.user && (
                                <div className="flex flex-wrap items-center gap-3">
                                    <Button size="lg" asChild>
                                        <Link href={register()}>
                                            {t('Get started for free')}
                                        </Link>
                                    </Button>
                                    <Button size="lg" variant="outline" asChild>
                                        <Link href={login()}>
                                            {t('Log in')}
                                        </Link>
                                    </Button>
                                </div>
                            )}
                        </div>

                        <SampleEntry />
                    </section>

                    <section
                        aria-label={t('What you get')}
                        className="grid border-t sm:grid-cols-2"
                    >
                        {features.map(({ title, description }, index) => (
                            <div
                                key={title}
                                className={
                                    'space-y-2 border-b py-8 sm:pr-10 ' +
                                    (index % 2 === 1
                                        ? 'sm:border-l sm:pl-10'
                                        : '')
                                }
                            >
                                <h2 className="text-lg font-semibold">
                                    {t(title)}
                                </h2>
                                <p className="max-w-md text-muted-foreground">
                                    {t(description)}
                                </p>
                            </div>
                        ))}
                    </section>
                </main>

                <footer className="mx-auto w-full max-w-[90rem] px-4 py-10 text-sm text-muted-foreground sm:px-6 lg:px-12">
                    {t(
                        'ContrataEu runs on your own AI key and your own rules.',
                    )}
                </footer>
            </div>
        </>
    );
}

/**
 * A sample entry, invented and labelled as such: a tailored CV on its sheet, stamped applied, with one placeholder
 * still to fill, which is what the product hands back.
 */
function SampleEntry() {
    const { t } = useTrans();

    return (
        <figure className="relative mx-auto w-full max-w-[34rem]">
            <div
                lang="en"
                className="sheet-document rounded-[2px] bg-sheet px-7 py-8 shadow-[0_1px_0_var(--border),0_28px_50px_-24px_rgb(20_32_46/0.4)] sm:px-11 sm:py-11"
                aria-hidden
            >
                <h1>Ana Souza</h1>
                <p>Recife, Brazil · ana@example.com · github.com/anasouza</p>
                <h2>Summary</h2>
                <p>
                    Backend engineer with 7 years in payments. Moved a
                    card-settlement service to Go and PostgreSQL, cutting
                    nightly reconciliation from 4 hours to{' '}
                    <mark>[TODO: new duration?]</mark>.
                </p>
                <h2>Experience</h2>
                <h3>Senior Software Engineer, Pagamentos Rápidos</h3>
                <p>
                    <em>Mar 2022 – Present</em>
                </p>
                <ul>
                    <li>Designed the idempotent Pix payout API.</li>
                    <li>Led on-call for settlement and reconciliation.</li>
                </ul>
                <h2>Skills</h2>
                <p>Go, PHP, Laravel, PostgreSQL, Kafka, Kubernetes, AWS</p>
            </div>

            <div
                className="absolute -top-5 right-2 inline-flex -rotate-[1.5deg] flex-col rounded-[3px] border-2 border-ink-violet bg-background/80 px-3 pt-2 pb-1.5 text-ink-violet backdrop-blur-[1px] sm:-right-3"
                aria-hidden
            >
                <span className="text-[1.375rem] leading-none font-bold tracking-[0.06em]">
                    APPLIED
                </span>
                <span className="mt-1 text-[0.6875rem] font-semibold tracking-[0.1em] tabular-nums">
                    18 NOV 2026 · EN
                </span>
            </div>

            <figcaption className="mt-4 text-sm text-muted-foreground">
                {t('Sample: an invented candidate and job.')}
            </figcaption>
        </figure>
    );
}
