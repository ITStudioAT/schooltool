import { expect, test, type Page } from '@playwright/test'

const viewports = [
    { width: 320, height: 568 },
    { width: 360, height: 640 },
    { width: 390, height: 844 },
    { width: 768, height: 1024 },
    { width: 1280, height: 800 },
    { width: 568, height: 320 },
]

async function expectContainedLayout(page: Page): Promise<void> {
    const overflow = await page.evaluate(() => {
        const width = document.documentElement.clientWidth
        const problems = []
        if (document.documentElement.scrollWidth > width + 1) problems.push('document')

        for (const element of document.querySelectorAll('button, h1, h2, h3, h4, .v-card-title, .v-card-actions, .profile-field, .v-otp-input')) {
            const box = element.getBoundingClientRect()
            if (!box.width || !box.height || element.closest('.v-navigation-drawer:not(.v-navigation-drawer--active)')) continue
            if (element.closest('.timetable-v3-results__table-scroll')) continue
            if (box.left < -1 || box.right > width + 1) problems.push(`${element.tagName}.${element.className}: ${Math.round(box.left)}..${Math.round(box.right)}`)
        }

        for (const element of document.querySelectorAll('h1, h2, h3, h4, .v-card-title, .v-btn__content, .drawer-user-details, .overview-v2-info-value, .timetable-v3__info-value')) {
            const box = element.getBoundingClientRect()
            if (!box.width || !box.height || element.closest('.v-navigation-drawer:not(.v-navigation-drawer--active)')) continue
            if (element.scrollWidth > element.clientWidth + 1) problems.push(`clipped ${element.tagName}.${element.className}: ${element.scrollWidth}/${element.clientWidth}`)
        }

        return problems
    })
    expect(overflow).toEqual([])
}

for (const viewport of viewports) {
    test.describe(`${viewport.width}x${viewport.height}`, () => {
        test.use({ viewport })

        test.beforeEach(async ({ page }) => {
            await page.route('**/api/**', route => route.abort())
            await page.route('**/sanctum/**', route => route.abort())
        })

        for (const scenario of ['student-overview', 'student-create', 'student-results', 'student-adoption', 'student-login', 'student-profile', 'admin-selection', 'admin-modules', 'admin-creation', 'admin-adoption', 'admin-appbar', 'admin-appbar&missing=1']) {
            test(`${scenario} contains long content and controls`, async ({ page }) => {
                const errors: string[] = []
                page.on('pageerror', error => errors.push(error.message))
                await page.goto(`/?scenario=${scenario}`)
                await expect(page.locator('html')).toHaveAttribute('data-fixture-ready', 'true')
                await page.evaluate(() => document.fonts.ready)
                if (['student-overview', 'student-create', 'student-profile', 'admin-selection', 'admin-modules'].includes(scenario)) {
                    await expect(page.getByText(/MustermannmitbesonderslangemDoppelnamen/).first()).toBeVisible()
                }
                if (['student-results', 'admin-creation'].includes(scenario)) {
                    await expect(page.getByRole('heading', { name: 'Stundenplan 1 von 2' })).toBeVisible()
                }
                if (scenario.startsWith('admin-appbar')) {
                    await expect(page.getByAltText('Logo')).toBeVisible()
                    expect(await page.getByAltText('Logo').evaluate((image: HTMLImageElement) => image.naturalWidth)).toBe(600)
                }
                if (['admin-selection', 'admin-modules', 'admin-creation', 'admin-adoption'].includes(scenario)) {
                    const name = page.locator('.timetable-v3__selection-value > span').first()
                    await expect(name).toBeVisible()
                    expect((await name.boundingBox())!.width).toBeGreaterThanOrEqual(120)
                }
                await expectContainedLayout(page)
                expect(errors).toEqual([])
            })
        }

        for (const scenario of ['student-create', 'admin-modules']) {
            for (const dialog of ['module', 'info', 'study']) {
                test(`${scenario} ${dialog} dialog stays usable`, async ({ page }) => {
                    await page.goto(`/?scenario=${scenario}&dialog=${dialog}`)
                    const card = page.locator('.v-dialog .v-card')
                    await expect(card).toBeVisible()
                    if (dialog === 'module') await expect(card.getByRole('checkbox')).toHaveCount(8)
                    await expectContainedLayout(page)
                    const bounds = await card.boundingBox()
                    expect(bounds!.y).toBeGreaterThanOrEqual(-1)
                    expect(bounds!.y + bounds!.height).toBeLessThanOrEqual(viewport.height + 1)
                    const closeButton = card.getByRole('button', { name: /Bestätigen|Schließen/ }).last()
                    await expect(closeButton).toBeInViewport()
                    await closeButton.click()
                    await expect(card).toBeHidden()
                })
            }
        }

        test('student drawer contains long names and email addresses', async ({ page }) => {
            await page.goto('/?scenario=student-profile&dialog=drawer')
            await expect(page.locator('.v-navigation-drawer--active')).toBeVisible()
            await expectContainedLayout(page)
        })

        test('shared timetable scrolls within its container and navigates', async ({ page }) => {
            await page.goto('/?scenario=shared-timetable')
            await expect(page.getByRole('heading', { name: 'Stundenplan 1 von 2' })).toBeVisible()
            await expectContainedLayout(page)
            await page.getByRole('button', { name: 'Nächster Stundenplan' }).click()
            await expect(page.getByRole('heading', { name: 'Stundenplan 2 von 2' })).toBeVisible()
        })

        test('legacy saved timetable retains readable hours and local scrolling', async ({ page }) => {
            await page.goto('/?scenario=student-legacy')
            const grid = page.locator('.student-published-timetable__grid')
            await expect(grid).toBeVisible()
            await expect(page.locator('.student-published-timetable__hour span')).toHaveText('17:50 - 18:35')
            await expect(page.locator('.student-published-timetable__hour span')).toBeVisible()
            if (viewport.width <= 390) {
                const dimensions = await grid.evaluate(element => ({ width: element.clientWidth, scrollWidth: element.scrollWidth }))
                expect(dimensions.scrollWidth).toBeGreaterThan(dimensions.width)
            }
            const dimensions = await page.evaluate(() => ({ width: document.documentElement.clientWidth, scrollWidth: document.documentElement.scrollWidth }))
            expect(dimensions.scrollWidth).toBeLessThanOrEqual(dimensions.width + 1)
        })

        if (viewport.width === 320 || viewport.height === 320) {
            for (const scenario of ['admin-subjects', 'admin-tests-dialog', 'admin-import-dialog']) {
                test(`${scenario} keeps peripheral controls reachable`, async ({ page }) => {
                    await page.goto(`/?scenario=${scenario}`)
                    await expect(page.locator('html')).toHaveAttribute('data-fixture-ready', 'true')
                    await expectContainedLayout(page)
                    if (scenario.endsWith('-dialog')) {
                        const card = page.locator('.v-dialog .v-card')
                        await expect(card).toBeVisible()
                        const closeButton = card.getByRole('button', { name: /Schließen|Abbrechen/ }).last()
                        await expect(closeButton).toBeInViewport()
                        await closeButton.click()
                        await expect(card).toBeHidden()
                    } else {
                        await expect(page.getByRole('button', { name: 'Kompaktstudium', exact: true })).toBeVisible()
                        await page.getByRole('button', { name: 'Kompaktstudium', exact: true }).click()
                        await expectContainedLayout(page)
                    }
                })
            }
        }
    })
}
