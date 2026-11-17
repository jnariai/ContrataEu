import { Head, Link, usePage } from '@inertiajs/react';
import { FileText, Languages, ListChecks, Sparkles } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import LanguageSwitcher from '@/components/language-switcher';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/hooks/use-trans';
import { tk } from '@/lib/i18n';
import { dashboard, login, register } from '@/routes';

const features = [
    {
        icon: Sparkles,
        title: tk('Tailored in about a minute'),
        description: tk(
            'Paste the job description and get a CV, cover letter, recruiter message and screening answers written for that job.',
        ),
    },
    {
        icon: ListChecks,
        title: tk('Your rules, your prompts'),
        description: tk(
            'Start from a proven screening playbook or override any part of the prompt with your own instructions.',
        ),
    },
    {
        icon: FileText,
        title: tk('ATS-friendly PDFs'),
        description: tk(
            'One clean, single-column template that parsers read correctly and recruiters enjoy.',
        ),
    },
    {
        icon: Languages,
        title: tk('English or Portuguese'),
        description: tk(
            'Keep one master CV and generate each application in English or Brazilian Portuguese.',
        ),
    },
];

export default function Welcome() {
    const { auth, name } = usePage().props;
    const { t } = useTrans();

    return (
        <>
            <Head title={t('Hire me')} />
            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <header className="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 px-4 py-6">
                    <div className="flex items-center gap-2 font-semibold">
                        <AppLogoIcon className="size-6 fill-current" />
                        {name}
                    </div>
                    <nav className="flex items-center gap-2">
                        <LanguageSwitcher />
                        {auth.user ? (
                            <Button asChild>
                                <Link href={dashboard()}>{t('Dashboard')}</Link>
                            </Button>
                        ) : (
                            <>
                                <Button variant="ghost" asChild>
                                    <Link href={login()}>{t('Log in')}</Link>
                                </Button>
                                <Button asChild>
                                    <Link href={register()}>
                                        {t('Sign up')}
                                    </Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>

                <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-16 px-4 py-12">
                    <section className="max-w-2xl space-y-6">
                        <h1 className="text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                            {t(
                                'From job description to ready-to-send application in a minute.',
                            )}
                        </h1>
                        <p className="text-lg text-muted-foreground">
                            {t(
                                'Tailor your CV, cover letter and recruiter message for every job, with your own rules and your own AI key. Apply more, stress less.',
                            )}
                        </p>
                        {!auth.user && (
                            <Button size="lg" asChild>
                                <Link href={register()}>
                                    {t('Get started for free')}
                                </Link>
                            </Button>
                        )}
                    </section>

                    <section className="grid gap-6 sm:grid-cols-2">
                        {features.map(({ icon: Icon, title, description }) => (
                            <div
                                key={title}
                                className="space-y-2 rounded-xl border p-6"
                            >
                                <Icon className="size-5 text-muted-foreground" />
                                <h2 className="font-medium">{t(title)}</h2>
                                <p className="text-sm text-muted-foreground">
                                    {t(description)}
                                </p>
                            </div>
                        ))}
                    </section>
                </main>
            </div>
        </>
    );
}
