import type { Locator, Page } from '@playwright/test'
import { TIMEOUTS } from '@constants/timeouts'
import { WP_ROUTES } from '@constants/wordpress-constants'

export class WordPressLoginPage {
  readonly usernameInput: Locator
  readonly passwordInput: Locator
  readonly submitButton: Locator
  readonly errorNotice: Locator
  readonly adminBar: Locator

  constructor(readonly page: Page) {
    this.usernameInput = page.locator('#user_login').describe('username input')
    this.passwordInput = page.locator('#user_pass').describe('password input')
    this.submitButton = page.locator('#wp-submit').describe('log in button')
    this.errorNotice = page.locator('#login_error').describe('login error notice')
    this.adminBar = page.locator('#wpadminbar').describe('wp-admin admin bar')
  }

  async open(): Promise<void> {
    await this.page.goto(WP_ROUTES.login)
  }

  async signIn(username: string, password: string): Promise<void> {
    await this.usernameInput.fill(username)
    await this.passwordInput.fill(password)
    await this.submitButton.click()
    // Wait for the admin document to parse, not every asset: WordPress's
    // dashboard can leave the `load` event pending on a slow request, which is
    // not a login failure and was the cause of a flaky timeout here. Reaching a
    // wp-admin URL already means the login POST succeeded (a bad login stays on
    // wp-login.php); the admin bar then confirms the session actually rendered.
    await this.page.waitForURL(/wp-admin/, {
      timeout: TIMEOUTS.LONG,
      waitUntil: 'domcontentloaded',
    })
    await this.adminBar.waitFor({ timeout: TIMEOUTS.MEDIUM })
  }
}
