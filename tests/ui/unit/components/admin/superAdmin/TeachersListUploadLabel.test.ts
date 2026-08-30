import { readFileSync } from 'node:fs'
import { describe, expect, it, vi } from 'vitest'
import TeachersList from '@/pages/admin/superAdmin/components/TeachersList.vue'

describe('Teachers list upload label', () => {
    it('labels the import upload as a file upload', () => {
        const teachersListSource = readFileSync(
            'resources/js/pages/admin/superAdmin/components/TeachersList.vue',
            'utf8',
        )
        const fileUploadSource = readFileSync('resources/js/pages/components/FileUpload.vue', 'utf8')

        expect(teachersListSource).toMatch(/<FileUpload[\s\S]*?:path="teachersListUploadPath"[\s\S]*?fileLabel[\s\S]*?\/>/)
        expect(teachersListSource).toContain('Excel- oder CSV-Datei (*.xlsx, *.xls, *.csv)')
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
            teachersListStore: { index },
        }
        const methods = (TeachersList as any).methods

        methods.importStarted.call(context)

        expect(context.is_import_running).toBe(true)
        expect(context.is_import_finished).toBe(false)

        methods.fileUploadFinished.call(context)

        expect(context.is_upload_finished).toBe(true)
        expect(context.is_import_running).toBe(true)

        await methods.handleImportFinished.call(context, {
            detail: {
                status: 200,
                message: 'Lehrerliste importiert.',
            },
        })

        expect(context.is_import_running).toBe(false)
        expect(context.is_import_finished).toBe(true)
        expect(context.import_status).toBe(200)
        expect(context.import_message).toBe('Lehrerliste importiert.')
        expect(index).toHaveBeenCalledOnce()
    })

    it('keeps the running indicator active after upload completion', () => {
        const teachersListSource = readFileSync(
            'resources/js/pages/admin/superAdmin/components/TeachersList.vue',
            'utf8',
        )

        expect(teachersListSource).toContain('title="Import läuft"')
        expect(teachersListSource).toContain('<v-progress-linear color="primary" indeterminate')
        expect(teachersListSource).toContain("window.addEventListener('teachers-list-import-finished'")
        expect(teachersListSource).toContain("window.removeEventListener('teachers-list-import-finished'")
    })
})
