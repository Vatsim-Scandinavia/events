export type User = {
    cid: number;
    name_full: string;
    email: string;
    avatar?: string;
    controller_rating: number;
    division: string | null;
    subdivision: string | null;
    oauth_provider: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};
