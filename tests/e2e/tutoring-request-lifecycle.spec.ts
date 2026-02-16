import { expect, test } from '@playwright/test'
import { loginAsTutoringUser } from './helpers/tutoring'
import { hideObstructiveUi } from './helpers/ui'

test('tutoring request lifecycle works for requester and offer owner', async ({ page }) => {
    const requestMessage = `E2E tutoring request ${Date.now()}`

    await loginAsTutoringUser(page, 'e2e.tutoring.peer@example.test')
    await page.goto('/homepage/tutoring_overview?school=E2E')
    await hideObstructiveUi(page)

    const offerCard = page.locator('[data-testid^="tutoring-offer-card-"]', { hasText: 'E2E Mathe Nachhilfe' }).first()
    await expect(offerCard).toBeVisible()
    await offerCard.click()

    await expect(page.getByTestId('tutoring-offer-detail-dialog')).toBeVisible()
    await page.getByTestId('tutoring-offer-detail-contact').click()
    await page.locator('[data-testid="tutoring-offer-detail-request-message"] textarea').fill(requestMessage)
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

    await page.getByTestId('tutoring-overview-received-requests').click()
    await expect(page.getByTestId('tutoring-received-requests-section')).toBeVisible()

    const receivedRequest = page.locator('[data-testid^="tutoring-received-request-"]', { hasText: 'TutoringPeer' }).first()
    await expect(receivedRequest).toBeVisible()
    await receivedRequest.locator('[data-testid^="tutoring-received-request-mark-done-"]').click()

    await expect(receivedRequest).toContainText('TutoringPeer')
})
