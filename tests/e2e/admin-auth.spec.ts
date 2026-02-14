import { expect, Page, test } from '@playwright/test'
import { hideObstructiveUi } from './helpers/ui'

async function loginAsE2EAdmin(page: Page): Promise<void> {
    await page.goto('/admin/login')
    await hideObstructiveUi(page)

    await expect(page.locator('[data-testid="admin-login-email"]')).toBeVisible()
    await page.locator('[data-testid="admin-login-email"] input').fill('e2e.admin@example.test')
    await page.getByTestId('admin-login-continue-password').click()

    await expect(page.locator('[data-testid="admin-login-password"]')).toBeVisible()
    await page.locator('[data-testid="admin-login-password"] input').fill('password123')
    await page.getByTestId('admin-login-submit-password').click()
}

test('unauthenticated user opening /admin is redirected to /admin/login', async ({ page }) => {
    await page.goto('/admin')
    await hideObstructiveUi(page)

    await expect(page).toHaveURL(/\/admin\/login$/)
    await expect(page.locator('[data-testid="admin-login-email"]')).toBeVisible()
})

test('admin login with wrong password stays on login page', async ({ page }) => {
    await page.goto('/admin/login')
    await hideObstructiveUi(page)

    await expect(page.locator('[data-testid="admin-login-email"]')).toBeVisible()
    await page.locator('[data-testid="admin-login-email"] input').fill('e2e.admin@example.test')
    await page.getByTestId('admin-login-continue-password').click()

    await expect(page.locator('[data-testid="admin-login-password"]')).toBeVisible()
    await page.locator('[data-testid="admin-login-password"] input').fill('wrong-password')
    await page.getByTestId('admin-login-submit-password').click()

    await expect(page).toHaveURL(/\/admin\/login$/)
    await expect(page.locator('[data-testid="admin-login-password"]')).toBeVisible()
})

test('admin can logout and protected route requires login again', async ({ page }) => {
    await loginAsE2EAdmin(page)
    await hideObstructiveUi(page)

    await expect(page).toHaveURL(/\/admin\/?$/)
    const logoutItem = page.locator('.v-navigation-drawer .v-list-item', { hasText: 'Abmelden' }).first()
    await expect(logoutItem).toBeVisible()
    await logoutItem.click()

    await expect(page).toHaveURL(/\/admin\/login$/)
    await page.goto('/admin')
    await expect(page).toHaveURL(/\/admin\/login$/)
})

test('admin login page can navigate to unknown-password flow and back', async ({ page }) => {
    await page.goto('/admin/login')
    await hideObstructiveUi(page)

    await page.getByTestId('admin-login-unknown-password').click()
    await expect(page).toHaveURL(/\/admin\/unknown_password$/)
    await expect(page.getByText('Kennwort unbekannt')).toBeVisible()

    await page.getByRole('button', { name: 'Login' }).click()
    await expect(page).toHaveURL(/\/admin\/login$/)
})
