import { expect, test } from '@playwright/test'
import { loginAsStudentWithPassword } from './helpers/student'

test('student profile shows seeded personal data', async ({ page }) => {
    await loginAsStudentWithPassword(page)

    await page.getByTestId('student-overview-open-menu').click()
    await page.getByTestId('student-drawer-profile').click()

    await expect(page).toHaveURL(/\/student\/profile$/)
    await expect(page.getByTestId('student-profile-page')).toBeVisible()
    await expect(page.getByTestId('student-profile-first-name')).toHaveText('E2E')
    await expect(page.getByTestId('student-profile-last-name')).toHaveText('Student')
    await expect(page.getByTestId('student-profile-email')).toContainText('e2e.student@example.test')
})
