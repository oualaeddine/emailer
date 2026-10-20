import { useEffect, useState, useCallback } from 'react';
import { Head, usePage } from '@inertiajs/react';
import {
    Badge,
    Button,
    DataGrid,
    DataGridBody,
    DataGridCell,
    DataGridHeader,
    DataGridHeaderCell,
    DataGridRow,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    Text,
    Toast,
    ToastTitle,
    Toaster,
    Tooltip,
    createTableColumn,
    makeStyles,
    tokens,
    useId,
    useToastController,
    type TableColumnDefinition,
} from '@fluentui/react-components';
import { AddRegular, EditRegular, ShieldKeyholeRegular } from '@fluentui/react-icons';
import { AppShell } from '@/Components/Shell/AppShell';
import { PageHelp } from '@/Components/Help/PageHelp';
import { useText } from '@/Hooks/useText';
import {
    createUser,
    fetchRoles,
    fetchUsers,
    resetUserTwoFactor,
    updateUser,
    type CreateUserPayload,
} from '@/Lib/api/identity';
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
        overflowX: 'auto',
        width: '100%',
        boxSizing: 'border-box',
    },
    gridScroll: {
        width: '100%',
        minWidth: '760px',
    },
    dataGrid: {
        width: '100%',
    },
    truncatedCell: {
        overflow: 'hidden',
        textOverflow: 'ellipsis',
        whiteSpace: 'nowrap',
        display: 'block',
        maxWidth: '320px',
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
    const [resetTarget, setResetTarget] = useState<User | null>(null);
    const [resetting, setResetting] = useState(false);

    // Administrators reset someone else's 2FA only (the API answers 422 for self).
    const authUserId = usePage<{ auth: { user: { id: string } | null } }>().props.auth.user?.id;
    const toasterId = useId('users-toaster');
    const { dispatchToast } = useToastController(toasterId);

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
                    ...(values.password !== ''
                        ? { password: values.password, password_confirmation: values.password_confirmation }
                        : {}),
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

    async function confirmResetTwoFactor() {
        if (!resetTarget) {
            return;
        }
        setResetting(true);
        try {
            await resetUserTwoFactor(resetTarget.id);
            setResetTarget(null);
            dispatchToast(
                <Toast>
                    <ToastTitle>{t.users.resetTwoFactor.success}</ToastTitle>
                </Toast>,
                { intent: 'success' },
            );
        } catch (error) {
            const data = (error as ValidationErrorResponse).response?.data as
                | { message?: string; errors?: Record<string, string[]> }
                | undefined;
            const message = Object.values(data?.errors ?? {})[0]?.[0] ?? data?.message ?? t.users.resetTwoFactor.failed;
            setResetTarget(null);
            dispatchToast(
                <Toast>
                    <ToastTitle>{message}</ToastTitle>
                </Toast>,
                { intent: 'error' },
            );
        } finally {
            setResetting(false);
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
            renderCell: (user) => {
                const label = user.role?.description ?? user.role?.name ?? '—';

                return (
                    <Tooltip content={label} relationship="label">
                        <Text className={styles.truncatedCell}>{label}</Text>
                    </Tooltip>
                );
            },
        }),
        createTableColumn<User>({
            columnId: 'status',
            renderHeaderCell: () => t.users.status,
            renderCell: (user) => (
                <Badge appearance="tint" color={user.is_active ? 'success' : 'danger'}>
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
                <>
                    <Button
                        icon={<EditRegular />}
                        appearance="subtle"
                        aria-label={t.common.edit}
                        onClick={() => openEditDialog(user)}
                    />
                    {user.id !== authUserId && (
                        <Tooltip content={t.users.resetTwoFactor.action} relationship="label">
                            <Button
                                icon={<ShieldKeyholeRegular />}
                                appearance="subtle"
                                onClick={() => setResetTarget(user)}
                            />
                        </Tooltip>
                    )}
                </>
            ),
        }),
    ];

    return (
        <AppShell>
            <Head title={t.users.title} />
            <PageHelp
                topic="admin-users"
                title={t.users.title}
                actions={
                    <Button appearance="primary" icon={<AddRegular />} onClick={openCreateDialog}>
                        {t.users.newUser}
                    </Button>
                }
            />
            <div className={styles.card}>
                <div className={styles.gridScroll}>
                <DataGrid
                    items={users}
                    columns={columns}
                    getRowId={(user) => user.id}
                    className={styles.dataGrid}
                    style={{ width: '100%' }}
                >
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
            </div>
            <Toaster toasterId={toasterId} position="bottom-end" />
            <Dialog
                open={resetTarget !== null}
                onOpenChange={(_, data) => {
                    if (!data.open && !resetting) {
                        setResetTarget(null);
                    }
                }}
            >
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>{t.users.resetTwoFactor.title}</DialogTitle>
                        <DialogContent>
                            {t.users.resetTwoFactor.body.replace('{name}', resetTarget?.name ?? '')}
                        </DialogContent>
                        <DialogActions>
                            <Button
                                appearance="secondary"
                                disabled={resetting}
                                onClick={() => setResetTarget(null)}
                            >
                                {t.common.cancel}
                            </Button>
                            <Button
                                appearance="primary"
                                disabled={resetting}
                                onClick={() => void confirmResetTwoFactor()}
                            >
                                {t.users.resetTwoFactor.confirm}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>
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
