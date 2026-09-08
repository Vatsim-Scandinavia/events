export type RoleSnapshot = {
    role: string;
    fir_id: number | null;
    fir: string | null;
    source: string;
};

export type AuditValue =
    | string
    | number
    | boolean
    | null
    | AuditValue[]
    | { [key: string]: AuditValue };

export type AuditLog = {
    id: number;
    actor_cid: number | null;
    actor_name: string | null;
    subject_type: 'fir' | 'user' | 'event' | 'airport';
    subject_id: number;
    subject_label: string;
    event: 'created' | 'updated' | 'deleted' | 'roles_updated';
    source: string;
    old_values: Record<string, AuditValue>;
    new_values: Record<string, AuditValue>;
    created_at: string;
};

export type AuditFilters = {
    search: string;
    subject_type: string;
    event: string;
    from: string;
    to: string;
};

export type PaginatedAuditLogs = {
    data: AuditLog[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
};
