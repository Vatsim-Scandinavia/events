export type ManagedFir = {
    id: number;
    code: string;
    name: string;
    members_count: number;
};

export type PaginatedFirs = {
    data: ManagedFir[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
};
