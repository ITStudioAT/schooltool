import { expect, test } from '@playwright/test'
import { loginAsTutoringUser } from './helpers/tutoring'
import { hideObstructiveUi } from './helpers/ui'

test('tutoring request lifecycle works for requester and offer owner', async ({ page }) => {
    test.setTimeout(60_000)
    const requestMessage = `E2E tutoring request ${Date.now()}`
    await page.emulateMedia({ reducedMotion: 'reduce' })

    await loginAsTutoringUser(page, 'e2e.tutoring.peer@example.test')
    await page.goto('/homepage/tutoring_overview?school=E2E')
    await hideObstructiveUi(page)

    const offerCard = page.locator('[data-testid^="tutoring-offer-card-"]', { hasText: 'E2E Mathe Nachhilfe' }).first()
    await expect(offerCard).toBeVisible()
    await offerCard.click()

    await expect(page.getByTestId('tutoring-offer-detail-dialog')).toBeVisible()
    await page.getByTestId('tutoring-offer-detail-contact').click()

    const requestMessageField = page.getByTestId('tutoring-offer-detail-request-message')
    if (await requestMessageField.count()) {
        await requestMessageField.locator('textarea').fill(requestMessage)
    }

    await page.getByTestId('tutoring-offer-detail-serious-request').click()
    await page.getByTestId('tutoring-offer-detail-send-request').click()

    await expect(
        page.getByTestId('tutoring-offer-detail-request-success').or(page.getByTestId('tutoring-offer-detail-request-existing')),
    ).toBeVisible()
    await page.getByTestId('tutoring-offer-detail-request-finished').click()

    await page.getByTestId('tutoring-overview-my-requests').click()
    await expect(page.getByTestId('tutoring-my-requests-section')).toBeVisible()
    await expect(page.locator('[data-testid^="tutoring-my-request-"]', { hasText: 'E2E Mathe Nachhilfe' }).first()).toBeVisible()

    await page.getByTestId('tutoring-overview-my-requests-back').click()
    await page.getByTestId('tutoring-overview-logout').click()

    await expect(page.getByTestId('tutoring-overview-start-login')).toBeVisible()

    await loginAsTutoringUser(page, 'e2e.tutoring@example.test')
    await page.goto('/homepage/tutoring_overview?school=E2E')
    await hideObstructiveUi(page)

    const receivedRequestsCard = page.getByTestId('tutoring-overview-received-requests')
    const startLoginCard = page.getByTestId('tutoring-overview-start-login')
    await expect(receivedRequestsCard.or(startLoginCard)).toBeVisible()
    if (await startLoginCard.isVisible()) {
        await loginAsTutoringUser(page, 'e2e.tutoring@example.test')
        await page.goto('/homepage/tutoring_overview?school=E2E')
        await hideObstructiveUi(page)
        await expect(receivedRequestsCard).toBeVisible()
    }

    await receivedRequestsCard.click()
    await expect(page.getByTestId('tutoring-received-requests-section')).toBeVisible({ timeout: 15_000 })

    const receivedRequest = page.locator('[data-testid^="tutoring-received-request-"]', { hasText: 'TutoringPeer' }).first()
    await expect(receivedRequest).toBeVisible()
    const markDoneButton = receivedRequest.locator('[data-testid^="tutoring-received-request-mark-done-"]')
    await expect(markDoneButton).toBeVisible()
    await markDoneButton.evaluate((el) => el.scrollIntoView({ block: 'center', behavior: 'instant' }))
    await markDoneButton.click()

    await expect(receivedRequest).toContainText('TutoringPeer')
})
