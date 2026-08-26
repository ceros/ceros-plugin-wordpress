/**
 * Stand-in for `@wordpress/element`, which is a webpack external mapped to
 * `window.wp.element` at build time and so is not installed.
 *
 * This is not a reimplementation: `@wordpress/element` re-exports React's API
 * verbatim, and every symbol the plugin imports from it (`Component`,
 * `useState`, `useEffect`, `useRef`, `useReducer`, `createPortal`) is React's
 * own. `createPortal` lives in react-dom, which is why this is a module rather
 * than a plain alias to `react`.
 *
 * Deliberately partial: the package's own additions (`RawHTML`,
 * `renderToString`, `createRoot`) are not React re-exports, so anything needing
 * them fails with a missing-export error rather than silently getting a fake.
 */
export * from 'react';
export { createPortal } from 'react-dom';
