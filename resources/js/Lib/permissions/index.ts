/**
 * docs/26-rbac.md §26.5 point 4 — frontend gating helpers. These mirror the
 * backend's permission matrix for UX purposes only (hiding nav items/
 * buttons); the backend remains the authoritative enforcement layer, so a
 * missing/incorrect check here is a UX bug, never a security hole.
 */
export function hasPermission(permissions: string[], permission: string): boolean {
    return permissions.includes(permission);
}

export function hasAnyPermission(permissions: string[], ...required: string[]): boolean {
    return required.some((permission) => permissions.includes(permission));
}
