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

async function waitForNoBlockingOverlay(page: Page): Promise<void> {
    await page.waitForFunction(() => {
        const activeScrim = document.querySelector('.v-overlay.v-overlay--active .v-overlay__scrim')
        return !activeScrim
    })
}

test('admin teaching page supports overview navigation without js runtime errors', async ({ page }) => {
    const runtimeErrors: string[] = []

    page.on('pageerror', (error) => {
        runtimeErrors.push(`pageerror: ${error.message}`)
    })

    page.on('console', (message) => {
        if (message.type() !== 'error') {
            return
        }

        const text = message.text()
        if (text.includes('Failed to load resource')) {
            return
        }

        runtimeErrors.push(`console: ${text}`)
    })

    await loginAsAdmin(page)

    await page.goto('/admin/teaching')
    await hideObstructiveUi(page)

    await expect(page).toHaveURL(/\/admin\/teaching/)
    await expect(page.getByText('Lehrbereich und Kurssteuerung')).toBeVisible()
    await expect(page.getByRole('button', { name: /Übersicht/ })).toBeVisible()
    await expect(page.getByRole('button', { name: /Einstellungen/ })).toBeVisible()
    await expect(page.getByRole('button', { name: /Suche/ })).toBeVisible()
    await expect(page.getByRole('button', { name: /Mehr/ })).toHaveCount(0)

    await page.getByRole('button', { name: /Suche/ }).click()
    await expect(page.locator('.its-grid-box__title-text', { hasText: 'Personen & Klassen' }).first()).toBeVisible()
    await expect(page.locator('.its-grid-box__title-text', { hasText: 'Trefferliste' }).first()).toBeVisible()

    await page.getByRole('button', { name: /Übersicht/ }).click()
    await expect(page.locator('.teaching-overview-panel-switcher')).toBeVisible()
    await expect(page.getByRole('button', { name: /Meine Fächer/ })).toBeVisible()
    await expect(page.getByTestId('teaching-overview-panel-more')).toHaveCount(0)

    const firstCourseChip = page.locator('.v-chip-group .v-chip').first()
    await expect(firstCourseChip).toBeVisible()
    await waitForNoBlockingOverlay(page)
    await firstCourseChip.click()

    await expect(page.getByTestId('teaching-overview-panel-more')).toBeVisible()
    await waitForNoBlockingOverlay(page)
    await page.getByTestId('teaching-overview-panel-more').click()
    await expect(page.getByTestId('teaching-more-card')).toBeVisible()
    await expect(page.getByTestId('teaching-attendance-matrix')).toHaveCount(0)
    await expect(page.getByTestId('teaching-more-menu-dummy_1')).toBeVisible()
    await expect(page.getByTestId('teaching-more-menu-dummy_2')).toBeVisible()
    await expect(page.getByTestId('teaching-more-menu-dummy_3')).toHaveCount(0)
    await expect(page.getByTestId('teaching-more-content')).toContainText('Bitte einen Bereich auswählen.')

    await expect(page.getByTestId('teaching-more-col')).not.toHaveClass(/teaching-more-col--full/)
    await page.getByTestId('teaching-more-menu-dummy_1').click()
    await expect(page.getByTestId('teaching-attendance-matrix')).toBeVisible()
    await expect(page.getByTestId('teaching-attendance-table')).toBeVisible()
    await expect(page.getByTestId('teaching-attendance-percent-header')).toBeVisible()
    await expect(page.getByTestId('teaching-more-col')).toHaveClass(/teaching-more-col--full/)

    await page.getByTestId('teaching-more-menu-dummy_2').click()
    await expect(page.getByTestId('teaching-performances-card')).toBeVisible()
    await expect(page.getByTestId('teaching-performances-table')).toBeVisible()
    await expect(page.getByTestId('teaching-attendance-matrix')).toHaveCount(0)
    await expect(page.getByTestId('teaching-more-col')).toHaveClass(/teaching-more-col--full/)

    await page.getByTestId('teaching-overview-panel-more').click()
    await expect(page.getByTestId('teaching-more-card')).toHaveCount(0)
    await expect(page.getByTestId('teaching-overview-panel-my_courses')).not.toHaveClass(/v-btn--active/)
    await expect(page.getByTestId('teaching-overview-panel-students')).toHaveClass(/v-btn--active/)
    await expect(page.getByTestId('teaching-overview-panel-infos')).toHaveClass(/v-btn--active/)
    await expect(page.getByTestId('teaching-overview-panel-works')).toHaveClass(/v-btn--active/)
    await expect(page.getByTestId('teaching-overview-panel-dates')).toHaveClass(/v-btn--active/)

    await expect(page.locator('.teaching-overview-panel-switcher .v-btn--active')).toHaveCount(4)

    expect(runtimeErrors, runtimeErrors.join('\n')).toEqual([])
})

test('shows generated random group cards without clipping them', async ({ page }) => {
    await loginAsAdmin(page)

    await page.goto('/admin/teaching')
    await hideObstructiveUi(page)

    const firstCourseChip = page.locator('.v-chip-group .v-chip').first()
    await expect(firstCourseChip).toBeVisible()
    await waitForNoBlockingOverlay(page)
    await firstCourseChip.click()

    await expect(page.getByTestId('teaching-overview-panel-table')).toBeVisible()
    await page.getByTestId('teaching-overview-panel-table').click()
    await expect(page.getByTestId('course-table')).toBeVisible()

    await page.getByRole('tab', { name: 'Einträge' }).click()
    const availableWorkCell = page.locator('.course-table-work-cell:not(.course-table-work-cell--free)')
    await expect(availableWorkCell).toHaveCount(1)
    await availableWorkCell.click()

    await page.getByTestId('course-table-date-create-work').click()
    await page.getByRole('button', { name: 'TW - Testarbeit', exact: true }).click()
    await page.getByTestId('course-table-date-create-work-mode')
        .getByRole('button', { name: 'Gruppenarbeit', exact: true })
        .click()
    await page.getByRole('tab', { name: 'Gruppen', exact: true }).click()
    await page.getByTestId('course-table-date-random-groups').click()
    await page.getByRole('button', { name: 'Erstellen', exact: true }).click()

    const groupsPanel = page.getByTestId('course-table-work-groups-panel')
    const groupCard = page.getByTestId('course-table-work-group-card-0')
    await expect(groupsPanel).toBeVisible()
    await expect(groupCard).toBeVisible()
    await expect(groupCard).toContainText('Gruppe 1')
    await expect(groupCard).toContainText('2 Mitglieder')
    await expect(groupCard).toContainText('Student')
    await expect(groupCard).toContainText('Peer')

    const isGroupCardPainted = await groupCard.evaluate((card) => {
        const cardRectangle = card.getBoundingClientRect()
        const paintedElement = document.elementFromPoint(
            cardRectangle.left + Math.min(12, cardRectangle.width / 2),
            cardRectangle.top + Math.min(12, cardRectangle.height / 2),
        )

        return paintedElement === card || card.contains(paintedElement)
    })

    expect(isGroupCardPainted).toBe(true)
})
