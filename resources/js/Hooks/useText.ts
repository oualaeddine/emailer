import { fr } from '@/Lib/i18n/fr';

/**
 * docs/07-ui-design.md §7.12 — single static French dictionary, no locale
 * switching. Returns the dictionary directly; kept as a hook (rather than a
 * plain import) so call sites read `const t = useText()` uniformly and a
 * future per-user override (unlikely, but keeps the call site stable) would
 * only change this one function.
 */
export function useText(): typeof fr {
    return fr;
}
