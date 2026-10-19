/**
 * docs/45-brevo-api-contracts.md §45.5.3 — public surface of the shared
 * ConditionBuilder module (WP-54U; embedded by WP-51 and WP-91U).
 *
 *   import ConditionBuilder, { buildFieldCatalog, isRuleTreeComplete } from '@/Components/Contacts/ConditionBuilder';
 */
import { ConditionBuilder } from './ConditionBuilder';

export default ConditionBuilder;
export { ConditionBuilder };
export { ConditionGroup, type ConditionGroupProps } from './ConditionGroup';
export { ConditionRow, type ConditionRowProps } from './ConditionRow';
export { FieldPicker } from './FieldPicker';
export { ValueEditor, type ValueEditorProps } from './editors/ValueEditor';
export { SaveSegmentDialog, type SaveSegmentDialogProps } from './SaveSegmentDialog';
export {
    ConnectedConditionBuilder,
    countRuleTree,
    defaultConditionOptionSources,
    useFieldCatalog,
    type ConnectedConditionBuilderProps,
    type UseFieldCatalogResult,
} from './connected';
export {
    FIELD_CATEGORIES,
    OPERATORS_BY_TYPE,
    attributeFieldDefinition,
    buildFieldCatalog,
    findField,
    operatorLabel,
    operatorsFor,
    staticFieldDefinitions,
    type BuildFieldCatalogOptions,
} from './catalog';
export {
    DEFAULT_MAX_CONDITIONS,
    countConditions,
    createCondition,
    createGroup,
    defaultEngagementValue,
    emptyRuleTree,
    isConditionComplete,
    isRuleTreeComplete,
    isValueComplete,
    valueShape,
    type ValueShape,
} from './tree';
export { ruleTreeErrors, validationErrors } from './errors';
export type {
    ConditionBuilderProps,
    ConditionOptionSources,
    FieldCatalog,
    FieldCategory,
    FieldDefinition,
    RuleTree,
} from '@/Lib/types/segments';
