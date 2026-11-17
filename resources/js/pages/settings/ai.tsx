import { Form, Head, Link } from '@inertiajs/react';
import {
    CheckCircle2,
    ExternalLink,
    PlugZap,
    Star,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import AiCredentialController from '@/actions/App/Http/Controllers/Settings/AiCredentialController';
import AiCredentialVerificationController from '@/actions/App/Http/Controllers/Settings/AiCredentialVerificationController';
import DefaultAiCredentialController from '@/actions/App/Http/Controllers/Settings/DefaultAiCredentialController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Badge } from '@/components/ui/badge';
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
import { formatDateTime } from '@/lib/format';
import { tk } from '@/lib/i18n';
import { index } from '@/routes/ai-credentials';

type Provider = {
    value: string;
    label: string;
    defaultModel: string;
    keysUrl: string;
};

type Credential = {
    id: string;
    provider: { value: string; label: string };
    model: string;
    masked_key: string;
    is_default: boolean;
    last_verified_at: string | null;
};

type Props = {
    credentials: Credential[];
    providers: Provider[];
};

export default function AiKeys({ credentials, providers }: Props) {
    const { t } = useTrans();

    return (
        <>
            <Head title={t('AI keys')} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('AI keys')}
                    description={t(
                        'Generations run on your own AI account. Your key is encrypted and never shown again after you save it.',
                    )}
                />

                {credentials.length === 0 ? (
                    <p className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                        {t('No AI key yet. Add one below to start generating.')}
                    </p>
                ) : (
                    <ul className="space-y-3">
                        {credentials.map((credential) => (
                            <CredentialRow
                                key={credential.id}
                                credential={credential}
                            />
                        ))}
                    </ul>
                )}
            </div>

            <AddCredential providers={providers} />
        </>
    );
}

function CredentialRow({ credential }: { credential: Credential }) {
    const { t, locale } = useTrans();

    return (
        <li className="space-y-3 rounded-xl border p-4">
            <div className="flex flex-wrap items-center gap-2">
                <span className="font-medium">{credential.provider.label}</span>
                {credential.is_default && <Badge>{t('Default')}</Badge>}
                {credential.last_verified_at ? (
                    <Badge variant="secondary" className="gap-1">
                        <CheckCircle2 className="size-3" />
                        {t('Verified :date', {
                            date: formatDateTime(
                                credential.last_verified_at,
                                locale.htmlLang,
                            ),
                        })}
                    </Badge>
                ) : (
                    <Badge variant="outline">{t('Not tested yet')}</Badge>
                )}
            </div>
            <dl className="grid gap-1 text-sm sm:grid-cols-[auto_1fr] sm:gap-x-4">
                <dt className="text-muted-foreground">{t('Model')}</dt>
                <dd className="font-mono break-all">{credential.model}</dd>
                <dt className="text-muted-foreground">{t('Key')}</dt>
                <dd className="font-mono">{credential.masked_key}</dd>
            </dl>
            <div className="flex flex-wrap gap-2">
                <Form
                    {...AiCredentialVerificationController.store.form(
                        credential.id,
                    )}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={processing}
                        >
                            <PlugZap className="size-4" />
                            {processing
                                ? t('Testing...')
                                : t('Test connection')}
                        </Button>
                    )}
                </Form>
                {!credential.is_default && (
                    <Button variant="outline" size="sm" asChild>
                        <Link
                            href={DefaultAiCredentialController.store(
                                credential.id,
                            )}
                            as="button"
                            preserveScroll
                        >
                            <Star className="size-4" />
                            {t('Make default')}
                        </Link>
                    </Button>
                )}
                <Button
                    variant="ghost"
                    size="sm"
                    className="text-destructive hover:text-destructive"
                    asChild
                >
                    <Link
                        href={AiCredentialController.destroy(credential.id)}
                        as="button"
                        preserveScroll
                        onBefore={() =>
                            confirm(
                                t('Delete this :provider key?', {
                                    provider: credential.provider.label,
                                }),
                            )
                        }
                    >
                        <Trash2 className="size-4" />
                        {t('Delete')}
                    </Link>
                </Button>
            </div>
        </li>
    );
}

function AddCredential({ providers }: { providers: Provider[] }) {
    const { t } = useTrans();
    const [providerValue, setProviderValue] = useState(providers[0].value);
    const provider =
        providers.find((option) => option.value === providerValue) ??
        providers[0];

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title={t('Add a key')}
                description={t(
                    'Anthropic, Google Gemini, OpenRouter or Groq. Usage is billed by your provider.',
                )}
            />

            <Form
                {...AiCredentialController.store.form()}
                options={{ preserveScroll: true }}
                resetOnSuccess
                className="space-y-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="provider">{t('Provider')}</Label>
                            <Select
                                name="provider"
                                value={providerValue}
                                onValueChange={setProviderValue}
                            >
                                <SelectTrigger id="provider">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {providers.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.provider} />
                        </div>

                        <div className="grid gap-2">
                            <div className="flex items-center justify-between gap-2">
                                <Label htmlFor="api_key">{t('API key')}</Label>
                                <a
                                    href={provider.keysUrl}
                                    target="_blank"
                                    rel="noreferrer noopener"
                                    className="inline-flex items-center gap-1 text-xs text-muted-foreground underline-offset-4 hover:underline"
                                >
                                    {t('Get a :provider key', {
                                        provider: provider.label,
                                    })}
                                    <ExternalLink className="size-3" />
                                </a>
                            </div>
                            <PasswordInput
                                id="api_key"
                                name="api_key"
                                required
                                autoComplete="off"
                                spellCheck={false}
                            />
                            <InputError message={errors.api_key} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="model">{t('Model')}</Label>
                            <Input
                                id="model"
                                name="model"
                                placeholder={provider.defaultModel}
                                className="font-mono"
                                spellCheck={false}
                            />
                            <p className="text-xs text-muted-foreground">
                                {t(
                                    'Leave empty to use :model. Any model id your provider supports works.',
                                    { model: provider.defaultModel },
                                )}
                            </p>
                            <InputError message={errors.model} />
                        </div>

                        <Button disabled={processing}>{t('Save key')}</Button>
                    </>
                )}
            </Form>
        </div>
    );
}

AiKeys.layout = {
    breadcrumbs: [{ title: tk('AI keys'), href: index() }],
};
