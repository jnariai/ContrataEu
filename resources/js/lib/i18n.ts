/**
 * Marks a static string as a translation key without translating it: for places hooks can't run
 * (a page's static `layout` object, module-level arrays). The component that renders it calls `t()`.
 * The translation completeness test collects the keys of both `t` and `tk` calls.
 */
export const tk = (key: string): string => key;
