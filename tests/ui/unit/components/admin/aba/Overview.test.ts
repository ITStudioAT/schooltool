import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it } from 'vitest'

describe('ABA overview list actions', () => {
    it('shows the aba id marker in the list title row', () => {
        const overviewPath = path.resolve(process.cwd(), 'resources/js/pages/admin/aba/components/Overview.vue')
        const overviewContent = fs.readFileSync(overviewPath, 'utf8')

        expect(overviewContent).toContain('#{{ aba.id }}')
    })

    it('keeps file management actions and removes legacy analysis actions', () => {
        const overviewPath = path.resolve(process.cwd(), 'resources/js/pages/admin/aba/components/Overview.vue')
        const overviewContent = fs.readFileSync(overviewPath, 'utf8')

        expect(overviewContent).toContain('Details')
        expect(overviewContent).toContain('Dateien')
        expect(overviewContent).toContain('Upload')
        expect(overviewContent).toContain('/admin/aba/details/${abaId}')
        expect(overviewContent).toContain('/api/admin/aba/uploads/chunk')
        expect(overviewContent).not.toContain('Analyse')
        expect(overviewContent).not.toContain('Ergebnisse')
        expect(overviewContent).not.toContain('/api/admin/abas/${abaId}/analysis')
        expect(overviewContent).not.toContain('/admin/aba/results/${abaId}')
    })

    it('renders the files button before the detail button', () => {
        const overviewPath = path.resolve(process.cwd(), 'resources/js/pages/admin/aba/components/Overview.vue')
        const overviewContent = fs.readFileSync(overviewPath, 'utf8')

        const filesIndex = overviewContent.indexOf('Dateien')
        const detailIndex = overviewContent.indexOf('Details')

        expect(filesIndex).toBeGreaterThan(-1)
        expect(detailIndex).toBeGreaterThan(-1)
        expect(filesIndex).toBeLessThan(detailIndex)
    })

    it('opens the dummy detail page for an aba row', () => {
        const overviewPath = path.resolve(process.cwd(), 'resources/js/pages/admin/aba/components/Overview.vue')
        const overviewContent = fs.readFileSync(overviewPath, 'utf8')

        expect(overviewContent).toContain('@click="openDetails(aba)"')
        expect(overviewContent).toContain('this.$router.push(`/admin/aba/details/${abaId}`)')
    })

    it('includes student_class in the save payload for create and edit', () => {
        const overviewPath = path.resolve(process.cwd(), 'resources/js/pages/admin/aba/components/Overview.vue')
        const overviewContent = fs.readFileSync(overviewPath, 'utf8')

        expect(overviewContent).toContain('student_class: this.form.student_class')
    })

    it('opens upload from files dialog instead of the aba list row', () => {
        const overviewPath = path.resolve(process.cwd(), 'resources/js/pages/admin/aba/components/Overview.vue')
        const overviewContent = fs.readFileSync(overviewPath, 'utf8')

        expect(overviewContent).toContain('<v-dialog v-model="filesDialogOpen"')
        expect(overviewContent).toContain('@click="openUploadDialog(selectedFilesAba)"')
        expect(overviewContent).toContain('@click="openFilesDialog(aba)"')
        expect(overviewContent).not.toContain('@click="openUploadDialog(aba)"')
    })

    it('no longer contains status polling for analysis runs', () => {
        const overviewPath = path.resolve(process.cwd(), 'resources/js/pages/admin/aba/components/Overview.vue')
        const overviewContent = fs.readFileSync(overviewPath, 'utf8')

        expect(overviewContent).not.toContain('statusPollTimer')
        expect(overviewContent).not.toContain('pollAnalysisStatus')
        expect(overviewContent).not.toContain('analysisConfirmDialogOpen')
    })
})
