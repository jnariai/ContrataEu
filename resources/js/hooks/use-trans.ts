import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import type { CurrentLocale } from '@/types';

type Replacements = Record<string, string | number>;

export type TranslateFunction = (
    key: string,
    replacements?: Replacements,
) => string;

/**
 * Laravel-style translations: the English sentence is the key, `lang/{locale}.json` holds the translation,
 * and `:name` placeholders are replaced (`:Name` capitalised, `:NAME` upper-cased), like `__()`.
 */
export function useTrans(): { t: TranslateFunction; locale: CurrentLocale } {
    const { translations, locale } = usePage().props;

    const t = useCallback<TranslateFunction>(
        (key, replacements = {}) => {
            let line = translations?.[key] ?? key;

            for (const [name, raw] of Object.entries(replacements)) {
                const value = String(raw);
                line = line
                    .replaceAll(`:${name.toUpperCase()}`, value.toUpperCase())
                    .replaceAll(
                        `:${name.charAt(0).toUpperCase()}${name.slice(1)}`,
                        value.charAt(0).toUpperCase() + value.slice(1),
                    )
                    .replaceAll(`:${name}`, value);
            }

            return line;
        },
        [translations],
    );

    return { t, locale };
}
