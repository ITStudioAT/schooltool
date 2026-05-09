import { expect, Page, test } from '@playwright/test'
import { hideObstructiveUi } from './helpers/ui'

type BackupRow = {
    id: number
    school_id: number
    schoolyear_id: number
    schoolyear_name: string
    user_id: number
    filename: string
    summary: {
        validation: {
            status: string
            issues: string[]
            warnings: string[]
        }
        total_rows: number
        file_count: number
        missing_file_count: number
        table_counts: Record<string, number>
    }
    download_url: string
    created_at: string
}

type RestoreRunRow = {
    id: number
    type: string
    status: string
    backup_filename: string
    pre_restore_backup_filename: string | null
    created_at: string
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

test('admin can preview, partially restore, inspect and delete a teaching backup', async ({ page }) => {
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

    let restoreCalls = 0
    let deleteCalls = 0
    let backups: BackupRow[] = [
        {
            id: 501,
            school_id: 1,
            schoolyear_id: 1,
            schoolyear_name: '2025/2026',
            user_id: 1,
            filename: 'e2e-teaching-backup.json',
            summary: {
                validation: {
                    status: 'valid',
                    issues: [],
                    warnings: [],
                },
                total_rows: 3,
                file_count: 0,
                missing_file_count: 0,
                table_counts: {
                    teaching_courses: 1,
                },
            },
            download_url: '/api/admin/teaching/backups/501/download',
            created_at: '2026-05-09T19:00:00.000Z',
        },
    ]
    let restoreRuns: RestoreRunRow[] = []

    await page.route('**/api/admin/teaching/backups/501/preview', async (route) => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                data: {
                    validation: {
                        status: 'valid',
                        issues: [],
                        warnings: [],
                    },
                    totals: {
                        rows: 3,
                        files: 0,
                        missing_files: 0,
                    },
                    settings: {},
                    setting_sections: [],
                    courses: [
                        {
                            id: 601,
                            title: 'E2E Backup Kurs',
                            teacher: 'E2E Teacher',
                            classes: ['1A'],
                            student_count: 1,
                            date_count: 1,
                            entry_count: 0,
                            behaviour_count: 0,
                            status: 'missing_current',
                        },
                    ],
                    curricula: [],
                },
            }),
        })
    })

    await page.route('**/api/admin/teaching/backups/501/restore', async (route) => {
        if (route.request().method() !== 'POST') {
            await route.fallback()
            return
        }

        restoreCalls += 1
        restoreRuns = [
            {
                id: 801,
                type: 'partial',
                status: 'completed',
                backup_filename: 'e2e-teaching-backup.json',
                pre_restore_backup_filename: null,
                created_at: '2026-05-09T19:02:00.000Z',
            },
        ]

        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                data: {
                    restored: {
                        courses: [
                            {
                                old_id: 601,
                                new_id: 701,
                                title: 'E2E Backup Kurs',
                            },
                        ],
                        curricula: [],
                        settings: [],
                    },
                    skipped: {
                        courses: [],
                        curricula: [],
                        settings: [],
                    },
                    warnings: [],
                    restore_run: {
                        id: 801,
                        type: 'partial',
                        status: 'completed',
                    },
                },
            }),
        })
    })

    await page.route('**/api/admin/teaching/backups/501', async (route) => {
        if (route.request().method() !== 'DELETE') {
            await route.fallback()
            return
        }

        deleteCalls += 1
        backups = []

        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ message: 'Datensicherung wurde gelöscht.' }),
        })
    })

    await page.route('**/api/admin/teaching/backups/restore-runs', async (route) => {
        if (route.request().method() !== 'GET') {
            await route.fallback()
            return
        }

        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ data: restoreRuns }),
        })
    })

    await page.route('**/api/admin/teaching/backups', async (route) => {
        if (route.request().method() !== 'GET') {
            await route.fallback()
            return
        }

        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ data: backups }),
        })
    })

    await loginAsAdmin(page)

    await page.goto('/admin/teaching')
    await hideObstructiveUi(page)

    await page.getByRole('button', { name: /Datensicherung/ }).click()
    await expect(page.getByText(/2025\/2026 - gesichert am/)).toBeVisible()

    await page.locator('button[title="Wiederherstellung prüfen"]').first().click()
    const previewDialog = page.locator('.v-overlay--active', { hasText: 'Wiederherstellung prüfen' }).last()
    await expect(previewDialog.getByText('Wiederherstellung prüfen')).toBeVisible()
    await expect(previewDialog.getByText('E2E Backup Kurs')).toBeVisible()

    await previewDialog.getByRole('button', { name: /^Wiederherstellen$/ }).click()
    await expect(previewDialog.getByText('Wiederhergestellt: 1 Kurse, 0 Curricula, 0 Einstellungsbereiche.')).toBeVisible()

    await previewDialog.getByRole('button', { name: 'Details anzeigen' }).click()
    const reportDialog = page.locator('.v-overlay--active', { hasText: 'Wiederherstellungsbericht' }).last()
    await expect(reportDialog.getByText('Wiederherstellungsbericht')).toBeVisible()
    await expect(reportDialog.getByText('Kurse')).toBeVisible()
    await reportDialog.getByRole('button', { name: /^Schließen$/ }).click()

    await previewDialog.getByRole('button', { name: /^Schließen$/ }).click()
    await page.locator('button[title="Datensicherung löschen"]').first().click()
    const deleteDialog = page.locator('.v-overlay--active', { hasText: 'Datensicherung löschen' }).last()
    await expect(deleteDialog.getByText('Datensicherung löschen')).toBeVisible()
    await deleteDialog.getByRole('button', { name: /^Löschen$/ }).click()
    await expect(page.getByText('Noch keine Datensicherung vorhanden')).toBeVisible()

    expect(restoreCalls).toBe(1)
    expect(deleteCalls).toBe(1)
    expect(runtimeErrors, runtimeErrors.join('\n')).toEqual([])
})
