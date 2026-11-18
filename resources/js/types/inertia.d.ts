import '@inertiajs/core';

// Mirrors App\Models\Fir as the site lists it.
export type Fir = {
    icao: string;
    name: string;
};

export type Vacc = {
    name: string;
    division: string | null;
    // VATSIM subdivision code, e.g. SCA.
    subdivision: string | null;
    // The FIRs the vACC covers, by ICAO code.
    firs: Fir[];
    website: string | null;
    // Public URL of the uploaded logo, null when none is set.
    logo: string | null;
};

// Mirrors App\Enums\Permission.
export type PermissionName = 'manage-events' | 'manage-staffing' | 'view-events' | 'manage-users';

// Mirrors the auth props shared by App\Http\Middleware\HandleInertiaRequests.
export type AuthUser = {
    id: number;
    name: string;
    cid: number | null;
    // e.g. "S2", or "OBS" for most pilots.
    rating: string | null;
    // The VATSIM subdivision code, e.g. "SCA"; null outside any subdivision.
    subdivision: string | null;
    isAdministrator: boolean;
    can: Record<PermissionName, boolean>;
};

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            vacc: Vacc;
            auth: {
                user: AuthUser | null;
                // The sign-in page, the configured provider's own path, and the button text.
                signIn: { page: string; url: string; label: string };
            };
            // IANA time zone event times are shown in, 'UTC' (zulu) by default.
            displayTimezone: string;
        };
        flashDataType: {
            success?: string;
            // A failed sign-in that trying again could fix.
            signInRetry?: boolean;
        };
    }
}
