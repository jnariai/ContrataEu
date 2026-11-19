/**
 * A heading with its one-line description. `page` is the page's own title (h1); `default` heads a settings
 * section; `small` heads a group inside one.
 */
export default function Heading({
    title,
    description,
    variant = 'default',
}: {
    title: string;
    description?: string;
    variant?: 'page' | 'default' | 'small';
}) {
    if (variant === 'page') {
        return (
            <header className="mb-8 space-y-1">
                <h1 className="text-[1.75rem] leading-tight font-semibold tracking-[-0.015em]">
                    {title}
                </h1>
                {description && (
                    <p className="text-muted-foreground">{description}</p>
                )}
            </header>
        );
    }

    return (
        <header className={variant === 'small' ? '' : 'mb-6 space-y-1'}>
            <h2
                className={
                    variant === 'small'
                        ? 'mb-0.5 text-base font-semibold'
                        : 'text-lg font-semibold'
                }
            >
                {title}
            </h2>
            {description && (
                <p className="text-sm text-muted-foreground">{description}</p>
            )}
        </header>
    );
}
