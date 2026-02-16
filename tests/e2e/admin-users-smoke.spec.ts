import { expect, Page, test } from '@playwright/test'
import { hideObstructiveUi } from './helpers/ui'

async function loginAsAdmin(page: Page): Promise<void> {
    await page.goto('/admin/login')
    await hideObstructiveUi(page)

    await expect(page.getByTestId('admin-login-email')).toBeVisible()
    await page.locator('[data-testid="admin-login-email"] input').fill('e2e.admin@example.test')
    await page.getByTestId('admin-login-continue-password').click()

    await expect(page.getByTestId('admin-login-password')).toBeVisible()
    await page.locator('[data-testid="admin-login-password"] input').fill('password123')
    await page.getByTestId('admin-login-submit-password').click()
    await expect(page).toHaveURL(/\/admin\/?$/)
}

test('admin users screen loads and lists seeded users', async ({ page }) => {
    await loginAsAdmin(page)

    await page.goto('/admin/users/all_users')
    await expect(page).toHaveURL(/\/admin\/users\/all_users/)
    await expect(page.getByText('Alle Benutzer')).toBeVisible()

    await expect(page.getByText('e2e.student@example.test')).toBeVisible({ timeout: 15000 })
    await expect(page.getByText('e2e.admin@example.test')).toBeVisible({ timeout: 15000 })
    await expect(page.getByText('e2e.tutoring@example.test')).toBeVisible({ timeout: 15000 })
})
