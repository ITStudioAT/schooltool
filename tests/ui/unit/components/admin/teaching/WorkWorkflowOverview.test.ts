import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import WorkWorkflowOverview from '@/pages/admin/teaching/overview/components/WorkWorkflowOverview.vue'
import { forgetAssessmentRun, observeAssessmentRun } from '@/helpers/workAssessmentRun'
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
            WorkQueueProgress: { template: '<span />', methods: { restart: vi.fn() } },
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

it.each([
    ['confirmed sheet from creation run', null, { date, state: 'completed', assignment_points: 35 }, '40 Punkte', ''],
    ['mixed and MC already included', { included: true, points: 35 }, null, '40 Punkte', ''],
    ['only MC', { included: false, points: null }, null, '5 Punkte', ''],
    ['sheet points missing', { included: true, points: null }, null, 'Noch offen', 'Punkte des Aufgabenblatts fehlen.'],
    ['creation run for another date', null, { date: '2026-11-19', state: 'completed', assignment_points: 35 }, 'Noch offen', 'Ein Aufgabenblatt ist nicht bestätigt.'],
] as const)('shows scoped assessment points and the independent Schooltool scale for %s', async (_label, sheet, run, total, missing) => {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: connection }) })
    const test = { work_id: 65, test_id: 42 }
    const currentWork = { ...work, type: 'TE', assessment_run: run,
        status: { multiple_choice_tests: { [date]: test, all: test } },
        multiple_choice_test_details: { 42: { points: 5, question_count: 10, duration_seconds: 480 } } }
    const { wrapper } = render(currentWork, false)
    try {
        await wrapper.setProps({
            course: { id: 2, teaching_entry_area: { entry_definitions: [{ category: 'Benotung', short_name: 'TE', maximum_points: 35 }] } },
            assessment: sheet ? { status: 'found', preparation: {
                status: 'ready', total_points: sheet.points === null && sheet.included ? null : (sheet.points ?? 0) + 5,
                components: { aufgabenblatt: sheet, multiple_choice: { included: true, points: 5 } },
            } } : null,
        })
        const facts = wrapper.get('[aria-label="Prüfungsdetails"]')
        const row = (label: string) => facts.findAll('dl > div').find(item => item.get('dt').text() === label)!.text()
        expect(row('Gesamtpunkte')).toContain(total)
        expect(row('Gesamtpunkte')).toContain(missing)
        expect(row('Multiple-Choice-Test')).toContain('5 Punkte10 Fr./8 min.')
        expect(row('Schooltool')).toContain('35 Punkte')
    } finally { wrapper.unmount() }
})

it('shows concise evidence for both provision channels beside the independent submission setting', async () => {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: connection }) })
    const currentWork = { ...work, date_for_all_groups: date, assessment_run: { date, state: 'completed', assignment_points: 35 },
        status: { student_distribution: { published_at: `${date}T09:00:00Z`, student_ids: [12] },
            submission_method: { channel: 'email', email: 'abgabe@example.test' },
            assignment_mail: { batches: { all: { transport: 'postmark', recipients: [{ student_id: 12, state: 'accepted', accepted_at: `${date}T09:00:00Z` }] } } },
        },
    }
    const { wrapper } = render(currentWork, false)
    try {
        await wrapper.setProps({ assessment: { status: 'unknown', emailDispatch: null } })
        const ways = wrapper.get('[aria-label="Prüfungsdetails"] .work-assessment-ways')
        expect(ways.text()).toContain('Im Schülerbereich bereitgestellt')
        expect(ways.text()).toContain('Per E-Mail versandt')
        expect(ways.text()).toContain('Datei per E-Mail an abgabe@example.test')
        expect(ways.text()).not.toContain('Postmark')
        await wrapper.setProps({ work: { ...currentWork, status: {
            assignment_mail: { batches: { all: { transport: 'postmark', state: 'draft', recipients: [{ student_id: 12, state: 'draft' }] } } },
        } } })
        expect(ways.text()).toContain('E-Mail vorbereitet')
        expect(ways.text()).toContain('Noch nicht festgelegt')
        expect(ways.text()).not.toContain('Per E-Mail versandt')
    } finally { wrapper.unmount() }
})

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
    const old = { ...work, status: { provision_method: { channel: 'portal' }, student_distribution: { published_at: `${date}T08:00:00Z`, withdrawn_at: `${date}T09:00:00Z` },
        submission_method: { channel: 'email', email: 'abgabe@example.test' }, grade_visibility: { visible: false },
        assignment_mail: { batches: { [date]: { state: 'sent', transport: 'mailpit',
            recipients: [{ student_id: 12, state: 'accepted', accepted_at: `${date}T08:00:00Z` }] } } } } }
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
        expect(wrapper.text()).toContain('Lokaler Mailpit-Test')
        expect(wrapper.text()).not.toContain('vom Versanddienst angenommen')
        const visibleRow = wrapper.get('[aria-label="Sichtbarkeit"]')
        const resultsRow = wrapper.get('[aria-label="Verständigung"]')
        const releaseRow = wrapper.get('[aria-label="Bereitstellung"]')
        expect(visibleRow.text()).toContain('Bewertungen freigeben')
        expect(resultsRow.text()).toContain('Keine Mitteilung im Kurs')
        expect(resultsRow.text()).not.toContain('Mailpit')
        expect(releaseRow.findComponent({ name: 'WorkProvisionMethod' }).props('hideActivator')).toBe(true)
        expect(wrapper.getComponent({ name: 'WorkAssignmentMail' }).props('hideActivator')).toBe(true)
        expect(resultsRow.findComponent({ name: 'WorkAssignmentMail' }).exists()).toBe(false)
        const editRelease = wrapper.get('[data-primary="true"] button')
        expect(editRelease.text()).toBe('Freigabe bearbeiten')
        expect(editRelease.attributes('prepend-icon')).toBe('mdi-pencil-outline')
        await action('Bewertung eintragen')!.trigger('click')
        expect(wrapper.emitted('edit-grades')).toHaveLength(1)
        await editRelease.trigger('click')
        await flushPromises()
        expect(put).not.toHaveBeenCalled()
        expect(openRelease).toHaveBeenCalledExactlyOnceWith({ channel: 'portal' })
        expect(post).not.toHaveBeenCalled()
        expect(wrapper.emitted('saved')).toBeUndefined()
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
        const announced = { ...work, status: { provision_method: { channel: 'portal' }, submission_method: { channel: 'portal' }, student_distribution: { prepared_at: `${date}T08:00:00Z` } } }
        await wrapper.setProps({ work: announced })
        expect(wrapper.text()).toContain('Schülerbereich vorbereitet')
        expect(primary()).toBe('Jetzt freigeben')
        await wrapper.get('[data-primary="true"] button').trigger('click')
        await flushPromises()
        expect(openRelease).toHaveBeenCalledOnce()
        expect(wrapper.find('input[value="portal"]').exists()).toBe(false)
        const reopened = { ...work, status: { provision_method: { channel: 'portal' }, submission_method: { channel: 'portal' },
            student_distribution: { withdrawn_at: `${date}T09:00:00Z`, published_at: `${date}T08:00:00Z` } } }
        await wrapper.setProps({ work: reopened })
        expect(primary()).toBe('Freigabe bearbeiten')
        await wrapper.setProps({ work: { ...reopened, status: { ...reopened.status, student_distribution: { deleted_at: `${date}T10:00:00Z` } } } })
        expect(wrapper.text()).toContain('Bereitstellung im Schülerbereich entfernt')
        expect(primary()).toBe('Bereitstellungsweg vorbereiten')
    } finally { wrapper.unmount() }
})

it('keeps provision current until a partially processed batch has a confirmed recipient', async () => {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: connection }) })
    const batch = { state: 'partial', transport: 'postmark', recipients: [{ student_id: 12, state: 'rejected', accepted_at: null }] }
    const withBatch = (value: object) => ({ ...work, status: {
        provision_method: { channel: 'email' }, assignment_mail: { batches: { [date]: value } },
    } })
    const { wrapper } = render(withBatch(batch))
    const current = () => wrapper.get('.work-phase--current .work-phase__title').text()
    try {
        await flushPromises()
        expect(current()).toBe('Bereitstellen')
        expect(wrapper.text()).toContain('E-Mail-Versand nicht bestätigt')
        await wrapper.setProps({ work: withBatch({ ...batch, recipients: [
            { student_id: 12, state: 'accepted', accepted_at: `${date}T10:00:00Z` }] }) })
        expect(current()).toBe('Abgeben')
        expect(wrapper.text()).toContain('Postmark hat 1 Nachricht angenommen')
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
        await wrapper.get('.provision-choices input[value="email"]').setValue()
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
    const submission = { channel: 'portal', email: null }
    const saved = { ...work, status: { provision_method: { channel: 'portal' }, submission_method: submission } }
    const put = vi.fn().mockResolvedValue({ data: { data: saved } })
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { connected: false } }), put })
    const { wrapper, action, openRelease, openMail } = render(work, true, updated => { void wrapper.setProps({ work: updated }) })
    try {
        await flushPromises()
        await action('Bereitstellungsweg vorbereiten')!.trigger('click')
        await wrapper.get('.provision-choices input[value="portal"]').setValue()
        await wrapper.get('.submission-methods input[value="portal"]').setValue()
        await action('Auswahl speichern')!.trigger('click')
        await flushPromises()
        expect(put).toHaveBeenCalledExactlyOnceWith(expect.stringContaining('/provision-method'), { channel: 'portal', expected: null, submission, expected_submission: null })
        expect(wrapper.text()).toContain('Schülerbereich gewählt')
        expect(openRelease).not.toHaveBeenCalled()
        expect(openMail).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})


it('routes an existing preparation with a missing submission method through the combined dialog', async () => {
    const status = { provision_method: { channel: 'portal' }, student_distribution: { prepared_at: `${date}T08:00:00Z` } }
    const submission = { channel: 'portal', email: null }
    const saved = { ...work, status: { ...status, submission_method: submission } }
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { connected: false } }),
        put: vi.fn().mockResolvedValue({ data: { data: saved } }) })
    const { wrapper, action, openRelease } = render({ ...work, status }, true, updated => { void wrapper.setProps({ work: updated }) })
    try {
        expect(action('Jetzt freigeben')).toBeUndefined()
        await action('Bereitstellungsweg vorbereiten')!.trigger('click')
        expect((wrapper.get('.provision-choices input[value="portal"]').element as HTMLInputElement).checked).toBe(true)
        await wrapper.get('.submission-methods input[value="portal"]').setValue()
        await action('Weiter')!.trigger('click')
        await flushPromises()
        expect(openRelease).toHaveBeenCalledExactlyOnceWith({ channel: 'portal' })
    } finally { wrapper.unmount() }
})


it('shows an active background job and leaves status and read-only module access available', async () => {
    const status = { ...connection, exercise: { status: 'missing' } }
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: status }), post: vi.fn() })
    workDropboxStates.set('7:2:65', connection)
    const run = { id: 'run-workflow', date, state: 'queued', queued_at: `${date}T10:00:00Z` }
    const { wrapper, action } = render()
    const phase = (label: string) => wrapper.findAll('.work-phase__toggle').find(button => button.text().startsWith(label))!
    try {
        await wrapper.setProps({ assessment: { status: 'missing', userId: 7, workId: work.id, courseId: course.id, date },
            lastCheck: 'vor dem Auftrag' })
        await flushPromises()
        await phase('Abgeben').trigger('click')
        expect(phase('Abgeben').attributes('aria-expanded')).toBe('true')

        await wrapper.setProps({ work: { ...work, assessment_run: run } as typeof work })
        expect(wrapper.get('[data-testid="assessment-background-status"]').text()).toContain('wartet auf den Start')
        expect(wrapper.find('[data-testid="work-dialog-last-check"]').exists()).toBe(false)
        expect(phase('Vorbereiten').attributes('aria-expanded')).toBe('true')
        for (const label of ['Bereitstellen', 'Abgeben', 'Bewerten']) {
            const toggle = phase(label)
            expect(toggle.attributes('disabled')).toBeDefined()
            expect(toggle.text()).toContain('Während der Erstellung gesperrt')
            await toggle.trigger('click')
            await toggle.trigger('keydown', { key: 'Enter' })
            expect(toggle.attributes('aria-expanded')).toBe('false')
        }
        expect(action('Leistungsfeststellung erstellen')).toBeUndefined()
        expect(action('Status erneut prüfen')!.attributes('disabled')).toBeUndefined()
        expect(action('Erstellungsauftrag anzeigen')!.attributes('disabled')).toBeUndefined()
        expect(action('Lernmodul ansehen')!.attributes('disabled')).toBeUndefined()
        expect(action('Bewertung eintragen')!.attributes('disabled')).toBeDefined()
        await action('Status erneut prüfen')!.trigger('click')
        expect(axios.post).not.toHaveBeenCalled()
        observeAssessmentRun('7:2:65', { ...run, state: 'running' })
        await flushPromises()
        expect(wrapper.get('[data-testid="assessment-background-status"]').text()).toContain('wird im Hintergrund erstellt')
        observeAssessmentRun('7:2:65', { ...run, state: 'failed' })
        await flushPromises()
        expect(wrapper.find('[data-testid="assessment-background-status"]').exists()).toBe(false)
        expect(phase('Bereitstellen').attributes('disabled')).toBeUndefined()
        expect(phase('Abgeben').attributes('disabled')).toBeUndefined()
        expect(phase('Bewerten').attributes('disabled')).toBeUndefined()
        expect(action('Leistungsfeststellung erstellen')!.attributes('disabled')).toBeUndefined()
    } finally { wrapper.unmount(); forgetAssessmentRun('7:2:65') }
})


it('keeps the chosen mail preparation reachable beside an existing portal release', async () => {
    const status = { provision_method: { channel: 'email' }, submission_method: { channel: 'portal' },
        student_distribution: { published_at: `${date}T08:00:00Z`, student_ids: [12] } }
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { connected: false } }) })
    const { wrapper, action, openMail, openRelease } = render({ ...work, status })
    try {
        await flushPromises()
        expect(action('Freigabe verwalten')).toBeDefined()
        await action('Aufgabenmail verwalten')!.trigger('click')
        await flushPromises()
        expect(openMail).toHaveBeenCalledExactlyOnceWith({ channel: 'email' })
        expect(openRelease).not.toHaveBeenCalled()
        await wrapper.setProps({ work: { ...work, status: { ...status, provision_method: { channel: 'portal' } } } })
        expect(action('Aufgabenmail verwalten')).toBeUndefined()
    } finally { wrapper.unmount() }
})


it('offers a read-only entry for an ended release without reopening provisioning or mail', async () => {
    const { wrapper, action, openRelease, openMail } = render({ ...work, status: {
        student_distribution: { published_at: date + 'T09:00:00Z', withdrawn_at: date + 'T10:00:00Z', ended_at: date + 'T11:00:00Z' },
        provision_method: { channel: 'email' }, submission_method: { channel: 'portal', email: null },
    } })
    try {
        expect(wrapper.text()).toContain('Freigabe beendet')
        const phase = wrapper.findAll('summary').find(item => item.text().includes('Bereitstellen'))
        if (phase) await phase.trigger('click')
        expect(action('Freigabe bearbeiten')).toBeUndefined()
        expect(action('Bereitstellungsweg vorbereiten')).toBeUndefined()
        expect(action('Aufgabenmail verwalten')).toBeUndefined()
        await action('Freigabe ansehen')!.trigger('click')
        expect(openRelease).toHaveBeenCalledOnce()
        expect(openMail).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})


it('shows documented delivery methods for the current group independently of the submission method', async () => {
    const time = date + 'T10:00:00Z'
    const otherDate = '2026-11-21'
    const log = { origin: 'dispatch_import', purpose: 'tasks', sha256: 'd'.repeat(64) }
    const { wrapper, action } = render({ ...work, groups: [{ date, student_ids: [12] }, { date: otherDate, student_ids: [13] }], status: {
        student_distribution: { published_at: time, student_ids: [12] },
        provision_method: { channel: 'portal' }, submission_method: { channel: 'portal' },
        dispatch_logs: [log], dispatch_notifications: [{ student_id: 13, sent_at: time, purpose: 'tasks', origin: 'dispatch_import', log_sha256: log.sha256 }],
        assignment_mail: { batches: {
            [date]: { transport: 'mailpit', state: 'sent', recipients: [{ student_id: 12, state: 'accepted', accepted_at: time }] },
            [otherDate]: { transport: 'postmark', state: 'sent', recipients: [{ student_id: 13, state: 'accepted', accepted_at: time }] },
        } },
    } })
    try {
        await wrapper.findAll('button').find(button => button.text().includes('Bereitstellen'))!.trigger('click')
        const provision = wrapper.get('[aria-label="Bereitstellung"]')
        expect(provision.text()).toContain('Im Schülerbereich bereitgestellt')
        expect(provision.text()).toContain('Lokaler Mailpit-Test')
        expect(provision.text()).not.toContain('Per E-Mail versandt')
        expect(action('Freigabe verwalten')).toBeDefined()
    } finally { wrapper.unmount() }
})
