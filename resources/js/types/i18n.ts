export type LanguageValue = 'en' | 'pt-BR';

export type LanguageOption = {
    value: LanguageValue;
    label: string;
};

export type CurrentLocale = LanguageOption & {
    htmlLang: string;
};

export type Translations = Record<string, string>;
