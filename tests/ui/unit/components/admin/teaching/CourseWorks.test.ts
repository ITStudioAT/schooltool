import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import { mount, flushPromises, DOMWrapper } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createVuetify } from 'vuetify'
import { VFileInput } from 'vuetify/components/VFileInput'
import { VMenu, VBtn, VList, VListItem, VIcon } from 'vuetify/components'
import CourseWorks from '@/pages/admin/teaching/overview/components/CourseWorks.vue'
import { formatViennaDateTime } from '@/helpers/date'
import WorkEvaluationImport from '@/pages/admin/teaching/overview/components/WorkEvaluationImport.vue'
import WorkEvaluationPdf from '@/pages/admin/teaching/overview/components/WorkEvaluationPdf.vue'
import WorkDispatchLog from '@/pages/admin/teaching/overview/components/WorkDispatchLog.vue'

describe('JSON assessment preview and separate apply', () => {
    const work = { id: 65, teaching_course_id: 2, title: 'Gespeicherte Arbeit' }
    const preview = {
        target_work: { id: 65, title: 'Gespeicherte Arbeit', course_title: 'Kurs' },
        exercise: { title: 'Quellarbeit', subject: 'Fach', group: 'Gruppe 1', date: '06.10.2026', checkpoint: 'Prüfstand' },
        maximum_minor: 500, hash: 'a'.repeat(64), can_import: true, overview_pdf: null,
        rows: [{ participant_id: 'A', identity: { first_name: 'Ada', last_name: 'Van Alpha', class_name: '3B', group_name: 'Gruppe 1' },
            target: { first_name: 'Ada', last_name: 'Van Alpha', class_name: '3B', group_name: 'Gruppe 1' },
            status: 'Bewertung erhalten', change: 'new', submission_state: 'received', submission_note: 'Eingang belegt',
            evaluation_state: 'partial', evaluation_note: 'Teilprüfung', total_minor: null, comment: 'Vorläufig',
            previous: { points: 4.75, comments: 'Abgeschlossen' }, criteria: [], adjustments: [], pdf: null, will_replace: false }],
    }

    it('renders separate statuses and previews before submitting the explicit apply hash', async () => {
        const post = vi.fn().mockResolvedValueOnce({ data: { preview } }).mockResolvedValueOnce({ data: { data: work } })
        vi.stubGlobal('axios', { post })
        const wrapper = mount(WorkEvaluationImport, { global: {
            plugins: [createTestingPinia({ createSpy: vi.fn, initialState: { AdminCourseStore: { selected_course: { id: 2 } } } }), createVuetify({ components: { VFileInput, VBtn } })],
            components: { 'v-file-input': VFileInput, 'v-btn': VBtn }, stubs: { 'v-file-input': false, VFileInput: false, 'v-btn': false, VBtn: false },
        } })
        try {
            const vm = wrapper.vm as any
            vm.openImport(work)
            vm.changeMode('json')
            await vm.selectJson({ target: { files: [new File(['{}'], 'Schooltool-Bewertungen.json')] } })
            await flushPromises()
            expect(post).toHaveBeenCalledTimes(1)
            expect(post.mock.calls[0][0]).toBe('/api/admin/teaching/course_works/65/import-json')
            expect(post.mock.calls[0][1].has('apply')).toBe(false)
            expect(wrapper.text()).toContain('Abgabe: Eingegangen').toContain('Bewertung: Teilweise bewertet').toContain('Bestehende Bewertung bleibt erhalten.')
            expect(wrapper.emitted('imported')).toBeUndefined()
            const button = wrapper.findAll('button').find(button => button.text().includes('Angezeigte Änderungen übernehmen'))!
            await button.trigger('click')
            await flushPromises()
            expect(post.mock.calls[1][1].get('apply')).toBe('1')
            expect(post.mock.calls[1][1].get('hash')).toBe(preview.hash)
            expect(wrapper.emitted('imported')?.[0]).toEqual([work])
        } finally { wrapper.unmount(); vi.unstubAllGlobals() }
    })

    it('shows the evidenced deadline and local absence reason while retaining an open server search', async () => {
        const localPreview = {
            ...preview,
            exercise: { ...preview.exercise, deadline: '05.10.2026, 17:00:00 Europe/Vienna' },
            final_download: { deadline_at: '2026-10-05T15:00:00Z', download_completed_at: '2026-10-07T20:49:54Z', scope: 'Lokaler Outlook-Bestand; Serversuche nicht vollständig belegt.' },
            submission_check: { state: 'open', checked_at: '2026-10-07T20:49:54Z' },
            rows: [{ ...preview.rows[0], submission_state: 'not_received', evaluation_state: 'complete', total_minor: 0,
                submission_note: 'Innerhalb der Frist nicht abgegeben', will_replace: true }],
        }
        const post = vi.fn().mockResolvedValue({ data: { preview: localPreview } })
        vi.stubGlobal('axios', { post })
        const wrapper = mount(WorkEvaluationImport, { global: {
            plugins: [createTestingPinia({ createSpy: vi.fn, initialState: { AdminCourseStore: { selected_course: { id: 2 } } } }), createVuetify({ components: { VFileInput } })],
            components: { 'v-file-input': VFileInput }, stubs: { 'v-file-input': false, VFileInput: false },
        } })
        try {
            const vm = wrapper.vm as any
            vm.openImport(work)
            await vm.selectJson({ target: { files: [new File(['{}'], 'Schooltool-Bewertungen.json')] } })
            await flushPromises()

            expect(wrapper.text()).toContain('Paketfrist: 05.10.2026, 17:00:00 Europe/Vienna')
                .toContain('Lokaler Abschlussdownload:').toContain('Serversuche nicht vollständig belegt.')
                .toContain('Innerhalb der Frist nicht abgegeben').toContain('Abgabeprüfung: noch offen')
            expect(post).toHaveBeenCalledTimes(1)
            expect(post.mock.calls[0][1].has('apply')).toBe(false)
        } finally { wrapper.unmount(); vi.unstubAllGlobals() }
    })

    it('shows the current teacher deadline and blocks applying historical deadline judgments', async () => {
        const localPreview = { ...preview, can_import: false,
            exercise: { ...preview.exercise, deadline: '08.10.2026, 16:00 Europe/Vienna' },
            deadline_context: { state: 'changed', current_at: '2026-10-08T12:50:00Z', requires_review: true,
                message: 'Die aktuelle Lehrkraftfrist bleibt erhalten. Fristabhängige Bewertungen erneut prüfen und exportieren.' },
            rows: [{ ...preview.rows[0], deadline_requires_review: true, will_replace: false }] }
        const post = vi.fn().mockResolvedValue({ data: { preview: localPreview } })
        vi.stubGlobal('axios', { post })
        const wrapper = mount(WorkEvaluationImport, { global: {
            plugins: [createTestingPinia({ createSpy: vi.fn, initialState: { AdminCourseStore: { selected_course: { id: 2 } } } }), createVuetify({ components: { VFileInput } })],
            components: { 'v-file-input': VFileInput }, stubs: { 'v-file-input': false, VFileInput: false },
        } })
        try {
            const vm = wrapper.vm as any
            vm.openImport(work)
            await vm.selectJson({ target: { files: [new File(['{}'], 'Schooltool-Bewertungen.json')] } })
            await flushPromises()
            expect(wrapper.text()).toContain('Aktuelle Lehrkraftfrist').toContain('14:50')
                .toContain('Paketfrist: 08.10.2026, 16:00 Europe/Vienna').toContain('erneut prüfen und exportieren')
                .toContain('Fristbezug erneut prüfen; bestehende Bewertung bleibt erhalten.')
            await vm.applyJson()
            expect(post).toHaveBeenCalledTimes(1)
            expect(post.mock.calls[0][1].has('apply')).toBe(false)
            expect(wrapper.emitted('imported')).toBeUndefined()
        } finally { wrapper.unmount(); vi.unstubAllGlobals() }
    })

    it('reloads the selected package after an old missing-deadline error without applying grades', async () => {
        const localPreview = { ...preview, can_import: false,
            deadline_context: { current_at: '2026-10-08T12:50:00Z', requires_review: true,
                message: 'Historische Paketfrist: 16:00. Aktuelle Lehrkraftfrist bleibt erhalten.' } }
        const post = vi.fn().mockRejectedValueOnce({ response: { status: 422, data: {
            errors: { package: ['Abgabeprüfung benötigt eine belegte Ziel-Frist mit Uhrzeit.'] } } } })
            .mockResolvedValueOnce({ data: { preview: localPreview } })
        vi.stubGlobal('axios', { post })
        const wrapper = mount(WorkEvaluationImport, { global: {
            plugins: [createTestingPinia({ createSpy: vi.fn, initialState: { AdminCourseStore: { selected_course: { id: 2 } } } }), createVuetify({ components: { VFileInput, VBtn } })],
            components: { 'v-file-input': VFileInput, 'v-btn': VBtn }, stubs: { 'v-file-input': false, VFileInput: false, 'v-btn': false, VBtn: false },
        } })
        try {
            const vm = wrapper.vm as any
            vm.openImport(work)
            await vm.selectJson({ target: { files: [new File(['{}'], 'Schooltool-Bewertungen.json')] } })
            await flushPromises()
            expect(wrapper.text()).toContain('Abgabeprüfung benötigt eine belegte Ziel-Frist mit Uhrzeit.')
            const retry = wrapper.findAll('button').find(button => button.text().includes('Vorschau erneut laden'))!
            await retry.trigger('click')
            await flushPromises()
            expect(post).toHaveBeenCalledTimes(2)
            expect(post.mock.calls[1][1].has('apply')).toBe(false)
            expect(post.mock.calls[1][1].get('package').name).toBe('Schooltool-Bewertungen.json')
            expect(wrapper.text()).not.toContain('Abgabeprüfung benötigt eine belegte Ziel-Frist mit Uhrzeit.')
            expect(wrapper.text()).toContain('14:50').toContain('Historische Paketfrist: 16:00')
            expect(wrapper.emitted('imported')).toBeUndefined()
        } finally { wrapper.unmount(); vi.unstubAllGlobals() }
    })

    it('clears a stale preview on conflict and never falls back to Markdown', async () => {
        const post = vi.fn().mockRejectedValue({ response: { status: 409, data: { message: 'Bitte Vorschau erneut laden.' } } })
        vi.stubGlobal('axios', { post })
        const ctx: any = { ...(WorkEvaluationImport as any).methods, import_work: work, import_open: true, selected_course: { id: 2 },
            json_preview: preview, json_uploads: { package: new File(['{}'], 'Schooltool-Bewertungen.json'), pdfs: [] }, $emit: vi.fn() }
        try {
            await ctx.applyJson()
            expect(ctx.json_preview).toBeNull()
            expect(ctx.import_error).toContain('Vorschau erneut laden')
            expect(post).toHaveBeenCalledTimes(1)
            expect(post.mock.calls[0][0]).toContain('/import-json')
            expect(ctx.$emit).not.toHaveBeenCalled()
        } finally { vi.unstubAllGlobals() }
    })
})

describe('Last work import time', () => {
    it('formats import timestamps in Vienna including summer and winter time', () => {
        expect(formatViennaDateTime('2026-10-06T06:30:00Z')).toBe('06.10.2026 um 08:30 Uhr')
        expect(formatViennaDateTime('2026-12-06T23:30:00Z')).toBe('07.12.2026 um 00:30 Uhr')
    })

    it.each([undefined, null, '', 'invalid'])('leaves missing or invalid import times blank: %s', timestamp => {
        expect(formatViennaDateTime(timestamp)).toBe('')
    })
})

describe('Grouped original dispatch downloads', () => {
    it('shows one action per purpose and lists all five protocols with meaningful time and test recipient labels', async () => {
        vi.stubGlobal('visualViewport', { width: 1024, height: 768, offsetLeft: 0, offsetTop: 0, addEventListener: vi.fn(), removeEventListener: vi.fn() })
        const logs = [
            { purpose: 'tasks', mode: 'test', recipient_scope: 'students', dispatch_at: '2026-10-02T15:02:40Z' },
            { purpose: 'results', mode: 'live', recipient_scope: 'students', dispatch_at: '2026-10-04T00:15:39Z' },
            ...['00:20:16', '00:47:41', '00:51:15'].map(time => ({ purpose: 'results', mode: 'teacher_test', recipient_scope: 'teacher', dispatch_at: `2026-10-04T${time}Z` })),
        ].map((log, index) => ({ ...log, origin: 'dispatch_import', sha256: String(index).repeat(64), name: 'Versandprotokoll.txt' }))
        const work = { id: 65, status: { dispatch_logs: logs, dispatch_notifications: [{ student_id: 12, log_sha256: logs[1].sha256 }] } }
        const wrapper = mount(WorkDispatchLog, { props: { work }, attachTo: document.body, global: {
            plugins: [createVuetify({ components: { VMenu, VBtn, VList, VListItem, VIcon } })],
            components: { 'v-menu': VMenu, 'v-btn': VBtn, 'v-list': VList, 'v-list-item': VListItem, 'v-icon': VIcon },
            stubs: { 'v-menu': false, VMenu: false, 'v-btn': false, VBtn: false, 'v-list': false, VList: false, 'v-list-item': false, VListItem: false, 'v-icon': false, VIcon: false },
        } })
        try {
            expect(wrapper.findAll('.v-btn').map(button => button.text())).toEqual(['Download Aufgabenversand', 'Download Ergebnisbenachrichtigung'])
            expect(wrapper.get('a[href]').attributes('href')).toContain(`/65/dispatch/${logs[0].sha256}`)
            await wrapper.findAll('.v-btn')[1].trigger('click')
            await flushPromises()
            const menu = new DOMWrapper(document.body).get('.v-overlay--active .v-list')
            const links = menu.findAll('a[href]')
            expect(links).toHaveLength(4)
            expect(links[0].text()).toContain('02:51').toContain('Live-Test · nur Lehrperson')
            expect(links[3].text()).toContain('02:15').toContain('Bestätigter Live-Versand · Schüler:innen')
            expect(links.map(link => link.attributes('href'))).toEqual([4, 3, 2, 1].map(index => `/api/admin/teaching/course_works/65/dispatch/${logs[index].sha256}`))
            expect(links.every(link => !link.text().includes('Download Ergebnisbenachrichtigung'))).toBe(true)
        } finally { wrapper.unmount(); vi.unstubAllGlobals() }
    })

    it('keeps a single legacy result protocol directly downloadable', () => {
        const wrapper = mount(WorkDispatchLog, { props: { work: { id: 65, status: { dispatch_logs: [{ origin: 'dispatch_import', sha256: 'a'.repeat(64), name: 'Versandprotokoll.txt' }] } } } })
        try {
            expect(wrapper.findAll('[href]')).toHaveLength(1)
            expect(wrapper.get('[href]').text()).toBe('Download Ergebnisbenachrichtigung')
        } finally { wrapper.unmount() }
    })
})

describe('Work evaluation PDF links', () => {
    const overall = { student_id: null, name: 'Gesamtübersicht.pdf', sha256: 'a'.repeat(64), origin: 'evaluation_import' }
    const personal = { student_id: 12, name: 'Beurteilung_Alpha_Ada.pdf', sha256: 'b'.repeat(64), origin: 'evaluation_import' }
    const foreign = { student_id: 13, name: 'Beurteilung_Beta_Bea.pdf', sha256: 'c'.repeat(64), origin: 'evaluation_import' }
    const work = { id: 8, status: { evaluation_pdfs: [overall, personal, foreign] } }

    it.each([[null, overall, 'Gesamtauswertung (PDF)'], [12, personal, 'Auswertung (PDF)'], ['12', personal, 'Auswertung (PDF)']])('renders only the report for student %s', (studentId, pdf, label) => {
        const wrapper = mount(WorkEvaluationPdf, { props: { work, studentId } })
        try {
            const link = wrapper.get('[href]')
            expect(link.text()).toBe(label)
            expect(link.attributes('href')).toBe(`/api/admin/teaching/course_works/8/evaluations/${pdf.sha256}?inline=1`)
            expect(link.attributes('title')).toBe(pdf.name)
            expect(link.attributes('target')).toBe('_blank')
            expect(wrapper.findAll('[href]')).toHaveLength(1)
        } finally { wrapper.unmount() }
    })

    it('shows no PDF for an unrelated person and refreshes the link after replacing a report', async () => {
        const wrapper = mount(WorkEvaluationPdf, { props: { work, studentId: 99 } })
        try {
            expect(wrapper.find('[href]').exists()).toBe(false)
            await wrapper.setProps({ studentId: 12 })
            expect(wrapper.get('[href]').attributes('href')).toContain(personal.sha256)
            const replacement = { ...personal, sha256: 'd'.repeat(64) }
            await wrapper.setProps({ work: { ...work, status: { evaluation_pdfs: [overall, foreign, replacement] } } })
            expect(wrapper.get('[href]').attributes('href')).toContain(replacement.sha256)
            expect(wrapper.get('[href]').attributes('href')).not.toContain(personal.sha256)
        } finally { wrapper.unmount() }
    })
})

describe('CourseWorks automatic folder import', () => {
    const fileAt = (path: string, text = 'fixture', type = 'text/plain') => {
        const file = new File([text], path.split('/').at(-1)!, { type })
        Object.defineProperty(file, 'webkitRelativePath', { value: path })
        return file
    }
    const work = { id: 65, teaching_course_id: '2', title: 'E-Mails' }
    const summary = { messages: ['Auswertungen übernommen.', 'Aufgabenversand: lokaler Test.', 'Ergebnisbenachrichtigung: Live-Versand.'], missing: [] }

    it.each(['missing package', 'duplicate package', 'invalid JSON', 'missing PDF', 'duplicate PDF', 'multiple roots', 'oversized package'])('rejects %s without a request or Markdown fallback', async scenario => {
        const post = vi.fn()
        vi.stubGlobal('axios', { post })
        const packageText = JSON.stringify({ overview_pdf: { filename: 'Report.pdf' }, records: [] })
        const packageFile = fileAt('Test/Schooltool-Bewertungen.json', scenario === 'invalid JSON' ? '{' : packageText)
        const selected = [packageFile, fileAt('Test/Report.pdf')]
        if (scenario === 'missing package') selected.splice(0, 1, fileAt('Test/Beurteilungen/Gesamtübersicht.md'))
        if (scenario === 'duplicate package') selected.push(fileAt('Test/Beurteilungen/Schooltool-Bewertungen.json', packageText))
        if (scenario === 'missing PDF') selected.pop()
        if (scenario === 'duplicate PDF') selected.push(fileAt('Test/Report.pdf'))
        if (scenario === 'multiple roots') selected.push(fileAt('Other/Unrelated.txt'))
        if (scenario === 'oversized package') Object.defineProperty(packageFile, 'size', { value: 262145 })
        const ctx: any = { ...(WorkEvaluationImport as any).methods, import_work: work, import_open: true, selected_course: { id: 2 },
            json_preview: { hash: 'old' }, json_uploads: { package: packageFile }, $emit: vi.fn() }
        const event = { target: { files: selected, value: 'folder' } }
        try {
            await ctx.selectJsonFolder(event)
            expect(post).not.toHaveBeenCalled()
            expect(ctx.json_preview).toBeNull()
            expect(ctx.json_uploads).toBeNull()
            expect(ctx.import_error).not.toBe('')
            if (scenario === 'missing package' || scenario === 'duplicate package') {
                expect(ctx.import_selection).toBe('Test')
                expect(ctx.import_error).toContain('direkt oder unter Beurteilungen')
            }
            expect(event.target.value).toBe('')
            expect(ctx.$emit).not.toHaveBeenCalled()
        } finally { vi.unstubAllGlobals() }
    })

    it('includes compact surname-first personal reports and matching PDFs in the folder request', async () => {
        const post = vi.fn().mockResolvedValue({ data: { data: work, summary } })
        vi.stubGlobal('axios', { post })
        const ctx: any = { ...(WorkEvaluationImport as any).methods, import_work: work, import_open: true, selected_course: { id: 2 }, $emit: vi.fn() }
        const selected = [
            fileAt('Test/Beurteilungen/Gesamtübersicht.md'),
            fileAt('Test/Beurteilungen/Gesamtübersicht.pdf', '%PDF-1.4', 'application/pdf'),
            fileAt('Test/Beurteilungen/Van Alpha_Ada.md', 'personal result'),
            fileAt('Test/Beurteilungen/Van Alpha_Ada.pdf', '%PDF-1.4', 'application/pdf'),
            fileAt('Test/Beurteilungen/Beta_Bea.md', 'open result'),
            fileAt('Test/Beurteilungen/Beta_Bea.pdf', '%PDF-1.4', 'application/pdf'),
            fileAt('Test/Abgaben/Van Alpha_Ada.md', 'submission'),
            fileAt('Test/Beurteilungen/Belege/Van Alpha_Ada.md', 'evidence'),
        ]
        try {
            await ctx.selectFolder({ target: { files: selected, value: 'folder' } })
            expect(post).toHaveBeenCalledTimes(1)
            const payload = post.mock.calls[0][1] as FormData
            const documents = JSON.parse(payload.get('documents') as string)
            expect(documents).toHaveLength(3)
            expect(documents.find((document: any) => document.path.endsWith('/Van Alpha_Ada.md')).text).toBe('personal result')
            expect(JSON.parse(payload.get('pdf_paths') as string)).toHaveLength(3)
            expect(payload.getAll('pdfs[]')).toHaveLength(3)
            expect(ctx.import_selection).toBe('Test · 6 Importdateien')
        } finally { vi.unstubAllGlobals() }
    })

    it('previews the JSON package through the existing folder field and rescans subsequent selections', async () => {
        const preview = { target_work: { course_title: 'Kurs', title: work.title }, exercise: { title: 'Quelle' }, maximum_minor: 500, rows: [], can_import: true, hash: 'a'.repeat(64) }
        const post = vi.fn().mockResolvedValue({ data: { preview } })
        vi.stubGlobal('axios', { post })
        const wrapper = mount(WorkEvaluationImport, { global: {
            plugins: [createTestingPinia({ createSpy: vi.fn, initialState: { AdminCourseStore: { selected_course: { id: 2 } } } }), createVuetify({ components: { VFileInput } })],
            components: { 'v-file-input': VFileInput }, stubs: { 'v-file-input': false, VFileInput: false },
        } })
        try {
            const vm = wrapper.vm as any
            vm.openImport(work)
            await wrapper.vm.$nextTick()
            const packageText = JSON.stringify({ overview_pdf: { filename: 'Gesamtübersicht.pdf' }, records: [{ pdf: { filename: 'Alpha_Ada.pdf' } }] })
            const selected = [
                fileAt('Test/Beurteilungen/Schooltool-Bewertungen.json', packageText, 'application/json'),
                fileAt('Test/Beurteilungen/Alpha_Ada.pdf', '%PDF-1.4', 'application/pdf'),
                fileAt('Test/Beurteilungen/Beurteilung_Alpha_Ada.md'),
                fileAt('Test/Beurteilungen/Gesamtübersicht.md'),
                fileAt('Test/Beurteilungen/Gesamtübersicht.pdf', '%PDF-1.4', 'application/pdf'),
                fileAt('Test/Versand/Aufgaben/Versand_2026-10-02_17-02-40/Versandprotokoll.txt'),
                fileAt('Test/Versand/Ergebnisse/Versand_2026-10-04_02-12-33/Versandprotokoll.txt'),
                fileAt('Test/Abgaben/Gesamtübersicht.md', 'not imported'),
                fileAt('Test/Material.pdf', 'not imported', 'application/pdf'),
                fileAt('Test/Beurteilungen/Archiv_2026-10-06/Schooltool-Bewertungen.json', 'not imported'),
                fileAt('Test/Beurteilungen/Archiv_2026-10-06/Gesamtübersicht.pdf', 'not imported', 'application/pdf'),
            ]
            const input = wrapper.get('input[type="file"]')
            expect(input.attributes()).toHaveProperty('webkitdirectory')
            Object.defineProperty(input.element, 'files', { value: selected, configurable: true })
            await input.trigger('change')
            await flushPromises()
            expect(post).toHaveBeenCalledTimes(1)
            expect(post.mock.calls[0][0]).toBe('/api/admin/teaching/course_works/65/import-json')
            const payload = post.mock.calls[0][1] as FormData
            expect((payload.get('package') as File).name).toBe('Schooltool-Bewertungen.json')
            expect(JSON.parse(payload.get('documents') as string).map((document: any) => document.path)).toEqual([
                'Test/Versand/Aufgaben/Versand_2026-10-02_17-02-40/Versandprotokoll.txt',
                'Test/Versand/Ergebnisse/Versand_2026-10-04_02-12-33/Versandprotokoll.txt',
            ])
            expect(payload.get('folder')).toBe('Test')
            expect(payload.getAll('pdfs[]').map(file => (file as File).name)).toEqual(['Gesamtübersicht.pdf', 'Alpha_Ada.pdf'])
            expect(payload.has('apply')).toBe(false)
            expect(payload.has('hash')).toBe(false)
            expect(wrapper.text()).toContain('Ziel: Kurs').toContain('Test · 5 Importdateien')
            expect(wrapper.text()).not.toContain('Beurteilung_Alpha_Ada.md')
            expect(wrapper.text()).not.toContain('Versandprotokoll.txt')
            expect(wrapper.text()).toContain('Angezeigte Änderungen übernehmen')
            expect(wrapper.emitted('imported')).toBeUndefined()
            expect((input.element as HTMLInputElement).value).toBe('')

            const updated = fileAt('Test/Schooltool-Bewertungen.json', JSON.stringify({ overview_pdf: null, records: [] }), 'application/json')
            Object.defineProperty(input.element, 'files', { value: [updated], configurable: true })
            await input.trigger('change')
            await flushPromises()
            expect(post).toHaveBeenCalledTimes(2)
            expect(post.mock.calls[1][1].getAll('pdfs[]')).toHaveLength(0)
            expect(wrapper.emitted('imported')).toBeUndefined()
            expect(wrapper.text()).toContain('Test · 1 Importdateien')
            vm.import_open = false
            vm.openImport(work)
            await wrapper.vm.$nextTick()
            expect(wrapper.text()).not.toContain('Test · 1 Importdateien')
        } finally { wrapper.unmount(); vi.unstubAllGlobals() }
    })

    it('shows optional missing parts and forwards updated work to the normal parent refresh', async () => {
        const post = vi.fn().mockResolvedValue({ data: { data: work, summary: { messages: ['Aufgabenversand geprüft.'], missing: ['Auswertungen', 'Ergebnisbenachrichtigung'] } } })
        vi.stubGlobal('axios', { post })
        const ctx: any = { ...(WorkEvaluationImport as any).methods, import_work: work, import_open: true, selected_course: { id: 2 }, $emit: vi.fn() }
        const event = { target: { files: [fileAt('Test/Versand/Aufgaben/Versand_2026-10-02_17-02-40/Versandprotokoll.txt')], value: 'folder' } }
        try {
            await ctx.selectFolder(event)
            expect(ctx.import_summary.missing).toEqual(['Auswertungen', 'Ergebnisbenachrichtigung'])
            expect(ctx.$emit).toHaveBeenCalledWith('imported', work)
            const parent: any = { refreshWorks: vi.fn(), editWork: vi.fn(), selected_course: { id: 2 } }
            await (CourseWorks as any).methods.evaluationImported.call(parent, work)
            expect(parent.refreshWorks).toHaveBeenCalled()
            expect(parent.editWork).toHaveBeenCalledWith(work)
            expect(event.target.value).toBe('')
        } finally { vi.unstubAllGlobals() }
    })

    it('reports a concrete atomic failure without announcing a successful import', async () => {
        const post = vi.fn().mockRejectedValue({ response: { data: { errors: { folder: ['Ada / 1A: Zuordnung ungeklärt. Keine Dateien wurden übernommen.'] } } } })
        vi.stubGlobal('axios', { post })
        const ctx: any = { ...(WorkEvaluationImport as any).methods, import_work: work, import_open: true, selected_course: { id: 2 }, $emit: vi.fn() }
        const event = { target: { files: [fileAt('Test/Beurteilungen/Gesamtübersicht.md')], value: 'folder' } }
        try {
            await ctx.selectFolder(event)
            expect(ctx.import_error).toContain('Ada / 1A: Zuordnung ungeklärt.')
            expect(ctx.import_summary).toBeNull()
            expect(ctx.$emit).not.toHaveBeenCalled()
            expect(event.target.value).toBe('')
        } finally { vi.unstubAllGlobals() }
    })

    it.each(['multiple roots', 'invalid encoding'])('rejects %s before uploading', async (scenario) => {
        const post = vi.fn()
        vi.stubGlobal('axios', { post })
        const bad = fileAt('Test/Beurteilungen/Gesamtübersicht.md')
        if (scenario === 'invalid encoding') Object.defineProperty(bad, 'arrayBuffer', { value: async () => new Uint8Array([255]).buffer })
        const ctx: any = { ...(WorkEvaluationImport as any).methods, import_work: work, import_open: true, selected_course: { id: 2 }, $emit: vi.fn() }
        try {
            await ctx.selectFolder({ target: { files: scenario === 'multiple roots' ? [bad, fileAt('Other/Beurteilungen/Gesamtübersicht.md')] : [bad], value: 'folder' } })
            expect(post).not.toHaveBeenCalled()
            expect(ctx.import_error).toContain(scenario === 'multiple roots' ? 'Genau einen' : 'UTF-8')
        } finally { vi.unstubAllGlobals() }
    })

    it('does not refresh a newly selected course with an old response', async () => {
        let finish: any
        const post = vi.fn().mockReturnValue(new Promise(resolve => { finish = resolve }))
        vi.stubGlobal('axios', { post })
        const ctx: any = { ...(WorkEvaluationImport as any).methods, import_work: work, import_open: true, selected_course: { id: 2 }, $emit: vi.fn() }
        try {
            const request = ctx.selectFolder({ target: { files: [fileAt('Test/Beurteilungen/Gesamtübersicht.md')], value: 'folder' } })
            await flushPromises()
            ctx.selected_course = { id: 3 }
            finish({ data: { data: work, summary } })
            await request
            expect(ctx.$emit).not.toHaveBeenCalled()
            expect(ctx.import_summary).toBeNull()
        } finally { vi.unstubAllGlobals() }
    })

    it('keeps numeric points as the evaluation instead of converting them to a school grade', () => {
        const methods = (CourseWorks as any).methods
        const ctx = { workSupportsPoints: methods.workSupportsPoints }
        const definition = { has_properties: true, properties_mode: 'points', maximum_points: 5 }
        expect(methods.workSupportsPoints.call(ctx, definition)).toBe(true)
        expect(methods.gradeFromPointsForWork.call(ctx, definition, 4.5)).toBe('4.5')
    })
})

describe('CourseWorks defaults', () => {
    it('uses the current grading types and requires the enabled maximum plus value', () => {
        const computed = (CourseWorks as any).computed
        const definition = { category: 'Benotung', short_name: 'A', properties_mode: 'plus', allows_maximum_plus: true }
        const ctx = {
            selected_course: { teaching_entry_area: { id: 1, entry_definitions: [definition, { category: 'Verhalten' }] } },
            selectedCourseSchema: { works: [{ short_name: 'OLD' }] },
            work_form: { type: 'A', maximum_plus: null as string | null },
        }

        expect(computed.teachingWorks.call(ctx)).toEqual([definition])
        expect(computed.workRequiresMaximumPlus.call(ctx)).toBe(true)
        expect(computed.workMaximumError.call(ctx)).toContain('positive ganze Zahl')
        ctx.work_form.maximum_plus = '6'
        expect(computed.workMaximumError.call(ctx)).toBe('')
        definition.allows_maximum_plus = false
        expect(computed.workRequiresMaximumPlus.call(ctx)).toBe(false)
    })

    it('defaults student sort mode to name', () => {
        const data = (CourseWorks as any).data.call({
            emptyWorkForm: () => ({}),
        })

        expect(data.students_sort_mode).toBe('last_name_first_name')
    })

    it('does not show the bulk action processing state by default', () => {
        const data = (CourseWorks as any).data.call({
            emptyWorkForm: () => ({}),
        })

        expect(data.is_applying_bulk_action).toBe(false)
    })

    it('does not start in a group-work mode transition', () => {
        const data = (CourseWorks as any).data.call({
            emptyWorkForm: () => ({}),
        })

        expect(data.is_changing_group_work_mode).toBe(false)
    })
})

describe('CourseWorks title rendering', () => {
    it('renders work title in second line in Arbeiten list', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('class="text-caption text-medium-emphasis work-type-first-line"')
        expect(source).toContain('class="work-meta-row"')
        expect(source).toContain('class="text-body-2 work-title-second-line"')
        expect(source).toContain('v-if="work.description" class="text-caption work-description-line"')
        expect(source).toContain('{{ work.title || \'—\' }}')
        expect(source).toContain('{{ work.description }}')
        expect(source).not.toContain('{{ work.description || \'—\' }}')
        expect(source).not.toContain('class="text-caption text-medium-emphasis work-title-second-line"')
        expect(source).not.toContain('<span v-if="work.title || work.description">– {{ work.title || work.description }}</span>')
    })

    it('renders grade distribution chips for each work list item', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('class="work-bottom-row"')
        expect(source).toContain('class="work-grade-distribution d-flex flex-wrap ga-1"')
        expect(source).toContain('v-for="item in workGradeDistribution(work)"')
        expect(source).toContain('{{ item.grade }}: {{ item.count }}')
        expect(source).toContain('class="work-actions work-import-actions d-flex align-center flex-wrap ga-2"')
    })

    it('renders and edits the finish-until date', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')
        const watcher = (CourseWorks as any).watch['work_form.finish_until_date']
        const ctx = {
            work_form: {
                finish_until_date: new Date(2026, 4, 20),
            },
            toDateString: (date: Date) => [
                date.getFullYear(),
                String(date.getMonth() + 1).padStart(2, '0'),
                String(date.getDate()).padStart(2, '0'),
            ].join('-'),
        }

        watcher.call(ctx, ctx.work_form.finish_until_date)

        expect(source).toContain('v-model="work_form.finish_until_date" clearable label="Fertig bis"')
        expect(source).toContain('Fertig bis {{ formatDate(work.finish_until_date) }}')
        expect(ctx.work_form.finish_until_date).toBe('2026-05-20')
    })

    it('defaults the finish-until date when the work date is first selected', () => {
        const watcher = (CourseWorks as any).watch['work_form.date_for_all_groups']
        const ctx = {
            is_initializing_form: false,
            normalizeDateString: (date: string) => date,
            work_form: {
                date_for_all_groups: '2026-05-16',
                finish_until_date: '',
                groups: [],
            },
        }

        watcher.call(ctx, ctx.work_form.date_for_all_groups)

        expect(ctx.work_form.finish_until_date).toBe('2026-05-16')
    })

    it('preserves a manually selected finish-until date when the work date changes', () => {
        const watcher = (CourseWorks as any).watch['work_form.date_for_all_groups']
        const ctx = {
            is_initializing_form: false,
            normalizeDateString: (date: string) => date,
            work_form: {
                date_for_all_groups: '2026-05-16',
                finish_until_date: '2026-05-20',
                groups: [],
            },
        }

        watcher.call(ctx, ctx.work_form.date_for_all_groups)

        expect(ctx.work_form.finish_until_date).toBe('2026-05-20')
    })

    it('opens a specific work from the route query after jumping from dates', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')
        const methods = (CourseWorks as any).methods
        const editWork = vi.fn()
        const ctx: Record<string, any> = {
            courseWorks: [
                { id: 11, teaching_course_id: 20, title: 'Andere Arbeit' },
                { id: 23, teaching_course_id: 20, title: 'Excel' },
            ],
            selected_course: { id: 20 },
            selected_courseWork: null,
            editWork,
            $route: { query: { work: '23' } },
        }

        const opened = methods.openWorkFromRouteQuery.call(ctx)

        expect(source).toContain('this.openWorkFromRouteQuery()')
        expect(source).toContain("'$route.query.work'()")
        expect(opened).toBe(true)
        expect(ctx.selected_courseWork).toEqual({ id: 23, teaching_course_id: 20, title: 'Excel' })
        expect(editWork).toHaveBeenCalledWith({ id: 23, teaching_course_id: 20, title: 'Excel' })
    })

    it('returns to the dates panel after cancelling a work opened from dates', () => {
        const methods = (CourseWorks as any).methods
        const replace = vi.fn(() => Promise.resolve())
        const ctx: Record<string, any> = {
            action: 'edit_course_work',
            selected_courseWork: { id: 23, title: 'Excel' },
            work_form: { id: 23 },
            pending_group_work: true,
            pending_random_groups: true,
            show_bulk_action: true,
            bulk_grade: '1',
            bulk_comment: 'Kommentar',
            selected_student_ids: [1, 2],
            show_points_grading_view: true,
            closeCommentDialog: vi.fn(),
            details_editable: true,
            details_snapshot: { id: 23 },
            show_dates: false,
            show_works: true,
            courseStore: {
                previous_selected_student: null,
                previous_show_infos: null,
                previous_show_dates: null,
            },
            shouldReturnToDatesPanel: methods.shouldReturnToDatesPanel,
            returnToDatesPanel: methods.returnToDatesPanel,
            emptyWorkForm: () => ({ groups: [] }),
            $route: {
                query: {
                    course: '20',
                    date: '370',
                    grades: 'sem1,sem2,year',
                    panel: 'works',
                    work: '23',
                    return_panel: 'dates',
                },
            },
            $router: { replace },
        }

        methods.abortEdit.call(ctx)

        expect(ctx.action).toBe('')
        expect(ctx.selected_courseWork).toBeNull()
        expect(ctx.work_form).toEqual({ groups: [] })
        expect(ctx.show_dates).toBe(true)
        expect(ctx.show_works).toBe(false)
        expect(replace).toHaveBeenCalledWith({
            query: {
                course: '20',
                date: '370',
                grades: 'sem1,sem2,year',
                panel: 'dates',
            },
        })
    })

    it('adds vertical spacing between work list items', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('class="work-list-item cursor-pointer"')
        expect(source).toContain('.work-list-item {\n    margin-bottom: 12px;\n}')
        expect(source).toContain('.work-list-item:last-child {\n    margin-bottom: 0;\n}')
    })

    it('renders the Arbeiten list as a responsive two-column grid', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('<v-list density="compact" class="work-list-grid">')
        expect(source).toContain('.work-list-grid {')
        expect(source).toContain('display: grid;')
        expect(source).toContain('@media (min-width: 900px)')
        expect(source).toContain('grid-template-columns: repeat(2, minmax(0, 1fr));')
        expect(source).toContain('.work-list-grid .work-list-item {')
        expect(source).toContain('align-self: stretch;')
        expect(source).toContain('.work-list-grid .work-list-item :deep(.v-list-item__content)')
    })

    it('renders work entries with type-based background colors', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')
        const methods = (CourseWorks as any).methods
        const firstTypeClass = methods.workEntryBackgroundClass.call({}, { type: 'SA' })
        const sameTypeClass = methods.workEntryBackgroundClass.call({}, { type: 'SA' })
        const emptyTypeClass = methods.workEntryBackgroundClass.call({}, { type: '' })

        expect(source).toContain('class="work-row w-100" :class="workEntryBackgroundClass(work)"')
        expect(source).toContain('.work-row--type-1 {')
        expect(source).toContain('border-radius: 8px;')
        expect(source).toContain('padding: 8px;')
        expect(firstTypeClass).toBe(sameTypeClass)
        expect(firstTypeClass).toMatch(/^work-row--type-[1-6]$/)
        expect(emptyTypeClass).toBe('work-row--type-empty')
    })

    it('renders work detail students as two-column colored blocks', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')
        const methods = (CourseWorks as any).methods
        const firstStudentClass = methods.studentBlockBackgroundClass.call({}, 11)
        const otherStudentClass = methods.studentBlockBackgroundClass.call({}, 12)
        const emptyStudentClass = methods.studentBlockBackgroundClass.call({}, null)

        expect(source).toContain('class="mt-3 student-card-grid"')
        expect(source).toContain('class="pa-3 student-card-block"')
        expect(source).toContain('class="pa-2 student-card-block"')
        expect(source).toContain(':class="studentBlockBackgroundClass(row.studentId)"')
        expect(source).toContain('<div v-else class="mt-3 student-card-grid">')
        expect(source).not.toContain('toggleChipGradingView')
        expect(source).not.toContain('Chip-Ansicht')
        expect(source).not.toContain('student-panel-grid')
        expect(source).not.toContain('v-expansion-panels v-else')
        expect(source).toContain('grid-template-columns: repeat(2, minmax(0, 1fr));')
        expect(source).toContain('.student-card-block--student {')
        expect(firstStudentClass).toBe(otherStudentClass)
        expect(firstStudentClass).toBe('student-card-block--student')
        expect(emptyStudentClass).toBe('student-card-block--type-empty')
    })

    it('preserves line breaks in student comment previews', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('class="text-caption text-medium-emphasis mt-1 student-comment-preview"')
        expect(source).toContain('const commentPreview = (commentValue || \'\').toString().trim()')
        expect(source).not.toContain(".trim().slice(0, 120)")
        expect(source).toContain('.student-comment-preview {')
        expect(source).toContain('white-space: pre-wrap;')
        expect(source).toContain('overflow-wrap: anywhere;')
    })

    it('keeps the full new work form editable from the beginning', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')
        const methods = (CourseWorks as any).methods
        const state = {
            emptyWorkForm: methods.emptyWorkForm,
            buildIndividualGroups: () => [],
            selected_course: { id: 20 },
            closeCommentDialog: vi.fn(),
            $nextTick: vi.fn(),
        }

        methods.newWork.call(state)

        expect(source).toContain(':disabled="is_saving || isEditingExistingDetails"')
        expect(source).toContain(':style="isEditingExistingDetails ? \'pointer-events:none; opacity:0.45\' : \'\'"')
        expect(source).toContain('<template v-if="action === \'new_course_work\' || details_editable">')
        expect(source).toContain('isEditingExistingDetails()')
        expect(state.details_editable).toBe(false)
        expect(state.action).toBe('new_course_work')
    })

    it('prevents saving work without a selected type', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')
        const computed = (CourseWorks as any).computed

        expect(source).not.toContain('v-if="!hasSelectedWorkType"')
        expect(source).toContain(':disabled="!canSaveWork"')
        expect(source).toContain('if (!this.hasSelectedWorkType) {')
        expect(source).toContain('useNotificationStore().notify({')
        expect(source).toContain("type: 'warning'")
        expect(computed.hasSelectedWorkType.call({ selectedTypeWork: null })).toBe(false)
        expect(computed.canSaveWork.call({
            is_saving: false,
            isEditingExistingDetails: false,
            hasSelectedWorkType: false,
        })).toBe(false)
    })

    it('keeps group size available immediately for group work', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('label="Gruppengröße"')
        expect(source).toContain('v-model.number="work_form.group_size"')
        expect(source).not.toContain('<v-text-field\n                                            v-if="work_form.is_random_groups"')
        expect(source).not.toContain('v-if="work_form.is_random_groups && hasUnassignedStudents"')
        expect(source).toContain("{{ work_form.groups?.length ? 'Neu erstellen' : 'Erstellen' }}")
    })

    it('can regenerate random groups when all students are already assigned', () => {
        const methods = (CourseWorks as any).methods
        const state = {
            activeCourseStudents: [
                { id: 1 },
                { id: 2 },
                { id: 3 },
                { id: 4 },
            ],
            work_form: {
                date_for_all_groups: '2026-05-16',
                group_size: 2,
                groups: [
                    { student_ids: [1, 2] },
                    { student_ids: [3, 4] },
                ],
            },
        }

        methods.generateRandomGroups.call(state)

        expect(state.work_form.groups).toHaveLength(2)
        expect(state.work_form.groups.flatMap((group) => group.student_ids).sort()).toEqual(['1', '2', '3', '4'])
    })

    it('locks the group-work switch once a work exists', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')
        const computed = (CourseWorks as any).computed

        expect(source).toContain('v-if="!canChangeGroupWorkMode"')
        expect(source).toContain('<v-switch\n                                    v-else')
        expect(source).toContain('if (!this.canChangeGroupWorkMode) return')
        expect(computed.canChangeGroupWorkMode.call({
            action: 'new_course_work',
            work_form: { id: null },
        })).toBe(true)
        expect(computed.canChangeGroupWorkMode.call({
            action: 'edit_course_work',
            work_form: { id: 10 },
        })).toBe(false)
        expect(computed.canChangeGroupWorkMode.call({
            action: 'new_course_work',
            work_form: { id: 10 },
        })).toBe(false)
    })

    it('ignores group-work switch changes for saved works', () => {
        const methods = (CourseWorks as any).methods
        const state = {
            work_form: {
                id: 10,
                is_group_work: false,
                is_random_groups: false,
                group_size: null,
                groups: [],
            },
            canChangeGroupWorkMode: false,
            pending_group_work: null,
            group_work_switch_key: 0,
            toBoolean: methods.toBoolean,
        }

        methods.setGroupWork.call(state, true)

        expect(state.work_form.is_group_work).toBe(false)
        expect(state.pending_group_work).toBeNull()
        expect(state.group_work_switch_key).toBe(0)
    })

    it('allows group-work switch changes while creating a new work', () => {
        const methods = (CourseWorks as any).methods
        const state = {
            work_form: {
                id: null,
                is_group_work: false,
                is_random_groups: false,
                group_size: null,
                groups: [],
            },
            canChangeGroupWorkMode: true,
            pending_group_work: null,
            group_work_switch_key: 0,
            is_changing_group_work_mode: false,
            toBoolean: methods.toBoolean,
            hasIndividualEntries: methods.hasIndividualEntries,
            hasGroupEntries: methods.hasGroupEntries,
            applyGroupWorkMode: methods.applyGroupWorkMode,
            syncGroupWorkModeState: methods.syncGroupWorkModeState,
            buildIndividualGroups: vi.fn(() => []),
            generateRandomGroups: vi.fn(),
        }

        methods.setGroupWork.call(state, true)

        expect(state.pending_group_work).toBeNull()
        expect(state.work_form.is_group_work).toBe(true)
        expect(state.work_form.group_size).toBe(2)
        expect(state.work_form.groups).toEqual([])
    })

    it('shows the current work mode clearly in the edit form', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')
        const computed = (CourseWorks as any).computed

        expect(source).toContain('class="work-mode-chip"')
        expect(source).toContain('v-if="!canChangeGroupWorkMode"')
        expect(source).toContain('{{ workModeLabel }}')
        expect(computed.workModeLabel.call({ work_form: { is_group_work: true } })).toBe('Gruppenarbeit')
        expect(computed.workModeLabel.call({ work_form: { is_group_work: false } })).toBe('Einzelarbeit')
        expect(computed.workModeIcon.call({ work_form: { is_group_work: true } })).toBe('mdi-account-group')
        expect(computed.workModeIcon.call({ work_form: { is_group_work: false } })).toBe('mdi-account')
    })

    it('does not treat empty serialized student rows as filled entries', () => {
        const methods = (CourseWorks as any).methods

        expect(methods.hasFilledStudentValues([{ student_id: 1, grade: '' }], 'grade')).toBe(false)
        expect(methods.hasFilledStudentValues([{ student_id: 1, grade: '3' }], 'grade')).toBe(true)
    })

    it('renders whether each work list item is group work in the type row', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('{{ workTypeModeLabel(work) }}')
        expect(source).toContain('workTypeModeLabel(work) {')
        expect(source).toContain("const modeLabel = work?.is_group_work ? 'Gruppenarbeit' : 'Einzelarbeit'")
        expect(source).toContain('.work-meta-row {')
        expect(source).toContain('justify-content: space-between;')
        expect(source).not.toContain('class="work-mode-chip ml-1"')
        expect(source).not.toContain('<span> - {{ work.is_group_work ? \'Gruppenarbeit\' : \'Einzelarbeit\' }}</span>')
    })

    it('shows an exclamation marker for group works with unassigned students', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')
        const methods = (CourseWorks as any).methods
        const ctx = {
            activeCourseStudents: [
                { id: 1 },
                { id: 2 },
                { id: 3 },
            ],
        }

        expect(source).toContain('v-if="workHasUnassignedStudents(work)"')
        expect(source).toContain('class="work-unassigned-chip"')
        expect(source).toContain('Nicht alle Schüler:innen sind einer Gruppe zugeordnet')
        expect(methods.workHasUnassignedStudents.call(ctx, {
            is_group_work: true,
            groups: [{ student_ids: [1, 2] }],
        })).toBe(true)
        expect(methods.workHasUnassignedStudents.call(ctx, {
            is_group_work: true,
            groups: [{ student_ids: [1, 2, 3] }],
        })).toBe(false)
        expect(methods.workHasUnassignedStudents.call(ctx, {
            is_group_work: false,
            groups: [{ student_ids: [1] }],
        })).toBe(false)
    })

    it('renders grade summaries in the group-work group list', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('class="group-grade-overview mt-2"')
        expect(source).toContain('Einzelwertung')
        expect(source).toContain('class="group-grade-list"')
        expect(source).toContain('v-for="row in groupStudentGradeRows(group)"')
        expect(source).toContain('{{ row.studentLabel }}')
        expect(source).toContain('{{ row.gradeLabel }}')
        expect(source).toContain('v-if="row.commentPreview"')
        expect(source).toContain('{{ row.commentPreview }}')
        expect(source).toContain('Gruppenwertung')
        expect(source).toContain('Note: {{ sharedGroupGradeLabel(group) }}')
    })

    it('keeps the group comment field available for individual group grading', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain("v-model=\"work_form.groups[group_dialog_index].comment\"")
        expect(source).toContain(":label=\"work_form.groups[group_dialog_index].use_individual_grades ? 'Kommentar (Gruppe)' : 'Kommentar (für alle)'\"")
        expect(source).not.toContain('v-if="!work_form.groups[group_dialog_index].use_individual_grades"\n                                            v-model="work_form.groups[group_dialog_index].comment"')
        expect(source).toContain("comment: group.comment ?? ''")
    })
})

describe('CourseWorks course-specific schema', () => {
    it('prefers the selected course schema snapshot over the global teaching store', () => {
        const computed = (CourseWorks as any).computed
        const ctx: Record<string, unknown> = {
            selected_course: {
                teaching_schema_id: 'schema-teacher',
                teacher_teaching_schema: {
                    id: 'schema-teacher',
                    works: [{ short_name: 'MA', name: 'Mitarbeit' }],
                    grading: { semester_count: 2 },
                },
            },
            teachingStore: {
                schemaById: () => ({ id: 'schema-global', works: [{ short_name: 'AK', name: 'Auftrag' }], grading: { semester_count: 1 } }),
            },
        }

        ctx.selectedCourseSchema = computed.selectedCourseSchema.call(ctx)

        expect((ctx.selectedCourseSchema as any).id).toBe('schema-teacher')
        expect(computed.teachingWorks.call(ctx)).toEqual([{ short_name: 'MA', name: 'Mitarbeit' }])
        expect(computed.semesterCount.call(ctx)).toBe(2)
    })
})

describe('CourseWorks points mode', () => {
    it('shows the Punkte action only for non-group work types with points-note configuration', () => {
        const computed = (CourseWorks as any).computed
        const ctx: Record<string, unknown> = {
            work_form: { type: 'SA', is_group_work: false },
            teachingWorks: [
                {
                    short_name: 'SA',
                    name: 'Schularbeit',
                    points_note_enabled: true,
                    points_table: [{ grade: '1', min_points: 40 }],
                    points_sonst_grade: '5',
                },
            ],
            workConfigForType(type: string) {
                return (this.teachingWorks as Array<Record<string, unknown>>).find((work) => work.short_name === type) ?? null
            },
            workSupportsPoints: (work: Record<string, unknown>) => Boolean(work?.points_note_enabled),
        }

        ctx.selectedTypeWork = computed.selectedTypeWork.call(ctx)

        expect(computed.selectedTypeSupportsPoints.call(ctx)).toBe(true)
        expect(computed.selectedTypeSupportsPoints.call({
            ...ctx,
            work_form: { type: 'SA', is_group_work: true },
        })).toBe(false)
    })

    it('derives the grade from entered points for per-work points tables', () => {
        const methods = (CourseWorks as any).methods
        const ctx: Record<string, any> = {
            work_form: {
                groups: [{
                    student_ids: [11],
                    grades: {},
                    comments: {},
                    points: {},
                }],
            },
            selectedTypeWork: {
                short_name: 'SA',
                points_note_enabled: true,
                points_table: [
                    { grade: '1', min_points: 40 },
                    { grade: '2', min_points: 35 },
                    { grade: '3', min_points: 30 },
                    { grade: '4', min_points: 25 },
                ],
                points_sonst_grade: '5',
            },
            normalizeNumericInput: methods.normalizeNumericInput,
            normalizePointsNumber: methods.normalizePointsNumber,
            workSupportsPoints: methods.workSupportsPoints,
            gradeFromPointsForWork: methods.gradeFromPointsForWork,
        }

        methods.setStudentPoints.call(ctx, 0, 11, '37,5')

        expect(ctx.work_form.groups[0].points[11]).toBe('37,5')
        expect(ctx.work_form.groups[0].grades[11]).toBe('2')
    })

    it('does not enable per-work point entry from semester point thresholds alone', () => {
        const methods = (CourseWorks as any).methods

        expect(methods.workSupportsPoints.call({}, {
            points_note_enabled: false,
            semester_points_table: [{ grade: '1', min_points: 5 }],
            semester_points_sonst_grade: '5',
        })).toBe(false)
    })

    it('serializes numeric points with student ids for saving', () => {
        const methods = (CourseWorks as any).methods
        const ctx: Record<string, any> = {
            normalizeNumericInput: methods.normalizeNumericInput,
            normalizePointsNumber: methods.normalizePointsNumber,
        }

        const result = methods.serializeGroupPoints.call(ctx, {
            student_ids: [11, 12, 13],
            points: {
                11: '42',
                12: '37,5',
                13: '',
            },
        })

        expect(result).toEqual([
            { student_id: 11, points: 42 },
            { student_id: 12, points: 37.5 },
        ])
    })

    it('renders the group dialog grade picker as clickable chips', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('class="d-flex flex-wrap ga-1 group-dialog-grade-chips"')
        expect(source).toContain('@click="setGroupGrade(group_dialog_index, grade.value)"')
        expect(source).not.toContain('v-model="work_form.groups[group_dialog_index].grade"')
    })

    it('renders group dialog students as removable chips with an inline add area toggle', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain(":icon=\"group_dialog_add_students_open ? 'mdi-close' : 'mdi-plus'\"")
        expect(source).toContain('v-if="group_dialog_add_students_open" class="group-dialog-add-students"')
        expect(source).toContain('@click="addStudentToGroup(group_dialog_index, student.value)"')
        expect(source).toContain('@click:close="removeStudentFromGroup(group_dialog_index, studentId)"')
        expect(source).not.toContain('<v-menu>')
    })

    it('opens the new group dialog with the student picker expanded', () => {
        const methods = (CourseWorks as any).methods
        const ctx: Record<string, any> = {
            work_form: {
                date_for_all_groups: '2026-05-16',
                groups: [
                    { student_ids: [1, 2] },
                ],
            },
            group_dialog_index: null,
            group_dialog_open: false,
            group_dialog_add_students_open: false,
        }

        methods.addGroup.call(ctx)

        expect(ctx.work_form.groups).toHaveLength(2)
        expect(ctx.group_dialog_index).toBe(1)
        expect(ctx.group_dialog_open).toBe(true)
        expect(ctx.group_dialog_add_students_open).toBe(true)
        expect(ctx.work_form.groups[1]).toMatchObject({
            student_ids: [],
            date: '2026-05-16',
            use_individual_grades: false,
        })
    })

    it('updates the shared group grade when a chip is selected', () => {
        const methods = (CourseWorks as any).methods
        const ctx: Record<string, any> = {
            work_form: {
                groups: [{ grade: '3' }],
            },
        }

        methods.setGroupGrade.call(ctx, 0, '1')
        expect(ctx.work_form.groups[0].grade).toBe('1')

        methods.setGroupGrade.call(ctx, 0, '')
        expect(ctx.work_form.groups[0].grade).toBe('')
    })

    it('adds and removes students in the group dialog through helper methods', () => {
        const methods = (CourseWorks as any).methods
        const ctx: Record<string, any> = {
            work_form: {
                groups: [{ student_ids: [3] }],
            },
            group_dialog_add_students_open: true,
            availableStudentItems(groupIndex: number) {
                expect(groupIndex).toBe(0)

                const items = [
                    { value: 2, title: '1A Beta, Bea' },
                    { value: 3, title: '1A Alpha, Ada' },
                ]

                const selectedIds = new Set((this.work_form.groups[groupIndex].student_ids || []).map(String))

                return items.filter((item) => !selectedIds.has(String(item.value)))
            },
            updateGroupStudents(group: Record<string, any>, ids: number[]) {
                group.student_ids = [...ids].sort((a, b) => a - b)
            },
        }

        methods.addStudentToGroup.call(ctx, 0, 2)
        expect(ctx.work_form.groups[0].student_ids).toEqual([2, 3])

        methods.addStudentToGroup.call(ctx, 0, 5)
        expect(ctx.work_form.groups[0].student_ids).toEqual([2, 3])
        expect(ctx.group_dialog_add_students_open).toBe(false)

        methods.removeStudentFromGroup.call(ctx, 0, 3)
        expect(ctx.work_form.groups[0].student_ids).toEqual([2])
    })

    it('does not list students that are already in the current group as available', () => {
        const methods = (CourseWorks as any).methods
        const ctx: Record<string, any> = {
            work_form: {
                groups: [
                    { student_ids: [3] },
                    { student_ids: [9] },
                ],
            },
            studentItems: [
                { value: 2, title: '1A Beta, Bea' },
                { value: 3, title: '1A Alpha, Ada' },
                { value: 9, title: '1A Delta, Dan' },
            ],
        }

        expect(methods.availableStudentItems.call(ctx, 0)).toEqual([
            { value: 2, title: '1A Beta, Bea' },
        ])
    })

    it('toggles the inline add-students area in the group dialog', () => {
        const methods = (CourseWorks as any).methods
        const ctx: Record<string, any> = {
            group_dialog_add_students_open: false,
        }

        methods.toggleGroupStudentPicker.call(ctx)
        expect(ctx.group_dialog_add_students_open).toBe(true)

        methods.toggleGroupStudentPicker.call(ctx)
        expect(ctx.group_dialog_add_students_open).toBe(false)
    })

    it('shows processing before applying a bulk grade', async () => {
        const methods = (CourseWorks as any).methods
        let sawProcessingState = false
        const ctx: Record<string, any> = {
            is_applying_bulk_action: false,
            selected_student_ids: [11],
            bulk_grade: '1',
            bulk_comment: '',
            work_form: {
                groups: [
                    {
                        student_ids: [11, 12],
                        grades: {},
                        comments: {},
                    },
                ],
            },
            async waitForBulkActionPaint() {
                sawProcessingState = this.is_applying_bulk_action
            },
        }

        await methods.applyBulkAction.call(ctx)

        expect(sawProcessingState).toBe(true)
        expect(ctx.work_form.groups[0].grades).toEqual({ 11: '1' })
        expect(ctx.work_form.groups[0].comments).toEqual({})
        expect(ctx.bulk_grade).toBeNull()
        expect(ctx.selected_student_ids).toEqual([])
        expect(ctx.is_applying_bulk_action).toBe(false)
    })
})

describe('CourseWorks grade distribution', () => {
    it('builds per-student group grade rows for individual group grading', () => {
        const methods = (CourseWorks as any).methods
        const ctx: Record<string, any> = {
            work_form: { type: 'PR' },
            activeCourseStudents: [
                { id: 2, schoolclass: '1A', last_name: 'Beta', first_name: 'Bea' },
                { id: 1, schoolclass: '1A', last_name: 'Alpha', first_name: 'Ada' },
            ],
            teachingWorks: [
                {
                    short_name: 'PR',
                    grades: [
                        { grade: '1' },
                        { grade: '2' },
                    ],
                },
            ],
        }
        Object.assign(ctx, methods)

        const result = methods.groupStudentGradeRows.call(ctx, {
            student_ids: [2, 1],
            use_individual_grades: true,
            grades: {
                1: '1',
                2: '',
            },
            comments: {
                1: 'Sehr gute Mitarbeit',
                2: '',
            },
        })

        expect(result).toEqual([
            {
                studentId: 1,
                studentLabel: '1A Alpha, Ada',
                gradeValue: '1',
                gradeLabel: '1',
                commentValue: 'Sehr gute Mitarbeit',
                commentPreview: 'Sehr gute Mitarbeit',
            },
            {
                studentId: 2,
                studentLabel: '1A Beta, Bea',
                gradeValue: '',
                gradeLabel: 'Keine Note',
                commentValue: '',
                commentPreview: '',
            },
        ])
    })

    it('builds a shared group grade label for group grading', () => {
        const methods = (CourseWorks as any).methods
        const ctx: Record<string, any> = {
            work_form: { type: 'PR' },
            teachingWorks: [
                {
                    short_name: 'PR',
                    grades: [
                        { grade: '1' },
                        { grade: '2' },
                    ],
                },
            ],
        }
        Object.assign(ctx, methods)

        expect(methods.sharedGroupGradeLabel.call(ctx, { grade: '2' })).toBe('2')
        expect(methods.sharedGroupGradeLabel.call(ctx, { grade: '' })).toBe('Keine Note')
    })

    it('counts individual grades and missing grades for a work item', () => {
        const methods = (CourseWorks as any).methods
        const ctx: Record<string, any> = {
            activeCourseStudents: [
                { id: 1 },
                { id: 2 },
                { id: 3 },
                { id: 4 },
            ],
            teachingWorks: [
                {
                    short_name: 'SA',
                    grades: [
                        { grade: '1' },
                        { grade: '2' },
                        { grade: '3' },
                    ],
                },
            ],
        }
        Object.assign(ctx, methods)

        const result = methods.workGradeDistribution.call(ctx, {
            id: 10,
            type: 'SA',
            is_group_work: false,
            groups: [
                { student_ids: [1], grades: { 1: '1' } },
                { student_ids: [2], grades: { 2: '2' } },
                { student_ids: [3], grades: { 3: '2' } },
                { student_ids: [4], grades: { 4: '' } },
            ],
        })

        expect(result).toEqual([
            { grade: '1', count: 1, color: 'primary' },
            { grade: '2', count: 2, color: 'primary' },
            { grade: 'Offen', count: 1, color: 'warning' },
        ])
    })

    it('counts a shared group grade for every student in the group', () => {
        const methods = (CourseWorks as any).methods
        const ctx: Record<string, any> = {
            activeCourseStudents: [
                { id: 1 },
                { id: 2 },
                { id: 3 },
                { id: 4 },
                { id: 5 },
            ],
            teachingWorks: [
                {
                    short_name: 'PR',
                    grades: [
                        { grade: '1' },
                        { grade: '2' },
                    ],
                },
            ],
        }
        Object.assign(ctx, methods)

        const result = methods.workGradeDistribution.call(ctx, {
            id: 11,
            type: 'PR',
            is_group_work: true,
            groups: [
                { student_ids: [1, 2, 3], grade: '1', grades: {} },
                { student_ids: [4, 5], grade: '2', grades: {} },
            ],
        })

        expect(result).toEqual([
            { grade: '1', count: 3, color: 'primary' },
            { grade: '2', count: 2, color: 'primary' },
        ])
    })

    it('does not count canceled students in the distribution', () => {
        const methods = (CourseWorks as any).methods
        const ctx: Record<string, any> = {
            activeCourseStudents: [
                { id: 1 },
                { id: 2 },
            ],
            teachingWorks: [
                {
                    short_name: 'SA',
                    grades: [
                        { grade: '1' },
                        { grade: '5' },
                    ],
                },
            ],
        }
        Object.assign(ctx, methods)

        const result = methods.workGradeDistribution.call(ctx, {
            id: 12,
            type: 'SA',
            is_group_work: false,
            groups: [
                { student_ids: [1], grades: { 1: '1' } },
                { student_ids: [2], grades: { 2: '1' } },
                { student_ids: [3], grades: { 3: '5' } },
            ],
        })

        expect(result).toEqual([
            { grade: '1', count: 2, color: 'primary' },
        ])
    })
})
