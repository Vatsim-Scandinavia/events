import type { ComponentProps } from 'react';
import { NativeSelect } from '@/components/ui/native-select';

// Zones grouped by region ("Europe", "America", …), each listed by its place name.
function groupByRegion(timezones: string[]) {
    const regions = new Map<string, string[]>();

    for (const timezone of timezones) {
        if (!timezone.includes('/')) {
            continue;
        }

        const region = timezone.slice(0, timezone.indexOf('/'));
        regions.set(region, [...(regions.get(region) ?? []), timezone]);
    }

    return [...regions];
}

const placeName = (timezone: string) =>
    timezone
        .slice(timezone.indexOf('/') + 1)
        .replaceAll('_', ' ')
        .replaceAll('/', ' / ');

/**
 * Picks an IANA time zone, with UTC (zulu) first and the rest grouped by region.
 */
export function TimezoneSelect({ timezones, ...props }: { timezones: string[] } & ComponentProps<typeof NativeSelect>) {
    return (
        // NativeSelect only colours direct <option>s; these sit in <optgroup>s.
        <NativeSelect
            className="[&_optgroup]:bg-popover [&_optgroup]:text-popover-foreground [&_option]:bg-popover [&_option]:text-popover-foreground"
            {...props}
        >
            <option value="UTC">UTC (zulu)</option>
            {groupByRegion(timezones).map(([region, zones]) => (
                <optgroup key={region} label={region}>
                    {zones.map((timezone) => (
                        <option key={timezone} value={timezone}>
                            {placeName(timezone)}
                        </option>
                    ))}
                </optgroup>
            ))}
        </NativeSelect>
    );
}
