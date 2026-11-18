import { Form, Head } from '@inertiajs/react';
import { ChevronDown, Lock, RotateCcw } from 'lucide-react';
import PromptOverrideController from '@/actions/App/Http/Controllers/Settings/PromptOverrideController';
import ConfirmDialog from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTrans } from '@/hooks/use-trans';
import { tk } from '@/lib/i18n';
import { index } from '@/routes/prompt-overrides';

type Prompt = {
    key: string;
    label: string;
    description: string;
    default: string;
    override: string | null;
};

type Props = {
    prompts: Prompt[];
    maxCharacters: number;
};

export default function Prompts({ prompts, maxCharacters }: Props) {
    const { t } = useTrans();

    return (
        <>
            <Head title={t('Prompts')} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('Prompts')}
                    description={t(
                        'Change how the AI writes for you. Each section starts from our default; your version replaces it until you reset it.',
                    )}
                />

                <p className="flex gap-2 rounded-md bg-muted/50 p-3 text-sm text-muted-foreground">
                    <Lock className="mt-0.5 size-4 shrink-0" />
                    {t(
                        'The output format (CV structure, sections, languages and the never-invent-facts rules) is controlled by the app and is always added after your text, so PDFs and parsing keep working.',
                    )}
                </p>

                {prompts.map((prompt) => (
                    <PromptEditor
                        key={prompt.key}
                        prompt={prompt}
                        maxCharacters={maxCharacters}
                    />
                ))}
            </div>
        </>
    );
}

function PromptEditor({
    prompt,
    maxCharacters,
}: {
    prompt: Prompt;
    maxCharacters: number;
}) {
    const { t } = useTrans();
    const fieldId = `prompt-${prompt.key}`;
    const customized = prompt.override !== null;

    return (
        <section className="space-y-3 rounded-xl border p-4 sm:p-6">
            <div className="flex flex-wrap items-center gap-2">
                <Label htmlFor={fieldId} className="text-base font-medium">
                    {prompt.label}
                </Label>
                <Badge variant={customized ? 'default' : 'secondary'}>
                    {customized ? t('Customized') : t('Default')}
                </Badge>
            </div>
            <p className="text-sm text-muted-foreground">
                {prompt.description}
            </p>

            {customized && (
                <Collapsible>
                    <CollapsibleTrigger className="group inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                        <ChevronDown className="size-4 transition-transform group-data-[state=open]:rotate-180" />
                        {t('Show the default')}
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <pre className="mt-2 max-h-80 overflow-auto rounded-md bg-muted/50 p-3 font-mono text-xs whitespace-pre-wrap">
                            {prompt.default}
                        </pre>
                    </CollapsibleContent>
                </Collapsible>
            )}

            <Form
                {...PromptOverrideController.update.form(prompt.key)}
                options={{ preserveScroll: true }}
                className="space-y-3"
            >
                {({ processing, errors, isDirty }) => (
                    <>
                        <Textarea
                            id={fieldId}
                            name="content"
                            defaultValue={prompt.override ?? prompt.default}
                            required
                            maxLength={maxCharacters}
                            rows={12}
                            className="font-mono text-xs"
                        />
                        <InputError message={errors.content} />
                        <div className="flex flex-wrap gap-2">
                            <Button disabled={processing || !isDirty}>
                                {t('Save my version')}
                            </Button>
                            {customized && (
                                <ConfirmDialog
                                    title={t(
                                        'Discard your version of “:prompt” and use the default?',
                                        { prompt: prompt.label },
                                    )}
                                    description={t(
                                        'Your edits to this prompt are lost.',
                                    )}
                                    confirmLabel={t('Reset to default')}
                                    action={PromptOverrideController.destroy(
                                        prompt.key,
                                    )}
                                    trigger={
                                        <Button type="button" variant="outline">
                                            <RotateCcw className="size-4" />
                                            {t('Reset to default')}
                                        </Button>
                                    }
                                />
                            )}
                        </div>
                    </>
                )}
            </Form>
        </section>
    );
}

Prompts.layout = {
    breadcrumbs: [{ title: tk('Prompts'), href: index() }],
};
