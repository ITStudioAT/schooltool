import { expect, Page, test } from '@playwright/test'

async function loginAsE2EStudent(page: Page): Promise<void> {
    await page.goto('/homepage/student')

    const schoolCard = page.locator('.school-item', { hasText: 'E2E School' })
    if (await schoolCard.isVisible()) {
        await schoolCard.click()
    }

    await expect(page.locator('[data-testid="student-login-email"]')).toBeVisible()
    await page.locator('[data-testid="student-login-email"] input').fill('e2e.student@example.test')
    await page.getByTestId('student-login-continue-password').click()

    await expect(page.locator('[data-testid="student-login-password"]')).toBeVisible()
    await page.locator('[data-testid="student-login-password"] input').fill('password123')
    await page.getByTestId('student-login-submit-password').click()
    await expect(page).toHaveURL(/\/student\/overview$/)
}

async function openCourseTab(page: Page, tabName: 'Termine' | 'Leistungen' | 'Verhalten'): Promise<void> {
    const visibleTab = page
        .locator('.course-tabs-desktop [role="tab"]:visible, .course-tabs-mobile button:visible')
        .filter({ hasText: tabName })
        .first()

    await expect(visibleTab).toBeVisible()
    await visibleTab.click()
}

test('student course details shows free reason and no placeholder dashes', async ({ page }) => {
    await loginAsE2EStudent(page)

    await page.locator('.course-card', { hasText: 'E2E Mathematik' }).click()
    await expect(page).toHaveURL(/\/student\/course\/\d+$/)

    await openCourseTab(page, 'Termine')
    await expect(page.getByText('E2E Lehrerfortbildung')).toBeVisible()
    await expect(page.locator('text=---')).toHaveCount(0)
});

test('leistungen marks open and finished entries with correct row state', async ({ page }) => {
    await loginAsE2EStudent(page)

    await page.locator('.course-card', { hasText: 'E2E Mathematik' }).click()
    await expect(page).toHaveURL(/\/student\/course\/\d+$/)

    await openCourseTab(page, 'Leistungen')
    await page.locator('.entries-semester-filter').getByRole('button', { name: '1+2' }).click()

    const openRow = page.locator('.entry-row', { has: page.locator('.entry-grade', { hasText: 'offen' }) }).first()
    await expect(openRow).toBeVisible()
    await expect(openRow).toHaveClass(/entry-row--open/)

    const finishedRow = page.locator('.entry-row', { has: page.locator('.entry-grade', { hasText: '2' }) }).first()
    await expect(finishedRow).toBeVisible()
    await expect(finishedRow).not.toHaveClass(/entry-row--open/)
});

test('behaviour resolves tolerant short code labels to readable text', async ({ page }) => {
    await loginAsE2EStudent(page)

    const mockedCourseId = 99999
    await page.route(`**/api/homepage/student/courses/${mockedCourseId}`, async (route) => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                course: {
                    id: mockedCourseId,
                    title: 'E2E Verhalten',
                    description: null,
                    teaching_schema_id: null,
                    teacher: 'E2E Teacher',
                    teacher_email: null,
                    teacher_teaching_notifications: [],
                    teacher_teaching_behaviour: [
                        { short_name: 'LV', name: 'Laptop vergessen' },
                    ],
                    classes: [],
                    students_count: 1,
                    stars: [],
                    sem_1_grade: null,
                    sem_2_grade: null,
                    sem_grade: null,
                    behaviour_1_grade: null,
                    behaviour_2_grade: null,
                    behaviour_grade: null,
                    notifications: [],
                    behaviour_entries: [
                        { id: 1, date: '2026-02-25', description: null, type: 'L' },
                    ],
                    show_behaviour: true,
                    course_dates: [],
                    next_course_date: null,
                    active_course_end_at: null,
                },
            }),
        })
    })
    await page.route(`**/api/homepage/student/courses/${mockedCourseId}/entries`, async (route) => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                entries: [],
                type_labels: {},
            }),
        })
    })

    await page.goto(`/student/course/${mockedCourseId}`)
    await expect(page).toHaveURL(new RegExp(`/student/course/${mockedCourseId}$`))

    await openCourseTab(page, 'Verhalten')
    await expect(page.locator('.behaviour-entry-type').first()).toContainText('Laptop vergessen')
})
