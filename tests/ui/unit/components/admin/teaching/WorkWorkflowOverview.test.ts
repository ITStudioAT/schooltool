import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import WorkWorkflowOverview from '@/pages/admin/teaching/overview/components/WorkWorkflowOverview.vue'
import { clearWorkDropboxStates, workDropboxStates } from '@/helpers/workDropbox'

const date = '2026-11-20'
const work = { id: 65, teaching_course_id: 2, title: 'Fiktive Arbeit', groups: [{ student_ids: [12] }], status: {} }
const course = { id: 2 }
const connection = { connected: true, folder: null, module_link: { folder: { id: 'id:module', path: '/Module/Fiktiv' } }, link_version: 'a'.repeat(64) }

function render(currentWork = work, active = true, onSaved = (_work: typeof work) => {}) {
    const openRelease = vi.fn()
    const openMail = vi.fn()
    const wrapper = mount(WorkWorkflowOverview, { props: { work: currentWork, course, date, active, onSaved }, global: {
        plugins: [createTestingPinia({ createSpy: vi.fn, initialState: { AdminAdminStore: { config: { user: { id: 7 } } } } })],
        stubs: {
            'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
            'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' },
            WorkStudentDistribution: { props: ['work', 'available'], setup: props => ({ openRelease: () => openRelease(props.work?.status?.provision_method) }),
                template: '<slot name="activator" :active="false" :available="available || !!work.status.student_distribution" :open="openRelease" />' },
            WorkQueueProgress: true,
            WorkAssignmentMail: { name: 'WorkAssignmentMail', props: { work: Object, hideActivator: Boolean }, setup(props, { expose }) {
                expose({ open: () => openMail(props.work?.status?.provision_method) })
            }, template: '<span />' },
            'v-radio-group': true, 'v-radio': true,
        },
    } })
    const action = (label: string) => wrapper.findAll('button').find(button => button.text() === label)
    return { wrapper, action, openRelease, openMail }
}

afterEach(() => { clearWorkDropboxStates(); vi.unstubAllGlobals() })

it('opens the MC picker directly from a prepared worksheet summary', async () => {
    const folder = { id: 'id:exercise', name: 'Fiktive Prüfung' }
    const preparation = { status: 'ready', total_points: 35, components: {
        aufgabenblatt: { included: true, points: 35 }, multiple_choice: { included: false },
    } }
    vi.stubGlobal('axios', { get: vi.fn((url, options) => Promise.resolve({ data:
        options?.params?.mc_selection === 'current' ? { linked_test_id: null }
            : options?.params?.mc_selection === 'catalogs' ? { catalogs: [{ id: 3, title: 'Grundlagen' }] }
                : { ...connection, exercise: { status: 'found', folder, preparation } },
    })) })
    workDropboxStates.set('7:2:65', connection)
    const { wrapper, action } = render({ ...work, status: { student_distribution: { prepared_at: date } } })
    try {
        await wrapper.setProps({ assessment: { status: 'found', folder, preparation } })
        await flushPromises()
        expect(wrapper.get('[aria-label="Prüfungsdetails"]').text()).toContain('35 Punkte')
        await action('Test hinzufügen')!.trigger('click')
        await flushPromises()
        expect(wrapper.get('[role="dialog"]').text()).toContain('Multiple-Choice-Test hinzufügen')
        expect(wrapper.get('[role="dialog"]').find('[label="Endgültige Abgabefrist"]').exists()).toBe(true)
    } finally { wrapper.unmount() }
})

it('loads only an existing proof when opening, with no invented check time, and refreshes only after the explicit click', async () => {
    const status = { ...connection, exercise: { status: 'unknown', checked_at: null } }
    const get = vi.fn().mockResolvedValue({ data: status })
    const post = vi.fn().mockResolvedValue({ data: { ...connection, exercise: { status: 'missing', checked_at: `${date}T10:00:00Z` } } })
    vi.stubGlobal('axios', { get, post })
    workDropboxStates.set('7:2:65', connection)
    const { wrapper, action } = render()
    try {
        await flushPromises()
        expect(get).toHaveBeenCalledWith(expect.stringContaining('/dropbox'),
            expect.objectContaining({ params: { include_exercise: 1, stored_only: 1, date } }))
        const saved = wrapper.emitted('exercise-status')?.at(-1)?.[0]
        expect(saved).toMatchObject({ status: 'unchecked', checkedAt: null })
        expect(action('Abgabeweg festlegen')).toBeDefined()
        expect(action('Bereitstellungsweg vorbereiten')).toBeDefined()
        expect(action('Aufgaben bereitstellen')).toBeUndefined()
        expect(post).not.toHaveBeenCalled()
        await action('Status erneut prüfen')!.trigger('click')
        await flushPromises()
        expect(post).toHaveBeenCalledWith(expect.stringContaining('/dropbox/refresh'), expect.objectContaining({ status_only: true, date }), expect.any(Object))
        expect(wrapper.emitted('exercise-status')?.at(-1)?.[0]).toMatchObject({ status: 'missing', checkedAt: `${date}T10:00:00Z` })
    } finally { wrapper.unmount() }
})

it('keeps an older paused release reachable and identifies a local mail receipt', async () => {
    const old = { ...work, status: { student_distribution: { published_at: `${date}T08:00:00Z`, withdrawn_at: `${date}T09:00:00Z` },
        submission_method: { channel: 'email', email: 'abgabe@example.test' }, grade_visibility: { visible: false },
        assignment_mail: { batches: { [date]: { state: 'sent', transport: 'mailpit' } } } } }
    const get = vi.fn().mockResolvedValue({ data: { ...connection, exercise: { status: 'unknown', checked_at: null } } })
    const post = vi.fn()
    const put = vi.fn()
    vi.stubGlobal('axios', { get, post, put })
    const { wrapper, action, openRelease } = render(old, true, saved => { void wrapper.setProps({ work: saved }) })
    try {
        await flushPromises()
        expect(wrapper.text()).toContain('Freigabe pausiert')
        expect(wrapper.text()).toContain('Abgabe per E-Mail an abgabe@example.test')
        expect(wrapper.text()).toContain('Bewertungen nur für die Lehrkraft sichtbar')
        expect(wrapper.text()).toContain('Aufgabenmail im lokalen Mailpit bestätigt')
        expect(wrapper.text()).not.toContain('vom Versanddienst angenommen')
        const visibleRow = wrapper.get('[aria-label="Sichtbarkeit"]')
        const resultsRow = wrapper.get('[aria-label="Verständigung"]')
        const releaseRow = wrapper.get('[aria-label="Bereitstellung"]')
        expect(visibleRow.text()).toContain('Bewertungen freigeben')
        expect(resultsRow.text()).toContain('Keine Mitteilung im Kurs')
        expect(resultsRow.text()).not.toContain('Mailpit')
        expect(releaseRow.findComponent({ name: 'WorkProvisionMethod' }).exists()).toBe(false)
        expect(wrapper.getComponent({ name: 'WorkAssignmentMail' }).props('hideActivator')).toBe(true)
        expect(resultsRow.findComponent({ name: 'WorkAssignmentMail' }).exists()).toBe(false)
        expect(wrapper.get('[data-primary="true"] button').text()).toBe('Freigabe fortsetzen')
        await action('Bewertung eintragen')!.trigger('click')
        expect(wrapper.emitted('edit-grades')).toHaveLength(1)
        await action('Freigabe fortsetzen')!.trigger('click')
        await flushPromises()
        expect(put).not.toHaveBeenCalled()
        expect(openRelease).toHaveBeenCalledExactlyOnceWith(undefined)
        expect(post).not.toHaveBeenCalled()
        await wrapper.setProps({ work: { ...old, status: { ...old.status, grade_visibility: { visible: true, student_notice: { student_ids: [12] } } } } })
        expect(resultsRow.text()).toContain('Mitteilung im Kurs für 1 Person')
        await wrapper.setProps({ active: false })
        expect(wrapper.findComponent({ name: 'WorkQueueProgress' }).exists()).toBe(false)
    } finally { wrapper.unmount() }
})

it('opens the applicable phase and highlights one real action as the saved work progresses', async () => {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { ...connection, exercise: { status: 'unknown' } } }) })
    workDropboxStates.set('7:2:65', connection)
    const { wrapper, openRelease } = render()
    const primary = () => wrapper.get('[data-primary="true"] button').text()
    try {
        await flushPromises()
        expect(primary()).toBe('Status erneut prüfen')
        expect(wrapper.findAll('.work-phase__title').map(item => item.text())).toEqual(['Vorbereiten', 'Bereitstellen', 'Abgeben', 'Bewerten'])
        await wrapper.setProps({ exerciseReady: true })
        expect(primary()).toBe('Bereitstellungsweg vorbereiten')
        expect(wrapper.findAll('[aria-expanded="true"]')).toHaveLength(1)
        const announced = { ...work, status: { student_distribution: { prepared_at: `${date}T08:00:00Z` } } }
        await wrapper.setProps({ work: announced })
        expect(wrapper.text()).toContain('Bereit zur Freigabe')
        expect(primary()).toBe('Jetzt freigeben')
        await wrapper.get('[data-primary="true"] button').trigger('click')
        await flushPromises()
        expect(openRelease).toHaveBeenCalledOnce()
        expect(wrapper.find('input[value="portal"]').exists()).toBe(false)
        const reopened = { ...work, status: { submission_method: { channel: 'portal' },
            student_distribution: { withdrawn_at: `${date}T09:00:00Z`, published_at: `${date}T08:00:00Z` } } }
        await wrapper.setProps({ work: reopened })
        expect(primary()).toBe('Freigabe fortsetzen')
        await wrapper.setProps({ work: { ...reopened, status: { ...reopened.status, student_distribution: { deleted_at: `${date}T10:00:00Z` } } } })
        expect(wrapper.text()).toContain('Bereitstellung entfernt')
        expect(primary()).toBe('Bereitstellungsweg vorbereiten')
    } finally { wrapper.unmount() }
})

it('keeps provision current until a partially processed batch has a confirmed recipient', async () => {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: connection }) })
    const batch = { state: 'partial', transport: 'postmark', recipients: [{ state: 'rejected', accepted_at: null }] }
    const withBatch = (value: object) => ({ ...work, status: {
        provision_method: { channel: 'email' }, assignment_mail: { batches: { [date]: value } },
    } })
    const { wrapper } = render(withBatch(batch))
    const current = () => wrapper.get('.work-phase--current .work-phase__title').text()
    try {
        await flushPromises()
        expect(current()).toBe('Bereitstellen')
        expect(wrapper.text()).toContain('Bisher keine Aufgabenmail bestätigt')
        await wrapper.setProps({ work: withBatch({ ...batch, recipients: [...batch.recipients,
            { state: 'accepted', accepted_at: `${date}T10:00:00Z` }] }) })
        expect(current()).toBe('Abgeben')
        expect(wrapper.text()).toContain('teilweise vom Versanddienst angenommen')
    } finally { wrapper.unmount() }
})

it('opens the mail editor only after a successful provision choice without changing the submission channel', async () => {
    const original = { ...work, status: { submission_method: { channel: 'portal' } } }
    const saved = { ...original, status: { ...original.status, provision_method: { channel: 'email' } } }
    const put = vi.fn().mockRejectedValueOnce({ response: { data: { message: 'Die Auswahl ist nicht mehr aktuell.' } } })
        .mockResolvedValueOnce({ data: { data: saved } })
    const get = vi.fn().mockResolvedValue({ data: { connected: false } })
    const post = vi.fn()
    vi.stubGlobal('axios', { get, put, post })
    const { wrapper, action, openMail } = render(original, true, updated => { void wrapper.setProps({ work: updated }) })
    try {
        await flushPromises()
        expect(wrapper.getComponent({ name: 'WorkProvisionMethod' }).props('portalAvailable')).toBe(false)
        expect(wrapper.findAll('button').filter(button => button.text() === 'Bereitstellungsweg vorbereiten')).toHaveLength(1)
        await action('Bereitstellungsweg vorbereiten')!.trigger('click')
        await wrapper.get('input[value="email"]').setValue()
        await action('Weiter')!.trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('Die Auswahl ist nicht mehr aktuell.')
        expect(openMail).not.toHaveBeenCalled()
        await action('Weiter')!.trigger('click')
        await flushPromises()
        expect(openMail).toHaveBeenCalledExactlyOnceWith({ channel: 'email' })
        expect(wrapper.props('work').status.submission_method).toEqual({ channel: 'portal' })
        expect(wrapper.text()).toContain('E-Mail gewählt')
        expect(post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('allows planning a portal provision before its preparation is available', async () => {
    const saved = { ...work, status: { provision_method: { channel: 'portal' } } }
    const put = vi.fn().mockResolvedValue({ data: { data: saved } })
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { connected: false } }), put })
    const { wrapper, action, openRelease, openMail } = render(work, true, updated => { void wrapper.setProps({ work: updated }) })
    try {
        await flushPromises()
        await action('Bereitstellungsweg vorbereiten')!.trigger('click')
        await wrapper.get('input[value="portal"]').setValue()
        await action('Auswahl speichern')!.trigger('click')
        await flushPromises()
        expect(put).toHaveBeenCalledExactlyOnceWith(expect.stringContaining('/provision-method'), { channel: 'portal', expected: null })
        expect(wrapper.text()).toContain('Schülerbereich gewählt')
        expect(openRelease).not.toHaveBeenCalled()
        expect(openMail).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})
