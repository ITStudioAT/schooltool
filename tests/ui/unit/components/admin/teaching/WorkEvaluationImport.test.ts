import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createVuetify } from 'vuetify'
import { VAlert, VBtn, VFileInput } from 'vuetify/components'
import WorkEvaluationImport from '@/pages/admin/teaching/overview/components/WorkEvaluationImport.vue'
import { clearWorkDropboxStates } from '@/helpers/workDropbox'

const work = { id: 65, teaching_course_id: 2, title: 'E-Mails' }
const connected = { can_preview: true, can_quick_import: true, folder: { id: 'id:work', name: 'Arbeit' } }

function evaluation(studentId: number | null, overrides = {}) {
    return {
        participant_id: `person-${studentId}`, student_id: studentId,
        identity: { first_name: 'Ada', last_name: 'Beispiel', class_name: '1A', group_name: 'INF' },
        previous: { points: 2, comments: 'Bisheriger Kommentar' }, status: 'Neu', change: 'new',
        evaluation_state: 'complete', submission_state: 'received', will_replace: true, total_minor: 400,
        event_times: { evaluation_completed_at: '2026-10-16T10:00:00Z' }, criteria: [], adjustments: [], ...overrides,
    }
}

function preview(overrides = {}) {
    return {
        target_work: { title: work.title, course_title: 'Kurs' }, exercise: {}, rows: [evaluation(11), evaluation(12, { will_replace: false, change: 'unchanged' })],
        maximum_minor: 500, can_import: true, hash: 'a'.repeat(64), ...overrides,
    }
}

function mountImporter() {
    return mount(WorkEvaluationImport, { global: {
        plugins: [createTestingPinia({ createSpy: vi.fn, initialState: {
            AdminCourseStore: { selected_course: { id: 2 } }, AdminAdminStore: { config: { user: { id: 7 } } },
        } }), createVuetify({ components: { VAlert, VBtn, VFileInput } })],
        components: { 'v-alert': VAlert, 'v-btn': VBtn, 'v-file-input': VFileInput },
        stubs: { WorkDropboxSource: true, 'v-alert': false, VAlert: false, 'v-btn': false, VBtn: false, 'v-file-input': false, VFileInput: false },
    } })
}

afterEach(() => { clearWorkDropboxStates(); vi.restoreAllMocks(); vi.unstubAllGlobals() })

describe('compact work import overview', () => {
    it.each(['upload', 'quick', 'dropbox'])('shows the same concise preview through %s without applying it', async source => {
        const post = vi.fn().mockResolvedValue({ data: { preview: preview() } })
        vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: connected }), post })
        const wrapper = mountImporter()
        try {
            const vm = wrapper.vm as any
            await vm.openImport(work, source === 'quick')
            if (source === 'upload') await vm.selectJson({ target: { files: [new File(['{}'], 'Schooltool-Bewertungen.json')] } })
            if (source === 'dropbox') await vm.previewDropbox(false)
            await flushPromises()
            const overview = wrapper.get('.work-import-overview')
            expect(overview.text()).toContain('Vorschau · 2 zugeordnete Studierende')
            expect(overview.get('ol').text()).toContain('1 Bewertung wird übernommen · 1 bleibt erhalten')
            expect(overview.text()).not.toContain('Beispiel')
            expect(overview.text()).not.toContain('16.10.2026')
            expect(wrapper.get('.work-import-details').attributes('open')).toBeUndefined()
            expect(wrapper.get('.work-import-details').text()).toContain('Beurteilung abgeschlossen')
            expect(wrapper.emitted('imported')).toBeUndefined()
            expect(post).toHaveBeenCalledTimes(1)
            expect(wrapper.findAll('button').find(button => button.text() === 'Angezeigte Änderungen übernehmen')!.attributes('disabled')).toBeUndefined()
        } finally { wrapper.unmount() }
    })

    it('counts unique assigned students across sources and keeps planned and prepared evidence separate from confirmed sends', async () => {
        const wrapper = mountImporter()
        try {
            await wrapper.setData({ import_open: true, import_work: work, json_preview: preview({
                provisions: [{ source: 'Bereitstellung.json', exercise: {}, roster_matches: true, already_imported: true,
                    rows: [{ ...evaluation(11), valid: true, events: [{ event_id: 'event', available_at: '2026-10-16T08:00:00Z' }] }] }],
                dispatches: [{ source: 'Versand.txt', purpose: 'tasks', already_imported: false, rows: [
                    { person: 'Ada Beispiel', student_id: 11, accepted: true, status: 'Live-Versand übernehmen', mode: 'live' },
                    { person: 'Bea Beispiel', student_id: 12, accepted: false, status: 'Serverseitig geplant', mode: 'scheduled' },
                    { person: 'Cora Beispiel', student_id: 13, accepted: false, status: 'Vorbereitet', mode: 'prepared' },
                ] }],
            }) })
            const overview = wrapper.get('.work-import-overview').text()
            expect(overview).toContain('3 zugeordnete Studierende')
            expect(overview).toContain('Bereitstellung für 1 Studierende: 0 neue Belege · 1 unverändert')
            expect(overview).toContain('1 mit bestätigtem Versand · 1 mit geplantem Versand · 1 nur vorbereitet')
            expect(overview).not.toContain('Beispiel')
            expect(wrapper.get('.work-import-details').attributes('open')).toBeUndefined()
        } finally { wrapper.unmount() }
    })

    it('replaces historical planning with confirmed send evidence per person and purpose', async () => {
        const wrapper = mountImporter()
        try {
            await wrapper.setData({ import_open: true, import_work: work, json_preview: preview({ rows: [], dispatches: [
                { source: 'Planung.txt', purpose: 'tasks', rows: [
                    { person: 'Ada Beispiel', student_id: 11, accepted: false, mode: 'scheduled' },
                    { person: 'Bea Beispiel', student_id: 12, accepted: false, mode: 'scheduled' },
                ] },
                { source: 'Versand.txt', purpose: 'tasks', rows: [
                    { person: 'Ada Beispiel', student_id: 11, accepted: true, mode: 'live' },
                ] },
                { source: 'Ergebnisplanung.txt', purpose: 'results', rows: [
                    { person: 'Ada Beispiel', student_id: 11, accepted: false, mode: 'scheduled' },
                ] },
            ] }) })
            const actions = wrapper.get('.work-import-overview').findAll('li')
            expect(actions[0].text()).toContain('Aufgabenversand:')
            expect(actions[0].text()).toContain('1 mit bestätigtem Versand · 1 mit geplantem Versand')
            expect(actions[0].text()).not.toContain('2 mit geplantem Versand')
            expect(actions[1].text()).toContain('Ergebnisbenachrichtigung:')
            expect(actions[1].text()).toContain('1 mit geplantem Versand')
            expect(actions[1].text()).not.toContain('mit bestätigtem Versand')
        } finally { wrapper.unmount() }
    })

    it('keeps distinct blockers visible outside collapsed evidence and prevents apply', async () => {
        const post = vi.fn()
        vi.stubGlobal('axios', { post })
        const wrapper = mountImporter()
        try {
            await wrapper.setData({ import_open: true, import_work: work, json_preview: preview({
                can_import: false,
                rows: [evaluation(null, { status: 'Person nicht eindeutig – Import gesperrt', will_replace: false })],
                provisions: [{ source: 'Bereitstellung.json', exercise: {}, roster_matches: false, rows: [] }],
                dispatches: [{ source: 'Versand.txt', purpose: 'results', rows: [{ person: 'Ada Beispiel', status: 'Providerkennung widersprüchlich – Import gesperrt' }] }],
            }) })
            const alerts = wrapper.findAllComponents(VAlert).filter(alert => !alert.element.closest('details'))
            expect(alerts.map(alert => alert.text()).join(' ')).toContain('Person nicht eindeutig – Import gesperrt')
            expect(alerts.map(alert => alert.text()).join(' ')).toContain('Bereitstellungsbeleg umfasst nicht genau alle aktiven Personen')
            expect(alerts.map(alert => alert.text()).join(' ')).toContain('Providerkennung widersprüchlich – Import gesperrt')
            expect(wrapper.findAll('button').find(button => button.text() === 'Angezeigte Änderungen übernehmen')!.attributes('disabled')).toBeDefined()
            await (wrapper.vm as any).applyJson()
            expect(post).not.toHaveBeenCalled()
        } finally { wrapper.unmount() }
    })

    it('uses success wording only after an explicit successful apply', async () => {
        const post = vi.fn().mockResolvedValueOnce({ data: { preview: preview() } })
            .mockResolvedValueOnce({ data: { data: work, messages: ['1 Bewertung übernommen.'] } })
        vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: connected }), post })
        const wrapper = mountImporter()
        try {
            const vm = wrapper.vm as any
            await vm.openImport(work, true)
            expect(wrapper.findComponent(VAlert).exists()).toBe(false)
            await vm.applyJson()
            await flushPromises()
            expect(wrapper.find('.work-import-overview').exists()).toBe(false)
            expect(wrapper.getComponent(VAlert).text()).toContain('Dropbox-Paket übernommen.')
            expect(wrapper.getComponent(VAlert).text()).toContain('1 Bewertung übernommen.')
            expect(wrapper.emitted('imported')).toHaveLength(1)
        } finally { wrapper.unmount() }
    })
})
