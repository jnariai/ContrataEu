import { router, usePage } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTrans } from '@/hooks/use-trans';
import { cn } from '@/lib/utils';
import { update } from '@/routes/locale';

export default function LanguageSwitcher({
    className,
}: {
    className?: string;
}) {
    const { languages } = usePage().props;
    const { t, locale } = useTrans();

    return (
        <Select
            value={locale.value}
            onValueChange={(value) =>
                router.patch(
                    update.url(),
                    { locale: value },
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            document.documentElement.lang = value;
                        },
                    },
                )
            }
        >
            <SelectTrigger
                aria-label={t('Language')}
                className={cn('w-auto gap-2', className)}
            >
                <Languages className="size-4 opacity-60" />
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {languages.map((language) => (
                    <SelectItem
                        key={language.value}
                        value={language.value}
                        lang={language.value}
                    >
                        {language.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
