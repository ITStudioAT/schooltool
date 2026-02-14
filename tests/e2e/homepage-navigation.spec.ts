import { expect, test } from '@playwright/test'
import { hideObstructiveUi } from './helpers/ui'

test('homepage footer can navigate to impressum and back to start page', async ({ page }) => {
    await page.goto('/')
    await hideObstructiveUi(page)

    await page.locator('footer').getByText('Impressum', { exact: true }).click()
    await expect(page).toHaveURL(/\/homepage\/impressum$/)
    await expect(page.locator('.v-card-title', { hasText: 'Impressum' })).toBeVisible()

    await page.getByRole('button', { name: 'Startseite' }).click()
    await expect(page).toHaveURL(/\/$/)
    await expect(page.getByRole('heading', { name: 'SchoolTool' })).toBeVisible()
})
