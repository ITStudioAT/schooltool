import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createVuetify } from 'vuetify'
import { VBtn } from 'vuetify/components'
import WorkDeliveryStatus from '@/pages/admin/teaching/overview/components/WorkDeliveryStatus.vue'
import { clearWorkDropboxStates, workDropboxStates } from '@/helpers/workDropbox'

afterEach(() => { clearWorkDropboxStates(); vi.unstubAllGlobals() })

it('saves a reversible manual dispatch completion and unlocks collection without claiming sent mail', async () => {
    const work = { id: 64, teaching_course_id: 16, title: 'Übung', groups: [{ student_ids: [1] }], status: {} }
    const saved = { ...work, status: { task_dispatch_completed: true } }
    const put = vi.fn().mockResolvedValueOnce({ data: { data: saved } })
        .mockResolvedValueOnce({ data: { data: work } })
    vi.stubGlobal('axios', { put })
    const wrapper = mount(WorkDeliveryStatus, { props: { work }, global: {
        plugins: [createTestingPinia({ createSpy: vi.fn }), createVuetify({ components: { VBtn } })],
        components: { 'v-btn': VBtn }, stubs: { 'v-btn': false, VBtn: false,
            'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' } },
    } })
    try {
        expect(wrapper.get('[data-workflow-stage="collection"]').element.disabled).toBe(true)
        await wrapper.get('[aria-label="E-Mails noch nicht versandt"]').trigger('click')
        await wrapper.findAll('button').find(button => button.text() === 'Als erledigt markieren')!.trigger('click')
        await flushPromises()
        expect(put).toHaveBeenCalledWith('/api/admin/teaching/course_works/64', { task_dispatch_completed: true })
        expect(wrapper.emitted('saved')).toEqual([[saved]])
        await wrapper.setProps({ work: saved })
        expect(wrapper.get('[data-workflow-stage="collection"]').element.disabled).toBe(false)
        await wrapper.get('[aria-label="E-Mail-Versand: manuell erledigt"]').trigger('click')
        expect(wrapper.get('[role="dialog"]').text()).toContain('Nächster Schritt: Abgaben einsammeln.')
        expect(wrapper.get('[role="dialog"]').text()).toContain('bestätigt keinen E-Mail-Versand')
        expect(wrapper.get('[role="dialog"]').text()).not.toContain('Nachrichten bestätigt')
        await wrapper.findAll('button').find(button => button.text() === 'Markierung aufheben')!.trigger('click')
        await flushPromises()
        expect(put).toHaveBeenLastCalledWith('/api/admin/teaching/course_works/64', { task_dispatch_completed: false })
        await wrapper.setProps({ work })
        expect(wrapper.get('[data-workflow-stage="collection"]').element.disabled).toBe(true)
    } finally { wrapper.unmount() }
})

it('keeps collection blocked and shows an error when manual completion cannot be saved', async () => {
    vi.stubGlobal('axios', { put: vi.fn().mockRejectedValue({ response: { data: { message: 'Speichern fehlgeschlagen.' } } }) })
    const wrapper = mount(WorkDeliveryStatus, { props: { work: { id: 64, title: 'Übung', groups: [{ student_ids: [1] }] } }, global: {
        plugins: [createTestingPinia({ createSpy: vi.fn }), createVuetify({ components: { VBtn } })],
        components: { 'v-btn': VBtn }, stubs: { 'v-btn': false, VBtn: false,
            'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue"><slot /></section>' } },
    } })
    try {
        await wrapper.get('[aria-label="E-Mails noch nicht versandt"]').trigger('click')
        await wrapper.findAll('button').find(button => button.text() === 'Als erledigt markieren')!.trigger('click')
        await flushPromises()
        expect(wrapper.get('[role="alert"]').text()).toContain('Speichern fehlgeschlagen.')
        expect(wrapper.emitted('saved')).toBeUndefined()
        expect(wrapper.get('[data-workflow-stage="collection"]').element.disabled).toBe(true)
    } finally { wrapper.unmount() }
})

it('opens results transfer for the existing work rather than requesting a new folder link', async () => {
    const work = { id: 86, teaching_course_id: 16, title: 'E-Mails', groups: [{ student_ids: [492] }], status: {
        assessment_json_imports: [{ student_id: 492, exercise_id: 'exercise', participant_id: 'user:492', record_checksum: 'a'.repeat(64),
            record: { participant_id: 'user:492', record_checksum: 'a'.repeat(64), submission_state: 'received', evaluation_state: 'complete', evaluation_completed_at: '2026-10-24T16:00:00Z' } }],
    } }
    const wrapper = mount(WorkDeliveryStatus, { props: { work, date: '2026-10-16' }, global: {
        plugins: [createTestingPinia({ createSpy: vi.fn })], stubs: {
            'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
            'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue"><slot /></section>' },
        },
    } })
    try {
        await wrapper.get('[data-workflow-stage="publication"]').trigger('click')
        await wrapper.findAll('button').find(button => button.text() === 'Ergebnisse übernehmen')!.trigger('click')
        expect(wrapper.emitted('import')).toEqual([[work, false, { date: '2026-10-16' }]])
    } finally { wrapper.unmount() }
})

it('offers grading in the evaluation dialog footer after simulator collection and disables it before collection', async () => {
    const work = { id: 86, teaching_course_id: 16, title: 'E-Mails', groups: [{ student_ids: [492] }],
        simulation_collection: { origin: 'mailpit_simulation', simulation: true, run_id: 'simulator-run', work_id: 86, course_id: 16,
            student_ids: [492], collected_count: 1, non_submission_count: 0, processed_count: 1, complete: true,
            completed_at: '2026-10-24T15:35:00Z', last_collected_at: '2026-10-24T15:35:00Z' } }
    const wrapper = mount(WorkDeliveryStatus, { props: { work }, global: {
        plugins: [createTestingPinia({ createSpy: vi.fn })],
        stubs: { 'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
            'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' } },
    } })
    try {
        await wrapper.get('[data-workflow-stage="evaluation"]').trigger('click')
        const grade = wrapper.get('[aria-label="Dialogaktionen"]').findAll('button').find(button => button.text() === 'Bewerten')
        expect(grade.exists()).toBe(true)
        expect(grade.element.disabled).toBe(false)
        await wrapper.setProps({ work: { ...work, simulation_collection: { ...work.simulation_collection,
            collected_count: 0, processed_count: 0, complete: false, completed_at: null, last_collected_at: null } } })
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
        expect(wrapper.get('[data-workflow-stage="evaluation"]').element.disabled).toBe(true)
    } finally { wrapper.unmount() }
})

it('shows the repeat collection completion in Vienna time without replacing it with the later storage time', async () => {
    const wrapper = mount(WorkDeliveryStatus, { props: { work: { id: 64, teaching_course_id: 16, title: 'E-Mails',
        groups: [{ student_ids: [1] }], status: { dispatch_logs: [{ origin: 'dispatch_import', purpose: 'tasks', sha256: 'a'.repeat(64) }],
            dispatch_notifications: [{ origin: 'dispatch_import', purpose: 'tasks', student_id: 1, sent_at: '2026-10-24T12:00:00Z', log_sha256: 'a'.repeat(64) }] },
        collection_run: { origin: 'dropbox_collection_run', work_id: 64, course_id: 16,
            sha256: 'e'.repeat(64), completed_at: '2026-10-24T13:46:16Z', stored_at: '2026-10-24T13:48:22Z' } } },
        global: { plugins: [createTestingPinia({ createSpy: vi.fn }), createVuetify({ components: { VBtn } })],
            components: { 'v-btn': VBtn }, stubs: { 'v-btn': false, VBtn: false,
                'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' } } } })
    try {
        await wrapper.get('[data-workflow-stage="collection"]').trigger('click')
        expect(wrapper.get('[role="dialog"]').text()).toContain('Letztes belegtes Einsammeln: 24.10.2026, 15:46 Uhr')
        expect(wrapper.get('[role="dialog"]').text()).not.toContain('15:48 Uhr')
        expect(wrapper.get('[data-workflow-stage="collection"]').attributes('data-workflow-state')).toBe('open')
    } finally { wrapper.unmount() }
})

it('keeps later stages visible but disabled until earlier steps are complete', async () => {
    const wrapper = mount(WorkDeliveryStatus, { props: { work: { id: 64, title: 'E-Mails', groups: [{ student_ids: [1, 2] }] } }, global: {
        plugins: [createTestingPinia({ createSpy: vi.fn }), createVuetify({ components: { VBtn } })], components: { 'v-btn': VBtn },
        stubs: { 'v-btn': false, VBtn: false, 'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' } } } })
    try {
        await wrapper.get('[data-workflow-stage="evaluation"]').trigger('click')
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
        for (const key of ['evaluation', 'publication', 'notification']) {
            expect(wrapper.get(`[data-workflow-stage="${key}"]`).element.disabled).toBe(true)
        }
        expect(wrapper.findAll('[data-workflow-stage]')).toHaveLength(4)
    } finally { wrapper.unmount() }
})

it('enables collection only for confirmed task emails in the selected group', async () => {
    const work = { id: 64, teaching_course_id: 16, title: 'E-Mails', groups: [
        { date: '2026-10-16', student_ids: [1, 2] }, { date: '2026-10-17', student_ids: [3] },
    ], status: { dispatch_logs: [{ origin: 'dispatch_import', purpose: 'tasks', sha256: 'a'.repeat(64) }],
        dispatch_notifications: [{ origin: 'dispatch_import', purpose: 'tasks', student_id: 1, sent_at: '2026-10-14T12:00:00Z', log_sha256: 'a'.repeat(64) }] } }
    const wrapper = mount(WorkDeliveryStatus, { props: { work, date: '2026-10-16' }, global: {
        plugins: [createTestingPinia({ createSpy: vi.fn }), createVuetify({ components: { VBtn } })], components: { 'v-btn': VBtn },
        stubs: { 'v-btn': false, VBtn: false } } })
    try {
        expect(wrapper.get('[data-workflow-stage="collection"]').element.disabled).toBe(false)
        await wrapper.setProps({ date: '2026-10-17' })
        expect(wrapper.get('[data-workflow-stage="collection"]').element.disabled).toBe(true)
    } finally { wrapper.unmount() }
})

it.each(['missing', 'sent', 'partial', 'unknown'])('shows the saved dispatch state and opens its details: %s', async status => {
    workDropboxStates.set('7:16:65', { exercise: { status: 'found', folder: { name: '2026-10-16_E-Mails' },
        email_dispatch: { status, confirmed_count: status === 'sent' ? 15 : 0, expected_count: 15, transport: 'mailpit', sent_at: '2026-10-23T22:23:34Z' } } })
    const wrapper = mount(WorkDeliveryStatus, { props: { work: { id: 65, teaching_course_id: 16, title: 'E-Mails', date_for_all_groups: '2026-10-16' } },
        global: { plugins: [createTestingPinia({ createSpy: vi.fn, initialState: { AdminAdminStore: { config: { user: { id: 7 } } } } }), createVuetify({ components: { VBtn } })],
            components: { 'v-btn': VBtn }, stubs: { 'v-btn': false, VBtn: false, 'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' } } } })
    try {
        expect(wrapper.findAll('button')).toHaveLength(5)
        expect(wrapper.get('[data-workflow-stage="collection"]').element.disabled).toBe(status !== 'sent')
        await wrapper.findAll('button')[0].trigger('click')
        if (status === 'sent') expect(wrapper.get('[role="dialog"]').text()).toContain('15 von 15 Nachrichten bestätigt')
        else expect(wrapper.get('[role="dialog"]').text()).not.toContain('15 von 15 Nachrichten bestätigt')
        if (status === 'missing') {
            await wrapper.setProps({ date: '2026-10-16', state: { status: 'found', userId: 7, workId: 65, courseId: 16, date: '2026-10-16',
                emailDispatch: { status: 'sent', confirmed_count: 15, expected_count: 15, transport: 'mailpit' } } })
            expect(wrapper.get('[role="dialog"]').text()).toContain('15 von 15 Nachrichten bestätigt')
            expect(wrapper.find('[data-workflow-stage="collection"]').exists()).toBe(true)
            expect(wrapper.get('[data-workflow-stage="collection"]').element.disabled).toBe(false)
        }
    } finally { wrapper.unmount() }
})
