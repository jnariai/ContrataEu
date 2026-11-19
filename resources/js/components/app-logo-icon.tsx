import type { SVGAttributes } from 'react';
import { cn } from '@/lib/utils';

/**
 * ContrataEu mark: an entry stamp (carimbo) pressed twice, the way real stamps land. The outer frame is inked in
 * `currentColor`; the inner frame and the check, a hair off register, in the "applied" violet (the outcome the logo
 * promises, and the one place an ink appears outside a status). `onBand` lifts the violet to read on the navy band.
 * favicon.svg is the same mark without the inner frame, which closes up at 16px.
 */
export default function AppLogoIcon({
    onBand = false,
    className,
    ...props
}: SVGAttributes<SVGElement> & { onBand?: boolean }) {
    return (
        <svg
            viewBox="0 0 32 32"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            stroke="currentColor"
            strokeLinecap="square"
            className={className}
            {...props}
        >
            <g transform="rotate(-10 16 16)">
                <rect
                    x="2.6"
                    y="6.6"
                    width="26.8"
                    height="18.8"
                    rx="2.4"
                    strokeWidth="2.4"
                />
                <g
                    transform="translate(1.1 1.1)"
                    className={cn(onBand ? 'text-band-ink' : 'text-ink-violet')}
                >
                    <rect
                        x="5.6"
                        y="9.6"
                        width="20.8"
                        height="12.8"
                        rx="1"
                        strokeWidth="1.2"
                    />
                    <path d="M11.4 16.1l3.2 3.2 6.6-6.6" strokeWidth="2.8" />
                </g>
            </g>
        </svg>
    );
}
