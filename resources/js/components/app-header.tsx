import { Link, usePage } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import AppLogo, { AppWordmark } from '@/components/app-logo';
import AppLogoIcon from '@/components/app-logo-icon';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { useTrans } from '@/hooks/use-trans';
import { tk } from '@/lib/i18n';
import { cn, toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import { create as createApplication } from '@/routes/applications';
import { edit as candidateProfile } from '@/routes/candidate-profile';

/** Where each item lives; `match` decides the active one (Settings spans every settings page). */
const navItems = [
    {
        title: tk('Applications'),
        href: dashboard(),
        match: (path: string) =>
            path.startsWith('/dashboard') ||
            (path.startsWith('/applications/') &&
                !path.startsWith('/applications/create')),
    },
    {
        title: tk('New application'),
        href: createApplication(),
        match: (path: string) => path.startsWith('/applications/create'),
    },
    {
        title: tk('Settings'),
        href: candidateProfile(),
        match: (path: string) => path.startsWith('/settings'),
    },
];

/** The navy band: the passport cover, with its security hairlines. The same band tops every signed-in page. */
export function AppHeader() {
    const { t } = useTrans();
    const page = usePage();
    const { auth } = page.props;
    const getInitials = useInitials();
    const path = new URL(page.url, 'http://x').pathname;

    return (
        <header className="guilloche bg-band text-band-foreground">
            <div className="mx-auto flex h-16 w-full max-w-[90rem] items-center gap-6 px-4 sm:px-6 lg:gap-10 lg:px-12">
                <Sheet>
                    <SheetTrigger
                        className="-ml-2 inline-flex size-10 items-center justify-center rounded-md text-band-foreground hover:bg-white/10 lg:hidden"
                        aria-label={t('Navigation menu')}
                    >
                        <Menu className="size-5" />
                    </SheetTrigger>
                    <SheetContent side="left" className="w-72 bg-card">
                        <SheetHeader>
                            <SheetTitle className="flex items-center gap-2 text-primary dark:text-ink-blue">
                                <AppLogoIcon className="size-6" aria-hidden />
                                <AppWordmark />
                            </SheetTitle>
                        </SheetHeader>
                        <nav className="flex flex-col px-4">
                            {navItems.map((item) => (
                                <Link
                                    key={item.title}
                                    href={item.href}
                                    aria-current={
                                        item.match(path) ? 'page' : undefined
                                    }
                                    className="border-b py-3 text-[0.9375rem] font-medium aria-[current=page]:text-primary aria-[current=page]:underline aria-[current=page]:decoration-2 aria-[current=page]:underline-offset-8"
                                >
                                    {t(item.title)}
                                </Link>
                            ))}
                        </nav>
                    </SheetContent>
                </Sheet>

                <Link
                    href={dashboard()}
                    prefetch
                    className="rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-band-foreground"
                >
                    <AppLogo />
                </Link>

                <nav
                    aria-label={t('Main')}
                    className="hidden h-full items-stretch gap-7 lg:flex"
                >
                    {navItems.map((item) => {
                        const current = item.match(path);

                        return (
                            <Link
                                key={item.title}
                                href={toUrl(item.href)}
                                aria-current={current ? 'page' : undefined}
                                className={cn(
                                    'flex items-center border-b-2 pt-0.5 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-band-foreground',
                                    current
                                        ? 'border-band-foreground font-semibold text-band-foreground'
                                        : 'border-transparent text-band-muted hover:text-band-foreground',
                                )}
                            >
                                {t(item.title)}
                            </Link>
                        );
                    })}
                </nav>

                {auth.user && (
                    <DropdownMenu>
                        {/* The account as an initials tile, like an ID photo square: a clear 44px target on any
                            screen, named for screen readers; the full name and email open in the menu. */}
                        <DropdownMenuTrigger
                            className="group -mr-1.5 ml-auto grid size-11 cursor-pointer place-items-center rounded-md transition-colors hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-band-foreground data-[state=open]:bg-white/10"
                            aria-label={t('Account menu for :name', {
                                name: auth.user.name,
                            })}
                        >
                            <Avatar className="size-8 rounded-[3px] border border-band-foreground/45">
                                <AvatarImage
                                    src={auth.user.avatar}
                                    alt=""
                                    className="rounded-[2px]"
                                />
                                <AvatarFallback className="rounded-[2px] bg-transparent text-[0.75rem] font-semibold tracking-[0.04em] text-band-foreground">
                                    {getInitials(auth.user.name)}
                                </AvatarFallback>
                            </Avatar>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent className="w-60" align="end">
                            <UserMenuContent user={auth.user} />
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </div>
        </header>
    );
}
