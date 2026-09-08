import { PlaneTakeoff } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';

export function EventBanner({
    src,
    className,
}: {
    src?: string | null;
    className?: string;
}) {
    const [failedSrc, setFailedSrc] = useState<string | null>(null);
    if (src && src !== failedSrc) {
        return (
            <img
                src={src}
                alt="Event banner"
                className={cn('aspect-[3/1] w-full object-cover', className)}
                onError={() => setFailedSrc(src)}
            />
        );
    }
    return (
        <div
            aria-label="Default event banner"
            role="img"
            className={cn(
                'bg-muted text-muted-foreground relative flex aspect-[3/1] w-full items-center justify-center overflow-hidden',
                className,
            )}
        >
            <svg
                className="absolute inset-0 size-full opacity-20"
                viewBox="0 0 900 300"
                preserveAspectRatio="xMidYMid slice"
                aria-hidden="true"
            >
                <g fill="none" stroke="currentColor" strokeWidth="1">
                    <circle cx="450" cy="150" r="80" />
                    <circle cx="450" cy="150" r="140" />
                    <circle cx="450" cy="150" r="220" />
                    <path
                        d="M0 150H900M450 0V300M80 260Q300 0 820 50"
                        strokeDasharray="5 8"
                    />
                    <path d="M0 75H900M0 225H900M225 0V300M675 0V300" />
                </g>
            </svg>
            <div className="bg-muted relative flex flex-col items-center gap-2 rounded-full px-8 py-5">
                <PlaneTakeoff className="size-10" aria-hidden="true" />
                <span className="text-xs font-medium tracking-[0.2em]">
                    VATSIM EVENTS
                </span>
            </div>
        </div>
    );
}
