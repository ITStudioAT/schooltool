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
            if (element.closest('.entry-tabs .v-slide-group__content')) continue
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

        test('teaching semester inputs fit and toggle correctly', async ({ page }) => {
            test.setTimeout(60_000)
            await page.goto('/?scenario=admin-teaching-semesters')
            await expect(page.locator('html')).toHaveAttribute('data-fixture-ready', 'true')
            const settings = page.getByRole('region', { name: 'Semester-Einstellungen' })
            await expect(settings.getByLabel('1. Semester', { exact: true })).toHaveCount(0)
            await settings.getByRole('button', { name: '2 Semester', exact: true }).click()
            await settings.getByLabel('2. Semester', { exact: true }).fill('60')
            await expect(settings.getByLabel('1. Semester', { exact: true })).toHaveValue('40')
            await expect(settings.getByLabel('1. Semester', { exact: true })).toHaveAttribute('readonly', '')
            for (const field of await settings.locator('.semester-percentage').all()) {
                const input = await field.locator('input').boundingBox()
                const suffix = await field.locator('.v-text-field__suffix').boundingBox()
                expect(suffix!.x - (input!.x + input!.width)).toBeGreaterThanOrEqual(0)
                expect(suffix!.x - (input!.x + input!.width)).toBeLessThanOrEqual(8)
            }
            await expect(settings.getByRole('button', { name: 'Speichern', exact: true })).toBeEnabled()
            await expectContainedLayout(page)
            await expect(page.getByRole('button', { name: 'Typewriter bearbeiten', exact: true })).toBeDisabled()
            await settings.getByRole('button', { name: 'Abbrechen', exact: true }).click()
            await expect(page.getByRole('button', { name: 'Typewriter bearbeiten', exact: true })).toBeEnabled()
            await page.getByTitle('Benotungsteil bearbeiten', { exact: true }).first().click()
            const gradingDialog = page.getByRole('dialog').filter({ has: page.getByLabel('Name des Benotungsteils', { exact: true }) })
            await expect(gradingDialog.getByLabel('Gewichtung', { exact: true })).toHaveValue('1')
            await expect(gradingDialog.getByRole('button', { name: 'Optional', exact: true })).toHaveAttribute('aria-pressed', 'true')
            await gradingDialog.getByRole('button', { name: 'Verpflichtend', exact: true }).click()
            await expect(gradingDialog.getByRole('button', { name: 'Verpflichtend', exact: true })).toHaveAttribute('aria-pressed', 'true')
            await expect(gradingDialog).toContainText('Fehlend bedeutet offen')
            await gradingDialog.getByRole('button', { name: 'Optional', exact: true }).click()
            await expect(gradingDialog.getByRole('button', { name: 'Optional', exact: true })).toHaveAttribute('aria-pressed', 'true')
            await gradingDialog.getByLabel('Gewichtung', { exact: true }).fill('6')
            await expect(gradingDialog.getByRole('button', { name: 'Speichern', exact: true })).toBeEnabled()
            await gradingDialog.getByRole('button', { name: 'Fester Prozentanteil', exact: true }).click()
            await expect(gradingDialog.getByLabel('Gewichtung', { exact: true })).toHaveCount(0)
            await gradingDialog.getByLabel('Fester Anteil', { exact: true }).fill('30')
            await expect(gradingDialog.getByRole('button', { name: 'Speichern', exact: true })).toBeEnabled()
            await expectContainedLayout(page)
            await gradingDialog.getByLabel('Fester Anteil', { exact: true }).fill('101')
            await expect(gradingDialog.getByRole('button', { name: 'Speichern', exact: true })).toBeDisabled()
            await gradingDialog.getByRole('button', { name: 'Gewichtung', exact: true }).click()
            await expect(gradingDialog.getByLabel('Gewichtung', { exact: true })).toHaveValue('6')
            await gradingDialog.getByLabel('Gewichtung', { exact: true }).fill('')
            await expect(gradingDialog.getByRole('button', { name: 'Speichern', exact: true })).toBeDisabled()
            await expectContainedLayout(page)
            await gradingDialog.getByRole('button', { name: 'Abbrechen', exact: true }).click()
            for (const name of ['Auftrag', 'Mitarbeit', 'Typewriter', 'Prüfung']) {
                if (name === 'Typewriter') {
                    const values = page.getByRole('button', { name: 'Typewriter bearbeiten', exact: true }).locator('.calculation-entry-value')
                    await expect(values).toHaveCount(3)
                    await expect(values.locator('.calculation-property-name')).toHaveText(['erledigt', 'nicht erledigt', 'erledigt und gefehlt'])
                    await expect(values.locator('.calculation-evaluation')).toHaveText(['+1', '-1', '0'])
                    for (const value of await values.all()) await expect(value).toHaveCSS('font-weight', '400')
                }
                await page.getByRole('button', { name: `${name} bearbeiten`, exact: true }).click()
                const dialog = page.getByRole('dialog', { name: `${name} bearbeiten` })
                await expect(dialog).toBeVisible()
                if (name === 'Typewriter') {
                    await expect(dialog.getByRole('combobox')).toHaveCount(0)
                    const choice = dialog.locator('fieldset').first()
                    await expect(dialog.getByRole('button', { name: /^[-+]?1$|^0$/ })).toHaveCount(0)
                    await choice.getByRole('spinbutton').fill('-1')
                    await expect(choice.getByRole('spinbutton')).toHaveValue('-1')
                    await choice.getByRole('button', { name: 'Nicht berücksichtigen', exact: true }).click()
                    await expect(choice.getByRole('button', { name: 'Nicht berücksichtigen', exact: true })).toHaveClass(/bg-primary/)
                    await expect(choice.getByRole('spinbutton')).toBeEnabled()
                    await choice.getByRole('spinbutton').fill('0')
                    await expect(choice.getByRole('spinbutton')).toHaveValue('0')
                    await expect(choice.getByRole('button', { name: 'Nicht berücksichtigen', exact: true })).toHaveAttribute('aria-pressed', 'false')
                    await expect(choice.getByRole('button', { name: 'Nicht berücksichtigen', exact: true })).not.toHaveClass(/bg-primary/)
                    const absentChoice = dialog.locator('fieldset').last()
                    await absentChoice.getByRole('spinbutton').fill('2')
                    await expect(absentChoice.getByRole('spinbutton')).toHaveValue('2')
                    await absentChoice.getByRole('spinbutton').fill('')
                    await expect(absentChoice.getByRole('spinbutton')).toHaveValue('')
                    await expect(dialog.getByRole('button', { name: 'Speichern', exact: true })).toBeEnabled()
                } else if (name === 'Auftrag') {
                    await dialog.getByRole('button', { name: 'Eingabe hinzufügen', exact: true }).click()
                    await dialog.getByLabel('Mögliche Eingabe', { exact: true }).fill('++++')
                    await dialog.getByRole('spinbutton').fill('4')
                    const row = dialog.locator('.calculation-score-row--free')
                    const positions = await row.locator(':scope > *').evaluateAll(elements => elements.map(element => element.getBoundingClientRect().top))
                    expect(Math.max(...positions) - Math.min(...positions)).toBeLessThan(2)
                    await expect(dialog.getByRole('button', { name: 'Speichern', exact: true })).toBeEnabled()
                    await dialog.getByRole('button', { name: 'Nicht berücksichtigen', exact: true }).click()
                    await expect(dialog.getByRole('button', { name: 'Speichern', exact: true })).toBeEnabled()
                    await row.getByRole('button', { name: 'Eingabe entfernen', exact: true }).click()
                    await expect(dialog.locator('.calculation-score-row--free')).toHaveCount(0)
                    await dialog.getByRole('button', { name: 'Standard +/−', exact: true }).click()
                    await expect(dialog.getByRole('button', { name: 'Standard +/−', exact: true })).toHaveAttribute('aria-pressed', 'true')
                    await expect(dialog.locator('.calculation-standard-preview')).toContainText('Jedes + zählt +1')
                    await dialog.getByRole('button', { name: 'Zusatzzeichen hinzufügen', exact: true }).click()
                    await dialog.getByLabel('Mögliche Eingabe', { exact: true }).fill('F')
                    await expect(dialog.getByRole('button', { name: 'Speichern', exact: true })).toBeDisabled()
                    await dialog.getByRole('button', { name: 'Nicht berücksichtigen', exact: true }).click()
                    await expect(dialog.getByRole('button', { name: 'Speichern', exact: true })).toBeEnabled()
                } else if (name === 'Prüfung') {
                    await dialog.getByRole('button', { name: 'Standard Noten', exact: true }).click()
                    await expect(dialog.getByRole('button', { name: 'Standard Noten', exact: true })).toHaveAttribute('aria-pressed', 'true')
                    await expect(dialog.locator('fieldset')).toHaveCount(0)
                    await dialog.getByRole('button', { name: 'Weitere Zeichen zuordnen', exact: true }).click()
                    await expect(dialog.locator('fieldset')).toHaveCount(1)
                    await expect(dialog.locator('legend')).toHaveText('F')
                    await expect(dialog.getByRole('spinbutton')).toHaveValue('')
                } else {
                    await expect(dialog.locator('input')).toHaveCount(0)
                }
                await page.keyboard.press('Escape')
                await expect(dialog).toBeVisible()
                await page.mouse.click(2, 2)
                await expect(dialog).toBeVisible()
                await dialog.getByRole('button', { name: 'Schließen', exact: true }).click()
                await expect(dialog).not.toBeVisible()
            }
            await page.getByRole('button', { name: 'Zuordnung', exact: true }).click()
            await expect(settings).not.toBeVisible()
            await expect(page.getByRole('button', { name: 'Typewriter bearbeiten', exact: true })).toHaveCount(0)
            await expect(page.getByRole('tablist')).not.toBeVisible()
            await page.getByRole('button', { name: 'Abbrechen', exact: true }).click()
            await expect(settings).toBeVisible()
            await settings.getByRole('button', { name: '2 Semester', exact: true }).click()
            await settings.getByLabel('2. Semester', { exact: true }).fill('')
            await expect(settings.getByRole('button', { name: 'Speichern', exact: true })).toBeDisabled()
            await settings.getByRole('button', { name: '1 Semester', exact: true }).click()
            await expect(settings.getByLabel('2. Semester', { exact: true })).toHaveCount(0)
            await expect(settings.getByRole('button', { name: 'Speichern', exact: true })).toBeEnabled()
            await expectContainedLayout(page)
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
