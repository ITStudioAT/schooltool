import { expect, test } from '@playwright/test'

test('unauthenticated user visiting /student/overview is redirected to /student login', async ({ page }) => {
    await page.goto('/student/overview')

    await expect(page).toHaveURL(/\/student$/)
    await expect(page.locator('[data-testid="student-login-email"]')).toBeVisible()
})

test('unauthenticated user visiting /student/profile is redirected to /student login', async ({ page }) => {
    await page.goto('/student/profile')

    await expect(page).toHaveURL(/\/student$/)
    await expect(page.locator('[data-testid="student-login-email"]')).toBeVisible()
})

test('unauthenticated user visiting /student/course/:id is redirected to /student login', async ({ page }) => {
    await page.goto('/student/course/1')

    await expect(page).toHaveURL(/\/student$/)
    await expect(page.locator('[data-testid="student-login-email"]')).toBeVisible()
})
