import { Form, Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import CandidateProfileController from '@/actions/App/Http/Controllers/Settings/CandidateProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTrans } from '@/hooks/use-trans';
import { tk } from '@/lib/i18n';
import { edit } from '@/routes/candidate-profile';
import type { LanguageValue } from '@/types';

type Props = {
    profile: {
        full_name: string;
        file_name: string | null;
        suggested_file_name: string;
        headline: string | null;
        location: string | null;
        work_authorization: string | null;
        cv_convention: string;
        links: {
            linkedin: string | null;
            github: string | null;
            portfolio: string | null;
        };
        default_output_language: LanguageValue;
    };
    exists: boolean;
    cvConventions: ConventionOption[];
    brazilConvention: { label: string; description: string };
};

type ConventionOption = { value: string; label: string; description: string };

export default function CandidateProfile({
    profile,
    exists,
    cvConventions,
    brazilConvention,
}: Props) {
    const { t } = useTrans();
    const { languages } = usePage().props;
    const [outputLanguage, setOutputLanguage] = useState(
        profile.default_output_language,
    );
    const [convention, setConvention] = useState(profile.cv_convention);
    const chosen =
        cvConventions.find((option) => option.value === convention) ??
        cvConventions[0];
    const applied = outputLanguage === 'pt-BR' ? brazilConvention : chosen;

    const textFields = [
        {
            name: 'headline',
            label: t('Headline'),
            placeholder: t('e.g. Senior Backend Engineer'),
            value: profile.headline,
        },
        {
            name: 'location',
            label: t('Location'),
            placeholder: t('e.g. Lisbon, Portugal'),
            value: profile.location,
        },
        {
            name: 'work_authorization',
            label: t('Work authorization'),
            placeholder: t('e.g. EU citizen, no sponsorship needed'),
            value: profile.work_authorization,
        },
    ];

    const linkFields = [
        { name: 'linkedin', label: 'LinkedIn', value: profile.links.linkedin },
        { name: 'github', label: 'GitHub', value: profile.links.github },
        {
            name: 'portfolio',
            label: t('Portfolio'),
            value: profile.links.portfolio,
        },
    ] as const;

    return (
        <>
            <Head title={t('Candidate profile')} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('Candidate profile')}
                    description={t(
                        'Facts about you that every generated document reuses.',
                    )}
                />

                {!exists && (
                    <p className="rounded-md border border-dashed p-3 text-sm text-muted-foreground">
                        {t(
                            'Fill this in once. It is needed before your first generation.',
                        )}
                    </p>
                )}

                <Form
                    {...CandidateProfileController.update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="full_name">
                                    {t('Full name')}
                                </Label>
                                <Input
                                    id="full_name"
                                    name="full_name"
                                    defaultValue={profile.full_name}
                                    required
                                    autoComplete="name"
                                />
                                <InputError message={errors.full_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="file_name">
                                    {t('Name on files (optional)')}
                                </Label>
                                <Input
                                    id="file_name"
                                    name="file_name"
                                    defaultValue={profile.file_name ?? ''}
                                    placeholder={profile.suggested_file_name}
                                    maxLength={60}
                                />
                                <p className="text-xs text-muted-foreground">
                                    {t(
                                        'Recruiters save CVs by name, so your PDFs are named after it (Name-CV.pdf). Leave empty to use “:name”, your first name and last surname.',
                                        { name: profile.suggested_file_name },
                                    )}
                                </p>
                                <InputError message={errors.file_name} />
                            </div>

                            {textFields.map((field) => (
                                <div key={field.name} className="grid gap-2">
                                    <Label htmlFor={field.name}>
                                        {field.label}
                                    </Label>
                                    <Input
                                        id={field.name}
                                        name={field.name}
                                        defaultValue={field.value ?? ''}
                                        placeholder={field.placeholder}
                                    />
                                    <InputError message={errors[field.name]} />
                                </div>
                            ))}

                            <div className="grid gap-2">
                                <Label htmlFor="default_output_language">
                                    {t('Default output language')}
                                </Label>
                                <Select
                                    name="default_output_language"
                                    value={outputLanguage}
                                    onValueChange={(value) =>
                                        setOutputLanguage(
                                            value as LanguageValue,
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id="default_output_language"
                                        className="sm:max-w-xs"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {languages.map((language) => (
                                            <SelectItem
                                                key={language.value}
                                                value={language.value}
                                            >
                                                {language.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-xs text-muted-foreground">
                                    {t(
                                        'Prefills the language of new applications. You can change it per job.',
                                    )}
                                </p>
                                <InputError
                                    message={errors.default_output_language}
                                />
                            </div>

                            <fieldset className="grid gap-3 rounded-lg border p-4">
                                <legend className="px-1 text-sm font-medium">
                                    {t('CV conventions')}
                                </legend>
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'Each region expects different things in a CV: how long it is, which personal details to show and which to leave out, and how to write your location. The language of each application picks the convention:',
                                    )}
                                </p>
                                <ul className="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                                    <li>
                                        <span className="font-medium text-foreground">
                                            {t('Portuguese (Brazil) documents')}
                                        </span>
                                        {': '}
                                        {t(
                                            'always follow Brazilian conventions, since they are for jobs in Brazil.',
                                        )}
                                    </li>
                                    <li>
                                        <span className="font-medium text-foreground">
                                            {t('English documents')}
                                        </span>
                                        {': '}
                                        {t(
                                            'follow the convention you pick here.',
                                        )}
                                    </li>
                                </ul>

                                <div className="grid gap-2">
                                    <Label htmlFor="cv_convention">
                                        {t('Convention for English documents')}
                                    </Label>
                                    <Select
                                        name="cv_convention"
                                        value={convention}
                                        onValueChange={setConvention}
                                    >
                                        <SelectTrigger
                                            id="cv_convention"
                                            className="sm:max-w-xs"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {cvConventions.map((option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.cv_convention}
                                    />
                                </div>

                                <p
                                    className="rounded-md bg-muted/50 p-3 text-sm"
                                    aria-live="polite"
                                >
                                    <span className="font-medium">
                                        {t(
                                            'New applications in :language use: :convention.',
                                            {
                                                language:
                                                    languages.find(
                                                        (language) =>
                                                            language.value ===
                                                            outputLanguage,
                                                    )?.label ?? outputLanguage,
                                                convention: applied.label,
                                            },
                                        )}
                                    </span>{' '}
                                    <span className="text-muted-foreground">
                                        {applied.description}
                                    </span>
                                </p>
                            </fieldset>

                            <fieldset className="grid gap-4">
                                <legend className="mb-2 text-sm font-medium">
                                    {t('Links')}
                                </legend>
                                {linkFields.map((field) => (
                                    <div
                                        key={field.name}
                                        className="grid gap-2"
                                    >
                                        <Label htmlFor={`links-${field.name}`}>
                                            {field.label}
                                        </Label>
                                        <Input
                                            id={`links-${field.name}`}
                                            name={`links[${field.name}]`}
                                            type="url"
                                            defaultValue={field.value ?? ''}
                                            placeholder="https://"
                                        />
                                        <InputError
                                            message={
                                                errors[`links.${field.name}`]
                                            }
                                        />
                                    </div>
                                ))}
                            </fieldset>

                            <Button disabled={processing}>{t('Save')}</Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

CandidateProfile.layout = {
    breadcrumbs: [{ title: tk('Candidate profile'), href: edit() }],
};
