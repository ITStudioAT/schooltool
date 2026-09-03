import { readFileSync } from 'node:fs'
import { afterEach, describe, expect, it, vi } from 'vitest'
import TeachersListImportDialog from '@/pages/admin/superAdmin/components/TeachersListImportDialog.vue'

afterEach(() => {
    vi.unstubAllGlobals()
})

describe('Teachers list upload label', () => {
    it('labels the import upload as a file upload', () => {
        const importDialogSource = readFileSync(
            'resources/js/pages/admin/superAdmin/components/TeachersListImportDialog.vue',
            'utf8',
        )
        const fileUploadSource = readFileSync('resources/js/pages/components/FileUpload.vue', 'utf8')

        expect(importDialogSource).toMatch(/<FileUpload[\s\S]*?:path="teachersListUploadPath"[\s\S]*?fileLabel[\s\S]*?\/>/)
        expect(importDialogSource).toContain('Excel- oder CSV-Datei (*.xlsx, *.xls, *.csv)')
        expect(importDialogSource).toContain('Nachname/Familienname, Vorname, Email/EMail')
        expect(importDialogSource).toContain('Optional:')
        expect(importDialogSource).toContain('Kurz/Kürzel')
        expect(fileUploadSource).toContain('Ziehen Sie eine Datei hierher oder <i>klicken Sie hier.</i>')
    })

    it('shows a running state until the backend import completion arrives', async () => {
        const index = vi.fn().mockResolvedValue(undefined)
        const context = {
            is_import_running: false,
            is_import_finished: false,
            is_upload_finished: false,
            import_status: null,
            import_message: '',
            startImportStatusPolling: vi.fn(),
            stopImportStatusPolling: vi.fn(),
            teachersListStore: { index },
            $emit: vi.fn(),
        }
        const methods = (TeachersListImportDialog as any).methods

        methods.importStarted.call(context)

        expect(context.is_import_running).toBe(true)
        expect(context.is_import_finished).toBe(false)

        methods.fileUploadFinished.call(context)

        expect(context.is_upload_finished).toBe(true)
        expect(context.is_import_running).toBe(true)

        await methods.applyImportCompletion.call(context, {
            status: 200,
            message: 'Lehrerliste importiert.',
        })

        expect(context.is_import_running).toBe(false)
        expect(context.is_import_finished).toBe(true)
        expect(context.import_status).toBe(200)
        expect(context.import_message).toBe('Lehrerliste importiert.')
        expect(index).toHaveBeenCalledOnce()
    })

    it('uses the backend status as a fallback when the Echo event is missing', async () => {
        const get = vi.fn().mockResolvedValue({
            data: {
                state: 'finished',
                status: 200,
                message: 'Lehrerliste importiert.',
            },
        })
        const applyImportCompletion = vi.fn().mockResolvedValue(undefined)
        vi.stubGlobal('axios', { get })

        const context = {
            is_import_running: true,
            import_status_poll_in_flight: false,
            applyImportCompletion,
        }

        await (TeachersListImportDialog as any).methods.pollImportStatus.call(context)

        expect(get).toHaveBeenCalledWith('/api/admin/teachers_list_import_status')
        expect(applyImportCompletion).toHaveBeenCalledWith({
            state: 'finished',
            status: 200,
            message: 'Lehrerliste importiert.',
        })
        expect(context.import_status_poll_in_flight).toBe(false)
    })

    it('keeps the running indicator active after upload completion', () => {
        const importDialogSource = readFileSync(
            'resources/js/pages/admin/superAdmin/components/TeachersListImportDialog.vue',
            'utf8',
        )

        expect(importDialogSource).toContain('title="Import läuft"')
        expect(importDialogSource).toContain('<v-progress-linear color="primary" indeterminate')
        expect(importDialogSource).toContain("window.addEventListener('teachers-list-import-finished'")
        expect(importDialogSource).toContain("window.removeEventListener('teachers-list-import-finished'")
        expect(importDialogSource).toContain('this.startImportStatusPolling()')
        expect(importDialogSource).toContain('teachersListApi.importStatus()')
    })

    it('places the import action in Lehrer instead of Lehrerliste', () => {
        const teachersSource = readFileSync('resources/js/pages/admin/superAdmin/components/Teachers.vue', 'utf8')
        const teachersListSource = readFileSync('resources/js/pages/admin/superAdmin/components/TeachersList.vue', 'utf8')

        expect(teachersSource).toContain('prepend-icon="mdi-import"')
        expect(teachersSource).toContain('Importieren')
        expect(teachersSource).toContain('<TeachersListImportDialog v-model="importDialog"')
        expect(teachersListSource).not.toContain('prepend-icon="mdi-import"')
        expect(teachersListSource).not.toContain('@click="is_upload = true"')
    })
})
