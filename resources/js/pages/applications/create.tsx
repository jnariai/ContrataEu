import { Head } from '@inertiajs/react';
import ApplicationController from '@/actions/App/Http/Controllers/ApplicationController';
import ApplicationForm from '@/components/application-form';
import Heading from '@/components/heading';
import { useTrans } from '@/hooks/use-trans';
import { tk } from '@/lib/i18n';
import { dashboard } from '@/routes';
import { create } from '@/routes/applications';
import type { ApplicationFormOptions, LanguageValue } from '@/types';

type Props = ApplicationFormOptions & { defaultLanguage: LanguageValue };

export default function CreateApplication({
    defaultLanguage,
    ...options
}: Props) {
    const { t } = useTrans();

    return (
        <>
            <Head title={t('New application')} />
            <div className="mx-auto w-full max-w-3xl px-4 py-6">
                <Heading
                    title={t('New application')}
                    description={t(
                        'Tell us about the job. You can generate your documents right after saving.',
                    )}
                />
                <ApplicationForm
                    form={ApplicationController.store.form()}
                    defaultLanguage={defaultLanguage}
                    options={options}
                    submitLabel={t('Save application')}
                />
            </div>
        </>
    );
}

CreateApplication.layout = {
    breadcrumbs: [
        { title: tk('Dashboard'), href: dashboard() },
        { title: tk('New application'), href: create() },
    ],
};
