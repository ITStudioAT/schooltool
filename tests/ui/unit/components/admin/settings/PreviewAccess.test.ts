import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createTestingPinia } from '@pinia/testing'
import { fireEvent, render, screen, waitFor } from '@testing-library/vue'
import PreviewAccess from '@/pages/admin/settings/components/PreviewAccess.vue'
import { usePreviewAccessStore } from '@/stores/admin/PreviewAccessStore'

const users = [
    { id: 7, first_name: 'Anna', last_name: 'Muster', email: 'anna@example.test', school_name: 'Schule A', allowed: false, eligible: true },
    { id: 9, first_name: 'Ben', last_name: 'Beispiel', email: 'ben@example.test', school_name: 'Schule B', allowed: true, eligible: false },
    { id: 10, first_name: 'Chris', last_name: 'Test', email: 'chris@example.test', school_name: 'Schule A', allowed: false, eligible: false, ineligible_reason: 'Benutzerkonto noch nicht bestätigt.' },
]

const stubs = {
    'v-card': { template: '<section><slot /></section>' },
    'v-alert': { template: '<div><slot /></div>' },
    'v-chip': { template: '<span><slot /></span>' },
    'v-progress-linear': { template: '<div />' },
    'v-switch': {
        props: ['modelValue', 'label', 'disabled'],
        emits: ['update:modelValue'],
        template: '<label><input type="checkbox" :checked="modelValue" :disabled="disabled" @change="$emit(\'update:modelValue\', $event.target.checked)" />{{ label }}</label>',
    },
    'v-text-field': {
        props: ['modelValue', 'label'],
        emits: ['update:modelValue'],
        template: '<label>{{ label }}<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></label>',
    },
    'v-btn': {
        props: ['disabled'],
        emits: ['click'],
        template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
    },
}

function renderPreview(roles = ['super_admin'], { enabled = false, loadSucceeded = true, isPreview = false } = {}) {
    const pinia = createTestingPinia({
        createSpy: vi.fn,
        initialState: {
            AdminAdminStore: { config: { is_auth: true, roles, preview: { is_preview: isPreview, live_url: 'https://live.example.test/admin' } } },
            AdminPreviewAccessStore: { data: { enabled, users } },
        },
    })
    const store = usePreviewAccessStore(pinia)
    vi.mocked(store.load).mockResolvedValue(loadSucceeded)
    render(PreviewAccess, { global: { plugins: [pinia], stubs } })

    return store
}

describe('Preview access settings', () => {
    beforeEach(() => vi.clearAllMocks())

    it('preserves the cached enabled state when refreshing the panel fails', async () => {
        const store = renderPreview(['super_admin'], { enabled: true, loadSucceeded: false })
        await waitFor(() => expect(store.load).toHaveBeenCalledOnce())

        expect(screen.getByRole('checkbox', { name: 'Vorschau aktiv (alle Schulen)' })).toBeChecked()
        expect(screen.getByRole('button', { name: 'Speichern' })).toBeDisabled()
        expect(screen.getByText('Vorschau eingeschaltet')).toBeInTheDocument()
        expect(store.saveEnabled).not.toHaveBeenCalled()
    })

    it('requires an explicit save for the global switch', async () => {
        const store = renderPreview()
        await waitFor(() => expect(store.load).toHaveBeenCalledOnce())
        await fireEvent.click(screen.getByRole('checkbox', { name: 'Vorschau aktiv (alle Schulen)' }))

        expect(store.saveEnabled).not.toHaveBeenCalled()

        await fireEvent.click(screen.getByRole('button', { name: 'Speichern' }))
        expect(store.saveEnabled).toHaveBeenCalledWith(true)
        await waitFor(() => expect(screen.getByRole('checkbox', { name: 'Vorschau aktiv (alle Schulen)' })).not.toBeChecked())
    })

    it('grants selected accounts and permits revoking ineligible accounts', async () => {
        const store = renderPreview()

        await fireEvent.click(screen.getByRole('button', { name: 'Zugang freigeben: Muster Anna' }))
        expect(store.saveUser).toHaveBeenCalledWith(7, true)

        await fireEvent.click(screen.getByRole('button', { name: 'Zugang entziehen: Beispiel Ben' }))
        expect(store.saveUser).toHaveBeenCalledWith(9, false)
        expect(screen.getByRole('button', { name: 'Zugang freigeben: Test Chris' })).toBeDisabled()
        expect(screen.getByText('Benutzerkonto noch nicht bestätigt.')).toBeInTheDocument()
    })

    it('filters accounts by name, email or school', async () => {
        renderPreview()
        await fireEvent.update(screen.getByLabelText('Konten der aktuellen Schule suchen'), 'schule b')

        expect(screen.getByText('Beispiel Ben')).toBeInTheDocument()
        expect(screen.queryByText('Muster Anna')).not.toBeInTheDocument()
    })

    it('disables controls while saving and preserves the displayed grant until success', async () => {
        const store = renderPreview()
        store.is_saving = true

        await waitFor(() => expect(screen.getByRole('button', { name: 'Zugang entziehen: Beispiel Ben' })).toBeDisabled())
        expect(screen.getByRole('checkbox', { name: 'Vorschau aktiv (alle Schulen)' })).toBeDisabled()
        expect(screen.getByText('Freigegeben')).toBeInTheDocument()
    })

    it('does not expose or fetch management controls for ordinary admins', () => {
        const store = renderPreview(['admin'])

        expect(screen.queryByText('Schooltool Vorschau')).not.toBeInTheDocument()
        expect(store.load).not.toHaveBeenCalled()
    })

    it('directs preview administrators to the main application without fetching or exposing management controls', () => {
        const store = renderPreview(['super_admin'], { isPreview: true })

        expect(screen.getByText(/Die Vorschau arbeitet mit einer getrennten Testkopie/)).toHaveTextContent('Änderungen gelten nur für die Vorschau')
        expect(screen.getByText(/Freigaben und der zentrale Schalter/)).toHaveTextContent('ausschließlich in der Hauptanwendung verwaltet')
        expect(screen.getByRole('link', { name: 'Zur Hauptanwendung' })).toHaveAttribute('href', 'https://live.example.test/admin')
        expect(screen.queryByRole('checkbox')).not.toBeInTheDocument()
        expect(screen.queryByRole('button')).not.toBeInTheDocument()
        expect(store.load).not.toHaveBeenCalled()
        expect(store.saveEnabled).not.toHaveBeenCalled()
        expect(store.saveUser).not.toHaveBeenCalled()
    })
})
