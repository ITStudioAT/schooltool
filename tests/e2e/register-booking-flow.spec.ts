import { expect, Page, test } from '@playwright/test'
import { fetchUserToken } from './helpers/tokens'
import { hideObstructiveUi } from './helpers/ui'

async function fillOtpInput(page: Page, testId: string, token: string): Promise<void> {
    const digits = token.trim().split('')
    const inputs = page.locator(`[data-testid="${testId}"] input`)
    await expect(inputs.first()).toBeVisible()
    const count = await inputs.count()

    if (count === 1) {
        await inputs.first().fill(token.trim())
        return
    }

    for (let i = 0; i < digits.length; i++) {
        await inputs.nth(i).fill(digits[i])
    }
}

test('register user can book and cancel a register date', async ({ page }) => {
    const email = 'e2e.register@example.test'

    await page.goto('/homepage/register?school=E2E')
    await hideObstructiveUi(page)

    await expect(page.getByTestId('register-step-email')).toBeVisible()
    await page.locator('[data-testid="register-email"] input').fill(email)
    await page.getByTestId('register-email-continue').click()

    await expect(page.getByTestId('register-step-login-token')).toBeVisible()

    const token = await fetchUserToken(email, 'token_2fa')
    await fillOtpInput(page, 'register-login-token-input', token)
    await page.getByTestId('register-login-token-submit').click()

    await expect(page).toHaveURL(/\/homepage\/register2/)

    if (await page.getByTestId('register2-bookings-overview').count()) {
        while ((await page.locator('[data-testid^="register2-booking-"]').count()) > 0) {
            const existingBooking = page.locator('[data-testid^="register2-booking-"]').first()
            await existingBooking.locator('[data-testid^="register2-booking-delete-"]').click()
        }
    }

    await expect(page.getByTestId('register2-slot-selection')).toBeVisible()
    await page.locator('[data-testid^="register2-date-"]').first().click()
    await page.locator('[data-testid^="register2-time-slot-"]').first().click()
    await page.getByTestId('register2-to-student-form').click()

    await expect(page.getByTestId('register2-student-form')).toBeVisible()
    await page.locator('[data-testid="register2-student-last-name"] input').fill('E2E Kid')
    await page.locator('[data-testid="register2-student-first-name"] input').fill('First')

    const birthdateInput = page.locator('[data-testid="register2-student-birthdate"] input')
    if (await birthdateInput.count()) {
        await birthdateInput.fill('2015-05-01')
    }

    await page.locator('[data-testid="register2-student-note"] input').fill('E2E booking flow')
    await page.getByTestId('register2-submit-booking').click()

    await expect(page.getByTestId('register2-booked-success')).toBeVisible()
    await page.getByTestId('register2-booking-finished').click()

    await expect(page.getByTestId('register2-bookings-overview')).toBeVisible()

    const bookingCard = page.locator('[data-testid^="register2-booking-"]').first()
    await expect(bookingCard).toBeVisible()
    await bookingCard.locator('[data-testid^="register2-booking-delete-"]').click()

    await expect(page.getByTestId('register2-slot-selection')).toBeVisible()
})
