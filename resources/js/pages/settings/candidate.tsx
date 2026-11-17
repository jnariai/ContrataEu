import { Form, Head, usePage } from '@inertiajs/react';
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
        headline: string | null;
        location: string | null;
        work_authorization: string | null;
        region_convention: string | null;
        links: {
            linkedin: string | null;
            github: string | null;
            portfolio: string | null;
        };
        default_output_language: LanguageValue;
    };
    exists: boolean;
    regionConventions: string[];
};

export default function CandidateProfile({
    profile,
    exists,
    regionConventions,
}: Props) {
    const { t } = useTrans();
    const { languages } = usePage().props;

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

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="region_convention">
                                        {t('CV conventions')}
                                    </Label>
                                    <Input
                                        id="region_convention"
                                        name="region_convention"
                                        list="region-conventions"
                                        defaultValue={
                                            profile.region_convention ?? ''
                                        }
                                        placeholder="EU"
                                    />
                                    <datalist id="region-conventions">
                                        {regionConventions.map((region) => (
                                            <option
                                                key={region}
                                                value={region}
                                            />
                                        ))}
                                    </datalist>
                                    <p className="text-xs text-muted-foreground">
                                        {t(
                                            'The region whose CV norms to follow (personal data, photo, date style).',
                                        )}
                                    </p>
                                    <InputError
                                        message={errors.region_convention}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="default_output_language">
                                        {t('Default output language')}
                                    </Label>
                                    <Select
                                        name="default_output_language"
                                        defaultValue={
                                            profile.default_output_language
                                        }
                                    >
                                        <SelectTrigger id="default_output_language">
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
                            </div>

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
