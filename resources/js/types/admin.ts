import type { PermissionName } from '@/types/inertia';

// Mirrors App\Http\Controllers\Admin\UserController::summary().
export type AdminUser = {
    id: number;
    name: string;
    // Only sent to Administrators.
    email?: string;
    cid: number | null;
    rating: string | null;
    roles: string[];
    joinedAt: string | null;
};

// A permission as offered on the role form.
export type PermissionOption = {
    value: PermissionName;
    label: string;
    description: string;
};

// Laravel's length-aware paginator, as Inertia serializes it.
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
