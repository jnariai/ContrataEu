/** Dates arrive as ISO 8601 (UTC) and are shown in the user's timezone and UI language. */
export function formatDateTime(iso: string, locale: string): string {
    return new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(iso));
}

export function formatBytes(bytes: number, locale: string): string {
    const units = ['byte', 'kilobyte', 'megabyte'] as const;
    let value = bytes;
    let unit = 0;

    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit++;
    }

    return new Intl.NumberFormat(locale, {
        style: 'unit',
        unit: units[unit],
        unitDisplay: 'short',
        maximumFractionDigits: unit === 0 ? 0 : 1,
    }).format(value);
}
