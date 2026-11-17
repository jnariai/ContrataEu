import { Head, setLayoutProps } from '@inertiajs/react';
import ApplicationController from '@/actions/App/Http/Controllers/ApplicationController';
import ApplicationForm from '@/components/application-form';
import Heading from '@/components/heading';
import { useTrans } from '@/hooks/use-trans';
import { tk } from '@/lib/i18n';
import { dashboard } from '@/routes';
import { edit, show } from '@/routes/applications';
import type { Application, ApplicationFormOptions } from '@/types';

type Props = ApplicationFormOptions & { application: Application };

export default function EditApplication({ application, ...options }: Props) {
    const { t } = useTrans();

    setLayoutProps({
        breadcrumbs: [
            { title: tk('Dashboard'), href: dashboard() },
            { title: application.company_name, href: show(application.id) },
            { title: tk('Edit'), href: edit(application.id) },
        ],
    });

    return (
        <>
            <Head
                title={t('Edit :company', {
                    company: application.company_name,
                })}
            />
            <div className="mx-auto w-full max-w-3xl px-4 py-6">
                <Heading
                    title={t('Edit application')}
                    description={t(
                        'Changes apply to the next generation. Versions you already generated stay as they are.',
                    )}
                />
                <ApplicationForm
                    form={ApplicationController.update.form(application.id)}
                    application={application}
                    defaultLanguage={application.language.value}
                    options={options}
                    submitLabel={t('Save changes')}
                />
            </div>
        </>
    );
}
