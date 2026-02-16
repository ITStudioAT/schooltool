import { expect, test } from '@playwright/test'
import { loginAsTutoringUser } from './helpers/tutoring'
import { hideObstructiveUi } from './helpers/ui'

test('tutoring user can create, edit, toggle and delete an own offer', async ({ page }) => {
    const baseTitle = `E2E Offer ${Date.now()}`
    const updatedTitle = `${baseTitle} Updated`

    await loginAsTutoringUser(page, 'e2e.tutoring@example.test')
    await hideObstructiveUi(page)

    await expect(page.getByTestId('tutoring-menu-create-offer')).toBeVisible()
    await page.getByTestId('tutoring-menu-create-offer').click()

    await expect(page.getByTestId('tutoring-offer-step-subject')).toBeVisible()
    await page.locator('[data-testid^="tutoring-offer-subject-"]').first().click()
    await page.getByTestId('tutoring-offer-next-subject').click()

    await expect(page.getByTestId('tutoring-offer-step-title')).toBeVisible()
    await page.locator('[data-testid="tutoring-offer-title"] input').fill(baseTitle)
    await page.locator('[data-testid="tutoring-offer-description"] textarea').fill('E2E-created tutoring offer description')
    await page.getByTestId('tutoring-offer-next-title').click()

    await expect(page.getByTestId('tutoring-offer-step-classes')).toBeVisible()
    await page.getByTestId('tutoring-offer-class-3').click()
    await page.getByTestId('tutoring-offer-next-classes').click()

    await expect(page.getByTestId('tutoring-offer-step-validity')).toBeVisible()
    await page.getByTestId('tutoring-offer-next-validity').click()

    await expect(page.getByTestId('tutoring-offer-step-details')).toBeVisible()
    await page.getByTestId('tutoring-offer-next-details').click()

    if (await page.getByTestId('tutoring-offer-step-mentor').count()) {
        await page.getByTestId('tutoring-offer-next-mentor').click()
    }

    await expect(page.getByTestId('tutoring-offer-step-visibility')).toBeVisible()
    await page.getByTestId('tutoring-offer-next-visibility').click()

    await expect(page.getByTestId('tutoring-offer-step-submit')).toBeVisible()
    await page.getByTestId('tutoring-offer-submit').click()

    await expect(page.getByTestId('tutoring-offer-step-success')).toBeVisible()
    await page.getByTestId('tutoring-offer-finish').click()

    const createdOffer = page.locator('[data-testid^="tutoring-my-offer-"]', { hasText: baseTitle }).first()
    await expect(createdOffer).toBeVisible()

    await createdOffer.locator('[data-testid^="tutoring-my-offer-toggle-"]').click()
    await expect(createdOffer.getByRole('button', { name: /Einschalten|Ausschalten/ })).toBeVisible()

    await createdOffer.locator('[data-testid^="tutoring-my-offer-edit-"]').click()
    await expect(page.getByTestId('tutoring-offer-step-subject')).toBeVisible()
    await page.getByTestId('tutoring-offer-next-subject').click()

    await expect(page.getByTestId('tutoring-offer-step-title')).toBeVisible()
    await page.locator('[data-testid="tutoring-offer-title"] input').fill(updatedTitle)
    await page.locator('[data-testid="tutoring-offer-description"] textarea').fill('E2E-updated tutoring offer description')
    await page.getByTestId('tutoring-offer-next-title').click()

    await expect(page.getByTestId('tutoring-offer-step-classes')).toBeVisible()
    await page.getByTestId('tutoring-offer-next-classes').click()

    await expect(page.getByTestId('tutoring-offer-step-validity')).toBeVisible()
    await page.getByTestId('tutoring-offer-next-validity').click()

    await expect(page.getByTestId('tutoring-offer-step-details')).toBeVisible()
    await page.getByTestId('tutoring-offer-next-details').click()

    if (await page.getByTestId('tutoring-offer-step-mentor').count()) {
        await page.getByTestId('tutoring-offer-next-mentor').click()
    }

    await expect(page.getByTestId('tutoring-offer-step-visibility')).toBeVisible()
    await page.getByTestId('tutoring-offer-next-visibility').click()

    await expect(page.getByTestId('tutoring-offer-step-submit')).toBeVisible()
    await page.getByTestId('tutoring-offer-submit').click()

    await expect(page.getByTestId('tutoring-offer-step-success')).toBeVisible()
    await page.getByTestId('tutoring-offer-finish').click()

    const updatedOffer = page.locator('[data-testid^="tutoring-my-offer-"]', { hasText: updatedTitle }).first()
    await expect(updatedOffer).toBeVisible()

    await updatedOffer.locator('[data-testid^="tutoring-my-offer-delete-"]').click()
    await updatedOffer.locator('[data-testid^="tutoring-my-offer-delete-confirm-"]').click()
    await expect(page.locator('[data-testid^="tutoring-my-offer-"]', { hasText: updatedTitle })).toHaveCount(0)
})
