import { expect, Page, test } from '@playwright/test'
import { hideObstructiveUi } from './helpers/ui'

const INVALID_PASSWORD_MESSAGE = 'Das eingegebene Passwort ist ungültig. Bitte versuche es erneut.'

async function openStudentLogin(page: Page): Promise<void> {
    await page.goto('/homepage/student')
    await hideObstructiveUi(page)

    const schoolCard = page.locator('.school-item', { hasText: 'E2E School' })
    if (await schoolCard.isVisible()) {
        await schoolCard.click()
    }

    await expect(page.locator('[data-testid="student-login-email"]')).toBeVisible()
}

async function tryStudentLogin(page: Page, email: string, password: string): Promise<void> {
    await openStudentLogin(page)

    await page.locator('[data-testid="student-login-email"] input').fill(email)
    await page.getByTestId('student-login-continue-password').click()
    await expect(page.locator('[data-testid="student-login-password"]')).toBeVisible()
    await page.locator('[data-testid="student-login-password"] input').fill(password)
    const submitButton = page.getByTestId('student-login-submit-password')
    await submitButton.evaluate((el) => el.scrollIntoView({ block: 'center', behavior: 'instant' }))
    await submitButton.click()
}

async function waitForStudentLoginResult(page: Page): Promise<'success' | 'invalid'> {
    for (let i = 0; i < 100; i++) {
        if (/\/student\/overview$/.test(page.url())) {
            return 'success'
        }

        if (await page.getByText(INVALID_PASSWORD_MESSAGE).isVisible()) {
            return 'invalid'
        }

        await page.waitForTimeout(100)
    }

    throw new Error(`Could not determine student login result. Current URL: ${page.url()}`)
}

async function dismissInvalidPasswordDialog(page: Page): Promise<void> {
    await expect(page.getByText(INVALID_PASSWORD_MESSAGE)).toBeVisible()
    await hideObstructiveUi(page)
    await page.getByRole('button', { name: 'OK', exact: true }).click({ force: true })
    await expect(page.getByText(INVALID_PASSWORD_MESSAGE)).toBeHidden()
}

async function loginWithKnownPassword(page: Page, email: string, preferredPassword: string, fallbackPassword: string): Promise<{
    currentPassword: string
    otherPassword: string
}> {
    await tryStudentLogin(page, email, preferredPassword)
    if ((await waitForStudentLoginResult(page)) === 'success') {
        return {
            currentPassword: preferredPassword,
            otherPassword: fallbackPassword,
        }
    }

    await dismissInvalidPasswordDialog(page)

    await tryStudentLogin(page, email, fallbackPassword)
    if ((await waitForStudentLoginResult(page)) === 'success') {
        return {
            currentPassword: fallbackPassword,
            otherPassword: preferredPassword,
        }
    }

    await dismissInvalidPasswordDialog(page)
    throw new Error(`Unable to login ${email} with either known test password.`)
}

async function closeSuccessDialogAndReturnToOverview(page: Page): Promise<void> {
    await expect(page.getByTestId('student-password-success-dialog')).toBeVisible()
    await hideObstructiveUi(page)
    await page.getByTestId('student-password-success-ok').click({ force: true })

    if (!/\/student\/overview$/.test(page.url())) {
        await page.goto('/student/overview')
    }

    await expect(page).toHaveURL(/\/student\/overview$/)
}

test('student can change password and login with the new password', async ({ page }) => {
    const email = 'e2e.student.password@example.test'
    const passwordA = 'password123'
    const passwordB = 'E2eNewPass!123'
    const { currentPassword, otherPassword: newPassword } = await loginWithKnownPassword(page, email, passwordA, passwordB)
    await hideObstructiveUi(page)

    await page.getByTestId('student-overview-open-menu').click()
    await page.getByTestId('student-drawer-password').click()
    await expect(page).toHaveURL(/\/student\/password$/)

    await page.locator('[data-testid="student-password-new"] input').fill(newPassword)
    await page.locator('[data-testid="student-password-confirm"] input').fill(newPassword)
    await page.getByTestId('student-password-submit').click()

    await closeSuccessDialogAndReturnToOverview(page)

    await page.getByTestId('student-overview-logout').click()
    await expect(page).toHaveURL(/\/student$/)

    await tryStudentLogin(page, email, currentPassword)
    await dismissInvalidPasswordDialog(page)

    await tryStudentLogin(page, email, newPassword)
    await expect(page).toHaveURL(/\/student\/overview$/)

    await page.getByTestId('student-overview-open-menu').click()
    await page.getByTestId('student-drawer-password').click()
    await expect(page).toHaveURL(/\/student\/password$/)

    await page.locator('[data-testid="student-password-new"] input').fill(currentPassword)
    await page.locator('[data-testid="student-password-confirm"] input').fill(currentPassword)
    await page.getByTestId('student-password-submit').click()

    await closeSuccessDialogAndReturnToOverview(page)

    await page.getByTestId('student-overview-logout').click()
    await expect(page).toHaveURL(/\/student$/)

    await tryStudentLogin(page, email, currentPassword)
    await expect(page).toHaveURL(/\/student\/overview$/)
})
