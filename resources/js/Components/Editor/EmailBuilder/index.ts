/**
 * WP-60 — public surface of the EmailBuilder. Pages import from here only:
 * `EmailBuilderLazy` and types. Everything else is reached through the lazy
 * `EmailBuilder.tsx` chunk.
 */
export { EmailBuilderLazy, prefetchEmailBuilder } from './EmailBuilderLazy';
export type { EmailBuilderProps, EmailBuilderRestoreOption, EmailBuilderSaveResult, EmailBuilderSource } from './types';
