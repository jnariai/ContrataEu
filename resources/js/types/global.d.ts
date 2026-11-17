import type { Auth } from '@/types/auth';
import type { CurrentLocale, LanguageOption, Translations } from '@/types/i18n';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            locale: CurrentLocale;
            languages: LanguageOption[];
            translations: Translations;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
