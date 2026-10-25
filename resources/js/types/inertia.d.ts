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
export type PermissionName = 'manage-events' | 'manage-staffing' | 'view-events' | 'book-positions' | 'manage-users';

// Mirrors the auth props shared by App\Http\Middleware\HandleInertiaRequests.
export type AuthUser = {
    id: number;
    name: string;
    cid: number | null;
    rating: string | null;
    roles: string[];
    isAdministrator: boolean;
    can: Record<PermissionName, boolean>;
};

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            vacc: Vacc;
            auth: {
                user: AuthUser | null;
                devLogin: boolean;
                // The configured sign-in provider's path and button text.
                signIn: { url: string; label: string };
            };
            // IANA time zone event times are shown in, 'UTC' (zulu) by default.
            displayTimezone: string;
        };
        flashDataType: {
            success?: string;
        };
    }
}
