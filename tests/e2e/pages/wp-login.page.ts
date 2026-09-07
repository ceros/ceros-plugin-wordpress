import type { Locator, Page } from '@playwright/test'
import { TIMEOUTS } from '@constants/timeouts'
import { WP_ROUTES } from '@constants/wordpress-constants'

export class WordPressLoginPage {
  readonly usernameInput: Locator
  readonly passwordInput: Locator
  readonly submitButton: Locator
  readonly errorNotice: Locator

  constructor(readonly page: Page) {
    this.usernameInput = page.locator('#user_login').describe('username input')
    this.passwordInput = page.locator('#user_pass').describe('password input')
    this.submitButton = page.locator('#wp-submit').describe('log in button')
    this.errorNotice = page.locator('#login_error').describe('login error notice')
  }

  async open(): Promise<void> {
    await this.page.goto(WP_ROUTES.login)
  }

  async signIn(username: string, password: string): Promise<void> {
    await this.usernameInput.fill(username)
    await this.passwordInput.fill(password)
    await this.submitButton.click()
    await this.page.waitForURL(/wp-admin/, { timeout: TIMEOUTS.LONG })
  }
}
