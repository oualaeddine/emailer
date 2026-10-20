/**
 * WP-61 — dynamic-content block (doc 43 §43.4.4, docs/46 §3, docs/45 §45.7.1).
 *
 * A dynamic section is an `mj-wrapper` carrying the GrapesJS component prop
 * `pjDynamic: { id, condition: RuleTree | null }` (kept in the project
 * JSON). On export the wrapper is framed by two `mj-raw` markers:
 *
 *   <mj-raw><div data-dynamic-rule="{id}"></mj-raw><mj-wrapper>…</mj-wrapper><mj-raw></div></mj-raw>
 *
 * so the compiled HTML carries `<div data-dynamic-rule="{id}">…</div>`, and
 * the rules are saved in `design_json.dynamic_blocks[]` (`DynamicBlockRecord`).
 * The rendering pipeline (ConditionalBlocksStage) keeps or removes each
 * block per recipient. Extending `mj-wrapper` (instead of a new type) keeps
 * grapesjs-mjml's drag / drop rules (`[data-gjs-type="mj-wrapper"]`).
 */
import type { Component, Editor } from 'grapesjs';
import type { RuleTree } from '@/Lib/types/segments';
import type { DynamicBlockRecord } from '../types';

export const DYNAMIC_PROP = 'pjDynamic';
/** Marker attribute in the compiled HTML (read by ConditionalBlocksStage). */
export const DYNAMIC_ATTRIBUTE = 'data-dynamic-rule';
/** Transient attribute used to re-attach a rule when a design is reopened from its MJML. */
const LIFT_ATTRIBUTE = 'data-pj-dynamic';

const ID_PATTERN = /^[A-Za-z0-9_-]{1,64}$/;

export interface DynamicConfig {
    id: string;
    condition: RuleTree | null;
}

export function emptyCondition(): RuleTree {
    return { version: 2, type: 'group', operator: 'and', children: [] };
}

export function newDynamicId(): string {
    const random = Math.random().toString(36).slice(2, 10).padEnd(8, '0');

    return `dyn_${Date.now().toString(36)}${random}`;
}

function isRuleTree(value: unknown): value is RuleTree {
    return typeof value === 'object' && value !== null && (value as RuleTree).type === 'group' && Array.isArray((value as RuleTree).children);
}

export function dynamicConfigOf(cmp: Component | null | undefined): DynamicConfig | null {
    const value = cmp?.get(DYNAMIC_PROP) as Partial<DynamicConfig> | undefined;
    if (!value || typeof value !== 'object' || typeof value.id !== 'string' || !ID_PATTERN.test(value.id)) {
        return null;
    }

    return { id: value.id, condition: isRuleTree(value.condition) ? value.condition : null };
}

/** The dynamic wrapper containing `cmp` (or `cmp` itself). */
export function closestDynamic(cmp: Component | null | undefined): Component | null {
    let current: Component | undefined | null = cmp;
    while (current) {
        if (dynamicConfigOf(current)) {
            return current;
        }
        current = current.parent();
    }

    return null;
}

export function walkComponents(root: Component | undefined, visit: (cmp: Component) => void): void {
    if (!root) {
        return;
    }
    visit(root);
    root.components().forEach((child: Component) => walkComponents(child, visit));
}

/** Rules known for the design being loaded, read when wrappers are re-created from MJML. */
const pendingRules = new Map<string, RuleTree | null>();

export function setPendingRules(blocks: DynamicBlockRecord[] | undefined): void {
    pendingRules.clear();
    for (const block of blocks ?? []) {
        const condition = block.variants?.[0]?.condition;
        pendingRules.set(block.id, isRuleTree(condition) ? condition : null);
    }
}

type WrapperModel = Component & { get(key: string): unknown };
type ToHtml = (this: Component, opts?: unknown) => string;
type Init = (this: Component, ...args: unknown[]) => void;

/**
 * Teaches `mj-wrapper` to export its markers and to re-attach a rule
 * lifted from MJML (`data-pj-dynamic`). Call once, after the plugin.
 */
export function registerDynamicContent(editor: Editor): void {
    const type = editor.Components.getType('mj-wrapper');
    if (!type) {
        return;
    }
    const proto = type.model.prototype as unknown as { toHTML: ToHtml; init?: Init };
    const baseToHtml = proto.toHTML;
    const baseInit = proto.init;

    editor.Components.addType('mj-wrapper', {
        model: {
            init(this: WrapperModel, ...args: unknown[]) {
                const attributes = { ...((this.get('attributes') as Record<string, string> | undefined) ?? {}) };
                const lifted = attributes[LIFT_ATTRIBUTE];
                if (typeof lifted === 'string' && ID_PATTERN.test(lifted)) {
                    delete attributes[LIFT_ATTRIBUTE];
                    this.set('attributes', attributes, { silent: true });
                    this.set(DYNAMIC_PROP, { id: lifted, condition: pendingRules.get(lifted) ?? null }, { silent: true });
                }
                baseInit?.apply(this, args);
            },
            toHTML(this: WrapperModel, opts?: unknown) {
                const html = baseToHtml.call(this, opts);
                const config = dynamicConfigOf(this);

                return config ? `<mj-raw><div ${DYNAMIC_ATTRIBUTE}="${config.id}"></mj-raw>${html}<mj-raw></div></mj-raw>` : html;
            },
        },
    });

    // A copied / duplicated dynamic section gets its own id (same rule).
    editor.on('component:clone', (cmp: Component) => {
        const config = dynamicConfigOf(cmp);
        if (config) {
            cmp.set(DYNAMIC_PROP, { id: newDynamicId(), condition: config.condition ? structuredClone(config.condition) : null });
        }
    });
}

/** Saved MJML → editor MJML: markers become a transient attribute on the wrapper (see `registerDynamicContent`). */
export function liftDynamicMarkers(mjml: string): string {
    return mjml
        .replace(
            /<mj-raw>\s*<div\s+data-dynamic-rule="([A-Za-z0-9_-]{1,64})"\s*>\s*<\/mj-raw>\s*<mj-wrapper\b/g,
            (_match, id: string) => `<mj-wrapper ${LIFT_ATTRIBUTE}="${id}"`,
        )
        .replace(/<\/mj-wrapper>\s*<mj-raw>\s*<\/div>\s*<\/mj-raw>/g, '</mj-wrapper>');
}

/** Gives every dynamic section under `root` a fresh id (inserted saved section, dropped block). */
export function renewDynamicIds(root: Component): void {
    walkComponents(root, (cmp) => {
        const config = dynamicConfigOf(cmp);
        if (config) {
            cmp.set(DYNAMIC_PROP, { id: newDynamicId(), condition: config.condition });
        }
    });
}

/**
 * `design_json.dynamic_blocks` of the current design. Duplicate ids (e.g. a
 * saved section inserted twice) are renewed first, so call this before
 * exporting the MJML.
 */
export function collectDynamicBlocks(editor: Editor): DynamicBlockRecord[] {
    const seen = new Set<string>();
    const blocks: DynamicBlockRecord[] = [];

    walkComponents(editor.getWrapper() ?? undefined, (cmp) => {
        let config = dynamicConfigOf(cmp);
        if (!config) {
            return;
        }
        if (seen.has(config.id)) {
            config = { id: newDynamicId(), condition: config.condition };
            cmp.set(DYNAMIC_PROP, config);
        }
        seen.add(config.id);
        blocks.push({
            id: config.id,
            type: 'dynamic_content',
            variants: [{ condition: config.condition ?? emptyCondition(), mjml: '' }],
            fallback_mjml: '',
        });
    });

    return blocks;
}
