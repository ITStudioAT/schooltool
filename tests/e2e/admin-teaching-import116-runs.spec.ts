import { expect, Page, test } from '@playwright/test'
import { hideObstructiveUi } from './helpers/ui'

type RunRow = {
    id: number
    status: string
    started_at: string
    finished_at: string
    undone_at: string | null
    source_name: string
    counts: {
        inserted: number
        updated: number
        deleted: number
        unchanged: number
        changes_total: number
        processed_rows: number
        seen_students: number
    }
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

test('admin import116 run management supports details, reset and delete flow', async ({ page }) => {
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

    let runs: RunRow[] = [
        {
            id: 301,
            status: 'completed',
            started_at: '2026-03-01T08:00:00.000Z',
            finished_at: '2026-03-01T08:01:00.000Z',
            undone_at: null,
            source_name: 'import-301.xlsx',
            counts: {
                inserted: 2,
                updated: 1,
                deleted: 0,
                unchanged: 0,
                changes_total: 3,
                processed_rows: 3,
                seen_students: 3,
            },
        },
        {
            id: 300,
            status: 'completed',
            started_at: '2026-02-28T08:00:00.000Z',
            finished_at: '2026-02-28T08:01:00.000Z',
            undone_at: null,
            source_name: 'import-300.xlsx',
            counts: {
                inserted: 1,
                updated: 0,
                deleted: 1,
                unchanged: 0,
                changes_total: 2,
                processed_rows: 2,
                seen_students: 2,
            },
        },
    ]

    const detailsByRunId: Record<number, unknown> = {
        301: {
            run: { id: 301 },
            changes: {
                inserted: [{ id: 1, name: 'Alpha Alice', student_code: 'S-301-A', class: '5A' }],
                updated: [],
                deleted: [],
            },
        },
        300: {
            run: { id: 300 },
            changes: {
                inserted: [],
                updated: [{ id: 2, name: 'Bravo Bob', student_code: 'S-300-B', class: '5B' }],
                deleted: [],
            },
        },
    }

    let resetCalls = 0
    let deleteCalls = 0

    await page.route('**/api/admin/teaching/import116/runs/reset', async (route) => {
        const method = route.request().method()
        if (method !== 'POST') {
            await route.fallback()
            return
        }

        resetCalls += 1
        const body = route.request().postDataJSON() as { target_import_id?: number }
        const targetImportId = Number(body?.target_import_id || 0)
        runs = runs.map((run) => (run.id === targetImportId ? { ...run, status: 'undone', undone_at: '2026-03-02T09:00:00.000Z' } : run))

        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                message: '1 Import wurde zurückgesetzt.',
                result: {
                    requested_count: 1,
                    reset_count: 1,
                    runs: [{ id: targetImportId }],
                },
            }),
        })
    })

    await page.route('**/api/admin/teaching/import116/runs/*', async (route) => {
        const method = route.request().method()
        const id = Number(route.request().url().split('/').pop())

        if (method === 'GET') {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify(detailsByRunId[id] ?? { run: { id }, changes: { inserted: [], updated: [], deleted: [] } }),
            })
            return
        }

        if (method === 'DELETE') {
            deleteCalls += 1
            runs = runs.filter((run) => run.id !== id)

            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ message: `Import #${id} wurde gelöscht.` }),
            })
            return
        }

        await route.fallback()
    })

    await page.route('**/api/admin/teaching/import116/runs', async (route) => {
        const method = route.request().method()

        if (method === 'GET') {
            const availableResetRuns = runs.filter((run) => run.status === 'completed' && !run.undone_at).length
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    data: runs,
                    meta: {
                        history_limit: 10,
                        reset_max_runs: 3,
                        available_reset_runs: availableResetRuns,
                    },
                }),
            })
            return
        }

        await route.fallback()
    })

    await loginAsAdmin(page)

    await page.goto('/admin/teaching')
    await hideObstructiveUi(page)

    await page.getByRole('button', { name: /Admin/ }).click()
    await expect(page.getByText('Import Sokrates 116')).toBeVisible()
    await expect(page.getByText('Import #301')).toBeVisible()
    await expect(page.getByText('Import #300')).toBeVisible()

    const run301Card = page.locator('.import116-run-card', { hasText: 'Import #301' }).first()
    await run301Card.getByRole('button', { name: 'Details anzeigen' }).click()
    await run301Card.getByRole('button', { name: 'Öffnen' }).first().click()
    await expect(run301Card.getByText('Alpha Alice')).toBeVisible()
    await expect(run301Card.getByText('S-301-A')).toBeVisible()

    await run301Card.click()
    await page.getByRole('button', { name: 'Import zurücksetzen' }).click()
    await expect(page.getByText('1 Import wurde zurückgesetzt.')).toBeVisible()

    await page.locator('.import116-run-card', { hasText: 'Import #300' }).first().getByRole('button', { name: 'Import löschen' }).click()
    await expect(page.getByText('Import #300 wurde gelöscht.')).toBeVisible()
    await expect(page.locator('.import116-run-card', { hasText: 'Import #300' })).toHaveCount(0)

    expect(resetCalls).toBe(1)
    expect(deleteCalls).toBe(1)
    expect(runtimeErrors, runtimeErrors.join('\n')).toEqual([])
})
