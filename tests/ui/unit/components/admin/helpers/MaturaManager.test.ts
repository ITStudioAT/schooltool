import { fireEvent, render, screen, waitFor, cleanup, within } from '@testing-library/vue'
import { reactive, nextTick } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import axios from 'axios'
import MaturaManager from '@/pages/admin/helpers/matura/MaturaManager.vue'
import { setupDraftScope, setupDraftKey, writeSetupDraft, readSetupDraft, readActiveSetup } from '@/pages/admin/helpers/matura/maturaSetupDraft'

const context = vi.hoisted(() => ({ admin: null as any, route: null as any, replace: vi.fn() }))
vi.mock('@/stores/admin/AdminStore', () => ({ useAdminStore: () => context.admin }))
vi.mock('vue-router', () => ({ useRoute: () => context.route, useRouter: () => ({ replace: context.replace }) }))
vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() } }))

function session(id = 1, year = 10) {
    return { id, schoolyear_id: year, name: `Matura ${year}`, exam_date: '2026-10-04', status: 'active', rooms_count: 1, students_count: 1, waiting_places: 1 }
}

function detail(id = 1, year = 10) {
    return { session: session(id, year), actor: { manager: true }, rooms: [], accesses: [], visits: [], server_time: '2026-10-04T09:00:00Z' }
}

function roomFixture() {
    const saved = { ...detail(), session: { ...session(), status: 'draft' }, rooms: [
        { id: 4, name: 'Raum A' }, { id: 5, name: 'Raum B' },
    ], students: [
        { id: 11, matura_room_id: 4, name: 'Andere Schülerin', class_name: '8A', import116_id: 99 },
        { id: 12, matura_room_id: 5, name: 'Gast B', class_name: 'Extern', import116_id: null },
        { id: 13, matura_room_id: 5, name: 'Müller Änne', class_name: '8B', import116_id: 1 },
    ] }
    context.route.query = { matura: '1' }
    vi.mocked(axios.get).mockImplementation(async (url) => {
        if (String(url).includes('schoolyear_id=')) return { data: { ready: true, sessions: [saved.session] } }
        if (String(url).endsWith('/roster')) return { data: { students: [{ id: 1, last_name: 'Müller', first_name: 'Änne', class: '8B' }], supervisors: [] } }
        return { data: saved }
    })
    return saved
}

function roomCard(name) { return within(screen.getByRole('heading', { name, level: 4 }).closest('article')) }

function deferred() {
    let resolve: (value: any) => void
    const promise = new Promise((done) => { resolve = done })
    return { promise, resolve: (value) => resolve(value) }
}

function draftFixture(overrides = {}) {
    return {
        form: { name: 'Entwurf Deutsch', exam_date: '2026-10-04', waiting_places: 4,
            rooms: [{ name: 'Entwurfsraum', student_ids: [], manualText: 'Gast Eins', classFilter: '', search: '' }] },
        completed: [true, true, true], activeStep: null, roomIndex: 0,
        ...overrides,
    }
}

function storeDraft(data = draftFixture(), sessionId = null, year = 10) {
    const scope = setupDraftScope(7, 8, year)
    writeSetupDraft(setupDraftKey(scope, sessionId), data)
    writeSetupDraft(`${scope}:active`, { sessionId })
    return scope
}

function mountManager(realSetup = false) {
    return render(MaturaManager, { global: { stubs: {
        MaturaBoard: { template: '<div>Live-Details</div>' },
        MaturaReport: true,
        ...(realSetup ? {} : { MaturaSetup: { props: { viewOnly: Boolean }, template: '<div v-if="viewOnly"><slot name="flow" /></div><div v-else>Einrichtung offen<button @click="$emit(\'save\', {})">Einrichtung speichern</button><button @click="$emit(\'cancel\')">Einrichtung abbrechen</button></div>' } }),
        'v-dialog': { props: ['modelValue'], template: '<div v-if="modelValue" role="dialog"><slot /></div>' },
        'v-card': { template: '<div><slot /></div>' },
    } } })
}

beforeEach(() => {
    window.sessionStorage.clear()
    window.localStorage.clear()
    vi.mocked(axios.put).mockReset()
    vi.mocked(axios.delete).mockReset()
    context.admin = reactive({ selected_schoolyear: { id: 10 }, config: { user: { id: 7, school_id: 8 }, selected_school: { id: 8 } } })
    context.route = reactive({ query: {} })
    context.replace.mockImplementation(async ({ query }) => { context.route.query = query })
    vi.mocked(axios.get).mockReset().mockImplementation(async (url) => {
        if (String(url).includes('schoolyear_id=')) {
            const year = Number(new URL(String(url), 'http://localhost').searchParams.get('schoolyear_id'))
            return { data: { ready: true, sessions: [session(year === 10 ? 1 : 2, year)] } }
        }
        if (String(url).endsWith('/roster')) return { data: { students: [], supervisors: [] } }
        return { data: detail() }
    })
    vi.mocked(axios.post).mockReset().mockResolvedValue({ data: { url: 'https://example.test/#private', id: 1 } })
})

afterEach(() => { cleanup() })

describe('00-Manager selected schoolyear', () => {
    it('manages room names and empty new rooms separately while preserving student assignments and independent drafts', async () => {
        const saved = roomFixture()
        const scope = setupDraftScope(7, 8, 10)
        const independentDraft = draftFixture()
        writeSetupDraft(setupDraftKey(scope, 1), independentDraft)
        vi.mocked(axios.put).mockImplementation(async () => {
            saved.rooms = [{ id: 4, name: 'Prüfungsraum' }, { id: 5, name: 'Raum B' }, { id: 6, name: 'Raum C' }]
            return { data: { id: 1 } }
        })
        mountManager(true)
        await fireEvent.click(await screen.findByRole('button', { name: /^2 Räume & Schüler / }))
        await fireEvent.click(screen.getByRole('button', { name: 'Räume bearbeiten' }))
        await screen.findByRole('heading', { name: 'Räume bearbeiten' })
        expect(screen.queryByRole('checkbox')).not.toBeInTheDocument()
        expect(screen.queryByPlaceholderText('Nachname Vorname')).not.toBeInTheDocument()
        await fireEvent.update(screen.getByPlaceholderText('z. B. 3.12'), 'Prüfungsraum')
        await fireEvent.click(screen.getByRole('button', { name: '+ Raum hinzufügen' }))
        await fireEvent.update(screen.getByPlaceholderText('z. B. 3.12'), 'Raum C')
        expect(axios.put).not.toHaveBeenCalled()
        await fireEvent.click(screen.getByRole('button', { name: 'Abbruch' }))
        expect(screen.getByRole('heading', { name: 'Raum A', level: 4 })).toBeInTheDocument()
        expect(screen.queryByRole('heading', { name: 'Raum C', level: 4 })).not.toBeInTheDocument()
        expect(readSetupDraft(setupDraftKey(scope, 1))).toEqual(independentDraft)

        await fireEvent.click(screen.getByRole('button', { name: 'Räume bearbeiten' }))
        await screen.findByRole('heading', { name: 'Räume bearbeiten' })
        await fireEvent.update(screen.getByPlaceholderText('z. B. 3.12'), 'Prüfungsraum')
        await fireEvent.click(screen.getByRole('button', { name: '+ Raum hinzufügen' }))
        await fireEvent.update(screen.getByPlaceholderText('z. B. 3.12'), 'Raum C')
        await fireEvent.click(screen.getByRole('button', { name: 'Änderungen speichern' }))

        await screen.findByRole('heading', { name: 'Raum C', level: 4 })
        expect(axios.put).toHaveBeenCalledWith('/admin/helpers/00-manager/1', {
            schoolyear_id: 10, name: 'Matura 10', exam_date: '2026-10-04', waiting_places: 1,
            rooms: [
                { name: 'Prüfungsraum', student_ids: [99], manual_students: [] },
                { name: 'Raum B', student_ids: [1], manual_students: [{ name: 'Gast B', class_name: 'Extern' }] },
                { name: 'Raum C', student_ids: [], manual_students: [] },
            ],
        })
        expect(readSetupDraft(setupDraftKey(scope, 1))).toEqual(independentDraft)
    })

    it('stages class batch assignments, discards them on abort and saves only the chosen room', async () => {
        const saved = roomFixture()
        saved.session.selected_classes = ['8A', '8B']
        const pool = [
            { id: 99, last_name: 'Andere', first_name: 'Schülerin', class: '8A' },
            { id: 1, last_name: 'Müller', first_name: 'Änne', class: '8B' },
            { id: 2, last_name: 'Neu', first_name: 'Emil', class: '8B' },
            { id: 3, last_name: 'Neu', first_name: 'Anna', class: '8A' },
            { id: 4, last_name: 'Nicht', first_name: 'Sichtbar', class: '8B' },
        ]
        vi.mocked(axios.get).mockImplementation(async (url) => {
            if (String(url).includes('schoolyear_id=')) return { data: { ready: true, sessions: [saved.session] } }
            if (String(url).endsWith('/roster')) return { data: { students: pool, supervisors: [] } }
            return { data: saved }
        })
        vi.mocked(axios.put).mockResolvedValue({ data: { id: 1 } })
        mountManager(true)
        await fireEvent.click(await screen.findByRole('button', { name: /^2 Räume & Schüler / }))
        await fireEvent.click(roomCard('Raum B').getByRole('button', { name: 'Bearbeiten' }))
        await screen.findByRole('heading', { name: 'Schüler zuordnen' })
        await fireEvent.update(screen.getByPlaceholderText('Nachname oder Vorname'), 'Neu')
        await fireEvent.click(within(screen.getByRole('group', { name: 'Klasse 8B' })).getByRole('button', { name: 'Alle angezeigten zuordnen' }))
        await fireEvent.click(within(screen.getByRole('group', { name: 'Klasse 8A' })).getByRole('button', { name: 'Alle angezeigten zuordnen' }))
        await fireEvent.click(within(screen.getByRole('group', { name: 'Klasse 8B' })).getByRole('button', { name: 'Auswahl leeren' }))
        expect(screen.getByRole('checkbox', { name: /Neu Emil/ })).not.toBeChecked()
        expect(screen.getByRole('checkbox', { name: /Neu Anna/ })).toBeChecked()
        await fireEvent.update(screen.getByPlaceholderText('Nachname oder Vorname'), '')
        expect(screen.getByRole('checkbox', { name: /Müller Änne/ })).toBeChecked()
        expect(screen.getByRole('checkbox', { name: /Andere Schülerin/ })).toBeDisabled()
        expect(screen.getByRole('checkbox', { name: /Andere Schülerin/ })).toHaveAccessibleName(/Bereits „Raum A“ zugeordnet/)
        expect(axios.put).not.toHaveBeenCalled()
        expect(axios.post).not.toHaveBeenCalled()
        await fireEvent.click(screen.getByRole('button', { name: 'Abbruch' }))

        await fireEvent.click(roomCard('Raum B').getByRole('button', { name: 'Bearbeiten' }))
        expect(await screen.findByRole('checkbox', { name: /Müller Änne/ })).toBeChecked()
        expect(screen.getByRole('checkbox', { name: /Neu Anna/ })).not.toBeChecked()
        expect(screen.getByRole('checkbox', { name: /Neu Emil/ })).not.toBeChecked()
        expect(axios.put).not.toHaveBeenCalled()
        await fireEvent.update(screen.getByPlaceholderText('Nachname oder Vorname'), 'Neu')
        await fireEvent.click(within(screen.getByRole('group', { name: 'Klasse 8A' })).getByRole('button', { name: 'Alle angezeigten zuordnen' }))
        await fireEvent.click(within(screen.getByRole('group', { name: 'Klasse 8B' })).getByRole('button', { name: 'Alle angezeigten zuordnen' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Änderungen speichern' }))

        await screen.findByRole('heading', { name: 'Raum B', level: 4 })
        expect(axios.put).toHaveBeenCalledTimes(1)
        expect(axios.put).toHaveBeenCalledWith('/admin/helpers/00-manager/1', expect.objectContaining({ rooms: [
            { name: 'Raum A', student_ids: [99], manual_students: [] },
            { name: 'Raum B', student_ids: [1, 3, 2], manual_students: [{ name: 'Gast B', class_name: 'Extern' }] },
        ] }))
    })

    it('leaves class editing without saving and reopens saved selections while preserving independent drafts', async () => {
        const saved = roomFixture()
        saved.session.selected_classes = ['8A', '8B']
        const draftKey = setupDraftKey(setupDraftScope(7, 8, 10), 1)
        const independentDraft = draftFixture()
        writeSetupDraft(draftKey, independentDraft)
        mountManager(true)
        await fireEvent.click(await screen.findByRole('button', { name: 'Matura bearbeiten' }))
        await screen.findByRole('heading', { name: 'Welche Klassen nehmen teil?' })
        expect(screen.getAllByRole('button', { name: 'Abbruch' })).toHaveLength(1)
        expect(screen.queryByRole('button', { name: 'Zurück' })).not.toBeInTheDocument()
        expect(screen.queryByRole('button', { name: 'Änderungen speichern' })).not.toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: /^8B / }))
        expect(screen.getByRole('button', { name: 'Änderungen speichern' })).toBeInTheDocument()
        await fireEvent.click(screen.getByRole('button', { name: /^8B / }))
        expect(screen.queryByRole('button', { name: 'Änderungen speichern' })).not.toBeInTheDocument()
        await fireEvent.click(screen.getByRole('button', { name: /^8B / }))
        await fireEvent.click(screen.getByRole('button', { name: 'Abbruch' }))

        await screen.findByRole('list', { name: 'Teilnehmende Klassen' })
        expect(axios.put).not.toHaveBeenCalled()
        expect(axios.post).not.toHaveBeenCalled()
        expect(readSetupDraft(draftKey)).toEqual(independentDraft)
        await fireEvent.click(screen.getByRole('button', { name: 'Matura bearbeiten' }))
        expect(await screen.findByRole('button', { name: /^8B / })).toHaveAttribute('aria-pressed', 'true')
        expect(screen.queryByRole('button', { name: 'Änderungen speichern' })).not.toBeInTheDocument()
    })

    it('saves only changed classes and retains assignments when a class is toggled off and on again', async () => {
        const saved = roomFixture()
        saved.session.selected_classes = ['8A', '8B']
        const draftKey = setupDraftKey(setupDraftScope(7, 8, 10), 1)
        const independentDraft = draftFixture()
        writeSetupDraft(draftKey, independentDraft)
        vi.mocked(axios.put).mockResolvedValue({ data: { id: 1 } })
        mountManager(true)
        await fireEvent.click(await screen.findByRole('button', { name: 'Matura bearbeiten' }))
        await fireEvent.click(await screen.findByRole('button', { name: /^8B / }))
        await fireEvent.click(screen.getByRole('button', { name: /^8B / }))
        await fireEvent.click(screen.getByRole('button', { name: /^8A / }))
        await fireEvent.click(screen.getByRole('button', { name: 'Änderungen speichern' }))

        await screen.findByRole('list', { name: 'Teilnehmende Klassen' })
        expect(axios.put).toHaveBeenCalledWith('/admin/helpers/00-manager/1', expect.objectContaining({
            selected_classes: ['8B'],
            rooms: [
                { name: 'Raum A', student_ids: [], manual_students: [] },
                { name: 'Raum B', student_ids: [1], manual_students: [{ name: 'Gast B', class_name: 'Extern' }] },
            ],
        }))
        expect(readSetupDraft(draftKey)).toEqual(independentDraft)
    })

    it('edits name and date from the heading while preserving saved classes, rooms and drafts', async () => {
        const saved = roomFixture()
        saved.session.selected_classes = ['8A', '8B']
        saved.session.toilet_places = 2
        const draftKey = setupDraftKey(setupDraftScope(7, 8, 10), 1)
        const independentDraft = draftFixture()
        writeSetupDraft(draftKey, independentDraft)
        vi.mocked(axios.put).mockImplementation(async (_url, data: any) => {
            saved.session.name = data.name
            saved.session.exam_date = data.exam_date
            return { data: { id: 1 } }
        })
        mountManager(true)
        await screen.findByRole('button', { name: 'Name und Datum bearbeiten' })
        expect(screen.getAllByRole('heading', { name: 'Matura 10', level: 2 })).toHaveLength(1)
        const contentHeading = screen.getByRole('button', { name: 'Name und Datum bearbeiten' }).parentElement
        expect(contentHeading).toHaveTextContent('Matura 10Sonntag, 04.10.2026')
        expect(within(contentHeading.closest('section')).getByText('MATURA')).toBeInTheDocument()
        await fireEvent.click(await screen.findByRole('button', { name: 'Name und Datum bearbeiten' }))
        const editor = within(screen.getByRole('form', { name: 'Name und Datum bearbeiten' }))
        expect(editor.getByLabelText('Name der Matura')).toHaveValue('Matura 10')
        expect(editor.getByLabelText('Prüfungsdatum')).toHaveValue('2026-10-04')
        await fireEvent.update(editor.getByLabelText('Name der Matura'), 'Verworfen')
        await fireEvent.click(editor.getByRole('button', { name: 'Abbruch' }))
        expect(axios.put).not.toHaveBeenCalled()

        await fireEvent.click(screen.getByRole('button', { name: 'Name und Datum bearbeiten' }))
        expect(screen.getByLabelText('Name der Matura')).toHaveValue('Matura 10')
        await fireEvent.update(screen.getByLabelText('Name der Matura'), '  Deutsch-HT  ')
        await fireEvent.update(screen.getByLabelText('Prüfungsdatum'), '2027-05-04')
        await fireEvent.click(screen.getByRole('button', { name: 'Speichern' }))

        await waitFor(() => expect(screen.queryByRole('form', { name: 'Name und Datum bearbeiten' })).not.toBeInTheDocument())
        expect(screen.getByRole('button', { name: 'Name und Datum bearbeiten' }).parentElement).toHaveTextContent('Deutsch-HTDienstag, 04.05.2027')
        expect(axios.put).toHaveBeenCalledWith('/admin/helpers/00-manager/1', {
            schoolyear_id: 10, name: 'Deutsch-HT', exam_date: '2027-05-04', waiting_places: 1, toilet_places: 2, selected_classes: ['8A', '8B'],
            rooms: [
                { name: 'Raum A', student_ids: [99], manual_students: [] },
                { name: 'Raum B', student_ids: [1], manual_students: [{ name: 'Gast B', class_name: 'Extern' }] },
            ],
        })
        expect(readSetupDraft(draftKey)).toEqual(independentDraft)
        expect(screen.queryByRole('button', { name: '+ Neue Matura' })).not.toBeInTheDocument()
    })

    it.each([422, 500])('retains heading edits after a %s failure and validates empty name and date', async (status) => {
        const saved = roomFixture()
        vi.mocked(axios.put).mockRejectedValueOnce({ response: { status, data: { message: 'Speichern fehlgeschlagen' } } })
        mountManager(true)
        await fireEvent.click(await screen.findByRole('button', { name: 'Name und Datum bearbeiten' }))
        await fireEvent.update(screen.getByLabelText('Name der Matura'), '   ')
        await fireEvent.click(screen.getByRole('button', { name: 'Speichern' }))
        expect(screen.getByRole('alert')).toHaveTextContent('Bitte gib der Matura einen Namen.')
        expect(axios.put).not.toHaveBeenCalled()
        await fireEvent.update(screen.getByLabelText('Name der Matura'), 'Deutsch-HT')
        await fireEvent.update(screen.getByLabelText('Prüfungsdatum'), '')
        await fireEvent.submit(screen.getByRole('form', { name: 'Name und Datum bearbeiten' }))
        expect(axios.put).not.toHaveBeenCalled()
        await fireEvent.update(screen.getByLabelText('Prüfungsdatum'), '2027-05-04')
        await fireEvent.click(screen.getByRole('button', { name: 'Speichern' }))
        await screen.findByText('Speichern fehlgeschlagen')
        expect(screen.getByLabelText('Name der Matura')).toHaveValue('Deutsch-HT')
        expect(screen.getByLabelText('Prüfungsdatum')).toHaveValue('2027-05-04')
        await waitFor(() => expect(screen.getByRole('button', { name: 'Speichern' })).toBeEnabled())
        vi.mocked(axios.put).mockImplementation(async (_url, data: any) => {
            Object.assign(saved.session, { name: data.name, exam_date: data.exam_date })
            return { data: { id: 1 } }
        })
        await fireEvent.click(screen.getByRole('button', { name: 'Speichern' }))
        await waitFor(() => expect(screen.queryByRole('form', { name: 'Name und Datum bearbeiten' })).not.toBeInTheDocument())
        expect(screen.getByRole('button', { name: 'Name und Datum bearbeiten' }).parentElement).toHaveTextContent('Deutsch-HT')
    })

    it('keeps selection and creation in the overview and hides them in matura details', async () => {
        mountManager()
        await screen.findByRole('button', { name: /Matura 10/ })
        expect(screen.getByRole('combobox', { name: 'Matura auswählen' })).toBeInTheDocument()
        expect(screen.getByRole('button', { name: '+ Neue Matura' })).toBeEnabled()

        await fireEvent.click(screen.getByRole('button', { name: /Matura 10/ }))
        await screen.findByText('Live-Details')
        expect(screen.queryByRole('combobox', { name: 'Matura auswählen' })).not.toBeInTheDocument()
        expect(screen.queryByRole('button', { name: '+ Neue Matura' })).not.toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Übersicht' }))
        await screen.findByRole('combobox', { name: 'Matura auswählen' })
        expect(screen.getByRole('button', { name: '+ Neue Matura' })).toBeEnabled()
        expect(screen.queryByText('Live-Details')).not.toBeInTheDocument()
    })

    it('edits the chosen room and preserves other room assignments and independent drafts', async () => {
        const saved = roomFixture()
        const scope = setupDraftScope(7, 8, 10)
        const independentDraft = draftFixture()
        writeSetupDraft(setupDraftKey(scope, 1), independentDraft)
        vi.mocked(axios.put).mockImplementation(async () => {
            saved.students[1].name = 'Gast B geändert'
            return { data: { id: 1 } }
        })
        mountManager(true)
        await fireEvent.click(await screen.findByRole('button', { name: /^2 Räume & Schüler / }))
        expect(screen.queryByRole('button', { name: 'Matura bearbeiten' })).not.toBeInTheDocument()
        await fireEvent.click(roomCard('Raum B').getByRole('button', { name: 'Bearbeiten' }))
        await screen.findByRole('heading', { name: 'Schüler zuordnen' })
        expect(screen.queryByPlaceholderText('z. B. 3.12')).not.toBeInTheDocument()
        expect(screen.getByRole('checkbox', { name: /Müller Änne/ })).toBeChecked()
        expect(screen.getByPlaceholderText('Nachname Vorname')).toHaveValue('Gast B')
        expect(screen.queryByRole('button', { name: '+ Raum hinzufügen' })).not.toBeInTheDocument()
        expect(screen.queryByRole('button', { name: 'Raum entfernen' })).not.toBeInTheDocument()
        expect(screen.getByRole('button', { name: /^✓ Matura & Klassen / })).toBeDisabled()
        await fireEvent.click(screen.getByRole('button', { name: 'Abbruch' }))
        expect(screen.getByRole('button', { name: /^2 Räume & Schüler / })).toHaveAttribute('aria-expanded', 'true')
        expect(axios.put).not.toHaveBeenCalled()
        await fireEvent.click(roomCard('Raum B').getByRole('button', { name: 'Bearbeiten' }))
        await screen.findByRole('heading', { name: 'Schüler zuordnen' })
        await fireEvent.update(screen.getByPlaceholderText('Nachname Vorname'), 'Gast B geändert')
        await fireEvent.click(screen.getByRole('button', { name: /^(Änderungen speichern|Weiter:|Zusammenfassung anzeigen)/ }))

        await screen.findByText('Gast B geändert · Extern')
        expect(screen.queryByRole('heading', { name: 'Schüler zuordnen' })).not.toBeInTheDocument()
        expect(screen.getByRole('button', { name: /^2 Räume & Schüler / })).toHaveAttribute('aria-expanded', 'true')
        expect(axios.put).toHaveBeenCalledWith('/admin/helpers/00-manager/1', {
            schoolyear_id: 10, name: 'Matura 10', exam_date: '2026-10-04', waiting_places: 1,
            rooms: [
                { name: 'Raum A', student_ids: [99], manual_students: [] },
                { name: 'Raum B', student_ids: [1], manual_students: [{ name: 'Gast B geändert' }] },
            ],
        })
        expect(readSetupDraft(setupDraftKey(scope, 1))).toEqual(independentDraft)
    })

    it('stages confirmed room removal in room management and keeps the last room', async () => {
        const saved = roomFixture()
        vi.mocked(axios.put).mockImplementation(async () => {
            saved.rooms = saved.rooms.filter((room) => room.id !== 5)
            saved.students = saved.students.filter((student) => student.matura_room_id !== 5)
            return { data: { id: 1 } }
        })
        mountManager(true)
        await fireEvent.click(await screen.findByRole('button', { name: /^2 Räume & Schüler / }))
        expect(roomCard('Raum B').queryByRole('button', { name: 'Löschen' })).not.toBeInTheDocument()
        await fireEvent.click(screen.getByRole('button', { name: 'Räume bearbeiten' }))
        await screen.findByRole('heading', { name: 'Räume bearbeiten' })
        expect(screen.queryByRole('checkbox')).not.toBeInTheDocument()
        expect(screen.queryByPlaceholderText('Nachname oder Vorname')).not.toBeInTheDocument()
        expect(screen.queryByPlaceholderText('Nachname Vorname')).not.toBeInTheDocument()
        await fireEvent.click(screen.getByRole('button', { name: /^Raum B / }))
        await fireEvent.click(screen.getByRole('button', { name: 'Raum entfernen' }))
        expect(screen.getByRole('dialog')).toHaveTextContent('Raum Raum B entfernen?')
        await fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Abbruch' }))
        expect(screen.getByPlaceholderText('z. B. 3.12')).toHaveValue('Raum B')
        await fireEvent.click(screen.getByRole('button', { name: 'Raum entfernen' }))
        await fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Raum entfernen' }))
        expect(axios.put).not.toHaveBeenCalled()
        await fireEvent.click(screen.getByRole('button', { name: 'Abbruch' }))
        expect(screen.getByRole('heading', { name: 'Raum B', level: 4 })).toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Räume bearbeiten' }))
        await screen.findByRole('heading', { name: 'Räume bearbeiten' })
        await fireEvent.click(screen.getByRole('button', { name: /^Raum B / }))
        await fireEvent.click(screen.getByRole('button', { name: 'Raum entfernen' }))
        await fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Raum entfernen' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Änderungen speichern' }))

        await waitFor(() => expect(screen.queryByRole('heading', { name: 'Räume bearbeiten' })).not.toBeInTheDocument())
        expect(screen.queryByRole('heading', { name: 'Raum B', level: 4 })).not.toBeInTheDocument()
        expect(axios.put).toHaveBeenCalledWith('/admin/helpers/00-manager/1', {
            schoolyear_id: 10, name: 'Matura 10', exam_date: '2026-10-04', waiting_places: 1,
            rooms: [{ name: 'Raum A', student_ids: [99], manual_students: [] }],
        })
        await waitFor(() => expect(screen.getByRole('button', { name: 'Räume bearbeiten' })).toBeEnabled())
        await fireEvent.click(screen.getByRole('button', { name: 'Räume bearbeiten' }))
        await screen.findByRole('heading', { name: 'Räume bearbeiten' })
        expect(screen.getByRole('button', { name: 'Raum entfernen' })).toBeDisabled()
    })

    it('keeps the chosen room editor and changed input after unsuccessful saving', async () => {
        roomFixture()
        vi.mocked(axios.put).mockRejectedValue({ response: { status: 422, data: { message: 'Raum bitte prüfen' } } })
        mountManager(true)
        await fireEvent.click(await screen.findByRole('button', { name: /^2 Räume & Schüler / }))
        await fireEvent.click(roomCard('Raum B').getByRole('button', { name: 'Bearbeiten' }))
        await screen.findByRole('heading', { name: 'Schüler zuordnen' })
        await fireEvent.update(screen.getByPlaceholderText('Nachname Vorname'), 'A'.repeat(181))
        await fireEvent.click(screen.getByRole('button', { name: /^(Änderungen speichern|Weiter:|Zusammenfassung anzeigen)/ }))
        expect(axios.put).not.toHaveBeenCalled()
        expect(screen.getByRole('alert')).toHaveTextContent('Ein Schülername darf höchstens 180 Zeichen haben.')
        await fireEvent.update(screen.getByPlaceholderText('Nachname Vorname'), 'Gast korrigiert')
        await fireEvent.click(screen.getByRole('button', { name: /^(Änderungen speichern|Weiter:|Zusammenfassung anzeigen)/ }))

        await screen.findByText('Raum bitte prüfen')
        expect(screen.getByRole('heading', { name: 'Schüler zuordnen' })).toBeInTheDocument()
        expect(screen.getByPlaceholderText('Nachname Vorname')).toHaveValue('Gast korrigiert')
        expect(screen.getByRole('checkbox', { name: /Müller Änne/ })).toBeChecked()
        await waitFor(() => expect(screen.getByRole('button', { name: 'Abbruch' })).toBeEnabled())
        await fireEvent.click(screen.getByRole('button', { name: 'Abbruch' }))
        expect(screen.getByRole('heading', { name: 'Raum B', level: 4 })).toBeInTheDocument()
        expect(screen.getByRole('button', { name: /^2 Räume & Schüler / })).toHaveAttribute('aria-expanded', 'true')
    })

    it.each([409, 500])('keeps staged room removal after a %s save failure and discards it on abort', async (status) => {
        roomFixture()
        vi.mocked(axios.put).mockRejectedValue({ response: { status, data: { message: 'Raum konnte nicht gelöscht werden' } } })
        mountManager(true)
        await fireEvent.click(await screen.findByRole('button', { name: /^2 Räume & Schüler / }))
        await fireEvent.click(screen.getByRole('button', { name: 'Räume bearbeiten' }))
        await screen.findByRole('heading', { name: 'Räume bearbeiten' })
        await fireEvent.click(screen.getByRole('button', { name: /^Raum B / }))
        await fireEvent.click(screen.getByRole('button', { name: 'Raum entfernen' }))
        await fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Raum entfernen' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Änderungen speichern' }))

        await screen.findByText('Raum konnte nicht gelöscht werden')
        expect(screen.getByRole('heading', { name: 'Räume bearbeiten' })).toBeInTheDocument()
        expect(screen.getByPlaceholderText('z. B. 3.12')).toHaveValue('Raum A')
        expect(screen.queryByRole('button', { name: /^Raum B / })).not.toBeInTheDocument()
        await waitFor(() => expect(screen.getByRole('button', { name: 'Abbruch' })).toBeEnabled())
        await fireEvent.click(screen.getByRole('button', { name: 'Abbruch' }))
        expect(screen.getByRole('heading', { name: 'Raum B', level: 4 })).toBeInTheDocument()
        expect(screen.getByText('Gast B · Extern')).toBeInTheDocument()
    })

    it.each(['active', 'closed', 'access'])('hides room changes for a %s matura', async (condition) => {
        const saved = roomFixture()
        if (condition === 'access') saved.accesses = [{ id: 6, valid: false, name: 'Aufsicht', expires_at: '2026-10-04T09:00:00Z', matura_room_id: 4 }]
        else saved.session.status = condition
        mountManager(true)
        await fireEvent.click(await screen.findByRole('button', { name: 'Vorbereitung' }))
        await fireEvent.click(await screen.findByRole('button', { name: /^2 Räume & Schüler / }))

        expect(screen.getByRole('heading', { name: 'Raum B', level: 4 })).toBeInTheDocument()
        expect(screen.queryByRole('button', { name: 'Bearbeiten' })).not.toBeInTheDocument()
        expect(screen.queryByRole('button', { name: 'Löschen' })).not.toBeInTheDocument()
        expect(screen.queryByRole('button', { name: 'Räume bearbeiten' })).not.toBeInTheDocument()
        expect(screen.queryByRole('button', { name: 'Name und Datum bearbeiten' })).not.toBeInTheDocument()
    })

    it.each(['draft', 'active', 'closed'])('opens a %s matura with three tasks and its saved assignments', async (status) => {
        const saved = { ...detail(), session: { ...session(), status }, rooms: [{ id: 4, name: 'Raum A' }], students: [
            { id: 11, matura_room_id: 4, name: 'Müller Änne', class_name: '8A', import116_id: 1 },
            { id: 12, matura_room_id: 4, name: 'Gast Eins', class_name: null, import116_id: null },
        ] }
        context.route.query = { matura: '1' }
        vi.mocked(axios.get).mockImplementation(async (url) => String(url).includes('schoolyear_id=')
            ? { data: { ready: true, sessions: [saved.session] } } : { data: saved })
        mountManager(true)
        await fireEvent.click(await screen.findByRole('button', { name: 'Vorbereitung' }))

        const tasks = screen.getAllByRole('button', { name: /^[1-3] (Matura & Klassen|Räume & Schüler|Ablauf) / })
        expect(tasks).toHaveLength(3)
        await fireEvent.click(tasks[0])
        expect(screen.getByRole('list', { name: 'Teilnehmende Klassen' })).toHaveTextContent('8A')
        expect(screen.queryByRole('heading', { name: 'Matura & Klassen' })).not.toBeInTheDocument()
        expect(screen.queryByText('Matura 10 · 04.10.2026')).not.toBeInTheDocument()
        expect(screen.getAllByText('8A').length).toBeGreaterThan(0)
        expect(screen.queryByText('Live-Details')).not.toBeInTheDocument()
        expect(screen.queryByRole('button', { name: 'Matura bearbeiten' }) !== null).toBe(status === 'draft')
        await fireEvent.click(tasks[1])
        expect(screen.getByText('1 Räume · 2 Schüler insgesamt')).toBeInTheDocument()
        expect(screen.getByText('Müller Änne · 8A')).toBeInTheDocument()
        expect(screen.getByText('Gast Eins')).toBeInTheDocument()
        await fireEvent.click(tasks[2])
        expect(screen.getByText('Kapazität und Ablauf')).toBeInTheDocument()
        await fireEvent.click(screen.getByRole('button', { name: 'Live-Übersicht' }))
        expect(screen.getByText('Live-Details')).toBeInTheDocument()
        await fireEvent.click(screen.getByRole('button', { name: 'Protokoll & PDF' }))
        expect(screen.queryByText('Live-Details')).not.toBeInTheDocument()
        expect(axios.put).not.toHaveBeenCalled()
        expect(readActiveSetup(setupDraftScope(7, 8, 10))).toBeNull()
    })

    it('requests the selected year and permits a direct link only from its filtered list', async () => {
        context.route.query = { matura: '1' }
        mountManager()
        await screen.findByText('Live-Details')
        expect(axios.get).toHaveBeenCalledWith('/admin/helpers/00-manager?schoolyear_id=10')
        expect(axios.get).toHaveBeenCalledWith('/admin/helpers/00-manager/1', { timeout: 12000 })
    })

    it('keeps station supervisors on their coordination view', async () => {
        const saved = { ...detail(), actor: { manager: false, name: 'Aufsicht Eins' } }
        context.route.query = { matura: '1' }
        vi.mocked(axios.get).mockImplementation(async (url) => String(url).includes('schoolyear_id=')
            ? { data: { ready: true, sessions: [saved.session] } } : { data: saved })
        mountManager(true)

        await screen.findByText('Live-Details')
        expect(screen.getByRole('heading', { name: 'Matura 10' })).toBeInTheDocument()
        expect(screen.getByText('Aufsicht Eins')).toBeInTheDocument()
        expect(screen.queryByRole('button', { name: /^1 Matura & Klassen / })).not.toBeInTheDocument()
        expect(screen.queryByRole('button', { name: 'Aufsichten & Start' })).not.toBeInTheDocument()
    })

    it('edits and saves a reopened preparation with its imported and manual students intact', async () => {
        const saved = { ...detail(), session: { ...session(), status: 'draft' }, rooms: [{ id: 4, name: 'Raum A' }], students: [
            { id: 11, matura_room_id: 4, name: 'Müller Änne', class_name: '8A', import116_id: 1 },
            { id: 12, matura_room_id: 4, name: 'Gast Eins', class_name: null, import116_id: null },
        ] }
        context.route.query = { matura: '1' }
        vi.mocked(axios.get).mockImplementation(async (url) => {
            if (String(url).includes('schoolyear_id=')) return { data: { ready: true, sessions: [saved.session] } }
            if (String(url).endsWith('/roster')) return { data: { students: [{ id: 1, last_name: 'Müller', first_name: 'Änne', class: '8A' }, { id: 2, last_name: 'Beispiel', first_name: 'Emil', class: '8B' }], supervisors: [] } }
            return { data: saved }
        })
        vi.mocked(axios.put).mockImplementation(async (_url, data: any) => {
            saved.session.name = data.name
            saved.session.selected_classes = data.selected_classes
            return { data: { id: 1 } }
        })
        mountManager(true)
        await fireEvent.click(await screen.findByRole('button', { name: /^1 Matura & Klassen / }))
        await fireEvent.click(screen.getByRole('button', { name: 'Matura bearbeiten' }))
        await screen.findByRole('heading', { name: 'Matura bearbeiten' })

        expect(screen.queryByLabelText('Name der Matura')).not.toBeInTheDocument()
        expect(screen.queryByLabelText('Prüfungsdatum')).not.toBeInTheDocument()
        expect(screen.getByRole('button', { name: /^8A / })).toHaveAttribute('aria-pressed', 'true')
        expect(screen.queryByRole('button', { name: 'Änderungen speichern' })).not.toBeInTheDocument()
        await fireEvent.click(screen.getByRole('button', { name: 'Abbruch' }))
        expect(screen.getByRole('button', { name: /^1 Matura & Klassen / })).toHaveAttribute('aria-expanded', 'true')
        expect(screen.getByRole('list', { name: 'Teilnehmende Klassen' })).toHaveTextContent('8A')
        expect(screen.queryByText('Live-Details')).not.toBeInTheDocument()
        await fireEvent.click(screen.getByRole('button', { name: 'Matura bearbeiten' }))
        await screen.findByRole('heading', { name: 'Welche Klassen nehmen teil?' })
        await fireEvent.click(screen.getByRole('button', { name: /^8B / }))
        await fireEvent.click(screen.getByRole('button', { name: /^(Änderungen speichern|Weiter:|Zusammenfassung anzeigen)/ }))

        await screen.findByRole('list', { name: 'Teilnehmende Klassen' })
        await waitFor(() => expect(screen.getByRole('button', { name: 'Matura bearbeiten' })).toBeEnabled())
        expect(screen.queryByLabelText('Name der Matura')).not.toBeInTheDocument()
        expect(screen.getByRole('button', { name: /^1 Matura & Klassen / })).toHaveAttribute('aria-expanded', 'true')
        expect(axios.put).toHaveBeenCalledWith('/admin/helpers/00-manager/1', {
            schoolyear_id: 10, name: 'Matura 10', exam_date: '2026-10-04', waiting_places: 1, selected_classes: ['8A', '8B'],
            rooms: [{ name: 'Raum A', student_ids: [1], manual_students: [{ name: 'Gast Eins', class_name: null }] }],
        })
        await fireEvent.click(screen.getByRole('button', { name: /^2 Räume & Schüler / }))
        expect(screen.queryByRole('checkbox', { name: /Müller Änne/ })).not.toBeInTheDocument()
        expect(screen.queryByRole('button', { name: 'Matura bearbeiten' })).not.toBeInTheDocument()
        await fireEvent.click(screen.getByRole('button', { name: 'Bearbeiten' }))
        await screen.findByRole('checkbox', { name: /Müller Änne/ })
        expect(screen.queryByPlaceholderText('z. B. 3.12')).not.toBeInTheDocument()
        expect(screen.getByRole('checkbox', { name: /Müller Änne/ })).toBeChecked()
        expect(screen.getByPlaceholderText('Nachname Vorname')).toHaveValue('Gast Eins')
        await fireEvent.click(screen.getByRole('button', { name: /^(Änderungen speichern|Weiter:|Zusammenfassung anzeigen)/ }))

        await screen.findByRole('button', { name: /^2 Räume & Schüler / })
        expect(screen.getByRole('button', { name: /^2 Räume & Schüler / })).toHaveAttribute('aria-expanded', 'true')
        expect(screen.queryByRole('checkbox', { name: /Müller Änne/ })).not.toBeInTheDocument()
        expect(axios.put).toHaveBeenCalledTimes(2)
    })

    it('keeps setup locked after access assignment and preserves starting from the flow task', async () => {
        const saved = { ...detail(), session: { ...session(), status: 'draft' }, students: [], accesses: [
            { id: 5, name: 'Aufsicht', matura_room_id: null, valid: false, expires_at: '2026-10-04T09:00:00Z' },
        ] }
        context.route.query = { matura: '1' }
        vi.mocked(axios.get).mockImplementation(async (url) => {
            if (String(url).includes('schoolyear_id=')) return { data: { ready: true, sessions: [saved.session] } }
            if (String(url).endsWith('/roster')) return { data: { students: [], supervisors: [] } }
            return { data: saved }
        })
        vi.mocked(axios.put).mockResolvedValue({ data: {} })
        mountManager(true)
        await fireEvent.click(await screen.findByRole('button', { name: /^1 Matura & Klassen / }))
        expect(screen.queryByRole('button', { name: 'Matura bearbeiten' })).not.toBeInTheDocument()
        await fireEvent.click(screen.getByRole('button', { name: /^3 Ablauf / }))
        await fireEvent.click(screen.getByRole('button', { name: 'Aufsichten & Start' }))
        await fireEvent.update(screen.getByRole('spinbutton', { name: 'Warteplätze an der Zwischenstation' }), '3')
        await fireEvent.click(screen.getByRole('button', { name: 'Matura starten' }))
        await waitFor(() => expect(axios.put).toHaveBeenCalledWith('/admin/helpers/00-manager/1/lifecycle', { status: 'active', waiting_places: 3, toilet_places: 1 }))
    })

    it.each([422, 500])('keeps edit input open after validation and a %s save failure until a successful retry', async (status) => {
        const saved = { ...detail(), session: { ...session(), status: 'draft' }, rooms: [{ id: 4, name: 'Raum A' }], students: [
            { id: 12, matura_room_id: 4, name: 'Gast Eins', class_name: null, import116_id: null },
        ] }
        context.route.query = { matura: '1' }
        vi.mocked(axios.get).mockImplementation(async (url) => {
            if (String(url).includes('schoolyear_id=')) return { data: { ready: true, sessions: [saved.session] } }
            if (String(url).endsWith('/roster')) return { data: { students: [], supervisors: [] } }
            return { data: saved }
        })
        vi.mocked(axios.put).mockRejectedValueOnce({ response: { status, data: { message: 'Speichern fehlgeschlagen' } } })
        mountManager(true)
        await fireEvent.click(await screen.findByRole('button', { name: /^2 Räume & Schüler / }))
        await fireEvent.click(screen.getByRole('button', { name: 'Räume bearbeiten' }))
        await screen.findByPlaceholderText('z. B. 3.12')

        await fireEvent.update(screen.getByPlaceholderText('z. B. 3.12'), '')
        await fireEvent.click(screen.getByRole('button', { name: /^(Änderungen speichern|Weiter:|Zusammenfassung anzeigen)/ }))
        expect(screen.getByRole('alert')).toHaveTextContent('Bitte gib jedem Raum einen Namen')
        expect(screen.getByPlaceholderText('z. B. 3.12')).toHaveValue('')
        expect(axios.put).not.toHaveBeenCalled()
        await fireEvent.update(screen.getByPlaceholderText('z. B. 3.12'), 'Raum korrigiert')
        await fireEvent.click(screen.getByRole('button', { name: /^(Änderungen speichern|Weiter:|Zusammenfassung anzeigen)/ }))
        await screen.findByText('Speichern fehlgeschlagen')
        expect(screen.getByPlaceholderText('z. B. 3.12')).toHaveValue('Raum korrigiert')
        await waitFor(() => expect(screen.getByRole('button', { name: /^(Änderungen speichern|Weiter:|Zusammenfassung anzeigen)/ })).toBeEnabled())
        const scope = setupDraftScope(7, 8, 10)
        expect(readSetupDraft(setupDraftKey(scope, 1))).toBeNull()
        const pendingSave = deferred()
        vi.mocked(axios.put).mockImplementationOnce(() => pendingSave.promise as any)
        await fireEvent.click(screen.getByRole('button', { name: /^(Änderungen speichern|Weiter:|Zusammenfassung anzeigen)/ }))
        expect(screen.getByPlaceholderText('z. B. 3.12')).toHaveValue('Raum korrigiert')
        saved.rooms[0].name = 'Raum korrigiert'
        pendingSave.resolve({ data: { id: 1 } })

        await screen.findByRole('heading', { name: 'Raum korrigiert', level: 4 })
        await waitFor(() => expect(screen.getByRole('button', { name: 'Räume bearbeiten' })).toBeEnabled())
        expect(screen.queryByLabelText('Name der Matura')).not.toBeInTheDocument()
        expect(screen.getByRole('button', { name: /^2 Räume & Schüler / })).toHaveAttribute('aria-expanded', 'true')
        expect(readSetupDraft(setupDraftKey(scope, 1))).toBeNull()
        expect(readActiveSetup(scope)).toBeNull()
        await fireEvent.click(screen.getByRole('button', { name: 'Räume bearbeiten' }))
        expect(await screen.findByPlaceholderText('z. B. 3.12')).toHaveValue('Raum korrigiert')
    })

    it('clears an out-of-year direct link without loading its details', async () => {
        context.route.query = { matura: '99' }
        mountManager()
        await screen.findByRole('button', { name: /Matura 10/ })
        await waitFor(() => expect(context.route.query.matura).toBeUndefined())
        expect(axios.get).not.toHaveBeenCalledWith('/admin/helpers/00-manager/99', expect.anything())
        expect(screen.queryByText('Live-Details')).not.toBeInTheDocument()
    })

    it('does not request sessions or details without a selected year', async () => {
        context.admin.selected_schoolyear = null
        context.route.query = { matura: '1' }
        mountManager()
        await screen.findByText('Bitte zuerst ein Schuljahr auswählen.')
        expect(axios.get).not.toHaveBeenCalled()
        expect(screen.getByRole('button', { name: '+ Neue Matura' })).toBeDisabled()
    })

    it('resets details, invitation data and an open dialog when the year changes', async () => {
        context.route.query = { matura: '1' }
        mountManager()
        await screen.findByText('Live-Details')
        await fireEvent.click(screen.getByRole('button', { name: 'Aufsichten & Start' }))
        await fireEvent.update(screen.getByPlaceholderText('Vorname Nachname'), 'Alte Aufsicht')
        await fireEvent.click(screen.getByRole('button', { name: 'Zugang erstellen' }))
        await screen.findByDisplayValue('https://example.test/#private')
        await waitFor(() => expect(screen.getByRole('button', { name: 'Matura abschließen' })).toBeEnabled())
        await fireEvent.click(screen.getByRole('button', { name: 'Matura abschließen' }))
        expect(screen.getByRole('dialog')).toBeInTheDocument()
        context.admin.selected_schoolyear = { id: 20 }
        await screen.findByRole('button', { name: /Matura 20/ })
        expect(screen.queryByText('Live-Details')).not.toBeInTheDocument()
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
        expect(screen.queryByDisplayValue('https://example.test/#private')).not.toBeInTheDocument()
        expect(context.route.query.matura).toBeUndefined()
        vi.mocked(axios.get).mockResolvedValue({ data: detail(2, 20) })
        await fireEvent.update(screen.getByRole('combobox'), '2')
        await screen.findByText('Live-Details')
        await fireEvent.click(screen.getByRole('button', { name: 'Aufsichten & Start' }))
        expect(screen.getByPlaceholderText('Vorname Nachname')).toHaveValue('')
    })

    it('ignores an old list response after a year switch, even when switching back', async () => {
        const oldList = deferred()
        vi.mocked(axios.get).mockImplementationOnce(() => oldList.promise as any)
        mountManager()
        context.admin.selected_schoolyear = { id: 20 }
        await screen.findByRole('button', { name: /Matura 20/ })
        context.admin.selected_schoolyear = { id: 10 }
        await screen.findByRole('button', { name: /Matura 10/ })
        oldList.resolve({ data: { ready: true, sessions: [{ ...session(99), name: 'Veraltete Liste' }] } })
        await nextTick()
        await nextTick()
        expect(screen.queryByText('Veraltete Liste')).not.toBeInTheDocument()
    })

    it('ignores pending detail and invitation responses from the previous year', async () => {
        const oldDetail = deferred()
        context.route.query = { matura: '1' }
        vi.mocked(axios.get).mockImplementationOnce(async () => ({ data: { ready: true, sessions: [session()] } }))
            .mockImplementationOnce(() => oldDetail.promise as any)
        mountManager()
        await waitFor(() => expect(axios.get).toHaveBeenCalledWith('/admin/helpers/00-manager/1', expect.anything()))
        context.admin.selected_schoolyear = { id: 20 }
        await screen.findByRole('button', { name: /Matura 20/ })
        oldDetail.resolve({ data: detail() })
        await nextTick()
        await nextTick()
        expect(screen.queryByText('Live-Details')).not.toBeInTheDocument()
        vi.mocked(axios.get).mockResolvedValue({ data: detail(2, 20) })
        await fireEvent.update(screen.getByRole('combobox'), '2')
        await screen.findByText('Live-Details')
        await fireEvent.click(screen.getByRole('button', { name: 'Aufsichten & Start' }))
        const oldInvite = deferred()
        vi.mocked(axios.post).mockImplementationOnce(() => oldInvite.promise as any)
        await fireEvent.update(screen.getByPlaceholderText('Vorname Nachname'), 'Aufsicht')
        await fireEvent.click(screen.getByRole('button', { name: 'Zugang erstellen' }))
        context.admin.selected_schoolyear = null
        oldInvite.resolve({ data: { url: 'https://example.test/#stale' } })
        await screen.findByText('Bitte zuerst ein Schuljahr auswählen.')
        expect(screen.queryByDisplayValue('https://example.test/#stale')).not.toBeInTheDocument()
    })

    it('does not reopen setup or select a newly saved matura after switching years', async () => {
        mountManager()
        await screen.findByRole('button', { name: /Matura 10/ })
        const oldRoster = deferred()
        vi.mocked(axios.get).mockImplementationOnce(() => oldRoster.promise as any)
        await fireEvent.click(screen.getByRole('button', { name: '+ Neue Matura' }))
        context.admin.selected_schoolyear = { id: 20 }
        await screen.findByRole('button', { name: /Matura 20/ })
        oldRoster.resolve({ data: { students: [], supervisors: [] } })
        await nextTick()
        await nextTick()
        expect(screen.queryByText('Einrichtung offen')).not.toBeInTheDocument()
        await fireEvent.click(screen.getByRole('button', { name: '+ Neue Matura' }))
        await screen.findByText('Einrichtung offen')
        const oldSave = deferred()
        vi.mocked(axios.post).mockImplementationOnce(() => oldSave.promise as any)
        await fireEvent.click(screen.getByRole('button', { name: 'Einrichtung speichern' }))
        context.admin.selected_schoolyear = { id: 10 }
        await screen.findByRole('button', { name: /Matura 10/ })
        oldSave.resolve({ data: { id: 99 } })
        await nextTick()
        await nextTick()
        expect(context.route.query.matura).toBeUndefined()
        expect(screen.queryByText('Einrichtung offen')).not.toBeInTheDocument()
        expect(screen.queryByText('Live-Details')).not.toBeInTheDocument()
    })

    it('selects a newly saved matura after refreshing the current year list', async () => {
        mountManager()
        await screen.findByRole('button', { name: /Matura 10/ })
        await fireEvent.click(screen.getByRole('button', { name: '+ Neue Matura' }))
        await screen.findByText('Einrichtung offen')
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { id: 99 } })
        vi.mocked(axios.get).mockImplementation(async (url) => String(url).includes('schoolyear_id=')
            ? { data: { ready: true, sessions: [session(99)] } }
            : { data: detail(99) })
        await fireEvent.click(screen.getByRole('button', { name: 'Einrichtung speichern' }))
        await screen.findByText('Live-Details')
        expect(context.route.query.matura).toBe(99)
        expect(screen.queryByRole('combobox', { name: 'Matura auswählen' })).not.toBeInTheDocument()
        expect(screen.queryByRole('button', { name: '+ Neue Matura' })).not.toBeInTheDocument()
    })

    it('hides the selection toolbar during setup and restores it after cancellation', async () => {
        context.route.query = { matura: '1' }
        mountManager()
        await screen.findByText('Live-Details')
        await fireEvent.click(screen.getByRole('button', { name: 'Übersicht' }))
        await screen.findByRole('combobox', { name: 'Matura auswählen' })
        await fireEvent.click(screen.getByRole('button', { name: '+ Neue Matura' }))
        await screen.findByText('Einrichtung offen')
        expect(screen.queryByRole('combobox', { name: 'Matura auswählen' })).not.toBeInTheDocument()
        expect(screen.queryByRole('button', { name: '+ Neue Matura' })).not.toBeInTheDocument()
        expect(screen.queryByRole('button', { name: 'Übersicht' })).not.toBeInTheDocument()
        await fireEvent.click(screen.getByRole('button', { name: 'Einrichtung abbrechen' }))
        expect(screen.getByRole('combobox', { name: 'Matura auswählen' })).toBeInTheDocument()
        expect(screen.getByRole('button', { name: '+ Neue Matura' })).toBeInTheDocument()
        expect(screen.queryByRole('button', { name: 'Übersicht' })).not.toBeInTheDocument()
    })

    it('restores the open setup, values, completed tasks and active room after remounting', async () => {
        mountManager(true)
        await screen.findByRole('button', { name: /Matura 10/ })
        await fireEvent.click(screen.getByRole('button', { name: '+ Neue Matura' }))
        await fireEvent.click(await screen.findByRole('button', { name: /^1 Matura & Klassen/ }))
        await fireEvent.update(screen.getByLabelText('Name der Matura'), 'Entwurf Deutsch')
        await fireEvent.update(screen.getByLabelText('Prüfungsdatum'), '2026-10-04')
        await fireEvent.click(screen.getByRole('button', { name: /^(Änderungen speichern|Weiter:|Zusammenfassung anzeigen)/ }))
        await fireEvent.click(screen.getByRole('button', { name: /^2 Räume & Schüler/ }))
        await fireEvent.update(screen.getByPlaceholderText('z. B. 3.12'), 'Raum A')
        await fireEvent.click(screen.getByRole('button', { name: '+ Raum hinzufügen' }))
        await fireEvent.update(screen.getByPlaceholderText('z. B. 3.12'), 'Raum B')
        await fireEvent.click(screen.getByText('Weitere Schüler ohne Import hinzufügen'))
        await fireEvent.update(screen.getByPlaceholderText('Nachname Vorname'), 'Gast Eins')
        await fireEvent.click(screen.getByRole('button', { name: /^(Änderungen speichern|Weiter:|Zusammenfassung anzeigen)/ }))
        await fireEvent.click(screen.getByRole('button', { name: /^3 Ablauf/ }))
        await fireEvent.update(screen.getByRole('spinbutton', { name: /Warteplätze an der Zwischenstation/ }), '4')
        cleanup()
        window.sessionStorage.clear()
        mountManager(true)
        expect(await screen.findByRole('spinbutton', { name: /Warteplätze an der Zwischenstation/ })).toHaveValue(4)
        expect(screen.queryByRole('combobox', { name: 'Matura auswählen' })).not.toBeInTheDocument()
        expect(screen.getByText('2 von 3 Aufgaben erledigt')).toBeInTheDocument()
        await fireEvent.click(screen.getByRole('button', { name: /^✓ Räume & Schüler/ }))
        expect(screen.getByPlaceholderText('z. B. 3.12')).toHaveValue('Raum B')
        expect(screen.getByPlaceholderText('Nachname Vorname')).toHaveValue('Gast Eins')
        await fireEvent.click(screen.getByRole('button', { name: /^✓ Matura & Klassen/ }))
        expect(screen.getByLabelText('Name der Matura')).toHaveValue('Entwurf Deutsch')
        expect(screen.getByLabelText('Prüfungsdatum')).toHaveValue('2026-10-04')
        expect(axios.post).not.toHaveBeenCalled()
    })

    it('restores drafts only for the selected user, school and year and discards them on abort', async () => {
        const scope = storeDraft()
        mountManager(true)
        await screen.findByText('Entwurf Deutsch')
        context.admin.selected_schoolyear = { id: 20 }
        await screen.findByRole('combobox', { name: 'Matura auswählen' })
        expect(screen.queryByText('Entwurf Deutsch')).not.toBeInTheDocument()
        context.admin.selected_schoolyear = { id: 10 }
        await screen.findByText('Entwurf Deutsch')
        context.admin.config.user.id = 99
        await screen.findByRole('combobox', { name: 'Matura auswählen' })
        expect(screen.queryByText('Entwurf Deutsch')).not.toBeInTheDocument()
        context.admin.config.user.id = 7
        await screen.findByText('Entwurf Deutsch')
        context.admin.config.selected_school.id = 99
        await screen.findByRole('combobox', { name: 'Matura auswählen' })
        expect(screen.queryByText('Entwurf Deutsch')).not.toBeInTheDocument()
        context.admin.config.selected_school.id = 8
        await screen.findByText('Entwurf Deutsch')
        await fireEvent.click(screen.getByRole('button', { name: 'Abbruch' }))
        expect(readSetupDraft(setupDraftKey(scope))).toBeNull()
        expect(readActiveSetup(scope)).toBeNull()
        cleanup()
        mountManager(true)
        await screen.findByRole('combobox', { name: 'Matura auswählen' })
        await fireEvent.click(screen.getByRole('button', { name: '+ Neue Matura' }))
        await screen.findByLabelText('Name der Matura')
        expect(screen.getByLabelText('Name der Matura')).toHaveValue('')
    })

    it('keeps a draft after failed saving and removes it only after successful saving', async () => {
        const scope = storeDraft()
        mountManager(true)
        await screen.findByRole('button', { name: 'Matura speichern' })
        vi.mocked(axios.post).mockRejectedValueOnce({ response: { status: 422, data: { message: 'Bitte prüfen' } } })
        await fireEvent.click(screen.getByRole('button', { name: 'Matura speichern' }))
        await screen.findByRole('alert')
        expect(readSetupDraft(setupDraftKey(scope))).not.toBeNull()
        expect(readActiveSetup(scope)).not.toBeNull()
        cleanup()
        mountManager(true)
        await screen.findByRole('button', { name: 'Matura speichern' })
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { id: 99 } })
        vi.mocked(axios.get).mockImplementation(async (url) => String(url).includes('schoolyear_id=')
            ? { data: { ready: true, sessions: [session(99)] } } : { data: detail(99) })
        await fireEvent.click(screen.getByRole('button', { name: 'Matura speichern' }))
        await screen.findByText('Live-Details')
        expect(readSetupDraft(setupDraftKey(scope))).toBeNull()
        expect(window.sessionStorage.getItem(`${scope}:active`)).toBeNull()
        expect(window.localStorage.getItem(`${scope}:active`)).toBeNull()
        expect(window.localStorage.getItem(setupDraftKey(scope))).toBeNull()
        cleanup()
        mountManager(true)
        await screen.findByText('Live-Details')
        expect(screen.queryByRole('button', { name: 'Matura speichern' })).not.toBeInTheDocument()
    })

    it('restores an edit draft only for its editable session and leaves a new draft separate', async () => {
        storeDraft(draftFixture({ form: { ...draftFixture().form, name: 'Neuer Entwurf' } }))
        storeDraft(draftFixture({ form: { ...draftFixture().form, name: 'Bearbeiteter Entwurf' } }), 1)
        vi.mocked(axios.get).mockImplementation(async (url) => {
            if (String(url).includes('schoolyear_id=')) return { data: { ready: true, sessions: [{ ...session(), status: 'draft', can_manage: true }] } }
            if (String(url).endsWith('/roster')) return { data: { students: [], supervisors: [] } }
            return { data: { ...detail(), session: { ...session(), status: 'draft' } } }
        })
        mountManager(true)
        await screen.findByRole('heading', { name: 'Matura bearbeiten' })
        expect(screen.getByRole('heading', { name: 'Entwurfsraum', level: 4 })).toBeInTheDocument()
        expect(screen.queryByText('Bearbeiteter Entwurf')).not.toBeInTheDocument()
        expect(screen.queryByLabelText('Name der Matura')).not.toBeInTheDocument()
        expect(screen.queryByText('Neuer Entwurf')).not.toBeInTheDocument()
        await fireEvent.click(screen.getByRole('button', { name: 'Abbruch' }))
        await fireEvent.click(await screen.findByRole('button', { name: 'Übersicht' }))
        await screen.findByRole('combobox', { name: 'Matura auswählen' })
        await fireEvent.click(screen.getByRole('button', { name: '+ Neue Matura' }))
        await screen.findByText('Neuer Entwurf')
        expect(axios.put).not.toHaveBeenCalled()
    })

    it('restores multiple selected classes and individual corrections after reload', async () => {
        const students = [
            { id: 1, first_name: 'Änne', last_name: 'Müller', class: '8A' },
            { id: 2, first_name: 'Emil', last_name: 'Öztürk', class: '8A' },
            { id: 3, first_name: 'Zoë', last_name: 'Groß', class: '8B' },
        ]
        vi.mocked(axios.get).mockImplementation(async (url) => {
            if (String(url).includes('schoolyear_id=')) return { data: { ready: true, sessions: [session()] } }
            if (String(url).endsWith('/roster')) return { data: { students, supervisors: [] } }
            return { data: detail() }
        })
        mountManager(true)
        await screen.findByRole('button', { name: /Matura 10/ })
        await fireEvent.click(screen.getByRole('button', { name: '+ Neue Matura' }))
        await fireEvent.click(await screen.findByRole('button', { name: /^1 Matura & Klassen/ }))
        await fireEvent.update(screen.getByLabelText('Name der Matura'), 'Deutsch')
        await fireEvent.click(screen.getByRole('button', { name: /^8A / }))
        await fireEvent.click(screen.getByRole('button', { name: /^8B / }))
        await fireEvent.click(screen.getByRole('button', { name: /^(Änderungen speichern|Weiter:|Zusammenfassung anzeigen)/ }))
        await fireEvent.click(screen.getByRole('button', { name: /^2 Räume & Schüler/ }))
        await fireEvent.update(screen.getByPlaceholderText('z. B. 3.12'), 'A')
        await fireEvent.click(screen.getByRole('checkbox', { name: /Müller Änne/ }))
        await fireEvent.click(screen.getByRole('checkbox', { name: /Groß Zoë/ }))
        cleanup()
        mountManager(true)
        await screen.findByRole('checkbox', { name: /Müller Änne/ })
        expect(screen.getByRole('checkbox', { name: /Müller Änne/ })).toBeChecked()
        expect(screen.getByRole('checkbox', { name: /Groß Zoë/ })).toBeChecked()
        expect(screen.getByRole('checkbox', { name: /Öztürk Emil/ })).not.toBeChecked()
        await fireEvent.click(screen.getByRole('button', { name: /^✓ Matura & Klassen/ }))
        expect(screen.getByRole('button', { name: /^8A / })).toHaveAttribute('aria-pressed', 'true')
        expect(screen.getByRole('button', { name: /^8B / })).toHaveAttribute('aria-pressed', 'true')
        expect(axios.post).not.toHaveBeenCalled()
    })
})
