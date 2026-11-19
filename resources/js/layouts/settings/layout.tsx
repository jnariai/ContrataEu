import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
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

/** Setup is what every application draws on; Account is the login itself. */
const navGroups: { title: string; items: NavItem[] }[] = [
    {
        title: tk('Setup'),
        items: [
            {
                title: tk('Candidate profile'),
                href: editCandidateProfile(),
                icon: null,
            },
            { title: tk('Documents'), href: masterDocuments(), icon: null },
            { title: tk('AI keys'), href: aiCredentials(), icon: null },
            { title: tk('Prompts'), href: promptOverrides(), icon: null },
        ],
    },
    {
        title: tk('Your account'),
        items: [
            { title: tk('Account'), href: edit(), icon: null },
            { title: tk('Security'), href: editSecurity(), icon: null },
            { title: tk('Appearance'), href: editAppearance(), icon: null },
        ],
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { t } = useTrans();
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <div className="py-8 lg:py-10">
            <div className="mb-8 space-y-1">
                <h1 className="text-[1.75rem] leading-tight font-semibold tracking-[-0.015em]">
                    {t('Settings')}
                </h1>
                <p className="text-muted-foreground">
                    {t(
                        'What every application draws on: your profile, documents, AI keys and prompts. Plus your account.',
                    )}
                </p>
            </div>

            <div className="flex flex-col gap-8 lg:flex-row lg:gap-12">
                <aside className="w-full lg:w-52 lg:shrink-0">
                    <nav
                        className="grid gap-6 sm:grid-cols-2 lg:grid-cols-1"
                        aria-label={t('Settings')}
                    >
                        {navGroups.map((group) => (
                            <div key={group.title} className="space-y-1.5">
                                <span className="field-label block px-2">
                                    {t(group.title)}
                                </span>
                                <ul className="border-t">
                                    {group.items.map((item) => {
                                        const current = isCurrentOrParentUrl(
                                            item.href,
                                        );

                                        return (
                                            <li
                                                key={toUrl(item.href)}
                                                className="border-b"
                                            >
                                                <Link
                                                    href={item.href}
                                                    aria-current={
                                                        current
                                                            ? 'page'
                                                            : undefined
                                                    }
                                                    className={cn(
                                                        'block px-2 py-2.5 text-sm transition-colors hover:bg-accent focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring',
                                                        current &&
                                                            'bg-card font-semibold text-primary dark:text-ink-blue',
                                                    )}
                                                >
                                                    {t(item.title)}
                                                </Link>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </div>
                        ))}
                    </nav>
                </aside>

                <div className="min-w-0 flex-1 lg:max-w-3xl">
                    <section className="space-y-12">{children}</section>
                </div>
            </div>
        </div>
    );
}
