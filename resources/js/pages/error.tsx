import { Head, Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/hooks/use-trans';
import { tk } from '@/lib/i18n';
import { dashboard, home } from '@/routes';

type Props = {
    status: number;
    /** A safe, already-translated reason (a business rule or a service outage); replaces the generic text. */
    message: string | null;
};

const pages: Record<number, { title: string; description: string }> = {
    403: {
        title: tk('You can’t open this page'),
        description: tk('You don’t have access to it.'),
    },
    404: {
        title: tk('Page not found'),
        description: tk(
            'The page you’re looking for doesn’t exist or was deleted.',
        ),
    },
    419: {
        title: tk('Page expired'),
        description: tk(
            'Your session timed out. Go back, refresh the page and try again.',
        ),
    },
    429: {
        title: tk('Too many requests'),
        description: tk('Wait a minute, then try again.'),
    },
    500: {
        title: tk('Something went wrong'),
        description: tk(
            'We hit an error on our side. Please try again in a moment.',
        ),
    },
    503: {
        title: tk('Service unavailable'),
        description: tk(
            'A service we rely on isn’t responding right now. Please try again in a few minutes.',
        ),
    },
};

const fallback = {
    title: tk('We couldn’t do that'),
    description: tk('Please go back and try again.'),
};

/** Shown for failed page loads in production, and for business or service errors on any page load. */
export default function ErrorPage({ status, message }: Props) {
    const { t } = useTrans();
    const { auth } = usePage().props;
    const page =
        pages[status] ??
        (status >= 502 && status <= 504 ? pages[503] : fallback);

    return (
        <>
            <Head title={t(page.title)} />
            <main className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 text-center">
                <AppLogoIcon
                    className="size-10 text-primary dark:text-ink-blue"
                    aria-hidden
                />
                <div className="max-w-md space-y-2">
                    <p className="text-sm font-medium text-muted-foreground tabular-nums">
                        {status}
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t(page.title)}
                    </h1>
                    <p className="text-muted-foreground">
                        {message ?? t(page.description)}
                    </p>
                </div>
                <div className="flex flex-wrap justify-center gap-2">
                    <Button
                        variant="outline"
                        onClick={() => window.history.back()}
                    >
                        {t('Go back')}
                    </Button>
                    <Button asChild>
                        <Link href={auth?.user ? dashboard() : home()}>
                            {auth?.user ? t('Dashboard') : t('Home')}
                        </Link>
                    </Button>
                </div>
            </main>
        </>
    );
}
