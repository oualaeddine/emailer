import { useEffect, useState, useCallback } from 'react';
import { Head } from '@inertiajs/react';
import {
    Badge,
    Button,
    DataGrid,
    DataGridBody,
    DataGridCell,
    DataGridHeader,
    DataGridHeaderCell,
    DataGridRow,
    Title1,
    Toolbar,
    createTableColumn,
    makeStyles,
    tokens,
    type TableColumnDefinition,
} from '@fluentui/react-components';
import { AddRegular, EditRegular } from '@fluentui/react-icons';
import { AppShell } from '@/Components/Shell/AppShell';
import { useText } from '@/Hooks/useText';
import { createUser, fetchRoles, fetchUsers, updateUser, type CreateUserPayload } from '@/Lib/api/identity';
import type { Role, User } from '@/Lib/types/identity';
import { UserFormDialog, type UserFormValues } from '@/Pages/Admin/UserFormDialog';

const useStyles = makeStyles({
    header: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginBottom: tokens.spacingVerticalL,
    },
    card: {
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalM,
    },
});

/**
 * docs/29-api-specification.md §29.2, docs/26-rbac.md §26.3 — `users.manage`.
 * Route-gated server-side (UserPolicy); the nav item itself is hidden
 * client-side for users without the permission (docs/08-navigation.md §8.4).
 */
export default function Users() {
    const styles = useStyles();
    const t = useText();

    const [users, setUsers] = useState<User[]>([]);
    const [roles, setRoles] = useState<Role[]>([]);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<User | null>(null);
    const [formErrors, setFormErrors] = useState<Partial<Record<string, string>>>({});

    const loadUsers = useCallback(async () => {
        const response = await fetchUsers();
        setUsers(response.data);
    }, []);

    useEffect(() => {
        void loadUsers();
        void fetchRoles().then(setRoles);
    }, [loadUsers]);

    function openCreateDialog() {
        setEditingUser(null);
        setFormErrors({});
        setDialogOpen(true);
    }

    function openEditDialog(user: User) {
        setEditingUser(user);
        setFormErrors({});
        setDialogOpen(true);
    }

    async function handleSubmit(values: UserFormValues) {
        setFormErrors({});
        try {
            if (editingUser) {
                await updateUser(editingUser.id, {
                    name: values.name,
                    role_id: values.role_id ?? undefined,
                    is_active: values.is_active,
                });
            } else {
                await createUser(values as CreateUserPayload);
            }
            setDialogOpen(false);
            await loadUsers();
        } catch (error) {
            if (isValidationError(error)) {
                setFormErrors(flattenValidationErrors(error));
            } else {
                throw error;
            }
        }
    }

    const columns: TableColumnDefinition<User>[] = [
        createTableColumn<User>({
            columnId: 'name',
            renderHeaderCell: () => t.users.name,
            renderCell: (user) => user.name,
        }),
        createTableColumn<User>({
            columnId: 'email',
            renderHeaderCell: () => t.users.email,
            renderCell: (user) => user.email,
        }),
        createTableColumn<User>({
            columnId: 'role',
            renderHeaderCell: () => t.users.role,
            renderCell: (user) => user.role?.description ?? user.role?.name ?? '—',
        }),
        createTableColumn<User>({
            columnId: 'status',
            renderHeaderCell: () => t.users.status,
            renderCell: (user) => (
                <Badge color={user.is_active ? 'success' : 'danger'}>
                    {user.is_active ? t.users.active : t.users.inactive}
                </Badge>
            ),
        }),
        createTableColumn<User>({
            columnId: 'last_login_at',
            renderHeaderCell: () => t.users.lastLogin,
            renderCell: (user) =>
                user.last_login_at ? new Date(user.last_login_at).toLocaleString('fr-FR') : t.users.never,
        }),
        createTableColumn<User>({
            columnId: 'actions',
            renderHeaderCell: () => t.common.actions,
            renderCell: (user) => (
                <Button
                    icon={<EditRegular />}
                    appearance="subtle"
                    aria-label={t.common.edit}
                    onClick={() => openEditDialog(user)}
                />
            ),
        }),
    ];

    return (
        <AppShell>
            <Head title={t.users.title} />
            <div className={styles.header}>
                <Title1>{t.users.title}</Title1>
                <Toolbar>
                    <Button appearance="primary" icon={<AddRegular />} onClick={openCreateDialog}>
                        {t.users.newUser}
                    </Button>
                </Toolbar>
            </div>
            <div className={styles.card}>
                <DataGrid items={users} columns={columns} getRowId={(user) => user.id} resizableColumns>
                    <DataGridHeader>
                        <DataGridRow>
                            {({ renderHeaderCell }) => (
                                <DataGridHeaderCell>{renderHeaderCell()}</DataGridHeaderCell>
                            )}
                        </DataGridRow>
                    </DataGridHeader>
                    <DataGridBody<User>>
                        {({ item, rowId }) => (
                            <DataGridRow<User> key={rowId}>
                                {({ renderCell }) => <DataGridCell>{renderCell(item)}</DataGridCell>}
                            </DataGridRow>
                        )}
                    </DataGridBody>
                </DataGrid>
            </div>
            <UserFormDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                roles={roles}
                user={editingUser}
                onSubmit={handleSubmit}
                errors={formErrors}
            />
        </AppShell>
    );
}

interface ValidationErrorResponse {
    response?: { status?: number; data?: { errors?: Record<string, string[]> } };
}

function isValidationError(error: unknown): error is ValidationErrorResponse {
    return (
        typeof error === 'object' &&
        error !== null &&
        (error as ValidationErrorResponse).response?.status === 422
    );
}

function flattenValidationErrors(error: ValidationErrorResponse): Record<string, string> {
    const errors = error.response?.data?.errors ?? {};

    return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, messages[0]]));
}
