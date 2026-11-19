import { Link } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import LanguageSwitcher from '@/components/language-switcher';
import { useTrans } from '@/hooks/use-trans';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

/** Sign-in and sign-up pages: the same navy band as the app, and the form on a single page below it. */
export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { t } = useTrans();

    return (
        <div className="flex min-h-svh flex-col bg-background">
            <header className="guilloche bg-band text-band-foreground">
                <div className="mx-auto flex h-16 w-full max-w-[90rem] items-center justify-between gap-4 px-4 sm:px-6 lg:px-12">
                    <Link
                        href={home()}
                        className="rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-band-foreground"
                    >
                        <AppLogo />
                    </Link>
                    <LanguageSwitcher className="h-9 border-band-muted/40 bg-transparent text-band-foreground hover:bg-white/10 [&_svg]:!text-band-muted" />
                </div>
            </header>

            <main className="flex flex-1 justify-center px-4 py-12 sm:py-20">
                <div className="w-full max-w-sm space-y-6">
                    <div className="space-y-1.5">
                        <h1 className="text-2xl leading-tight font-semibold tracking-[-0.015em]">
                            {title ? t(title) : null}
                        </h1>
                        {description && (
                            <p className="text-muted-foreground">
                                {t(description)}
                            </p>
                        )}
                    </div>
                    <div className="rounded-md border bg-card p-6 shadow-[0_14px_30px_-18px_rgb(20_32_46/0.3)]">
                        {children}
                    </div>
                </div>
            </main>
        </div>
    );
}
