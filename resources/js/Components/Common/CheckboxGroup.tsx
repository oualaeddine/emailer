import { FieldContextProvider } from '@fluentui/react-components';
import type { HTMLAttributes, ReactNode } from 'react';

/**
 * Container for a list of checkboxes placed inside a Fluent `<Field>`.
 *
 * `<Field>` hands its control id and `required` to every control that reads
 * the field context. With several checkboxes they all received the same id,
 * so each label toggled the first checkbox, and every box became natively
 * required. This group takes the Field's id / aria attributes itself (as a
 * `role="group"`) and resets the context for the checkboxes inside.
 */
export function CheckboxGroup({ children, ...rest }: HTMLAttributes<HTMLDivElement> & { children: ReactNode }) {
    return (
        <div role="group" {...rest}>
            <FieldContextProvider value={undefined}>{children}</FieldContextProvider>
        </div>
    );
}
