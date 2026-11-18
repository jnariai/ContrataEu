import { Form, Head, Link } from '@inertiajs/react';
import {
    CheckCircle2,
    CircleCheckBig,
    ExternalLink,
    Pencil,
    PlugZap,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import ActiveAiCredentialController from '@/actions/App/Http/Controllers/Settings/ActiveAiCredentialController';
import AiCredentialController from '@/actions/App/Http/Controllers/Settings/AiCredentialController';
import AiCredentialVerificationController from '@/actions/App/Http/Controllers/Settings/AiCredentialVerificationController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { cn } from '@/lib/utils';
import { index } from '@/routes/ai-credentials';

type Provider = {
    value: string;
    label: string;
    defaultModel: string;
    keysUrl: string;
    notice: string | null;
};

type Credential = {
    id: string;
    name: string | null;
    provider: { value: string; label: string };
    model: string;
    masked_key: string;
    active: boolean;
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
                        'Generations run on your own AI account. Keep as many keys as you like; the active one is used for generations and CV imports. Keys are encrypted and never shown again after you save them.',
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

            <AddCredential
                providers={providers}
                hasKeys={credentials.length > 0}
            />
        </>
    );
}

function CredentialRow({ credential }: { credential: Credential }) {
    const { t, locale } = useTrans();
    const [editing, setEditing] = useState(false);

    return (
        <li
            className={cn(
                'space-y-3 rounded-xl border p-4',
                credential.active && 'border-primary ring-1 ring-primary',
            )}
        >
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="font-medium">
                        {credential.name ?? credential.provider.label}
                    </span>
                    {credential.name && (
                        <span className="text-sm text-muted-foreground">
                            {credential.provider.label}
                        </span>
                    )}
                    {credential.active && (
                        <Badge className="gap-1">
                            <CircleCheckBig className="size-3" />
                            {t('Active')}
                        </Badge>
                    )}
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
                {!credential.active && (
                    <Button size="sm" asChild>
                        <Link
                            href={ActiveAiCredentialController.store(
                                credential.id,
                            )}
                            as="button"
                            preserveScroll
                        >
                            {t('Use this key')}
                        </Link>
                    </Button>
                )}
            </div>

            {editing ? (
                <EditCredential
                    credential={credential}
                    onDone={() => setEditing(false)}
                />
            ) : (
                <dl className="grid gap-1 text-sm sm:grid-cols-[auto_1fr] sm:gap-x-4">
                    <dt className="text-muted-foreground">{t('Model')}</dt>
                    <dd className="font-mono break-all">{credential.model}</dd>
                    <dt className="text-muted-foreground">{t('Key')}</dt>
                    <dd className="font-mono">{credential.masked_key}</dd>
                </dl>
            )}

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
                {!editing && (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setEditing(true)}
                    >
                        <Pencil className="size-4" />
                        {t('Edit')}
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
                                t('Delete the key “:key”?', {
                                    key:
                                        credential.name ??
                                        credential.provider.label,
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

/** Rename the key or change its model; the key itself can't change (add a new one instead). */
function EditCredential({
    credential,
    onDone,
}: {
    credential: Credential;
    onDone: () => void;
}) {
    const { t } = useTrans();

    return (
        <Form
            {...AiCredentialController.update.form(credential.id)}
            options={{ preserveScroll: true }}
            onSuccess={onDone}
            className="grid gap-3 sm:grid-cols-2"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-1.5">
                        <Label htmlFor={`name-${credential.id}`}>
                            {t('Name (optional)')}
                        </Label>
                        <Input
                            id={`name-${credential.id}`}
                            name="name"
                            defaultValue={credential.name ?? ''}
                            placeholder={credential.provider.label}
                            maxLength={60}
                            autoFocus
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor={`model-${credential.id}`}>
                            {t('Model')}
                        </Label>
                        <Input
                            id={`model-${credential.id}`}
                            name="model"
                            defaultValue={credential.model}
                            required
                            spellCheck={false}
                            className="font-mono"
                        />
                        <InputError message={errors.model} />
                    </div>
                    <div className="flex gap-2 sm:col-span-2">
                        <Button size="sm" disabled={processing}>
                            {t('Save')}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={onDone}
                        >
                            {t('Cancel')}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function AddCredential({
    providers,
    hasKeys,
}: {
    providers: Provider[];
    hasKeys: boolean;
}) {
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
                    'Anthropic, OpenAI, Google Gemini, OpenRouter, OrcaRouter, Groq or DeepSeek. Usage is billed by your provider.',
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
                            {provider.notice && (
                                <p
                                    className="rounded-md bg-amber-50 p-2 text-xs text-amber-900 dark:bg-amber-950 dark:text-amber-200"
                                    role="note"
                                >
                                    {provider.notice}
                                </p>
                            )}
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

                        <div className="grid gap-2">
                            <Label htmlFor="name">{t('Name (optional)')}</Label>
                            <Input
                                id="name"
                                name="name"
                                placeholder={t('e.g. Work :provider', {
                                    provider: provider.label,
                                })}
                                maxLength={60}
                            />
                            <p className="text-xs text-muted-foreground">
                                {t(
                                    'Helps tell keys apart when you have several for the same provider.',
                                )}
                            </p>
                            <InputError message={errors.name} />
                        </div>

                        {hasKeys && (
                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id="activate"
                                    name="activate"
                                    value="1"
                                    defaultChecked
                                />
                                <Label htmlFor="activate">
                                    {t('Use this key now')}
                                </Label>
                            </div>
                        )}

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
