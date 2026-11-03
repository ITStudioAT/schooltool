import { describe, expect, it, vi } from 'vitest'
import { createTestingPinia } from '@pinia/testing'
import { fireEvent, render, screen, waitFor } from '@testing-library/vue'
import { nextTick } from 'vue'
import ModuleStatusesCard from '@/pages/admin/settings/components/ModuleStatusesCard.vue'
import { useSchoolToolStore } from '@/stores/admin/SchoolToolStore'

function moduleRow(key, label, hasUserView = true) {
    return {
        key, label, hasUserView, meta: `Modul ${label}`,
        adminVisibleField: `${key}_visible_admin`,
        userVisibleField: `${key}_visible_user`,
        userTestModeField: `${key}_user_test_mode`,
        userComingSoonField: `${key}_user_comming_soon`,
    }
}

function renderPanel(attributes = {}) {
    const visibility = Object.fromEntries(
        ['register', 'teaching', 'materials', 'restaurant', 'aba', 'students_timetables'].flatMap((key) => [
            [`${key}_visible_admin`, true],
            [`${key}_visible_user`, true],
            [`${key}_user_test_mode`, false],
            [`${key}_user_comming_soon`, false],
        ]),
    )
    const pinia = createTestingPinia({
        createSpy: vi.fn,
        initialState: {
            AdminSchoolToolStore: {
                data: {
                    id: 7, ...visibility, students_timetables_admin_version: 'v2',
                    module_rows: [moduleRow('register', 'Anmeldetool'), moduleRow('students_timetables', 'SEPP')],
                    ...attributes,
                },
            },
        },
    })
    const store = useSchoolToolStore(pinia)
    vi.mocked(store.saveModuleStatuses).mockImplementation(async (payload) => {
        store.data = { ...store.data, ...payload }
        return true
    })
    render(ModuleStatusesCard, {
        global: {
            plugins: [pinia],
            stubs: {
                'v-sheet': { template: '<section><slot /></section>' },
                'v-chip': { template: '<span><slot /></span>' },
            },
        },
    })
    return store
}

function adminButton(state) {
    return screen.getByRole('button', { name: new RegExp(`^Anmeldetool: Admin ${state}\\.`) })
}

function userButton(state) {
    return screen.getByRole('button', { name: new RegExp(`^Anmeldetool: Benutzer ${state}\\.`) })
}

async function clickAndSave(button) {
    await fireEvent.click(button)
    await waitFor(() => expect(button).toBeEnabled())
}

describe('ModuleStatusesCard', () => {
    it('disables only unavailable user controls and preserves their stored flags', async () => {
        const store = renderPanel({
            module_rows: [
                moduleRow('register', 'Anmeldetool'),
                moduleRow('teaching', 'Unterricht'),
                moduleRow('restaurant', 'Restaurant'),
                moduleRow('students_timetables', 'SEPP'),
                moduleRow('aba', 'ABA', false),
                moduleRow('materials', 'Materialien', false),
            ],
            aba_visible_user: false,
            aba_user_test_mode: true,
        })

        for (const module of ['ABA', 'Materialien']) {
            const unavailable = screen.getByRole('button', { name: `${module}: Benutzer nicht verfügbar. Keine eigene Benutzeransicht vorhanden.` })
            expect(unavailable).toBeDisabled()
            expect(unavailable).toHaveTextContent('Nicht verfügbar')
            expect(screen.getByRole('button', { name: new RegExp(`^${module}: Admin Angezeigt`) })).toBeEnabled()
            await fireEvent.click(unavailable)
        }
        for (const module of ['Anmeldetool', 'Unterricht', 'Restaurant', 'SEPP']) {
            expect(screen.getByRole('button', { name: new RegExp(`^${module}: Benutzer Anzeige`) })).toBeEnabled()
        }
        expect(store.saveModuleStatuses).not.toHaveBeenCalled()

        await clickAndSave(userButton('Anzeige'))
        expect(store.saveModuleStatuses).toHaveBeenLastCalledWith(expect.objectContaining({
            aba_visible_user: false, aba_user_test_mode: true, aba_user_comming_soon: false,
            materials_visible_user: true, materials_user_test_mode: false, materials_user_comming_soon: false,
        }))
    })

    it('builds two labelled controls per stored module row without the SEPP version selector', () => {
        const store = renderPanel()
        expect(screen.getAllByRole('button')).toHaveLength(4)
        expect(screen.getByRole('button', { name: /^SEPP: Admin Angezeigt\./ })).toBeInTheDocument()
        expect(screen.getByText('Modul SEPP')).toBeInTheDocument()
        expect(screen.getByText('App-weit gültig!')).toBeInTheDocument()
        expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
        expect(screen.queryByText('Aktive Version')).not.toBeInTheDocument()
        expect(store.saveModuleStatuses).not.toHaveBeenCalled()
    })

    it('cycles Anzeige, Testmodus, Kommt bald and Aus, saving exclusive flags immediately', async () => {
        const store = renderPanel()
        await clickAndSave(userButton('Anzeige'))
        expect(userButton('Testmodus')).toHaveAccessibleName(/Nächster Klick: Kommt bald/)
        expect(store.saveModuleStatuses).toHaveBeenLastCalledWith(expect.objectContaining({
            register_visible_user: false, register_user_test_mode: true, register_user_comming_soon: false,
        }))
        await clickAndSave(userButton('Testmodus'))
        expect(userButton('Kommt bald')).toHaveAccessibleName(/Nächster Klick: Aus/)
        expect(store.saveModuleStatuses).toHaveBeenLastCalledWith(expect.objectContaining({
            register_visible_user: false, register_user_test_mode: false, register_user_comming_soon: true,
        }))
        await clickAndSave(userButton('Kommt bald'))
        expect(userButton('Aus')).toHaveAccessibleName(/Nächster Klick: Anzeige/)
        expect(store.saveModuleStatuses).toHaveBeenLastCalledWith(expect.objectContaining({
            register_visible_user: false, register_user_test_mode: false, register_user_comming_soon: false,
        }))
        await clickAndSave(userButton('Aus'))
        expect(userButton('Anzeige')).toHaveAccessibleName(/Nächster Klick: Testmodus/)
        expect(store.saveModuleStatuses).toHaveBeenLastCalledWith(expect.objectContaining({
            register_visible_user: true, register_user_test_mode: false, register_user_comming_soon: false,
        }))
        expect(store.saveModuleStatuses).toHaveBeenCalledTimes(4)
    })

    it('toggles Admin off and on without restoring user access automatically', async () => {
        const store = renderPanel()
        await clickAndSave(adminButton('Angezeigt'))
        expect(adminButton('Aus')).toHaveAttribute('aria-pressed', 'false')
        expect(userButton('Aus')).toHaveAccessibleName(/Nächster Klick: Testmodus/)
        expect(store.saveModuleStatuses).toHaveBeenLastCalledWith(expect.objectContaining({
            register_visible_admin: false, register_visible_user: false,
        }))
        await clickAndSave(adminButton('Aus'))
        expect(adminButton('Angezeigt')).toHaveAttribute('aria-pressed', 'true')
        expect(userButton('Aus')).toHaveAccessibleName(/Nächster Klick: Anzeige/)
        expect(store.saveModuleStatuses).toHaveBeenLastCalledWith(expect.objectContaining({
            register_visible_admin: true, register_visible_user: false,
        }))
    })

    it('skips Anzeige when Admin is off but allows Testmodus, Kommt bald and Aus', async () => {
        const store = renderPanel({ register_visible_admin: false, register_visible_user: false })
        expect(userButton('Aus')).toHaveAccessibleName(/Nächster Klick: Testmodus/)
        await clickAndSave(userButton('Aus'))
        await clickAndSave(userButton('Testmodus'))
        await clickAndSave(userButton('Kommt bald'))
        expect(userButton('Aus')).toBeEnabled()
        expect(store.saveModuleStatuses).toHaveBeenCalledTimes(3)
        for (const [payload] of vi.mocked(store.saveModuleStatuses).mock.calls) {
            expect(payload.register_visible_admin).toBe(false)
            expect(payload.register_visible_user).toBe(false)
        }
    })

    it.each([
        ['Testmodus', { register_user_test_mode: true }],
        ['Kommt bald', { register_user_comming_soon: true }],
    ])('preserves the existing %s status when toggling Admin', async (state, flags) => {
        renderPanel({ register_visible_user: false, ...flags })
        await clickAndSave(adminButton('Angezeigt'))
        expect(userButton(state)).toBeEnabled()
        await clickAndSave(adminButton('Aus'))
        expect(userButton(state)).toBeEnabled()
    })

    it('preserves hidden module flags and never submits the hidden admin version', async () => {
        const store = renderPanel({ aba_visible_user: false, aba_user_test_mode: true })
        await clickAndSave(userButton('Anzeige'))
        const [payload] = vi.mocked(store.saveModuleStatuses).mock.calls[0]
        expect(payload).toMatchObject({
            id: 7, aba_visible_admin: true, aba_visible_user: false,
            aba_user_test_mode: true, aba_user_comming_soon: false,
            students_timetables_visible_admin: true, students_timetables_visible_user: true,
        })
        expect(payload).not.toHaveProperty('students_timetables_admin_version')
        expect(store.data.students_timetables_admin_version).toBe('v2')
    })

    it('locks all controls until saving and the store refresh finish to prevent overlapping requests', async () => {
        const store = renderPanel()
        let finishSave
        vi.mocked(store.saveModuleStatuses).mockImplementationOnce((payload) => {
            store.data = { ...store.data, ...payload }
            return new Promise((resolve) => { finishSave = resolve })
        })
        await fireEvent.click(userButton('Anzeige'))
        expect(screen.getByRole('status')).toHaveTextContent('Änderung wird gespeichert')
        for (const button of screen.getAllByRole('button')) {
            expect(button).toBeDisabled()
        }
        await fireEvent.click(adminButton('Angezeigt'))
        await fireEvent.click(userButton('Testmodus'))
        expect(store.saveModuleStatuses).toHaveBeenCalledOnce()
        finishSave(true)
        await waitFor(() => expect(userButton('Testmodus')).toBeEnabled())
        await clickAndSave(userButton('Testmodus'))
        expect(userButton('Kommt bald')).toBeEnabled()
        expect(store.saveModuleStatuses).toHaveBeenCalledTimes(2)
    })

    it.each(['rejected', 'thrown'])('restores confirmed values and permits retry after a %s save', async (failure) => {
        const store = renderPanel()
        if (failure === 'thrown') {
            vi.mocked(store.saveModuleStatuses).mockRejectedValueOnce(new Error('Connection lost'))
        } else {
            vi.mocked(store.saveModuleStatuses).mockResolvedValueOnce(false)
        }
        await clickAndSave(userButton('Anzeige'))
        expect(userButton('Anzeige')).toBeEnabled()
        expect(screen.getByRole('status')).toHaveTextContent('Änderung konnte nicht gespeichert werden')
        await clickAndSave(userButton('Anzeige'))
        expect(userButton('Testmodus')).toBeEnabled()
        expect(screen.getByRole('status')).toBeEmptyDOMElement()
    })

    it('follows external configuration changes while idle', async () => {
        const store = renderPanel()
        store.data = { ...store.data, register_visible_user: false, register_user_comming_soon: true }
        await nextTick()
        expect(userButton('Kommt bald')).toBeEnabled()
        expect(store.saveModuleStatuses).not.toHaveBeenCalled()
    })

    it('disables edits until a configuration id is available', () => {
        const store = renderPanel({ id: null })
        for (const button of screen.getAllByRole('button')) {
            expect(button).toBeDisabled()
        }
        expect(store.saveModuleStatuses).not.toHaveBeenCalled()
    })
})
