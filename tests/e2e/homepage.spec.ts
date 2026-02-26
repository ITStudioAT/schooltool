import { expect, test } from '@playwright/test'

test('homepage loads', async ({ page }) => {
    await page.goto('/')
    await expect(page).toHaveURL(/\/$/)
    await expect(page.getByRole('heading', { level: 1, name: /school\s*tool/i })).toBeVisible()
    await expect(page.getByRole('heading', { name: /wählen sie ihr werkzeug/i })).toBeVisible()
})
