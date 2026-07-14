import axios from 'axios';
import type { AuthenticatedUser, PaginatedResponse, Role, User } from '@/Lib/types/identity';

/**
 * docs/29-api-specification.md §29.2 — Identity & Auth.
 */

export async function fetchMe(): Promise<AuthenticatedUser> {
    const response = await axios.get<{ data: AuthenticatedUser }>('/api/v1/me');

    return response.data.data;
}

export async function fetchUsers(page = 1): Promise<PaginatedResponse<User>> {
    const response = await axios.get<PaginatedResponse<User>>('/api/v1/users', { params: { page } });

    return response.data;
}

export interface CreateUserPayload {
    name: string;
    email: string;
    password: string;
    role_id: number;
}

export async function createUser(payload: CreateUserPayload): Promise<User> {
    const response = await axios.post<{ data: User }>('/api/v1/users', payload);

    return response.data.data;
}

export interface UpdateUserPayload {
    name?: string;
    role_id?: number;
    is_active?: boolean;
}

export async function updateUser(id: string, payload: UpdateUserPayload): Promise<User> {
    const response = await axios.patch<{ data: User }>(`/api/v1/users/${id}`, payload);

    return response.data.data;
}

export async function fetchRoles(): Promise<Role[]> {
    const response = await axios.get<{ data: Role[] }>('/api/v1/roles');

    return response.data.data;
}
