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
    const action = (label: string) => wrapper.findAll('button').find(button => button.attributes('aria-label') === label || button.text() === label)
    const phase = (label: string) => wrapper.findAll('.work-phase').find(item => item.get('.work-phase__title').text() === label)!
    return { wrapper, action, phase, openRelease, openMail }
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
    const post = vi.fn().mockResolvedValue({ data: { ...connection, saved_work: work, exercise: { status: 'missing', checked_at: `${date}T10:00:00Z` } } })
    vi.stubGlobal('axios', { get, post })
    workDropboxStates.set('7:2:65', connection)
    const { wrapper, action } = render()
    try {
        await flushPromises()
        expect(get).toHaveBeenCalledWith(expect.stringContaining('/dropbox'),
            expect.objectContaining({ params: { include_exercise: 1, stored_only: 1, date } }))
        const saved = wrapper.emitted('exercise-status')?.at(-1)?.[0]
        expect(saved).toMatchObject({ status: 'unchecked', checkedAt: null })
        expect(action('Abgabeweg festlegen')).toBeUndefined()
        expect(action('Bereitstellungsweg vorbereiten')).toBeUndefined()
        expect(action('Aufgaben bereitstellen')).toBeUndefined()
        expect(post).not.toHaveBeenCalled()
        await action('Gesamtstatus prüfen')!.trigger('click')
        await flushPromises()
        expect(post).toHaveBeenCalledWith(expect.stringContaining('/dropbox/refresh'),
            expect.objectContaining({ status_only: true, overall: true, stream_progress: true, date }), expect.any(Object))
        expect(wrapper.emitted('exercise-status')?.at(-1)?.[0]).toMatchObject({ status: 'missing', checkedAt: `${date}T10:00:00Z` })
    } finally { wrapper.unmount() }
})

it('shows each streamed check, clears a replaced run and identifies a failed active check without a check mark', async () => {
    const requests: { push: (event: object) => void, finish: () => void, signal: AbortSignal }[] = []
    const post = vi.fn((_url, _data, options) => new Promise(resolve => {
        let ndjson = ''
        requests.push({ signal: options.signal, push(event) {
            ndjson += JSON.stringify(event) + '\n'
            options.onDownloadProgress({ event: { target: { responseText: ndjson } } })
        }, finish: () => resolve({ data: ndjson }) })
    }))
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: connection }), post })
    const { wrapper, action } = render()
    const panel = () => wrapper.get('[aria-label="Fortschritt der Prüfung"]')
    const report = (request: typeof requests[number], active: string, completed: string[] = []) => request.push({ type: 'progress',
        data: { state: 'running', steps: ['connection', 'verification', 'portal'], active, completed } })
    try {
        await flushPromises()
        await action('Gesamtstatus prüfen')!.trigger('click')
        expect(panel().text()).toContain('Prüfung wird gestartet')
        report(requests[0], 'verification', ['connection'])
        await flushPromises()
        expect(panel().get('[aria-current="step"]').text()).toContain('Dropbox-Prüfungsordner prüfen')
        expect(panel().findAll('li').at(0)!.text()).toContain('✓')
        await wrapper.setProps({ date: '2026-11-21' })
        expect(requests[0].signal.aborted).toBe(true)
        expect(wrapper.find('[aria-label="Fortschritt der Prüfung"]').exists()).toBe(false)

        await action('Gesamtstatus prüfen')!.trigger('click')
        report(requests[0], 'portal', ['connection', 'verification'])
        requests[0].finish()
        await flushPromises()
        expect(panel().text()).toContain('Prüfung wird gestartet')
        report(requests[1], 'portal', ['connection', 'verification'])
        await flushPromises()
        expect(panel().get('[aria-current="step"]').text()).toContain('Portalabgaben mit Dropbox abgleichen')
        expect(panel().findAll('.check-progress-done')).toHaveLength(2)
        requests[1].push({ type: 'error', data: { status: 503, body: { message: 'Dropbox ist vorübergehend nicht erreichbar.' } } })
        requests[1].finish()
        await flushPromises()
        expect(panel().text()).toContain('Prüfung unterbrochen')
        expect(panel().findAll('.check-progress-done')).toHaveLength(2)
        expect(panel().get('.check-progress-failed').text()).toContain('Nicht abgeschlossen')
        expect(wrapper.get('[role="alert"]').text()).toContain('Dropbox ist vorübergehend nicht erreichbar.')
        expect(wrapper.emitted('saved')).toBeUndefined()
    } finally { wrapper.unmount() }
})

it('checks the selected date and advances only after every portal original is confirmed', async () => {
    const folder = { id: 'id:exam', name: 'Fiktive Prüfung' }
    const confirmed = { status: 'found', folder, checked_at: `${date}T13:00:00Z`, preparation: { status: 'ready', portal_candidates_id: 'id:candidates' } }
    const current = { ...work, groups: [{ date, student_ids: [12] }], status: {
        submission_method: { channel: 'portal' }, student_distribution: { published_at: `${date}T09:00:00Z`, ended_at: `${date}T12:00:00Z`, student_ids: [12] },
        portal_submissions: [{ id: 'original-1', student_id: 12 }, { id: 'original-2', student_id: 12 }],
    } }
    const result = (complete: boolean) => ({ data: { ...connection, exercise: confirmed, workflow_checked_at: confirmed.checked_at,
        saved_work: { ...current, portal_copy_progress: { [date]: { complete, observed_at: confirmed.checked_at, folder_id: folder.id, candidates_id: 'id:candidates' } } },
        portal_reconciliation: { total: 2, confirmed: complete ? 2 : 1, different: complete ? 0 : 1 },
    } })
    const post = vi.fn().mockResolvedValueOnce(result(false)).mockResolvedValueOnce(result(true))
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: connection }), post })
    workDropboxStates.set('7:2:65', connection)
    const { wrapper, action } = render(current, true, saved => { void wrapper.setProps({ work: saved }) })
    const next = () => wrapper.get('.work-phase--next .work-phase__title').text()
    const completed = () => wrapper.findAll('.work-phase--complete .work-phase__title').map(phase => phase.text())
    try {
        await wrapper.setProps({ assessment: confirmed })
        await flushPromises()
        expect(action('Gesamtstatus prüfen')!.isVisible()).toBe(true)
        expect(completed()).toEqual(['Vorbereiten', 'Bereitstellen'])
        await action('Gesamtstatus prüfen')!.trigger('click')
        await flushPromises()
        expect(next()).toBe('Abgeben')
        expect(wrapper.text()).toContain('1 von 2 Portaldateien in Dropbox bestätigt · 1 weicht ab')
        await action('Gesamtstatus prüfen')!.trigger('click')
        await flushPromises()
        expect(next()).toBe('Bewerten')
        expect(completed()).toEqual(['Vorbereiten', 'Bereitstellen', 'Abgeben'])
        expect(wrapper.text()).toContain('2 von 2 Portaldateien in Dropbox bestätigt')
        expect(post.mock.calls.map(([url, data]) => [url, data.date, data.overall])).toEqual([
            ['/api/admin/teaching/course_works/65/dropbox/refresh', date, true],
            ['/api/admin/teaching/course_works/65/dropbox/refresh', date, true],
        ])
    } finally { wrapper.unmount() }
})

it.each(['direct', 'changed link on path lookup', 'different folder', 'different date', 'missing link', 'module'] as const)(
    'shows the dated Dropbox folder using the current binding: %s', async kind => {
        const folder = { id: 'id:exam', name: date + ' – Kurs-2 – Fiktive Arbeit', path: '/Materialien/Leistungsfeststellungen/' + date + ' – Kurs-2 – Fiktive Arbeit' }
        const proof = { linked: true, folder_id: folder.id, candidates_id: 'id:candidates', observed_at: date + 'T13:00:00Z', complete: true }
        const currentWork = { ...work, groups: [{ date, student_ids: [12] }],
            portal_copy_progress: { [kind === 'different date' ? '2026-11-21' : date]: proof },
            status: { submission_method: { channel: 'portal' }, student_distribution: {
                published_at: date + 'T10:00:00Z', ended_at: date + 'T12:00:00Z', student_ids: [12],
            }, portal_submissions: [{ id: 'original', student_id: 12 }] },
        }
        const linkedState = { ...connection, module_link: kind === 'module' ? connection.module_link : null,
            folder: kind === 'missing link' || kind === 'module' ? null : { name: folder.name, id: kind === 'different folder' ? 'id:old' : folder.id } }
        const get = vi.fn((_url, options) => Promise.resolve({ data: options?.params?.include_folder_path
            ? { ...linkedState, folder, link_version: kind === 'changed link on path lookup' ? 'b'.repeat(64) : linkedState.link_version }
            : linkedState }))
        vi.stubGlobal('axios', { get })
        workDropboxStates.set('7:2:65', linkedState)
        const { wrapper, action } = render(currentWork)
        try {
            await wrapper.setProps({ assessment: { status: 'missing' } })
            await flushPromises()
            const phase = wrapper.findAll('.work-phase').find(item => item.find('.work-phase__title').text() === 'Vorbereiten')!
            expect(phase.get('h4').find('button').exists()).toBe(false)
            expect(action('Prüfungsordner ansehen')).toBeUndefined()
            if (kind === 'direct' || kind === 'changed link on path lookup') {
                const folderName = phase.get('.work-phase__folder-name')
                expect(folderName.text()).toBe(folder.name)
                expect(folderName.attributes('tabindex')).toBe('0')
                expect(phase.text()).not.toContain('Prüfungsordner bestätigt')
                expect(phase.text()).not.toContain('Erledigt')
                expect(phase.text()).not.toContain('Noch nicht erstellt')
                expect(action('Verbindung verwalten')).toBeUndefined()
                expect(get.mock.calls.some(([, options]) => options?.params?.include_folder_path)).toBe(false)
                await folderName.trigger('focus')
                await flushPromises()
                expect(folderName.get('v-tooltip').attributes('text')).toBe(kind === 'direct'
                    ? folder.path : 'Dropbox-Pfad konnte nicht geladen werden.')
                expect(phase.get('.work-phase__body').isVisible()).toBe(true)
            } else {
                expect(phase.find('.work-phase__folder-name').exists()).toBe(false)
                if (kind === 'module') {
                    await action('Verbindung verwalten')!.trigger('click')
                    expect(wrapper.get('[role="dialog"]').text()).toContain('/Module/Fiktiv')
                    expect(get.mock.calls.some(([url]) => url.endsWith('/folders'))).toBe(false)
                } else {
                    expect(phase.text()).not.toContain('Aufgabenstellung bestätigt')
                }
            }
        } finally { wrapper.unmount() }
    },
)

it('shows a confirmed preparation in the header and opens the saved connection from its icon', async () => {
    const folder = { id: 'id:exam', name: date + ' – Kurs-2 – Fiktive Arbeit', path: '/Module/Fiktiv/Leistungsfeststellungen/Prüfung' }
    const exercise = { status: 'found', folder, preparation: { status: 'ready' } }
    const linked = { ...connection, exercise }
    const get = vi.fn().mockResolvedValue({ data: linked })
    vi.stubGlobal('axios', { get })
    workDropboxStates.set('7:2:65', linked)
    const { wrapper, phase } = render()
    try {
        const assessment = { ...exercise, userId: 7, workId: work.id, courseId: course.id, date }
        await wrapper.setProps({ assessment, exerciseReady: true })
        await flushPromises()

        const preparation = phase('Vorbereiten')
        const folderName = preparation.get('.work-phase__folder-name')
        expect(folderName.text()).toBe(folder.name)
        expect(folderName.get('v-tooltip').attributes('text')).toBe(folder.path)
        expect(preparation.text()).not.toContain('Aufgabenstellung bestätigt')
        expect(preparation.find('.work-phase__body button').exists()).toBe(false)
        const controls = preparation.get('.work-phase__heading-action')
        expect(controls.findAll('button').map(button => button.attributes('aria-label')))
            .toEqual(['Verbindung verwalten', 'Leistungsfeststellung verwalten'])
        expect(controls.text()).not.toContain('Verbindung verwalten')

        await wrapper.setProps({ assessment: { ...assessment, folder: null } })
        expect(preparation.find('.work-phase__folder-name').exists()).toBe(false)
        expect(preparation.text()).not.toContain('Aufgabenstellung bestätigt')

        const previousRequests = get.mock.calls.length
        await controls.get('button[aria-label="Verbindung verwalten"]').trigger('click')
        expect(wrapper.get('[role="dialog"]').text()).toContain('/Module/Fiktiv')
        expect(get).toHaveBeenCalledTimes(previousRequests)
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
        expect(wrapper.text()).toContain('Lokaler Mailpit-Test')
        expect(wrapper.text()).not.toContain('vom Versanddienst angenommen')
        const releaseRow = wrapper.get('[aria-label="Bereitstellung"]')
        expect(releaseRow.findComponent({ name: 'WorkProvisionMethod' }).props('hideActivator')).toBe(true)
        expect(wrapper.getComponent({ name: 'WorkAssignmentMail' }).props('hideActivator')).toBe(true)
        const editRelease = wrapper.get('[data-primary="true"] button')
        expect(editRelease.text()).toBe('Freigabe bearbeiten')
        expect(editRelease.attributes('prepend-icon')).toBe('mdi-pencil-outline')
        expect(action('Bewertung verwalten')).toBeUndefined()
        await editRelease.trigger('click')
        await flushPromises()
        expect(put).not.toHaveBeenCalled()
        expect(openRelease).toHaveBeenCalledExactlyOnceWith({ channel: 'portal' })
        expect(post).not.toHaveBeenCalled()
        expect(wrapper.emitted('saved')).toBeUndefined()
        const collected = { ...old, status: { ...old.status, submission_collection_completed: true } }
        await wrapper.setProps({ work: collected })
        const visibleRow = wrapper.get('[aria-label="Sichtbarkeit"]')
        const resultsRow = wrapper.get('[aria-label="Verständigung"]')
        expect(wrapper.text()).toContain('Bewertungen nur für die Lehrkraft sichtbar')
        expect(visibleRow.text()).toContain('Bewertungen freigeben')
        expect(resultsRow.text()).toContain('Keine Mitteilung im Kurs')
        expect(resultsRow.text()).not.toContain('Mailpit')
        expect(resultsRow.findComponent({ name: 'WorkAssignmentMail' }).exists()).toBe(false)
        expect(action('Bewertung eintragen')).toBeUndefined()
        await action('Bewertung verwalten')!.trigger('click')
        const assessmentDialog = wrapper.get('[role="dialog"]')
        expect(assessmentDialog.get('[aria-label="Dialogaktionen"]').findAll('button').map(button => button.text())).toEqual(['Schließen'])
        await action('Schließen')!.trigger('click')
        await action('Importieren')!.trigger('click')
        expect(wrapper.emitted('import')).toEqual([[collected, false, { date, automatic: true }]])
        await wrapper.setProps({ work: { ...collected, status: { ...collected.status, grade_visibility: { visible: true, student_notice: { student_ids: [12] } } } } })
        expect(resultsRow.text()).toContain('Mitteilung im Kurs für 1 Person')
        await wrapper.setProps({ active: false })
        expect(wrapper.findComponent({ name: 'WorkQueueProgress' }).exists()).toBe(false)
    } finally { wrapper.unmount() }
})

it('keeps all phases open and highlights one real action as the saved work progresses', async () => {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { ...connection, exercise: { status: 'unknown' } } }) })
    workDropboxStates.set('7:2:65', connection)
    const { wrapper, action, openRelease, phase } = render()
    const primary = () => wrapper.get('[data-primary="true"] button').text()
    try {
        await flushPromises()
        expect(primary()).toBe('Gesamtstatus prüfen')
        const labels = ['Vorbereiten', 'Bereitstellen', 'Abgeben', 'Bewerten']
        expect(wrapper.findAll('.work-phase__title').map(item => item.text())).toEqual(labels)
        for (const label of labels) {
            const heading = phase(label).get('h4')
            expect(heading.find('button, [aria-expanded], [aria-controls]').exists()).toBe(false)
        }
        for (const label of labels.slice(1)) {
            expect(phase(label).get('h4 v-icon').attributes('icon')).toBe('mdi-lock-outline')
            expect(phase(label).text()).toContain('Zuerst „Vorbereiten“ abschließen')
        }
        await wrapper.setProps({ exerciseReady: true })
        expect(primary()).toBe('Bereitstellungsweg vorbereiten')
        expect(phase('Bereitstellen').get('.work-phase__body').isVisible()).toBe(true)
        expect(phase('Abgeben').text()).toContain('Zuerst „Bereitstellen“ abschließen')
        expect(action('Bewertung verwalten')).toBeUndefined()
        const announced = { ...work, status: { provision_method: { channel: 'portal' }, submission_method: { channel: 'portal' }, student_distribution: { prepared_at: `${date}T08:00:00Z` } } }
        await wrapper.setProps({ work: announced })
        expect(wrapper.text()).toContain('Schülerbereich vorbereitet')
        expect(wrapper.findAll('.work-phase--complete').map(phase => phase.get('.work-phase__title').text())).toEqual(['Vorbereiten'])
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

it('shows the portal cutoff independently of the selected date and the end of the release', async () => {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: connection }) })
    const releasedWork = { ...work, finish_until_date: '2026-11-24', finish_until_time: '12:30', status: {
        submission_method: { channel: 'portal' }, student_distribution: { published_at: `${date}T09:00:00Z`, student_ids: [12] },
    } }
    const { wrapper, action, phase } = render(releasedWork, false)
    const submission = () => phase('Abgeben').get('h4')
    try {
        await wrapper.setProps({ now: Date.parse('2026-11-24T11:29:59Z') })
        expect(submission().text()).not.toContain('Portalabgabe geschlossen')
        expect(submission().text()).not.toContain('Erledigt')
        expect(phase('Abgeben').get('.work-phase__heading-action button').attributes('aria-label')).toBe('Abgabeweg verwalten')
        await action('Abgabeweg verwalten')!.trigger('click')

        await wrapper.setProps({ now: Date.parse('2026-11-24T11:30:01Z') })
        expect(submission().text()).toContain('Portalabgabe geschlossen · 0 Abgaben')
        expect(submission().text()).not.toContain('Erledigt')
        const notice = wrapper.get('[role="dialog"] label:has(> input[value="portal"]) + [role="status"]')
        expect(notice.text()).toBe('Abgabefrist abgelaufen. Datei-Upload gesperrt.')
        expect(action('Schülerabgaben einsehen')).toBeUndefined()

        await wrapper.setProps({ date: '2026-11-25' })
        expect(submission().text()).toContain('Portalabgabe geschlossen · 0 Abgaben')

        await wrapper.setProps({ work: { ...releasedWork, finish_until_time: '13:30' } })
        expect(submission().text()).not.toContain('Portalabgabe geschlossen')
        expect(submission().text()).not.toContain('Erledigt')
        expect(wrapper.get('[role="dialog"]').text()).not.toContain('Datei-Upload gesperrt')
    } finally { wrapper.unmount() }
})

it.each(['selected', 'all'])('opens expired portal submissions from Abgeben for the requested scope: %s', async scope => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-11-24T11:30:01Z'))
    const reopened = { student_id: 12, date, ends_at: '2026-11-24T13:45:00+00:00', release_ended_at: null }
    const released = { ...work, finish_until_date: '2026-11-24', finish_until_time: '12:30', status: {
        submission_method: { channel: 'portal' }, student_distribution: { published_at: `${date}T09:00:00Z` },
    } }
    const saved = { ...released, status: { ...released.status, portal_submission_reopenings: [reopened] } }
    const students = [{ id: 12, name: 'Alex Beispiel', open_until: null },
        { id: 13, name: 'Sascha Test', open_until: null }, { id: 14, name: 'Kim Probe', open_until: '2026-11-24T14:00:00Z' }]
    const get = vi.fn((url: string) => Promise.resolve({ data: url.endsWith('/submission-reopening')
        ? { data: { date, students, ambiguous: true } } : connection }))
    const post = vi.fn().mockResolvedValue({ data: { data: saved } })
    vi.stubGlobal('axios', { get, post })
    const { wrapper, action, phase } = render(released, false)
    try {
        await wrapper.setProps({ now: Date.parse('2026-11-24T11:30:01Z') })
        const headingActions = phase('Abgeben').findAll('.work-phase__heading-action button[aria-haspopup="dialog"]')
        expect(headingActions.map(button => button.attributes('aria-label'))).toEqual(['Portalabgabe wieder öffnen', 'Abgabeweg verwalten'])
        await action('Portalabgabe wieder öffnen')!.trigger('click')
        await flushPromises()
        expect(get).toHaveBeenCalledWith('/api/admin/teaching/course_works/65/submission-reopening', { params: { date } })
        const dialog = wrapper.get('[role="dialog"]')
        expect(dialog.text()).toContain('Kim Probe · offen bis')
        expect(dialog.text()).toContain('Personen mit mehreren Durchführungsterminen werden bei dieser Wiederöffnung ausgelassen.')
        await dialog.get(`input[value="${scope}"]`).setValue(true)
        await dialog.get('input[type="datetime-local"]').setValue('2026-11-24T14:45')
        if (scope === 'selected') {
            expect(action('Wieder öffnen')!.attributes()).toHaveProperty('disabled')
            await dialog.get('input[value="12"]').setValue(true)
        }
        await dialog.get('form').trigger('submit')
        await flushPromises()
        expect(post).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/65/submission-reopening', {
            date, scope, deadline_date: '2026-11-24', deadline_time: '14:45', ...(scope === 'selected' ? { student_ids: [12] } : {}),
        })
        expect(wrapper.emitted('saved')?.[0]).toEqual([saved])
        await wrapper.setProps({ work: saved })
        expect(phase('Abgeben').get('h4').text()).toContain('Persönliche Frist aktiv')
        expect(phase('Abgeben').get('h4').text()).not.toContain('Erledigt')
        expect(wrapper.text()).toContain('Upload für 1 wieder geöffnet')
    } finally { wrapper.unmount(); vi.useRealTimers() }
})

it('suggests the next Vienna half hour on each opening and keeps a manually edited deadline', async () => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-11-24T22:30:00Z'))
    const released = { ...work, finish_until_date: '2026-11-24', finish_until_time: '12:30', status: {
        submission_method: { channel: 'portal' }, student_distribution: { ended_at: '2026-11-24T11:30:00Z' },
    } }
    const get = vi.fn((url: string) => Promise.resolve({ data: url.endsWith('/submission-reopening')
        ? { data: { date, students: [{ id: 12, name: 'Alex Beispiel', open_until: null }] } } : connection }))
    const post = vi.fn()
    vi.stubGlobal('axios', { get, post })
    const { wrapper, action } = render(released, false)
    try {
        await action('Portalabgabe wieder öffnen')!.trigger('click')
        await flushPromises()
        const dialog = wrapper.get('[role="dialog"]')
        const input = () => wrapper.get<HTMLInputElement>('input[type="datetime-local"]')
        expect(input().element.value).toBe('2026-11-25T00:00')
        await dialog.get('input[value="all"]').setValue(true)
        for (const invalid of ['2026-11-24T23:00', '2027-03-28T02:30']) {
            await input().setValue(invalid)
            expect(dialog.text()).toContain('Gib eine gültige zukünftige Upload-Frist')
            await dialog.get('form').trigger('submit')
        }
        expect(post).not.toHaveBeenCalled()
        await input().setValue('2026-11-25T01:10')
        vi.setSystemTime(new Date('2026-11-24T22:37:00Z'))
        await wrapper.setProps({ now: Date.now() })
        expect(input().element.value).toBe('2026-11-25T01:10')
        await dialog.findAll('button').find(button => button.text() === 'Schließen')!.trigger('click')
        await action('Portalabgabe wieder öffnen')!.trigger('click')
        await flushPromises()
        expect(input().element.value).toBe('2026-11-25T00:30')
    } finally { wrapper.unmount(); vi.useRealTimers() }
})

it('moves a closed portal to Bewerten and shows submission evidence for the selected date', async () => {
    const otherDate = '2026-11-23'
    const released = { ...work, finish_until_date: '2026-11-24', finish_until_time: '12:30', groups: [
        { date, student_ids: [12] }, { date: otherDate, student_ids: [13, 14] },
    ], status: { automation_enabled: false, submission_method: { channel: 'portal' }, student_distribution: {
        published_at: `${date}T08:00:00Z`, ended_at: '2026-11-24T11:45:00Z', student_ids: [12, 13, 14],
    } } }
    const file = (student_id: number) => ({ id: `file-${student_id}`, student_id, name: 'Beispiel.pdf',
        sha256: 'a'.repeat(64), submitted_at: '2026-11-23T11:00:00Z' })
    const withFiles = (ids: number[]) => ({ ...released, status: { ...released.status, portal_submissions: ids.map(file) } })
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: connection }) })
    const { wrapper, action, phase } = render(withFiles([12, 13]), false)
    const completed = () => wrapper.findAll('.work-phase--complete').map(phase => phase.get('.work-phase__title').text())
    const next = () => wrapper.get('.work-phase--current .work-phase__title').text()
    try {
        await wrapper.setProps({ now: Date.parse('2026-11-24T12:00:00Z') })
        expect(completed()).toEqual(['Vorbereiten', 'Bereitstellen', 'Abgeben'])
        expect(next()).toBe('Bewerten')
        expect(wrapper.get('.work-phase--next-assessment').text()).toContain('Nächster Schritt')
        expect(phase('Vorbereiten').text()).not.toContain('Erledigt')
        expect(phase('Bereitstellen').text()).not.toContain('Erledigt')
        expect(phase('Abgeben').text()).not.toContain('Erledigt')
        expect(action('Schülerabgaben einsehen')).toBeUndefined()
        expect(action('Abgabestatus ansehen')).toBeUndefined()

        await wrapper.setProps({ date: otherDate })
        expect(completed()).toEqual(['Vorbereiten', 'Bereitstellen', 'Abgeben'])
        expect(next()).toBe('Bewerten')
        expect(action('Schülerabgaben einsehen')).toBeUndefined()

        await wrapper.setProps({ work: withFiles([12]) })
        expect(next()).toBe('Bewerten')
        expect(action('Schülerabgaben einsehen')).toBeUndefined()
        expect(completed()).toEqual(['Vorbereiten', 'Bereitstellen', 'Abgeben'])

        await wrapper.setProps({ work: withFiles([12, 13, 14]) })
        expect(completed()).toContain('Abgeben')
        expect(next()).toBe('Bewerten')
        expect(action('Schülerabgaben einsehen')).toBeUndefined()

        const graded = withFiles([12])
        await wrapper.setProps({ work: { ...graded, groups: [graded.groups[0], { ...graded.groups[1], grade: '2' }] } })
        expect(completed()).toEqual(['Vorbereiten', 'Bereitstellen', 'Abgeben', 'Bewerten'])
        expect(wrapper.find('.work-phase--next-assessment').exists()).toBe(false)
    } finally { wrapper.unmount() }
})

it('copies the canonical grading order directly from Bewerten for the selected prepared date', async () => {
    const otherDate = '2026-11-23'
    const released = { ...work, groups: [{ date, student_ids: [12] }, { date: otherDate, student_ids: [13] }],
        portal_copy_progress: { [otherDate]: { linked: true, folder_id: 'id:exam', complete: true } }, status: {
        submission_method: { channel: 'portal' }, student_distribution: { published_at: `${date}T08:00:00Z`, student_ids: [12, 13],
            ended_at: '2026-11-24T11:00:00Z' },
        portal_submissions: [{ id: 'file-13', student_id: 13, sha256: 'a'.repeat(64), submitted_at: `${otherDate}T10:00:00Z` }],
    } }
    const text = 'Geprüfter Bewertungsauftrag für Durchführung am 23.11.2026'
    const get = vi.fn((url: string) => Promise.resolve({ data: url.endsWith('/ai-export') ? { data: {
        work_id: 65, course_id: 2, roster_complete: true, text, context: { purpose: 'grade-exercise', mode: 'grade',
            work: { scheduled_date: otherDate }, operation: { grade: true, preserve_existing: true, send: false, schooltool_import: false } },
    } } : connection }))
    const writeText = vi.fn().mockResolvedValue(undefined)
    vi.stubGlobal('axios', { get })
    vi.stubGlobal('navigator', { userAgent: 'Test', clipboard: { writeText } })
    const { wrapper, action } = render(released, false)
    try {
        const trigger = () => action('Bewertungskontext kopieren')!
        expect(trigger().attributes()).toHaveProperty('disabled')
        await wrapper.setProps({ date: otherDate })
        expect(wrapper.get('.work-phase--next-assessment .work-phase__title').text()).toBe('Bewerten')
        expect(trigger().attributes('disabled')).toBeUndefined()
        expect(trigger().attributes('min-height')).toBe('44')
        await trigger().trigger('click')
        await flushPromises()
        expect(get).toHaveBeenCalledWith('/api/admin/teaching/course_works/65/ai-export', expect.objectContaining({
            params: { purpose: 'evaluation', evaluation_mode: 'grade', date: otherDate },
        }))
        expect(writeText).toHaveBeenCalledExactlyOnceWith(text)
        expect(wrapper.get('[role="dialog"] [role="status"]').text()).toContain('Bewertungsauftrag kopiert')
    } finally { wrapper.unmount() }
})

it('copies the grading order for a confirmed assessment once the empty portal closes', async () => {
    const deadline = '2026-11-24T11:30:00Z'
    const released = { ...work, date_for_all_groups: date, finish_until_date: '2026-11-24', finish_until_time: '12:30',
        status: { submission_method: { channel: 'portal' }, portal_submissions: [],
            student_distribution: { published_at: `${date}T09:00:00Z`, student_ids: [12] } } }
    const assessment = { status: 'found', userId: 7, workId: work.id, courseId: course.id, date,
        folder: { id: 'id:exercise', name: `${date}_Fiktive Arbeit` }, assignmentStatus: 'ready' }
    const context = {
        purpose: 'grade-exercise', mode: 'grade', work_id: work.id, course_id: course.id,
        work: { title: work.title, scheduled_date: date }, submission_channel: 'portal', portal_submissions: [],
        operation: { grade: true, preserve_existing: true, send: false, schooltool_import: false, simulation: false },
    }
    const text = `# Auftrag: Arbeit bewerten\n\nFehlende Abgaben nach Frist und Bestand prüfen.\n\n${JSON.stringify(context)}`
    const get = vi.fn((url: string) => Promise.resolve({ data: url.endsWith('/ai-export') ? { data: {
        work_id: work.id, course_id: course.id, roster_complete: true, text, context,
    } } : connection }))
    const writeText = vi.fn().mockResolvedValue(undefined)
    vi.stubGlobal('axios', { get })
    vi.stubGlobal('navigator', { userAgent: 'Test', clipboard: { writeText } })
    workDropboxStates.set('7:2:65', connection)
    const { wrapper, action, phase } = render(released, false)
    const copy = () => action('Bewertungskontext kopieren')!
    const submission = () => phase('Abgeben').get('h4')
    try {
        await wrapper.setProps({ assessment, exerciseReady: true, now: Date.parse(deadline) - 1000 })
        expect(action('Bewertungskontext kopieren')).toBeUndefined()

        const afterDeadline = Date.parse(deadline) + 1000
        const reopening = { student_id: 12, date, ends_at: '2026-11-24T13:45:00Z', release_ended_at: null }
        await wrapper.setProps({ now: afterDeadline, work: { ...released, status: { ...released.status,
            portal_submission_reopenings: [reopening] } } })
        expect(submission().text()).toContain('Persönliche Frist aktiv')
        expect(action('Bewertungskontext kopieren')).toBeUndefined()

        await wrapper.setProps({ work: released, exerciseReady: false, assessment: { ...assessment, status: 'missing' } })
        expect(submission().text()).toContain('Portalabgabe geschlossen · 0 Abgaben')
        expect(copy().element.disabled).toBe(true)

        await wrapper.setProps({ assessment, exerciseReady: true, disabled: true })
        expect(copy().element.disabled).toBe(true)
        expect(get.mock.calls.some(([url]) => url.endsWith('/ai-export'))).toBe(false)
        expect(writeText).not.toHaveBeenCalled()

        await wrapper.setProps({ disabled: false })
        expect(wrapper.get('.work-phase--next-assessment .work-phase__title').text()).toBe('Bewerten')
        expect(copy().isVisible()).toBe(true)
        expect(copy().element.disabled).toBe(false)
        await copy().trigger('click')
        await flushPromises()

        expect(get).toHaveBeenCalledWith('/api/admin/teaching/course_works/65/ai-export', expect.objectContaining({
            params: { purpose: 'evaluation', evaluation_mode: 'grade', date },
        }))
        expect(writeText).toHaveBeenCalledExactlyOnceWith(text)
        expect(wrapper.get('[role="dialog"] [role="status"]').text()).toContain('Bewertungsauftrag kopiert')
    } finally { wrapper.unmount() }
})

it('keeps a saved portal upload accessible via the submission gear after the release is ended', async () => {
    const released = { ...work, date_for_all_groups: date, status: {
        submission_method: { channel: 'portal' },
        student_distribution: { published_at: `${date}T08:00:00Z`, student_ids: [12], ended_at: `${date}T12:00:00Z` },
        portal_submissions: [{ id: 'test-file', student_id: 12, student_name: 'Ada Test', name: 'abgabe.txt', submitted_at: `${date}T10:00:00Z` }],
    } }
    const get = vi.fn().mockResolvedValue({ data: connection })
    vi.stubGlobal('axios', { get })
    const { wrapper, action, phase } = render(released, false)
    try {
        expect(wrapper.get('.work-phase--next-copy .work-phase__title').text()).toBe('Abgeben')
        expect(phase('Abgeben').get('h4').text()).toContain('Portalabgabe geschlossen · 1 Abgabe')
        expect(action('Schülerabgaben einsehen')).toBeUndefined()
        await action('Abgabeweg verwalten')!.trigger('click')
        await flushPromises()
        expect(wrapper.get('[role="dialog"]').text()).toContain('abgabe.txt')
        expect(wrapper.get('[role="dialog"] [aria-label="abgabe.txt herunterladen"]').attributes('download')).toBe('abgabe.txt')
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
    const get = vi.fn().mockResolvedValue({ data: connection })
    const post = vi.fn()
    vi.stubGlobal('axios', { get, put, post })
    workDropboxStates.set('7:2:65', connection)
    const { wrapper, action, phase, openMail } = render(original, true, updated => { void wrapper.setProps({ work: updated }) })
    try {
        await wrapper.setProps({ assessment: { status: 'found' } })
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
        expect(phase('Bereitstellen').get('h4 .work-phase__status').text()).toBe('Per E-Mail')
        expect(post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('allows planning a portal provision once the folder is found, even if the release is unavailable', async () => {
    const submission = { channel: 'portal', email: null }
    const saved = { ...work, status: { provision_method: { channel: 'portal' }, submission_method: submission } }
    const put = vi.fn().mockResolvedValue({ data: { data: saved } })
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: connection }), put })
    workDropboxStates.set('7:2:65', connection)
    const { wrapper, action, phase, openRelease, openMail } = render(work, true, updated => { void wrapper.setProps({ work: updated }) })
    try {
        await wrapper.setProps({ assessment: { status: 'found' } })
        await flushPromises()
        await action('Bereitstellungsweg vorbereiten')!.trigger('click')
        await wrapper.get('.provision-choices input[value="portal"]').setValue()
        await wrapper.get('.submission-methods input[value="portal"]').setValue()
        await action('Auswahl speichern')!.trigger('click')
        await flushPromises()
        expect(put).toHaveBeenCalledExactlyOnceWith(expect.stringContaining('/provision-method'), { channel: 'portal', expected: null, submission, expected_submission: null })
        expect(phase('Bereitstellen').get('h4 .work-phase__status').text()).toBe('Im Schülerbereich')
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


it('keeps follow-up statuses visible and closes their actions until the required assessment is confirmed', async () => {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: connection }) })
    workDropboxStates.set('7:2:65', connection)
    const { wrapper, action, phase } = render()
    const assessment = { status: 'missing', userId: 7, workId: work.id, courseId: course.id, date }
    try {
        await flushPromises()
        await wrapper.setProps({ exerciseReady: true })
        await action('Bereitstellungsweg vorbereiten')!.trigger('click')
        expect(wrapper.get('[role="dialog"]').text()).toContain('Bereitstellungsweg')

        await wrapper.setProps({ assessment, exerciseReady: false })

        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
        expect(phase('Vorbereiten').get('.work-phase__body').isVisible()).toBe(true)
        expect(wrapper.get('[data-primary="true"] button').text()).toBe('Leistungsfeststellung erstellen')
        for (const label of ['Bereitstellen', 'Abgeben', 'Bewerten']) {
            expect(phase(label).get('h4').text()).toContain('Zuerst Leistungsfeststellung erstellen')
            if (label !== 'Bereitstellen') expect(phase(label).get('.work-phase__body').isVisible()).toBe(true)
            expect(phase(label).find('button').exists()).toBe(false)
        }
        await action('Leistungsfeststellung erstellen')!.trigger('click')
        expect(wrapper.get('[role="dialog"]').text()).toContain('Leistungsfeststellung erstellen')
        await action('Schließen')!.trigger('click')

        await wrapper.setProps({ assessment: { ...assessment, status: 'found', folder: { id: 'id:exercise', name: 'Fiktive Prüfung' } } })

        expect(action('Bereitstellungsweg vorbereiten')!.isVisible()).toBe(true)
        expect(action('Abgabeweg festlegen')).toBeUndefined()
        expect(action('Bewertung verwalten')).toBeUndefined()
    } finally { wrapper.unmount() }
})

it('allows grading manual work and preparing a previously configured release as the next step', async () => {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: connection }) })
    workDropboxStates.set('7:2:65', connection)
    const { wrapper, action } = render({ ...work, status: { automation_enabled: false } })
    try {
        await wrapper.setProps({ assessment: { status: 'missing', userId: 7, workId: work.id, courseId: course.id, date } })
        expect(action('Bewertung verwalten')!.isVisible()).toBe(true)

        await wrapper.setProps({ work: { ...work, status: { student_distribution: { prepared_at: `${date}T08:00:00Z` } } } })
        expect(action('Bereitstellungsweg vorbereiten')!.isVisible()).toBe(true)
        expect(action('Bewertung verwalten')).toBeUndefined()
    } finally { wrapper.unmount() }
})

it('shows an active background job and leaves status and read-only module access available', async () => {
    const status = { ...connection, exercise: { status: 'missing', reusable: 'linked' } }
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: status }), post: vi.fn() })
    workDropboxStates.set('7:2:65', connection)
    const run = { id: 'run-workflow', date, state: 'queued', queued_at: `${date}T10:00:00Z` }
    const { wrapper, action, phase } = render()
    try {
        await wrapper.setProps({ assessment: { status: 'missing', userId: 7, workId: work.id, courseId: course.id, date },
            lastCheck: 'vor dem Auftrag' })
        await flushPromises()

        await wrapper.setProps({ work: { ...work, assessment_run: run } as typeof work })
        expect(wrapper.get('[data-testid="assessment-background-status"]').text()).toContain('wartet auf den Start')
        expect(wrapper.find('[data-testid="work-dialog-last-check"]').exists()).toBe(false)
        const preparation = wrapper.findAll('.work-phase').find(item => item.get('.work-phase__title').text() === 'Vorbereiten')!
        expect(preparation.get('.work-phase__body').isVisible()).toBe(true)
        for (const label of ['Bereitstellen', 'Abgeben', 'Bewerten']) {
            expect(phase(label).get('h4').text()).toContain('Während der Erstellung gesperrt')
            if (label !== 'Bereitstellen') expect(phase(label).get('.work-phase__body').isVisible()).toBe(true)
            expect(phase(label).find('button').exists()).toBe(false)
        }
        expect(action('Leistungsfeststellung erstellen')).toBeUndefined()
        expect(action('Gesamtstatus prüfen')!.attributes('disabled')).toBeUndefined()
        expect(action('Erstellungsauftrag anzeigen')!.attributes('disabled')).toBeUndefined()
        expect(action('Lernmodul ansehen')!.attributes('disabled')).toBeUndefined()
        expect(action('Bewertung verwalten')).toBeUndefined()
        await action('Gesamtstatus prüfen')!.trigger('click')
        expect(axios.post).not.toHaveBeenCalled()
        observeAssessmentRun('7:2:65', { ...run, state: 'running' })
        await flushPromises()
        expect(wrapper.get('[data-testid="assessment-background-status"]').text()).toContain('wird im Hintergrund erstellt')
        observeAssessmentRun('7:2:65', { ...run, state: 'failed' })
        await flushPromises()
        expect(wrapper.find('[data-testid="assessment-background-status"]').exists()).toBe(false)
        for (const label of ['Bereitstellen', 'Abgeben', 'Bewerten']) {
            expect(phase(label).get('h4').text()).toContain('Zuerst Leistungsfeststellung erstellen')
            expect(phase(label).find('button').exists()).toBe(false)
        }
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


it('shows the portal method and one read-only gear for an ended release', async () => {
    const { wrapper, action, phase, openRelease, openMail } = render({ ...work, status: {
        student_distribution: { published_at: date + 'T09:00:00Z', withdrawn_at: date + 'T10:00:00Z', ended_at: date + 'T11:00:00Z' },
        provision_method: { channel: 'email' }, submission_method: { channel: 'portal', email: null },
    } })
    try {
        const provision = phase('Bereitstellen')
        expect(provision.get('h4 .work-phase__status').text()).toBe('Im Schülerbereich')
        expect(provision.text()).not.toMatch(/Erledigt|Freigabe beendet|Bereitstellung|Im Schülerbereich bereitgestellt/)
        const gear = provision.get('.work-phase__heading-row button[aria-label="Freigabe ansehen"]')
        expect(gear.get('v-icon').attributes('icon')).toBe('mdi-cog-outline')
        expect(gear.get('v-tooltip').attributes('text')).toBe('Freigabe ansehen')
        expect(provision.findAll('button').some(button => button.text() === 'Freigabe ansehen')).toBe(false)
        expect(action('Freigabe bearbeiten')).toBeUndefined()
        expect(action('Bereitstellungsweg vorbereiten')).toBeUndefined()
        expect(action('Aufgabenmail verwalten')).toBeUndefined()
        await wrapper.setProps({ disabled: true })
        expect(gear.element.hasAttribute('disabled')).toBe(true)
        await gear.trigger('click')
        expect(openRelease).not.toHaveBeenCalled()
        await wrapper.setProps({ disabled: false })
        await gear.trigger('click')
        await flushPromises()
        expect(openRelease).toHaveBeenCalledOnce()
        expect(openMail).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it.each([false, true])('recommends copying from Abgeben until the server confirms all originals: %s', async complete => {
    const openedTarget = { status: 'found', folder: { id: 'id:exam' }, preparation: { status: 'ready' } }
    const progress = { [date]: { folder_id: 'id:exam', complete } }
    const post = vi.fn().mockResolvedValueOnce({ data: { data: { work_id: work.id, date, run_id: 'a-run',
        files: [
            { item: 0, name: 'Original.pdf' },
            { item: 1, name: 'Schon-da.pdf' },
            { item: 2, name: 'Offen.pdf' },
        ] } } })
        .mockResolvedValueOnce({ data: { data: { status: 'copied' } } })
        .mockResolvedValueOnce({ data: { data: { status: 'existing' } } })
        .mockResolvedValueOnce({ data: { data: {
            ...(complete ? { status: 'copied' } : { status: 'open', message: 'Der persönliche Dropbox-Abgabeordner fehlt.' }),
            portal_copy_progress: progress,
        } } })
    vi.stubGlobal('axios', { post, get: vi.fn().mockResolvedValue({ data: connection }) })
    const distribution = { published_at: date + 'T09:00:00Z', ended_at: date + 'T11:00:00Z', student_ids: [12] }
    const status = { student_distribution: distribution, submission_method: { channel: 'portal' },
        portal_submissions: [{ student_id: 12, id: 'an-original' }] }
    const current = { ...work, groups: [{ date, student_ids: [12] }], status }
    const { wrapper, action, phase } = render(current)
    const label = 'Abgaben in Dropbox kopieren'
    try {
        await wrapper.setProps({ now: Date.parse(date + 'T12:00:00Z'), assessment: openedTarget })
        const next = () => wrapper.get('.work-phase--current .work-phase__title').text()
        expect(next()).toBe('Abgeben')
        expect(phase('Bewerten').text()).toContain('Zuerst „Abgeben“ abschließen')
        expect(action('Bewertung verwalten')).toBeUndefined()
        expect(action('Importieren')).toBeUndefined()
        expect(action(label)?.isVisible()).toBe(true)
        expect(action(label)?.element.closest('[data-primary="true"]')).toBeTruthy()
        const headingActions = wrapper.get('.work-phase--next-copy .work-phase__heading-action')
        expect(headingActions.findAll('button[aria-haspopup="dialog"]').map(button => button.attributes('aria-label')))
            .toEqual([label, 'Portalabgabe wieder öffnen', 'Abgabeweg verwalten'])
        expect(action(label)?.attributes('prepend-icon')).toBe('mdi-dropbox')
        expect(action('Schülerabgaben einsehen')).toBeUndefined()
        expect(post).not.toHaveBeenCalled()
        await action(label)!.trigger('click')
        await flushPromises()
        expect(post.mock.calls.map(([url, data]) => [url, data])).toEqual([
            ['/api/admin/teaching/course_works/65/submissions/copy', { date }],
            ...[0, 1, 2].map(item => ['/api/admin/teaching/course_works/65/submissions/copy/file', { run_id: 'a-run', item }]),
        ])
        expect(wrapper.text()).toContain(complete ? '2 kopiert · 1 bereits vorhanden · 0 offen' : '1 kopiert · 1 bereits vorhanden · 1 offen')
        if (!complete) expect(wrapper.text()).toContain('Der persönliche Dropbox-Abgabeordner fehlt.')
        expect(wrapper.emitted('saved')?.at(-1)?.[0]).toMatchObject({ portal_copy_progress: progress })
        await wrapper.setProps({ work: { ...current, portal_copy_progress: progress } })
        expect(next()).toBe(complete ? 'Bewerten' : 'Abgeben')
        expect(wrapper.get('[role="dialog"]').isVisible()).toBe(true)
        await action('Schließen')!.trigger('click')
        if (complete) {
            await action('Bewertung verwalten')!.trigger('click')
            expect(wrapper.get('[role="dialog"]').text()).toContain('Bewerten')
        } else {
            expect(action('Bewertung verwalten')).toBeUndefined()
        }

        await wrapper.setProps({ work: { ...current, status: { ...status, portal_submission_reopenings: [
            { student_id: 12, date, release_ended_at: distribution.ended_at, ends_at: date + 'T13:00:00Z' },
        ] } } })
        expect(action(label)).toBeUndefined()
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
        expect(action('Bewertung verwalten')).toBeUndefined()
        await wrapper.setProps({ work: { ...current, status: { ...status, submission_method: { channel: 'email' } } } })
        expect(action(label)).toBeUndefined()
        await wrapper.setProps({ work: { ...current, status: { ...status,
            student_distribution: { ...distribution, ended_at: null, withdrawn_at: date + 'T11:00:00Z' } } } })
        expect(action(label)).toBeUndefined()
    } finally { wrapper.unmount() }
})


it('shows the candidate of the in-flight request and confirms a row only after all its originals succeed', async () => {
    const target = { status: 'found', folder: { id: 'id:exam' }, preparation: { status: 'ready' } }
    const candidates = [{ id: 12, name: 'Ada Test' }, { id: 13, name: 'Ben Test' }, { id: 14, name: 'Cem Test' }]
    const originals = [
        { item: 0, candidate_id: 12, name: 'Erste.docx' }, { item: 1, candidate_id: 12, name: 'Zweite.docx' },
        { item: 2, candidate_id: 14, name: 'Dritte.docx' },
    ]
    const plan = (run_id: string) => ({ data: { data: { work_id: work.id, date, run_id, candidates, files: originals } } })
    const result = (status: string, message?: string) => ({ data: { data: { status, message } } })
    let confirmFirst: (value: ReturnType<typeof result>) => void = () => {}
    const post = vi.fn().mockResolvedValueOnce(plan('one'))
        .mockImplementationOnce(() => new Promise(resolve => { confirmFirst = resolve }))
        .mockResolvedValueOnce(result('open', 'Namenskonflikt')).mockResolvedValueOnce(result('copied'))
        .mockResolvedValueOnce(plan('two'))
        .mockResolvedValueOnce(result('existing')).mockResolvedValueOnce(result('copied')).mockResolvedValueOnce(result('existing'))
    vi.stubGlobal('axios', { post, get: vi.fn().mockResolvedValue({ data: connection }) })
    const { wrapper, action } = render({ ...work, groups: [{ date, student_ids: [12, 13, 14] }], status: {
        student_distribution: { published_at: date + 'T09:00:00Z', ended_at: date + 'T11:00:00Z', student_ids: [12, 13, 14] },
        submission_method: { channel: 'portal' }, portal_submissions: [{ student_id: 12, id: 'a' }, { student_id: 14, id: 'b' }],
    } }, false)
    const row = (name: string) => wrapper.findAll('[aria-label="Kandidat:innen und Kopierstatus"] > li').find(row => row.text().includes(name))!
    try {
        await wrapper.setProps({ assessment: target, now: Date.parse(date + 'T12:00:00Z') })
        await action('Abgaben in Dropbox kopieren')!.trigger('click')
        await flushPromises()
        expect(row('Ada Test').attributes('aria-current')).toBe('true')
        expect(row('Ada Test').text()).toContain('Wird bearbeitet · 0/2 Dateien')
        expect(row('Ben Test').text()).toContain('Keine Portalabgabe')
        expect(row('Cem Test').text()).toContain('Wartet')

        confirmFirst(result('copied'))
        await flushPromises()
        expect(row('Ada Test').text()).toContain('Offen · 1/2 Dateien')
        expect(row('Ada Test').text()).toContain('Zweite.docx · Namenskonflikt')
        expect(row('Ada Test').find('[icon="mdi-check-circle"]').exists()).toBe(false)
        expect(row('Cem Test').find('[icon="mdi-check-circle"]').exists()).toBe(true)

        await action('Erneut prüfen und kopieren')!.trigger('click')
        await flushPromises()
        expect(row('Ada Test').text()).toContain('Abgelegt · 2/2 Dateien')
        expect(row('Ada Test').find('[icon="mdi-check-circle"]').exists()).toBe(true)
        expect(row('Cem Test').text()).toContain('Abgelegt')
        expect(action('Erneut prüfen und kopieren')).toBeUndefined()
    } finally { wrapper.unmount() }
})

it('shows documented delivery methods for the current group independently of the submission method', async () => {
    const time = date + 'T10:00:00Z'
    const otherDate = '2026-11-21'
    const log = { origin: 'dispatch_import', purpose: 'tasks', sha256: 'd'.repeat(64) }
    const { wrapper, action, phase } = render({ ...work, groups: [{ date, student_ids: [12] }, { date: otherDate, student_ids: [13] }], status: {
        student_distribution: { published_at: time, student_ids: [12] },
        provision_method: { channel: 'portal' }, submission_method: { channel: 'portal' },
        dispatch_logs: [log], dispatch_notifications: [{ student_id: 13, sent_at: time, purpose: 'tasks', origin: 'dispatch_import', log_sha256: log.sha256 }],
        assignment_mail: { batches: {
            [date]: { transport: 'mailpit', state: 'sent', recipients: [{ student_id: 12, state: 'accepted', accepted_at: time }] },
            [otherDate]: { transport: 'postmark', state: 'sent', recipients: [{ student_id: 13, state: 'accepted', accepted_at: time }] },
        } },
    } })
    try {
        const provision = wrapper.get('[aria-label="Bereitstellung"]')
        expect(provision.isVisible()).toBe(true)
        expect(phase('Bereitstellen').get('h4 .work-phase__status').text()).toBe('Im Schülerbereich')
        expect(provision.text()).not.toContain('Im Schülerbereich bereitgestellt')
        expect(provision.text()).toContain('Lokaler Mailpit-Test')
        expect(provision.text()).not.toContain('Per E-Mail versandt')
        expect(action('Freigabe verwalten')).toBeDefined()
    } finally { wrapper.unmount() }
})

it('shows the selected email method and keeps its unconfirmed dispatch visible', () => {
    const { wrapper, phase } = render({ ...work, status: {
        provision_method: { channel: 'email' }, submission_method: { channel: 'portal' },
        assignment_mail: { batches: { [date]: { transport: 'postmark', state: 'failed',
            recipients: [{ student_id: 12, state: 'rejected' }] } } },
    } }, false)
    try {
        const provision = phase('Bereitstellen')
        expect(provision.get('h4 .work-phase__status').text()).toBe('Per E-Mail')
        expect(provision.text()).toContain('E-Mail-Versand nicht bestätigt')
        expect(provision.find('button[aria-label="Freigabe ansehen"]').exists()).toBe(false)
    } finally { wrapper.unmount() }
})
