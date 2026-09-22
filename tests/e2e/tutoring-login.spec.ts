import { expect, test } from '@playwright/test'

test('removed tutoring entry pages behave like other unknown homepage routes', async ({ page }) => {
    const unknownResponse = await page.goto('/homepage/removed-module')
    await page.waitForLoadState('networkidle')
    const unknownPage = await page.locator('body').innerText()

    for (const path of ['/homepage/tutoring_overview', '/homepage/tutoring', '/homepage/tutoring_response']) {
        const response = await page.goto(path)
        await page.waitForLoadState('networkidle')

        expect(response?.status()).toBe(unknownResponse?.status())
        await expect(page.locator('[data-testid^="tutoring-"]')).toHaveCount(0)
        expect(await page.locator('body').innerText()).toBe(unknownPage)
    }
})

test('general admin login remains available', async ({ page }) => {
    await page.goto('/admin/login')

    await expect(page.getByTestId('admin-login-email')).toBeVisible()
})
