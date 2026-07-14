import { FormEvent, useState } from 'react';
import {
    Button,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    DialogTrigger,
    Dropdown,
    Field,
    Input,
    Option,
    Switch,
} from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import type { Role, User } from '@/Lib/types/identity';

export interface UserFormValues {
    name: string;
    email: string;
    password: string;
    role_id: number | null;
    is_active: boolean;
}

interface UserFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    roles: Role[];
    user?: User | null;
    onSubmit: (values: UserFormValues) => Promise<void>;
    errors?: Partial<Record<keyof UserFormValues, string>>;
}

/**
 * docs/29-api-specification.md §29.2 — POST/PATCH /api/v1/users.
 * Shared by "create" (user=null) and "edit" (user set) since both forms
 * differ only in whether the password field and pre-filled values apply.
 */
export function UserFormDialog({ open, onOpenChange, roles, user, onSubmit, errors }: UserFormDialogProps) {
    const t = useText();
    const isEditing = user != null;

    const [values, setValues] = useState<UserFormValues>({
        name: user?.name ?? '',
        email: user?.email ?? '',
        password: '',
        role_id: user?.role?.id ?? null,
        is_active: user?.is_active ?? true,
    });
    const [submitting, setSubmitting] = useState(false);

    async function handleSubmit(event: FormEvent) {
        event.preventDefault();
        setSubmitting(true);
        try {
            await onSubmit(values);
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <Dialog open={open} onOpenChange={(_, data) => onOpenChange(data.open)}>
            <DialogSurface>
                <form onSubmit={handleSubmit}>
                    <DialogBody>
                        <DialogTitle>{isEditing ? t.users.editTitle : t.users.createTitle}</DialogTitle>
                        <DialogContent>
                            <Field
                                label={t.users.name}
                                validationState={errors?.name ? 'error' : 'none'}
                                validationMessage={errors?.name}
                                required
                            >
                                <Input
                                    value={values.name}
                                    onChange={(_, data) => setValues((v) => ({ ...v, name: data.value }))}
                                />
                            </Field>
                            {!isEditing && (
                                <>
                                    <Field
                                        label={t.users.email}
                                        validationState={errors?.email ? 'error' : 'none'}
                                        validationMessage={errors?.email}
                                        required
                                    >
                                        <Input
                                            type="email"
                                            value={values.email}
                                            onChange={(_, data) => setValues((v) => ({ ...v, email: data.value }))}
                                        />
                                    </Field>
                                    <Field
                                        label={t.users.password}
                                        validationState={errors?.password ? 'error' : 'none'}
                                        validationMessage={errors?.password}
                                        required
                                    >
                                        <Input
                                            type="password"
                                            value={values.password}
                                            onChange={(_, data) => setValues((v) => ({ ...v, password: data.value }))}
                                        />
                                    </Field>
                                </>
                            )}
                            <Field
                                label={t.users.role}
                                validationState={errors?.role_id ? 'error' : 'none'}
                                validationMessage={errors?.role_id}
                                required
                            >
                                <Dropdown
                                    value={roles.find((r) => r.id === values.role_id)?.description ?? ''}
                                    onOptionSelect={(_, data) =>
                                        setValues((v) => ({ ...v, role_id: Number(data.optionValue) }))
                                    }
                                >
                                    {roles.map((role) => (
                                        <Option key={role.id} value={String(role.id)}>
                                            {role.description ?? role.name}
                                        </Option>
                                    ))}
                                </Dropdown>
                            </Field>
                            {isEditing && (
                                <Field label={t.users.status}>
                                    <Switch
                                        checked={values.is_active}
                                        label={values.is_active ? t.users.active : t.users.inactive}
                                        onChange={(_, data) =>
                                            setValues((v) => ({ ...v, is_active: data.checked }))
                                        }
                                    />
                                </Field>
                            )}
                        </DialogContent>
                        <DialogActions>
                            <DialogTrigger disableButtonEnhancement>
                                <Button appearance="secondary">{t.common.cancel}</Button>
                            </DialogTrigger>
                            <Button appearance="primary" type="submit" disabled={submitting}>
                                {t.common.save}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </form>
            </DialogSurface>
        </Dialog>
    );
}
