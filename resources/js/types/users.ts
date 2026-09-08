export type ManagedUser = {
    cid: number;
    name_full: string;
    email: string;
    controller_rating: number;
    division: string | null;
    subdivision: string | null;
    joined_at: string | null;
    roles: string[];
    grants: RoleGrant[];
};

export type RoleGrant = {
    id: number;
    role: string | null;
    team_id: number | null;
    source: string;
};

export type UserRole = {
    name: string;
    is_global: boolean;
    assignable: boolean;
};

export type UserTeam = {
    id: number;
    code: string;
    name: string;
};

export type UserFilters = {
    search: string;
    role: string | null;
    team_id: number | null;
};

export type PaginatedUsers = {
    data: ManagedUser[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
};
