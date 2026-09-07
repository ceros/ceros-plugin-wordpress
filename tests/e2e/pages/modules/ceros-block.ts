import type { FrameLocator, Locator, Page } from '@playwright/test'
import { TIMEOUTS } from '@constants/timeouts'

/**
 * The Ceros block as rendered inside the editor.
 *
 * The root is a Page or a FrameLocator: the block normally lives in the canvas
 * iframe, but the server-rendered preview draws it in the top document. One
 * object covers both.
 *
 * Methods here are readiness gates only — assertions belong to specs.
 */
export type CerosBlockRoot = Page | FrameLocator

export class CerosBlock {
  readonly emptyState: Locator
  readonly browseExperiencesButton: Locator
  readonly pasteUrlInput: Locator

  readonly preview: Locator
  readonly previewNote: Locator
  readonly ssrPreviewFrame: Locator

  readonly errorPanel: Locator
  readonly errorHeading: Locator
  readonly errorSettingsLink: Locator
  readonly errorBoundary: Locator

  readonly loading: Locator

  constructor(readonly root: CerosBlockRoot) {
    this.emptyState = root.locator('.ceros-block__empty').describe('ceros block empty state')
    this.browseExperiencesButton = root
      .getByRole('button', { name: 'Browse Experiences' })
      .describe('browse experiences button')
    this.pasteUrlInput = root.locator('.ceros-block__paste-input').describe('paste url input')

    this.preview = root.locator('.ceros-block__preview').describe('ceros block preview')
    this.previewNote = root.locator('.ceros-block__preview-note').describe('preview note')
    this.ssrPreviewFrame = root
      .locator('.ceros-block__ssr-preview-frame')
      .describe('server-rendered preview frame')

    this.errorPanel = root.locator('.ceros-block__error').describe('ceros block error panel')
    this.errorHeading = this.errorPanel
      .getByRole('heading', { level: 3 })
      .describe('error panel heading')
    this.errorSettingsLink = this.errorPanel
      .getByRole('link', { name: 'Go to Ceros Settings' })
      .describe('go to ceros settings link')
    this.errorBoundary = root.locator('.ceros-block__error-boundary').describe('error boundary')

    this.loading = root.locator('.ceros-block__loading').describe('ceros block loading state')
  }

  async waitForEmptyState(): Promise<void> {
    await this.emptyState.waitFor({ timeout: TIMEOUTS.LONG })
  }

  async waitForPreview(): Promise<void> {
    await this.preview.waitFor({ timeout: TIMEOUTS.LONG })
  }

  async waitForErrorPanel(): Promise<void> {
    await this.errorPanel.waitFor({ timeout: TIMEOUTS.MEDIUM_LONG })
  }
}
