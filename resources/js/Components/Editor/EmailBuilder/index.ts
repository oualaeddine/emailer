/**
 * WP-60 — public surface of the EmailBuilder. Pages import from here only:
 * `EmailBuilderLazy` and types. Everything else is reached through the lazy
 * `EmailBuilder.tsx` chunk.
 */
export { EmailBuilderLazy, prefetchEmailBuilder } from './EmailBuilderLazy';
export type {
    DynamicBlockRecord,
    EmailBuilderProps,
    EmailBuilderRestoreOption,
    EmailBuilderSaveResult,
    EmailBuilderSource,
    EmailDesignWithDynamic,
} from './types';
