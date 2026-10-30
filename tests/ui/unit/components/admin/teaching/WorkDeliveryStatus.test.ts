import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createVuetify } from 'vuetify'
import { VBtn } from 'vuetify/components'
import WorkDeliveryStatus from '@/pages/admin/teaching/overview/components/WorkDeliveryStatus.vue'
import WorkGradingAction from '@/pages/admin/teaching/overview/components/WorkGradingAction.vue'
import { clearWorkDropboxStates, workDropboxStates } from '@/helpers/workDropbox'
import { workManualCompletionFields } from '@/helpers/workProgress'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'

afterEach(() => { clearWorkDropboxStates(); vi.unstubAllGlobals() })

it('opens task preparation for a local copy without a Dropbox connection or an existing release', async () => {
    const wrapper = mount(WorkDeliveryStatus, { props: { disabled: false, exerciseReady: false,
        work: { id: 91, teaching_course_id: 17, local_task_copy: true, status: { automation_enabled: false } },
    }, global: { plugins: [createTestingPinia({ createSpy: vi.fn })], stubs: {
        'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
        'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' },
        WorkStudentDistribution: { props: ['available'], template: '<button v-if="available">Im Schülerbereich bereitstellen</button>' },
    } } })
    try {
        const action = wrapper.get('[data-workflow-stage="preparation"]')
        expect((action.element as HTMLButtonElement).disabled).toBe(false)
        await action.trigger('click')
        expect(wrapper.get('[role="dialog"]').text()).toContain('Im Schülerbereich bereitstellen')
        expect(wrapper.text()).not.toContain('Im Schülerbereich bereitgestellt')
        await wrapper.setProps({ disabled: true })
        expect((action.element as HTMLButtonElement).disabled).toBe(true)
    } finally { wrapper.unmount() }
})

it('shows released students and their actual MC test status in the preparation dialog', async () => {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { attempts: [
        { student_id: 12, status: 'in_progress' }, { student_id: 13, status: 'submitted' },
    ] } }) })
    const pinia = createTestingPinia({ createSpy: vi.fn })
    useCourseStore(pinia).selected_course = { id: 16, students_info: [
        { user_id: 12, first_name: 'Ada', last_name: 'Alpha' }, { user_id: 13, first_name: 'Ben', last_name: 'Beta' },
    ] } as never
    const wrapper = mount(WorkDeliveryStatus, { props: { work: { id: 64, teaching_course_id: 16, title: 'E-Mails',
        date_for_all_groups: '2026-10-20', groups: [{ student_ids: [12, 13] }], status: {
            student_distribution: { published_at: '2026-10-28T12:00:00Z', student_ids: [12, 13] },
            multiple_choice_tests: { '2026-10-20': { test_id: 2 } },
        } } }, global: { plugins: [pinia], stubs: {
        'v-btn': { template: '<button><slot /></button>' },
        'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' },
        WorkStudentDistribution: { template: '<button>Freigabe verwalten</button>' },
    } } })
    try {
        await wrapper.get('[aria-label="Aufgaben im Schülerbereich bereitgestellt"]').trigger('click')
        await flushPromises()
        const dialog = wrapper.get('[role="dialog"]')
        expect(dialog.text()).toContain('Alpha Ada')
        expect(dialog.text()).toContain('In Bearbeitung')
        expect(dialog.text()).toContain('Beta Ben')
        expect(dialog.text()).toContain('Abgegeben')
        expect(dialog.text()).toContain('Freigabe verwalten')
    } finally { wrapper.unmount() }
})

it('withdraws the release only after confirmation and a fresh version check', async () => {
    const work = { id: 64, teaching_course_id: 16, title: 'Aufgabe', groups: [{ student_ids: [12] }], status: {
        student_distribution: { published_at: '2026-10-28T12:00:00Z', student_ids: [12] },
    } }
    const get = vi.fn().mockResolvedValue({ data: { data: { ...work, student_distribution_version: 'a'.repeat(64) } } })
    const post = vi.fn().mockResolvedValue({ data: { data: work } })
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkDeliveryStatus, { props: { work }, global: {
        plugins: [createTestingPinia({ createSpy: vi.fn })], stubs: {
            'v-btn': { template: '<button><slot /></button>' },
            'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' },
            WorkStudentDistribution: { template: '<button>Freigabe verwalten</button>' },
        },
    } })
    try {
        await wrapper.get('[aria-label="Aufgaben im Schülerbereich bereitgestellt"]').trigger('click')
        await wrapper.findAll('button').find(button => button.text() === 'Freigabe zurücknehmen')!.trigger('click')
        expect(post).not.toHaveBeenCalled()
        const confirmation = wrapper.findAll('[role="dialog"]').find(dialog => dialog.text().includes('Aufgabenfreigabe zurücknehmen?'))!
        await confirmation.findAll('button').find(button => button.text() === 'Freigabe zurücknehmen')!.trigger('click')
        await flushPromises()
        expect((post.mock.calls[0][1] as FormData).get('publish')).toBe('0')
        expect((post.mock.calls[0][1] as FormData).get('expected_version')).toBe('a'.repeat(64))
        expect(wrapper.emitted('saved')).toHaveLength(1)
    } finally { wrapper.unmount() }
})

it('offers the test-wide pause before any student has started', async () => {
    const work = { id: 64, teaching_course_id: 16, title: 'Aufgabe', date_for_all_groups: '2026-10-20', groups: [{ student_ids: [12] }], status: {
        student_distribution: { published_at: '2026-10-28T12:00:00Z', student_ids: [12] },
        multiple_choice_tests: { '2026-10-20': { test_id: 4 } },
    } }
    const get = vi.fn().mockResolvedValueOnce({ data: { test: { id: 4, title: 'MC E-Mails', status: 'published' },
        server_now: '2026-10-29T10:00:00Z', attempts: [{ student_id: 12, status: 'not_started', completed_at: null }] } })
        .mockResolvedValueOnce({ data: { test: { id: 4, title: 'MC E-Mails', status: 'published', paused_at: '2026-10-29T10:00:00Z' }, attempts: [] } })
    const post = vi.fn().mockResolvedValue({ data: {} })
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkDeliveryStatus, { props: { work }, global: {
        plugins: [createTestingPinia({ createSpy: vi.fn })], stubs: {
            'v-btn': { template: '<button><slot /></button>' },
            'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' },
            WorkStudentDistribution: { template: '<button>Freigabe verwalten</button>' },
        },
    } })
    try {
        await wrapper.get('[aria-label="Aufgaben im Schülerbereich bereitgestellt"]').trigger('click'); await flushPromises()
        await wrapper.findAll('button').find(button => button.text() === 'Alle pausieren · MC E-Mails')!.trigger('click'); await flushPromises()
        expect(post.mock.calls[0][0]).toContain('/4/pause')
        expect(wrapper.text()).toContain('Alle fortsetzen · MC E-Mails')
        expect(wrapper.text()).toContain('können ihn derzeit weder starten noch fortsetzen')
    } finally { wrapper.unmount() }
})

it('offers individual controls and requires confirmation before resetting all attempts', async () => {
    const work = { id: 64, teaching_course_id: 16, title: 'Aufgabe', date_for_all_groups: '2026-10-20', groups: [{ student_ids: [12, 13] }], status: {
        student_distribution: { published_at: '2026-10-28T12:00:00Z', student_ids: [12, 13] },
        multiple_choice_tests: { '2026-10-20': { test_id: 4 } },
    } }
    const test = { id: 4, title: 'MC E-Mails', status: 'published' }
    const attempts = [{ id: 1, student_id: 12, status: 'in_progress', control_revision: 'a'.repeat(64) },
        { id: 2, student_id: 13, status: 'timeout', completed_at: '2026-10-29T09:00:00Z', control_revision: 'b'.repeat(64) }]
    let paused = false
    const get = vi.fn().mockImplementation(async () => ({ data: { test, attempts: attempts.map(attempt => attempt.id === 1
        ? { ...attempt, individual_paused_at: paused ? '2026-10-29T10:00:00Z' : null } : attempt) } }))
    const post = vi.fn().mockImplementation(async (_url, payload) => { if (payload.action === 'pause') paused = true; return { data: {} } })
    vi.stubGlobal('axios', { get, post })
    const pinia = createTestingPinia({ createSpy: vi.fn })
    useCourseStore(pinia).selected_course = { id: 16, students_info: [{ user_id: 12, first_name: 'Ada', last_name: 'Alpha' },
        { user_id: 13, first_name: 'Ben', last_name: 'Beta' }] } as never
    const wrapper = mount(WorkDeliveryStatus, { props: { work }, global: { plugins: [pinia], stubs: {
        'v-btn': { props: ['disabled', 'ariaLabel'], template: '<button :disabled="disabled" :aria-label="ariaLabel"><slot /></button>' },
        'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' },
        WorkStudentDistribution: { template: '<span />' },
    } } })
    try {
        await wrapper.get('[aria-label="Aufgaben im Schülerbereich bereitgestellt"]').trigger('click'); await flushPromises()
        expect(wrapper.get('[aria-label="Pausieren: Alpha Ada · MC E-Mails"]').text()).toContain('mdi-pause-circle-outline')
        expect(wrapper.get('[aria-label="Zurücksetzen: Alpha Ada · MC E-Mails"]').text()).toContain('mdi-backup-restore')
        await wrapper.get('[aria-label="Pausieren: Alpha Ada · MC E-Mails"]').trigger('click'); await flushPromises()
        expect(post).toHaveBeenCalledWith(expect.stringContaining('/4/attempts/control'), { action: 'pause', attempt_ids: [1] })
        expect(wrapper.get('[aria-label="Fortsetzen: Alpha Ada · MC E-Mails"]').text()).toContain('mdi-play-circle-outline')
        await wrapper.findAll('button').find(button => button.text() === 'Alle zurücksetzen · MC E-Mails')!.trigger('click')
        expect(post).toHaveBeenCalledTimes(1)
        const dialog = wrapper.findAll('[role="dialog"]').find(item => item.text().includes('MC-Test zurücksetzen?'))!
        expect(dialog.text()).toContain('Alle 2 Versuche')
        await dialog.findAll('button').find(button => button.text() === 'Zurücksetzen')!.trigger('click'); await flushPromises()
        expect(post).toHaveBeenLastCalledWith(expect.stringContaining('/4/attempts/control'), { action: 'reset', attempt_ids: [1, 2],
            expected: { 1: 'a'.repeat(64), 2: 'b'.repeat(64) }, all: true, confirmed: true })
    } finally { wrapper.unmount() }
})

it('places preparation and student release inside the preparation icon dialog', async () => {
    const work = { id: 65, teaching_course_id: 16, title: 'E-Mails', groups: [{ student_ids: [12] }], status: {} }
    const state = { status: 'found', userId: 7, workId: 65, courseId: 16, date: '2026-10-16',
        folder: { id: 'id:exercise', name: 'LF' }, emailPreparation: { status: 'missing' } }
    const wrapper = mount(WorkDeliveryStatus, { props: { work, state, date: state.date, exerciseReady: false }, global: {
        plugins: [createTestingPinia({ createSpy: vi.fn, initialState: { AdminAdminStore: { config: { user: { id: 7 } } } } })],
        stubs: { 'v-textarea': true, 'v-file-input': true, 'v-checkbox': true,
            'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
            'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' } },
    } })
    try {
        expect(wrapper.text()).not.toContain('E-Mails vorbereiten')
        expect(wrapper.text()).not.toContain('Im Schülerbereich bereitstellen')
        await wrapper.get('[data-workflow-stage="preparation"]').trigger('click')
        expect(wrapper.text()).toContain('E-Mails vorbereiten')
        expect(wrapper.text()).not.toContain('Im Schülerbereich bereitstellen')
        await wrapper.setProps({ exerciseReady: true })
        await wrapper.findAll('button').find(button => button.text() === 'E-Mails vorbereiten')!.trigger('click')
        expect(wrapper.text()).toContain('Wie sollen die Schüler:innen angesprochen werden?')
        await wrapper.findAll('button').find(button => button.text() === 'Im Schülerbereich bereitstellen')!.trigger('click')
        expect(wrapper.text()).toContain('Aufgaben im Schülerbereich · E-Mails')
    } finally { wrapper.unmount() }
})

it('allows submission collection after portal publication without email preparation or dispatch', async () => {
    const wrapper = mount(WorkDeliveryStatus, { props: { work: { id: 64, title: 'Aufgabe', groups: [{ student_ids: [12] }], status: {
        student_distribution: { published_at: '2026-10-28T12:00:00Z', student_ids: [12] },
    } } }, global: {
        plugins: [createTestingPinia({ createSpy: vi.fn })],
        stubs: { 'v-textarea': true, 'v-file-input': true, 'v-checkbox': true,
            'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
            'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' } },
    } })
    try {
        expect(wrapper.find('[data-workflow-stage="preparation"]').exists()).toBe(false)
        expect(wrapper.find('[data-workflow-stage="send"]').exists()).toBe(false)
        await wrapper.get('[aria-label="Aufgaben im Schülerbereich bereitgestellt"]').trigger('click')
        expect(wrapper.text()).toContain('Im Schülerbereich bereitgestellt')
        await wrapper.findAll('button').find(button => button.text() === 'Schließen')!.trigger('click')
        expect(wrapper.get('[data-workflow-stage="collection"]').attributes('disabled')).toBeUndefined()
        await wrapper.get('[data-workflow-stage="collection"]').trigger('click')
        expect(wrapper.get('[role="dialog"]').text()).toContain('Abgaben einsammeln')
        expect(wrapper.get('[role="dialog"]').text()).toContain('MC-TestKein MC-Test')
    } finally { wrapper.unmount() }
})

it('keeps the submission status separate when the MC test status cannot be loaded', async () => {
    vi.stubGlobal('axios', { get: vi.fn().mockRejectedValue(new Error('unavailable')) })
    const work = { id: 64, teaching_course_id: 16, title: 'Aufgabe', date_for_all_groups: '2026-10-20',
        groups: [{ student_ids: [12] }], status: {
            student_distribution: { published_at: '2026-10-28T12:00:00Z', student_ids: [12] },
            multiple_choice_tests: { '2026-10-20': { test_id: 4 } },
        } }
    const wrapper = mount(WorkDeliveryStatus, { props: { work }, global: { plugins: [createTestingPinia({ createSpy: vi.fn })], stubs: {
        'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
        'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' },
        WorkSubmissionCollection: { template: '<button>Einsammeln</button>' },
    } } })
    try {
        await wrapper.get('[data-workflow-stage="collection"]').trigger('click')
        await flushPromises()
        const dialog = wrapper.get('[role="dialog"]')
        expect(dialog.text()).toContain('AbgabeUngeklärt')
        expect(dialog.text()).toContain('MC-TestStatus nicht verfügbar')
        expect(dialog.text()).toContain('Der MC-Test-Status konnte nicht geladen werden.')
    } finally { wrapper.unmount() }
})

it('shows only evidenced individual submission states for the selected date', async () => {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { test: { id: 4 }, attempts: [
        { student_id: 12, status: 'not_started' }, { student_id: 13, status: 'timeout' },
    ] } }) })
    const checksum = 'a'.repeat(64)
    const work = { id: 64, teaching_course_id: 16, title: 'Aufgabe', date_for_all_groups: '2026-10-20',
        groups: [{ date: '2026-10-20', student_ids: [12, 13] }, { date: '2026-10-21', student_ids: [14] }], status: {
            student_distribution: { published_at: '2026-10-28T12:00:00Z', student_ids: [12, 13] },
            multiple_choice_tests: { '2026-10-20': { test_id: 4 } },
            assessment_json_imports: [{ student_id: 12, exercise_id: 'exercise', participant_id: 'user:12', record_checksum: checksum,
                record: { participant_id: 'user:12', record_checksum: checksum, submission_state: 'received' } }],
        } }
    const pinia = createTestingPinia({ createSpy: vi.fn })
    useCourseStore(pinia).selected_course = { id: 16, students_info: [
        { user_id: 12, first_name: 'Ada', last_name: 'Alpha' }, { user_id: 13, first_name: 'Ben', last_name: 'Beta' },
        { user_id: 14, first_name: 'Cara', last_name: 'Gamma' },
    ] } as never
    const wrapper = mount(WorkDeliveryStatus, { props: { work }, global: { plugins: [pinia], stubs: {
        'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
        'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' },
        WorkSubmissionCollection: { template: '<button>Einsammeln</button>' },
    } } })
    try {
        await wrapper.get('[data-workflow-stage="collection"]').trigger('click')
        await flushPromises()
        const dialog = wrapper.get('[role="dialog"]')
        const rows = dialog.findAll('li')
        expect(rows[0].text()).toContain('Alpha Ada')
        expect(rows[0].text()).toContain('AbgabeLiegt vor')
        expect(rows[0].text()).toContain('MC-TestNoch nicht begonnen')
        expect(rows[1].text()).toContain('Beta Ben')
        expect(rows[1].text()).toContain('AbgabeUngeklärt')
        expect(rows[1].text()).toContain('MC-TestZeit abgelaufen')
        expect(rows[1].find('.student-status__badge--warning').text()).toBe('Zeit abgelaufen')
        expect(dialog.text()).not.toContain('Gamma Cara')
        expect(dialog.text()).toContain('Einsammeln')
    } finally { wrapper.unmount() }
})

it('disables all workflow controls while the folder is not linked', async () => {
    const wrapper = mount(WorkDeliveryStatus, { props: { work: { id: 64, title: 'Übung', status: { task_dispatch_completed: true } }, disabled: true }, global: {
        plugins: [createTestingPinia({ createSpy: vi.fn }), createVuetify({ components: { VBtn } })],
        components: { 'v-btn': VBtn }, stubs: { 'v-btn': false, VBtn: false,
            'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' } },
    } })
    try {
        expect(wrapper.findAll('[data-workflow-stage]').every(button => (button.element as HTMLButtonElement).disabled)).toBe(true)
        await wrapper.get('[data-workflow-stage="preparation"]').trigger('click')
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
        await wrapper.setProps({ disabled: false })
        expect((wrapper.get('[data-workflow-stage="preparation"]').element as HTMLButtonElement).disabled).toBe(false)
    } finally { wrapper.unmount() }
})

it.each(['ready', 'missing', 'foreign'])('keeps one preparation control with inspection and manual completion: %s', async status => {
    const get = vi.fn()
    const put = vi.fn()
    vi.stubGlobal('axios', { get, put, post: vi.fn() })
    const wrapper = mount(WorkDeliveryStatus, {
        props: {
            work: { id: 65, teaching_course_id: 16, title: 'E-Mails', status: {} }, date: '2026-10-16',
            state: { status: 'found', userId: status === 'foreign' ? 8 : 7, workId: 65, courseId: 16,
                date: '2026-10-16', folder: { id: 'id:exercise', name: 'LF' },
                emailPreparation: { status: status === 'missing' ? 'missing' : 'ready', recipients_count: 15 } },
        },
        global: {
            plugins: [createTestingPinia({ createSpy: vi.fn, initialState: { AdminAdminStore: { config: { user: { id: 7 } } } } })],
            stubs: { 'v-textarea': true, 'v-file-input': true, 'v-checkbox': true,
                'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' } },
        },
    })
    try {
        const controls = wrapper.findAll('[data-workflow-stage]')
        expect(controls).toHaveLength(6)
        expect(new Set(controls.map(control => control.get('v-icon').text())).size).toBe(6)
        await wrapper.get('[data-workflow-stage="preparation"]').trigger('click')

        const inspect = wrapper.findAll('button').find(button => button.text() === 'Versandbereitschaft ansehen')!
        expect(inspect.attributes('style') || '').toContain(status === 'ready' ? '' : 'display: none')
        expect(wrapper.findAll('button').some(button => button.text() === 'Als erledigt markieren')).toBe(true)
        if (status === 'ready') {
            await inspect.trigger('click')
            await flushPromises()
            expect(wrapper.text()).toContain('Versandbereit – noch nicht versandt.')
            expect(wrapper.text()).toContain('15')
            expect(wrapper.findAll('button').some(button => button.text() === 'E-Mail ansehen')).toBe(true)
        }
        expect(get).not.toHaveBeenCalled()
        expect(put).not.toHaveBeenCalled()
        expect(axios.post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('offers reversible manual completion for all six steps in order', async () => {
    let work = { id: 64, teaching_course_id: 16, title: 'Übung', groups: [{ student_ids: [1] }], status: {} }
    const put = vi.fn().mockImplementation(async (_url, payload) => {
        work = { ...work, status: { ...work.status, ...payload } }
        return { data: { data: work } }
    })
    vi.stubGlobal('axios', { put })
    const wrapper = mount(WorkDeliveryStatus, { props: { work }, global: {
        plugins: [createTestingPinia({ createSpy: vi.fn })],
        stubs: { 'v-textarea': true, 'v-file-input': true, 'v-checkbox': true,
            'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
            'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' } },
    } })
    try {
        expect(wrapper.get('[data-workflow-stage="preparation"]').element.disabled).toBe(false)
        expect(wrapper.findAll('[data-workflow-stage]').slice(1).every(button => button.element.disabled)).toBe(true)
        for (const [key, field] of Object.entries(workManualCompletionFields)) {
            const stage = wrapper.get(`[data-workflow-stage="${key}"]`)
            expect(stage.element.disabled).toBe(false)
            await stage.trigger('click')
            await wrapper.findAll('button').find(button => button.text() === 'Als erledigt markieren')!.trigger('click')
            await flushPromises()
            expect(put).toHaveBeenLastCalledWith('/api/admin/teaching/course_works/64', { [field]: true })
            await wrapper.setProps({ work })
            await stage.trigger('click')
            expect(wrapper.get('[role="dialog"]').text()).toContain('manuell')
            await wrapper.findAll('button').find(button => button.text() === 'Schließen')!.trigger('click')
        }
        await wrapper.setProps({ work: { ...work, status: { ...work.status, email_preparation_completed: false } } })
        expect(wrapper.findAll('[data-workflow-stage]').slice(1).every(button => button.element.disabled)).toBe(true)
        await wrapper.setProps({ work })
        for (const [key, field] of Object.entries(workManualCompletionFields).reverse()) {
            await wrapper.get(`[data-workflow-stage="${key}"]`).trigger('click')
            await wrapper.findAll('button').find(button => button.text() === 'Markierung aufheben')!.trigger('click')
            await flushPromises()
            expect(put).toHaveBeenLastCalledWith('/api/admin/teaching/course_works/64', { [field]: false })
            await wrapper.setProps({ work })
        }
        expect(wrapper.get('[data-workflow-stage="evaluation"]').element.disabled).toBe(true)
        expect(wrapper.emitted('saved')).toHaveLength(12)
    } finally { wrapper.unmount() }
})

it('saves a reversible manual dispatch completion and unlocks collection without claiming sent mail', async () => {
    const work = { id: 64, teaching_course_id: 16, title: 'Übung', groups: [{ student_ids: [1] }], status: { email_preparation_completed: true } }
    const saved = { ...work, status: { ...work.status, task_dispatch_completed: true } }
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
    const wrapper = mount(WorkDeliveryStatus, { props: { work: { id: 64, title: 'Übung', groups: [{ student_ids: [1] }], status: { email_preparation_completed: true } } }, global: {
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

it('saves and revokes manual collection completion while preserving evidence counts and unlocking grading', async () => {
    const work = { id: 64, teaching_course_id: 16, title: 'Übung', groups: [{ student_ids: [1] }], status: { email_preparation_completed: true, task_dispatch_completed: true } }
    const saved = { ...work, status: { ...work.status, submission_collection_completed: true } }
    const put = vi.fn().mockResolvedValueOnce({ data: { data: saved } }).mockResolvedValueOnce({ data: { data: work } })
    vi.stubGlobal('axios', { put })
    const wrapper = mount(WorkDeliveryStatus, { props: { work }, global: {
        plugins: [createTestingPinia({ createSpy: vi.fn })],
        stubs: { 'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
            'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' } },
    } })
    try {
        expect(wrapper.get('[data-workflow-stage="evaluation"]').element.disabled).toBe(true)
        await wrapper.get('[data-workflow-stage="collection"]').trigger('click')
        await wrapper.findAll('button').find(button => button.text() === 'Als erledigt markieren')!.trigger('click')
        await flushPromises()
        expect(put).toHaveBeenCalledWith('/api/admin/teaching/course_works/64', { submission_collection_completed: true })
        expect(wrapper.emitted('saved')).toEqual([[saved]])
        await wrapper.setProps({ work: saved })
        expect(wrapper.get('[data-workflow-stage="evaluation"]').element.disabled).toBe(false)
        await wrapper.get('[data-workflow-stage="evaluation"]').trigger('click')
        const grade = wrapper.get('[aria-label="Dialogaktionen"]').findAll('button').find(button => button.text() === 'Bewerten')!
        expect(grade.element.disabled).toBe(false)
        await wrapper.get('[data-workflow-stage="collection"]').trigger('click')
        const details = wrapper.get('[role="dialog"]').text()
        expect(details).toContain('Abgaben einsammeln: manuell erledigt')
        expect(details).toContain('AbgabeUngeklärt')
        expect(details).toContain('bestätigt keine Abgaben und keinen Sammellauf')
        expect(details).toContain('Zeitpunkt nicht gespeichert')
        expect(details).toContain('Nächster Schritt: Bewerten.')
        await wrapper.findAll('button').find(button => button.text() === 'Markierung aufheben')!.trigger('click')
        await flushPromises()
        expect(put).toHaveBeenLastCalledWith('/api/admin/teaching/course_works/64', { submission_collection_completed: false })
        await wrapper.setProps({ work })
        expect(wrapper.get('[data-workflow-stage="evaluation"]').element.disabled).toBe(true)
    } finally { wrapper.unmount() }
})

it('keeps grading blocked when manual collection completion fails to save', async () => {
    vi.stubGlobal('axios', { put: vi.fn().mockRejectedValue({ response: { data: { message: 'Speichern fehlgeschlagen.' } } }) })
    const wrapper = mount(WorkDeliveryStatus, { props: { work: { id: 64, title: 'Übung', groups: [{ student_ids: [1] }], status: { email_preparation_completed: true, task_dispatch_completed: true } } }, global: {
        plugins: [createTestingPinia({ createSpy: vi.fn }), createVuetify({ components: { VBtn } })],
        components: { 'v-btn': VBtn }, stubs: { 'v-btn': false, VBtn: false,
            'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' } },
    } })
    try {
        await wrapper.get('[data-workflow-stage="collection"]').trigger('click')
        await wrapper.findAll('button').find(button => button.text() === 'Als erledigt markieren')!.trigger('click')
        await flushPromises()
        expect(wrapper.get('[role="alert"]').text()).toContain('Speichern fehlgeschlagen.')
        expect(wrapper.emitted('saved')).toBeUndefined()
        expect(wrapper.get('[data-workflow-stage="evaluation"]').element.disabled).toBe(true)
    } finally { wrapper.unmount() }
})

it('opens results transfer for the existing work rather than requesting a new folder link', async () => {
    const work = { id: 86, teaching_course_id: 16, title: 'E-Mails', groups: [{ student_ids: [492] }], status: {
        email_preparation_completed: true, task_dispatch_completed: true, submission_collection_completed: true,
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
        status: { email_preparation_completed: true, task_dispatch_completed: true },
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
        groups: [{ student_ids: [1] }], status: { email_preparation_completed: true, dispatch_logs: [{ origin: 'dispatch_import', purpose: 'tasks', sha256: 'a'.repeat(64) }],
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
        await wrapper.findAll('button').find(button => button.text() === 'Schließen')!.trigger('click')
        expect(wrapper.get('[data-workflow-stage="evaluation"]').element.disabled).toBe(false)
        await wrapper.get('[data-workflow-stage="evaluation"]').trigger('click')
        expect(wrapper.get('[role="dialog"]').text()).toContain('Einsammellauf hat offene Prüflücken')
        expect(wrapper.getComponent(WorkGradingAction).props('disabled')).toBe(false)
    } finally { wrapper.unmount() }
})

it('shows each student’s saved criteria separately from the MC test and leaves unfinished totals open', async () => {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { test: { id: 4, title: 'MC', total_points: 2 }, attempts: [
        { student_id: 12, completed_at: '2026-10-29T10:00:00Z', score: '1.40' }, { student_id: 13, completed_at: null, score: null },
    ] } }) })
    const pinia = createTestingPinia({ createSpy: vi.fn })
    useCourseStore(pinia).selected_course = { id: 16, students_info: [{ user_id: 12, first_name: 'Ada', last_name: 'Alpha' },
        { user_id: 13, first_name: 'Ben', last_name: 'Beta' }], teaching_entry_area: { entry_definitions: [{ category: 'Benotung', short_name: 'A5', maximum_points: 5 }] } } as never
    const checksum = 'a'.repeat(64)
    const work = { id: 64, teaching_course_id: 16, title: 'E-Mails', type: 'A5', groups: [{ student_ids: [12, 13] }], status: {
        email_preparation_completed: true, task_dispatch_completed: true, submission_collection_completed: true,
        multiple_choice_tests: { all: { test_id: 4 } }, assessment_json_imports: [{ student_id: 12, exercise_id: 'exercise', participant_id: 'user:12', record_checksum: checksum,
            record: { participant_id: 'user:12', record_checksum: checksum, evaluation_state: 'partial', total_minor: null,
                criteria: [{ criterion: 'PDF-Inhalt', maximum_minor: 100, earned_minor: 75 }, { criterion: 'E-Mail', maximum_minor: 175, earned_minor: null }] } }],
    } }
    const wrapper = mount(WorkDeliveryStatus, { props: { work }, global: { plugins: [pinia], stubs: {
        'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
        'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' },
    } } })
    try {
        await wrapper.get('[data-workflow-stage="evaluation"]').trigger('click'); await flushPromises()
        const rows = wrapper.get('[aria-label="Punkteübersicht"]').findAll('li')
        expect(rows[0].text()).toContain('Alpha Ada')
        expect(rows[0].text()).toContain('PDF-Inhalt0,75 / 1')
        expect(rows[0].text()).toContain('E-MailOffen / 1,75')
        expect(rows[0].text()).toContain('MC-Test · MC (Teststand)1,4 / 2')
        expect(rows[0].text()).toContain('GesamtbewertungOffen / 5')
        expect(rows[1].text()).toContain('Beta Ben')
        expect(rows[1].text()).toContain('MC-Test · MC (Teststand)Offen / 2')
        expect(rows[1].text()).toContain('GesamtbewertungOffen / 5')
        const averages = wrapper.get('[aria-label="Mittelwerte"]').text()
        expect(averages).toContain('PDF-Inhalt0,75 / 1 · 1 Bewertung')
        expect(averages).toContain('E-MailKeine Werte / 1,75 · 0 Bewertungen')
        expect(averages).toContain('MC-Test · MC (Teststand)1,4 / 2 · 1 Bewertung')
        expect(averages).toContain('GesamtbewertungKeine Werte / 5 · 0 Bewertungen')
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
        expect(wrapper.findAll('[data-workflow-stage]')).toHaveLength(6)
    } finally { wrapper.unmount() }
})

it('enables collection only for confirmed task emails in the selected group', async () => {
    const work = { id: 64, teaching_course_id: 16, title: 'E-Mails', groups: [
        { date: '2026-10-16', student_ids: [1, 2] }, { date: '2026-10-17', student_ids: [3] },
    ], status: { email_preparation_completed: true, dispatch_logs: [{ origin: 'dispatch_import', purpose: 'tasks', sha256: 'a'.repeat(64) }],
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
        email_preparation: { status: 'ready' },
        email_dispatch: { status, confirmed_count: status === 'sent' ? 15 : 0, expected_count: 15, transport: 'mailpit', sent_at: '2026-10-23T22:23:34Z' } } })
    const wrapper = mount(WorkDeliveryStatus, { props: { work: { id: 65, teaching_course_id: 16, title: 'E-Mails', date_for_all_groups: '2026-10-16' } },
        global: { plugins: [createTestingPinia({ createSpy: vi.fn, initialState: { AdminAdminStore: { config: { user: { id: 7 } } } } }), createVuetify({ components: { VBtn } })],
            components: { 'v-btn': VBtn }, stubs: { 'v-btn': false, VBtn: false, 'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' } } } })
    try {
        expect(wrapper.findAll('button')).toHaveLength(6)
        expect(wrapper.get('[data-workflow-stage="collection"]').element.disabled).toBe(status !== 'sent')
        await wrapper.get('[data-workflow-stage="send"]').trigger('click')
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
