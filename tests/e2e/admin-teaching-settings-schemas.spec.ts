import { expect, Page, test } from '@playwright/test'
import { hideObstructiveUi } from './helpers/ui'

type TeachingSchema = {
    id: string
    name: string
    works: Array<{ short_name: string; name: string }>
    grading: Record<string, unknown>
}

type TeachingSettingsPayload = {
    teaching_schemas: TeachingSchema[]
    teaching_behaviour: Array<{ short_name: string; name: string }>
    teaching_notifications: Array<{ short_name: string; name: string }>
}

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

test('teaching settings supports schema create rename and guarded delete conflict flow', async ({ page }) => {
    const runtimeErrors: string[] = []
    const inUseSchemaId = 'schema-in-use'

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

    let currentSettings: TeachingSettingsPayload = {
        teaching_schemas: [
            {
                id: inUseSchemaId,
                name: 'In Use Schema',
                works: [{ short_name: 'TW', name: 'Testarbeit' }],
                grading: { semester_count: 2 },
            },
            {
                id: 'schema-free',
                name: 'Schema Frei',
                works: [{ short_name: 'MA', name: 'Mitarbeit' }],
                grading: { semester_count: 2 },
            },
        ],
        teaching_behaviour: [],
        teaching_notifications: [],
    }

    let deleteConflictObserved = false

    await page.route('**/api/admin/teaching/load_settings**', async (route) => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ settings: currentSettings }),
        })
    })

    await page.route('**/api/admin/teaching/courses**', async (route) => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                data: [
                    {
                        id: 91,
                        title: 'Schema Abhängigkeit',
                        classes: ['1A'],
                        teaching_schema_id: inUseSchemaId,
                        students: [],
                        students_deleted: [],
                    },
                ],
                classes: ['1A'],
            }),
        })
    })

    await page.route('**/api/admin/teaching/save_settings**', async (route) => {
        const body = route.request().postDataJSON() as { teaching_schemas?: TeachingSchema[] }
        const submittedSchemas = body?.teaching_schemas ?? []
        const isDeleteAttempt = submittedSchemas.length < currentSettings.teaching_schemas.length

        if (isDeleteAttempt) {
            deleteConflictObserved = true
            await route.fulfill({
                status: 409,
                contentType: 'application/json',
                body: JSON.stringify({
                    message: 'Schema wird in Fächern verwendet und kann nicht gelöscht werden: In Use Schema',
                }),
            })
            return
        }

        currentSettings = {
            ...currentSettings,
            teaching_schemas: submittedSchemas,
        }

        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                settings: currentSettings,
            }),
        })
    })

    await page.goto('/admin/teaching')
    await hideObstructiveUi(page)

    await expect(page.getByRole('button', { name: /Einstellungen/ })).toBeVisible()
    await page.getByRole('button', { name: /Einstellungen/ }).click()

    await expect(page.getByRole('button', { name: 'Benotungsschemas' })).toBeVisible()
    await page.getByRole('button', { name: 'Benotungsschemas' }).click()

    const inUseChip = page.locator('.v-chip', { hasText: 'In Use Schema' }).first()
    await inUseChip.click()
    await expect(page.getByRole('button', { name: 'Löschen' })).toHaveCount(0)

    await page.locator('button:has(.mdi-plus)').first().click()
    const newSchemaChip = page.locator('.v-chip', { hasText: 'Neues Schema' }).first()
    await expect(newSchemaChip).toBeVisible()
    await newSchemaChip.click()

    await page.getByRole('button', { name: 'Umbenennen' }).click()
    await page.getByLabel('Name').fill('Schema Umbenannt E2E')
    await page.getByLabel('Name').press('Enter')

    const renamedChip = page.locator('.v-chip', { hasText: 'Schema Umbenannt E2E' }).first()
    await expect(renamedChip).toBeVisible()
    await renamedChip.click()

    await page.getByRole('button', { name: 'Löschen' }).click()
    await page.getByRole('button', { name: 'Endgültig löschen' }).click()
    await expect(page.getByText(/Schema wird in Fächern verwendet/)).toBeVisible()

    expect(deleteConflictObserved).toBe(true)
    expect(runtimeErrors, runtimeErrors.join('\n')).toEqual([])
})
