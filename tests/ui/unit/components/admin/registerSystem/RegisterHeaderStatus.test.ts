import { createTestingPinia } from '@pinia/testing'
import { render, screen, waitFor } from '@testing-library/vue'
import { afterEach, describe, expect, it, vi } from 'vitest'
import RegisterDetails from '@/pages/admin/registerSystem/RegisterDetails.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useRegisterStore } from '@/stores/admin/RegisterStore'

afterEach(() => vi.unstubAllGlobals())

function renderDetails() {
    const pinia = createTestingPinia({
        initialState: {
            AdminAdminStore: {
                selected_schoolyear: { id: 1, name: '2030/31' },
                selected_register: { id: 5, schoolyear_id: 2, schoolyear_name: '2031/32', is_active: true },
            },
        },
    })
    render(RegisterDetails, {
        global: {
            plugins: [pinia],
            stubs: {
                VContainer: { template: '<div><slot /></div>' },
                VRow: { template: '<div><slot /></div>' },
                VBtn: { template: '<button><slot /></button>' },
                'v-btn': { template: '<button><slot /></button>' },
                VIcon: true,
                Overview: true,
                MainMenu: true,
                DatesWithMenu: true,
                AddDates: true,
                AddPerson: true,
                ShowBookings: true,
                RegisterUsers: true,
            },
        },
    })
    return useAdminStore(pinia)
}

describe('Registration header status', () => {
    it('uses the selected system schoolyear and reacts to its opening status', async () => {
        const store = renderDetails()
        expect(screen.getByText('2031/32')).toBeInTheDocument()
        expect(screen.queryByText('2030/31')).not.toBeInTheDocument()
        expect(screen.getByText('Anmeldesystem geöffnet')).toBeInTheDocument()
        expect(screen.getAllByRole('button', { name: 'Zurück' })).toHaveLength(1)

        store.selected_register.is_active = false
        await waitFor(() => expect(screen.getByText('Anmeldesystem geschlossen')).toBeInTheDocument())
        store.selected_register = null
        await waitFor(() => expect(screen.getByText('Öffnungsstatus nicht verfügbar')).toBeInTheDocument())
    })

    it('uses a matching selected schoolyear but never labels a different year as the system year', async () => {
        const store = renderDetails()
        store.selected_register = { id: 5, schoolyear_id: 1, is_active: false }
        await waitFor(() => expect(screen.getByText('2030/31')).toBeInTheDocument())
        store.selected_register.schoolyear_id = 2
        await waitFor(() => expect(screen.getByText('Kein Schuljahr verfügbar')).toBeInTheDocument())
    })

    it('marks an empty successful response as known and a failed refresh as unavailable', async () => {
        const pinia = createTestingPinia({ stubActions: false })
        const store = useRegisterStore(pinia)
        const post = vi.fn().mockResolvedValue({ data: [] })
        vi.stubGlobal('axios', { post })

        expect(store.active_registers_status).toBe('idle')
        const request = store.loadActiveRegisters()
        expect(store.active_registers_status).toBe('loading')
        expect(await request).toBe(true)
        expect(store.active_registers_status).toBe('ready')
        expect(store.active_registers).toEqual([])

        post.mockRejectedValue({ response: { status: 500, data: { message: 'Fehler' } } })
        expect(await store.loadActiveRegisters()).toBe(false)
        expect(store.active_registers_status).toBe('error')
    })
})
