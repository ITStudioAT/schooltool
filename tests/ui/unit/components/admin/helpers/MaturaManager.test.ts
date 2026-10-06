import { fireEvent, render, screen, waitFor, cleanup } from '@testing-library/vue'
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
        ...(realSetup ? {} : { MaturaSetup: { template: '<div>Einrichtung offen<button @click="$emit(\'save\', {})">Einrichtung speichern</button><button @click="$emit(\'cancel\')">Einrichtung abbrechen</button></div>' } }),
        'v-dialog': { props: ['modelValue'], template: '<div v-if="modelValue" role="dialog"><slot /></div>' },
        'v-card': { template: '<div><slot /></div>' },
    } } })
}

beforeEach(() => {
    window.sessionStorage.clear()
    window.localStorage.clear()
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
    it('requests the selected year and permits a direct link only from its filtered list', async () => {
        context.route.query = { matura: '1' }
        mountManager()
        await screen.findByText('Live-Details')
        expect(axios.get).toHaveBeenCalledWith('/admin/helpers/00-manager?schoolyear_id=10')
        expect(axios.get).toHaveBeenCalledWith('/admin/helpers/00-manager/1', { timeout: 12000 })
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
        await fireEvent.click(screen.getByRole('button', { name: 'Aufsichten & Einrichtung' }))
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
        await fireEvent.click(screen.getByRole('button', { name: 'Aufsichten & Einrichtung' }))
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
        await fireEvent.click(screen.getByRole('button', { name: 'Aufsichten & Einrichtung' }))
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
        expect(screen.getByRole('combobox')).toHaveValue('99')
    })

    it('hides the selection toolbar during setup and restores it after cancellation', async () => {
        context.route.query = { matura: '1' }
        mountManager()
        await screen.findByText('Live-Details')
        await fireEvent.click(screen.getByRole('button', { name: '+ Neue Matura' }))
        await screen.findByText('Einrichtung offen')
        expect(screen.queryByRole('combobox', { name: 'Matura auswählen' })).not.toBeInTheDocument()
        expect(screen.queryByRole('button', { name: '+ Neue Matura' })).not.toBeInTheDocument()
        expect(screen.queryByRole('button', { name: 'Übersicht' })).not.toBeInTheDocument()
        await fireEvent.click(screen.getByRole('button', { name: 'Einrichtung abbrechen' }))
        expect(screen.getByRole('combobox', { name: 'Matura auswählen' })).toBeInTheDocument()
        expect(screen.getByRole('button', { name: '+ Neue Matura' })).toBeInTheDocument()
        expect(screen.getByRole('button', { name: 'Übersicht' })).toBeInTheDocument()
    })

    it('restores the open setup, values, completed tasks and active room after remounting', async () => {
        mountManager(true)
        await screen.findByRole('button', { name: /Matura 10/ })
        await fireEvent.click(screen.getByRole('button', { name: '+ Neue Matura' }))
        await fireEvent.click(await screen.findByRole('button', { name: /^1 Matura & Klassen/ }))
        await fireEvent.update(screen.getByLabelText('Name der Matura'), 'Entwurf Deutsch')
        await fireEvent.update(screen.getByLabelText('Prüfungsdatum'), '2026-10-04')
        await fireEvent.click(screen.getByRole('button', { name: 'Übernehmen' }))
        await fireEvent.click(screen.getByRole('button', { name: /^2 Räume & Schüler/ }))
        await fireEvent.update(screen.getByPlaceholderText('z. B. 3.12'), 'Raum A')
        await fireEvent.click(screen.getByRole('button', { name: '+ Raum hinzufügen' }))
        await fireEvent.update(screen.getByPlaceholderText('z. B. 3.12'), 'Raum B')
        await fireEvent.click(screen.getByText('Weitere Schüler ohne Import hinzufügen'))
        await fireEvent.update(screen.getByPlaceholderText('Nachname Vorname'), 'Gast Eins')
        await fireEvent.click(screen.getByRole('button', { name: 'Übernehmen' }))
        await fireEvent.click(screen.getByRole('button', { name: /^3 Ablauf/ }))
        await fireEvent.update(screen.getByRole('spinbutton'), '4')
        cleanup()
        window.sessionStorage.clear()
        mountManager(true)
        expect(await screen.findByRole('spinbutton')).toHaveValue(4)
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

    it('keeps scoped drafts on cancel and restores only the selected user, school and year', async () => {
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
        await fireEvent.click(screen.getByRole('button', { name: 'Abbrechen' }))
        expect(readSetupDraft(setupDraftKey(scope))).not.toBeNull()
        expect(readActiveSetup(scope)).toBeNull()
        cleanup()
        mountManager(true)
        await screen.findByRole('combobox', { name: 'Matura auswählen' })
        await fireEvent.click(screen.getByRole('button', { name: '+ Neue Matura' }))
        await screen.findByText('Entwurf Deutsch')
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
        await screen.findByText('Bearbeiteter Entwurf')
        expect(screen.getByRole('heading', { name: 'Matura bearbeiten' })).toBeInTheDocument()
        expect(screen.queryByText('Neuer Entwurf')).not.toBeInTheDocument()
        await fireEvent.click(screen.getByRole('button', { name: 'Abbrechen' }))
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
        await fireEvent.click(screen.getByRole('button', { name: 'Übernehmen' }))
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
