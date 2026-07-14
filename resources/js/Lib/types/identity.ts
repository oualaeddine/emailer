/**
 * Mirrors App\Modules\Identity\Http\Resources\* 1:1 (docs/03-folder-structure.md §3.2).
 */

export interface Permission {
    name: string;
    module: string;
    description: string | null;
}

export interface Role {
    id: number;
    name: string;
    description: string | null;
    permissions?: Permission[];
}

export interface User {
    id: string;
    name: string;
    email: string;
    avatar_path: string | null;
    is_active: boolean;
    last_login_at: string | null;
    role: Role | null;
    created_at: string;
}

export interface AuthenticatedUser extends User {
    permissions: string[];
}

/**
 * Laravel's default AnonymousResourceCollection JSON envelope
 * (docs/29-api-specification.md §29.1 pagination convention).
 */
export interface PaginatedResponse<T> {
    data: T[];
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
}
