import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { index as aiCredentials } from '@/routes/ai-credentials';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editCandidateProfile } from '@/routes/candidate-profile';
import { index as masterDocuments } from '@/routes/master-documents';
import { edit } from '@/routes/profile';
import { index as promptOverrides } from '@/routes/prompt-overrides';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';
import { useTrans } from '@/hooks/use-trans';
import { tk } from '@/lib/i18n';

const sidebarNavItems: NavItem[] = [
    {
        title: tk('Candidate profile'),
        href: editCandidateProfile(),
        icon: null,
    },
    {
        title: tk('Documents'),
        href: masterDocuments(),
        icon: null,
    },
    {
        title: tk('AI keys'),
        href: aiCredentials(),
        icon: null,
    },
    {
        title: tk('Prompts'),
        href: promptOverrides(),
        icon: null,
    },
    {
        title: tk('Account'),
        href: edit(),
        icon: null,
    },
    {
        title: tk('Security'),
        href: editSecurity(),
        icon: null,
    },
    {
        title: tk('Appearance'),
        href: editAppearance(),
        icon: null,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { t } = useTrans();
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <div className="px-4 py-6">
            <Heading
                title={t('Settings')}
                description={t('Manage your profile and account settings')}
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav
                        className="flex flex-col space-y-1 space-x-0"
                        aria-label={t('Settings')}
                    >
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': isCurrentOrParentUrl(item.href),
                                })}
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon className="h-4 w-4" />
                                    )}
                                    {t(item.title)}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="min-w-0 flex-1 lg:max-w-3xl">
                    <section className="space-y-12">{children}</section>
                </div>
            </div>
        </div>
    );
}
