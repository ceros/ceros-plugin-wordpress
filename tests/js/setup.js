import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';

// Testing Library only auto-registers cleanup when a global afterEach exists.
// This suite uses explicit imports rather than Vitest globals, so without this
// every render stays in the document and later queries see earlier tests' DOM.
afterEach( cleanup );
