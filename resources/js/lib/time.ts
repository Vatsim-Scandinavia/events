// Clock times in the viewer's chosen time zone, for times worked out in the browser. Most
// times arrive preformatted by App\Services\DisplayTime, which also gives the zone's label.

const clocks = new Map<string, Intl.DateTimeFormat>();

const clockFormat = (timezone: string) => {
    if (!clocks.has(timezone)) {
        clocks.set(timezone, new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23', timeZone: timezone }));
    }

    return clocks.get(timezone)!;
};

/**
 * HH:MM in the given time zone, a number of minutes after an ISO 8601 moment.
 */
export const clockIn = (timezone: string, iso: string, minutesLater = 0) =>
    clockFormat(timezone).format(new Date(new Date(iso).getTime() + minutesLater * 60_000));

/**
 * Whether times in this zone are UTC, written with a "z". The server only accepts the name "UTC"
 * (Laravel's `timezone:all` leaves out aliases like "Etc/UTC"), so that is the one name to check.
 */
export const isUtc = (timezone: string) => timezone === 'UTC';

/**
 * What follows a time to say which zone it is in: "z" for UTC, as on VATSIM, otherwise the
 * zone's short name at that moment, such as " CEST".
 */
export const zoneSuffix = (timezone: string, iso: string) => {
    if (isUtc(timezone)) {
        return 'z';
    }

    const name = new Intl.DateTimeFormat('en-GB', { timeZone: timezone, timeZoneName: 'short' })
        .formatToParts(new Date(iso))
        .find((part) => part.type === 'timeZoneName')?.value;

    return name ? ` ${name}` : '';
};

/**
 * Names the zone times are shown in, for notes like "All times are in …".
 */
export const describeTimezone = (timezone: string) => (isUtc(timezone) ? 'UTC (zulu)' : timezone.replaceAll('_', ' '));
