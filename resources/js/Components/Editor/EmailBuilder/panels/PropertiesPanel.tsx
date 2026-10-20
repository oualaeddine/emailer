/**
 * WP-60 — right "Propriétés" panel (docs/46 §5, §7): Fluent fields read the
 * selected component's MJML attributes and commit on blur / Enter / select,
 * one undo step per change.
 */
import { useEffect, useState } from 'react';
import { Field, Input, Select, Text, makeStyles, tokens } from '@fluentui/react-components';
import type { BuilderSelection } from '../engine/createEditor';
import { text } from '../strings';
import { PRESETS_BY_BLOCK, fieldsFor, type PropertyFieldDef } from './properties';

const useStyles = makeStyles({
    root: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalS },
    swatch: {
        width: '12px',
        height: '12px',
        borderRadius: tokens.borderRadiusSmall,
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke1}`,
    },
    muted: { color: tokens.colorNeutralForeground3 },
});

function PropertyField({ def, value, onCommit }: { def: PropertyFieldDef; value: string; onCommit: (value: string) => void }) {
    const styles = useStyles();
    const [draft, setDraft] = useState(value);
    useEffect(() => setDraft(value), [value]);

    if (def.kind === 'select') {
        return (
            <Field label={def.label} size="small">
                <Select size="small" value={value} onChange={(_, data) => onCommit(data.value)}>
                    <option value="">{text.common.default}</option>
                    {def.options?.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </Select>
            </Field>
        );
    }

    const commit = () => {
        if (draft !== value) {
            onCommit(draft.trim());
        }
    };

    return (
        <Field label={def.label} size="small">
            <Input
                size="small"
                type={def.kind === 'url' ? 'url' : 'text'}
                value={draft}
                placeholder={def.placeholder}
                contentBefore={def.kind === 'color' && draft ? <span className={styles.swatch} style={{ background: draft }} /> : undefined}
                onChange={(_, data) => setDraft(data.value)}
                onBlur={commit}
                onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                        commit();
                    }
                }}
            />
        </Field>
    );
}

export interface PropertiesPanelProps {
    selection: BuilderSelection | null;
    onChange: (values: Record<string, string>) => void;
}

export function PropertiesPanel({ selection, onChange }: PropertiesPanelProps) {
    const styles = useStyles();

    if (!selection) {
        return <Text size={200}>{text.noSelection}</Text>;
    }

    const fields = fieldsFor(selection.type, selection.block);
    const preset = selection.block ? PRESETS_BY_BLOCK[selection.block] : undefined;
    const blockLabel = selection.block ? text.blocks[selection.block] : selection.name;

    return (
        <div className={styles.root}>
            <Text weight="semibold">{blockLabel}</Text>
            {preset && (
                <Field label={preset.label} size="small">
                    <Select
                        size="small"
                        value={preset.current(selection.attributes)}
                        onChange={(_, data) => {
                            const option = preset.options.find((o) => o.value === data.value);
                            if (option) {
                                onChange(option.attributes);
                            }
                        }}
                    >
                        <option value="" disabled>
                            {text.common.default}
                        </option>
                        {preset.options.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </Select>
                </Field>
            )}
            {fields.length === 0 && !preset && (
                <Text size={200} className={styles.muted}>
                    {text.noFields}
                </Text>
            )}
            {fields.map((def) => (
                <PropertyField
                    key={`${selection.id}-${def.name}`}
                    def={def}
                    value={selection.attributes[def.name] ?? ''}
                    onCommit={(value) => onChange({ [def.name]: value })}
                />
            ))}
        </div>
    );
}
