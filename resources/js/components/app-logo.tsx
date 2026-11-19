import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';

/**
 * The name as set in the logo: "Contrata" medium, "Eu" extra-bold, so the "hire me" lands on the person. Any other
 * app name is set plainly.
 */
export function AppWordmark() {
    const { name } = usePage().props;
    const parts = /^(.+?)(Eu)$/.exec(name);

    return (
        <span className="text-[1.0625rem] tracking-[-0.015em]">
            {parts ? (
                <>
                    <span className="font-medium">{parts[1]}</span>
                    <span className="font-extrabold">{parts[2]}</span>
                </>
            ) : (
                <span className="font-bold">{name}</span>
            )}
        </span>
    );
}

/** The mark and name as they sit on the navy band. */
export default function AppLogo() {
    return (
        <span className="flex items-center gap-2">
            <AppLogoIcon className="size-6" onBand aria-hidden />
            <AppWordmark />
        </span>
    );
}
