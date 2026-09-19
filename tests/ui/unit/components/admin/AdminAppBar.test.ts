import { fireEvent, render, screen, waitFor } from '@testing-library/vue'
import { createTestingPinia } from '@pinia/testing'
import { describe, expect, it, vi } from 'vitest'
import AdminAppBar from '@/pages/admin/components/AdminAppBar.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolyearStore } from '@/stores/admin/SchoolyearStore'

const vuetifyStubs = {
    'v-app-bar': {
        props: ['color'],
        template: '<header :data-color="color"><slot name="prepend" /><slot name="title" /><slot name="append" /></header>',
    },
    'v-btn': {
        props: ['disabled', 'loading'],
        emits: ['click'],
        template: '<button v-bind="$attrs" :disabled="disabled" :data-loading="loading" @click="$emit(\'click\')"><slot /></button>',
    },
    'v-dialog': {
        props: {
            modelValue: Boolean,
            persistent: Boolean,
        },
        template: '<div v-if="modelValue" v-bind="$attrs" role="dialog" :data-persistent="persistent"><slot /></div>',
    },
    'v-card': { template: '<section><slot /></section>' },
    'v-card-title': { template: '<h2><slot /></h2>' },
    'v-card-text': { template: '<div><slot /></div>' },
    'v-card-actions': { template: '<footer><slot /></footer>' },
    'v-alert': { template: '<div role="alert"><slot /></div>' },
    'v-divider': { template: '<hr />' },
    'v-icon': { template: '<span />' },
    'v-progress-circular': { template: '<span />' },
    'v-spacer': { template: '<span />' },
}

function renderAppBar(overrides = {}) {
    const pinia = createTestingPinia({
        createSpy: vi.fn,
        initialState: {
            AdminSchoolyearStore: {
                schoolyears: [
                    { id: 2, name: 'Schuljahr 2026/27', concerns: '2026/27' },
                    { id: 1, name: 'Schuljahr 2025/26', concerns: '2025/26' },
                    { id: 3, name: 'Schuljahr 2024/25', concerns: '2024/25' },
                ],
            },
        },
    })
    const rendered = render(AdminAppBar, {
        props: {
            modelValue: true,
            isVisible: true,
            title: 'Testschule',
            schoolwideActiveSchoolyear: {
                id: 2,
                name: 'Schuljahr 2026/27',
                concerns: '2026/27',
            },
            selectedSchoolyear: {
                id: 2,
                name: 'Schuljahr 2026/27',
                concerns: '2026/27',
            },
            ...overrides,
        },
        global: {
            plugins: [pinia],
            stubs: vuetifyStubs,
        },
    })

    return {
        ...rendered,
        adminStore: useAdminStore(pinia),
        schoolyearStore: useSchoolyearStore(pinia),
    }
}

describe('AdminAppBar', () => {
    it('uses the resolved admin shell color', () => {
        renderAppBar({ shellColor: '#336699' })

        expect(screen.getByRole('banner')).toHaveAttribute('data-color', '#336699')
    })

    it('only offers the preview link with explicit access', async () => {
        const { rerender } = renderAppBar({
            preview: { is_preview: false, can_access: false, url: 'https://preview.example.test/admin' },
        })

        expect(screen.queryByText('Zur Vorschau')).not.toBeInTheDocument()

        await rerender({ preview: { is_preview: false, can_access: true, url: 'https://preview.example.test/admin' } })

        expect(screen.getByText('Zur Vorschau')).toHaveAttribute('href', 'https://preview.example.test/admin')
    })

    it('identifies the preview and links directly back to the live application', () => {
        renderAppBar({
            preview: { is_preview: true, live_url: 'https://live.example.test/admin' },
        })

        expect(screen.getByText('Vorschau · Testkopie')).toBeInTheDocument()
        expect(screen.getByRole('button', { name: 'Zur Hauptanwendung' })).toHaveAttribute('href', 'https://live.example.test/admin')
        expect(screen.queryByText('Zur Vorschau')).not.toBeInTheDocument()
    })

    it('always shows the schoolwide and personal schoolyears', () => {
        renderAppBar()

        expect(screen.getByText('Schulweit:', { exact: false })).toBeInTheDocument()
        expect(screen.getByText('Persönlich:', { exact: false })).toBeInTheDocument()
        expect(screen.getAllByText('2026/27')).toHaveLength(2)
        expect(screen.getByRole('button', { name: 'Schulweit aktives Schuljahr 2026/27' })).toBeInTheDocument()
        expect(screen.getByRole('button', { name: 'Persönliches Ansichtsjahr 2026/27' })).toBeInTheDocument()
    })

    it('warns when the personal view uses a different schoolyear', () => {
        renderAppBar({
            selectedSchoolyear: {
                id: 1,
                name: 'Schuljahr 2025/26',
                concerns: '2025/26',
            },
        })

        expect(screen.getByText('2026/27')).toBeInTheDocument()
        expect(screen.getByText('Persönlich:', { exact: false })).toBeInTheDocument()
        expect(screen.getByText('2025/26')).toBeInTheDocument()
    })

    it('shows a clear error when no schoolwide schoolyear is configured', () => {
        renderAppBar({ schoolwideActiveSchoolyear: null })

        expect(screen.getByText('Kein schulweites Schuljahr')).toBeInTheDocument()
        expect(screen.getByText('Persönlich:', { exact: false })).toBeInTheDocument()
        expect(screen.getByText('2026/27')).toBeInTheDocument()
    })

    it('keeps the personal schoolyear button visible when no personal schoolyear is configured', () => {
        renderAppBar({ selectedSchoolyear: null })

        expect(screen.getByRole('button', { name: 'Kein persönliches Ansichtsjahr festgelegt' })).toBeInTheDocument()
        expect(screen.getByText('Kein persönliches Schuljahr')).toBeInTheDocument()
    })

    it('opens a persistent dialog and changes the schoolwide schoolyear via a year button', async () => {
        const { adminStore, schoolyearStore } = renderAppBar({ canManageSchoolwideSchoolyear: true })
        vi.mocked(schoolyearStore.index).mockResolvedValue(true)
        vi.mocked(schoolyearStore.setActiveSchoolyearInSchoolTool).mockResolvedValue(true)
        vi.mocked(adminStore.loadConfig).mockResolvedValue({})

        await fireEvent.click(screen.getByRole('button', { name: 'Schulweit aktives Schuljahr 2026/27' }))

        expect(schoolyearStore.index).toHaveBeenCalledOnce()
        expect(screen.getByRole('dialog')).toHaveAttribute('data-persistent', 'true')
        expect(screen.getByText('Schulweites Schuljahr wählen')).toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: /2025\/26/ }))

        expect(schoolyearStore.setActiveSchoolyearInSchoolTool).toHaveBeenCalledWith(1)
        expect(schoolyearStore.setActiveSchoolyear).not.toHaveBeenCalled()
        expect(adminStore.loadConfig).toHaveBeenCalledOnce()
        await waitFor(() => {
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
        })
    })

    it('changes only the personal view from the personal schoolyear button', async () => {
        const { adminStore, schoolyearStore } = renderAppBar({
            selectedSchoolyear: {
                id: 1,
                name: 'Schuljahr 2025/26',
                concerns: '2025/26',
            },
        })
        vi.mocked(schoolyearStore.index).mockResolvedValue(true)
        vi.mocked(schoolyearStore.setActiveSchoolyear).mockResolvedValue(true)
        vi.mocked(adminStore.loadConfig).mockResolvedValue({})

        await fireEvent.click(screen.getByRole('button', { name: 'Persönliches Ansichtsjahr 2025/26' }))

        expect(screen.getByText('Persönliches Ansichtsjahr wählen')).toBeInTheDocument()
        await fireEvent.click(screen.getByRole('button', { name: /2024\/25/ }))

        expect(schoolyearStore.setActiveSchoolyear).toHaveBeenCalledWith(3)
        expect(schoolyearStore.setActiveSchoolyearInSchoolTool).not.toHaveBeenCalled()
        expect(adminStore.loadConfig).toHaveBeenCalledOnce()
    })

    it('disables the schoolwide button without the administrator role', () => {
        renderAppBar({ canManageSchoolwideSchoolyear: false })

        expect(screen.getByRole('button', { name: 'Schulweit aktives Schuljahr 2026/27' })).toBeDisabled()
    })
})
