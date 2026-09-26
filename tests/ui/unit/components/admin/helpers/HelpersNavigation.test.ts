import { createTestingPinia } from '@pinia/testing'
import { fireEvent, render, screen, waitFor } from '@testing-library/vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { describe, expect, it, vi } from 'vitest'
import Helpers from '@/pages/admin/helpers/Helpers.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { resolveAdminRouteAccess } from '../../../../../../resources/routes/admin.js'

async function renderPage(path = '/admin/helpers') {
    const getClasses = vi.fn(async () => ({ data: { data: [
        { name: '1A', variants: ['1A'], student_count: 23, announced: false },
        { name: '2B', variants: ['2B'], student_count: 19, announced: false },
        { name: '8A', variants: ['8A-BU', '8A-DG'], student_count: 15, announced: true },
    ] } }))
    vi.stubGlobal('axios', { get: getClasses })

    const router = createRouter({
        history: createMemoryHistory(),
        routes: [{ path: '/admin/helpers', component: Helpers }],
    })
    await router.push(path)
    await router.isReady()

    const pinia = createTestingPinia({
        initialState: {
            AdminAdminStore: { selected_schoolyear: { id: 1, name: '2030/31' } },
        },
    })

    render({ template: '<router-view />' }, {
        global: {
            plugins: [
                pinia,
                router,
            ],
            stubs: {
                VContainer: { template: '<div><slot /></div>' },
                VSheet: { template: '<div><slot /></div>' },
                VBtn: { template: '<button type="button"><slot /></button>' },
                'v-btn': { template: '<button type="button"><slot /></button>' },
                VDialog: { template: '<div role="dialog"><slot /></div>' },
                'v-dialog': { template: '<div role="dialog"><slot /></div>' },
                VCard: { template: '<div><slot /></div>' },
                'v-card': { template: '<div><slot /></div>' },
                VCardTitle: { template: '<h2><slot /></h2>' },
                'v-card-title': { template: '<h2><slot /></h2>' },
                VCardText: { template: '<div><slot /></div>' },
                'v-card-text': { template: '<div><slot /></div>' },
                VCardActions: { template: '<div><slot /></div>' },
                'v-card-actions': { template: '<div><slot /></div>' },
                VSpacer: { template: '<span />' },
                'v-spacer': { template: '<span />' },
                AdminPageHeader: { props: ['location', 'section'], template: '<h1>{{ location }} · {{ section }}</h1>' },
            },
        },
    })

    return { router, store: useAdminStore(pinia), getClasses }
}

describe('Helpers navigation', () => {
    it('uses the existing admin shell capability', () => {
        expect(resolveAdminRouteAccess('/admin/helpers')).toEqual({ public: false, capability: 'home' })
    })

    it('opens Klassensprecherwahl by default and switches to Matura with a deep link', async () => {
        const { router, getClasses } = await renderPage()

        expect(screen.getByRole('heading', { name: 'Helpers · Klassensprecherwahl' })).toBeInTheDocument()
        expect(screen.getByRole('group', { name: 'Klassensprecherwahl-Auswahl' })).toBeInTheDocument()
        expect(screen.getByRole('button', { name: 'Klassensprecherwahl 2030/31' })).toBeInTheDocument()
        expect(screen.getByRole('button', { name: 'Überblick' })).toBeInTheDocument()
        expect(screen.getByRole('button', { name: 'Auswahl 2' })).toBeInTheDocument()
        expect(screen.queryByText('Dieser Bereich ist in Vorbereitung.')).not.toBeInTheDocument()
        await waitFor(() => expect(screen.getByRole('table')).toBeInTheDocument())
        expect(screen.getByText('3 Klassen')).toBeInTheDocument()
        expect(screen.getByRole('cell', { name: '1A' })).toBeInTheDocument()
        expect(screen.getByRole('cell', { name: '2B' })).toBeInTheDocument()
        expect(screen.getByRole('cell', { name: '8A-BU/DG' })).toBeInTheDocument()
        expect(screen.getByRole('cell', { name: '23' })).toBeInTheDocument()
        expect(screen.getByRole('cell', { name: '19' })).toBeInTheDocument()
        expect(screen.getByRole('cell', { name: '15' })).toBeInTheDocument()
        expect(screen.getByRole('status', { name: '1A: nicht ausgeschrieben' })).toHaveTextContent('✕ Offen')
        expect(screen.getByRole('status', { name: '8A: ausgeschrieben' })).toHaveTextContent('✓ Ausgeschrieben')
        expect(getClasses).toHaveBeenCalledWith('/api/admin/helpers/classes', { params: { schoolyear_id: 1 } })

        await fireEvent.click(screen.getByRole('button', { name: 'Matura' }))
        await waitFor(() => expect(router.currentRoute.value.fullPath).toBe('/admin/helpers?panel=matura'))
        expect(screen.getByRole('heading', { name: 'Helpers · Matura' })).toBeInTheDocument()
        expect(screen.getByRole('group', { name: 'Matura-Auswahl' })).toBeInTheDocument()
        expect(screen.queryByRole('table')).not.toBeInTheDocument()
    })

    it('selects individual classes and can select or deselect all classes', async () => {
        await renderPage()
        await waitFor(() => expect(screen.getByRole('table')).toBeInTheDocument())

        const firstClass = screen.getByRole('checkbox', { name: 'Klasse 1A auswählen' })
        const secondClass = screen.getByRole('checkbox', { name: 'Klasse 2B auswählen' })
        const announcedClass = screen.getByRole('checkbox', { name: 'Klasse 8A auswählen' })
        expect(announcedClass).toBeDisabled()
        await fireEvent.click(firstClass)
        expect(firstClass).toBeChecked()
        expect(secondClass).not.toBeChecked()

        await fireEvent.click(screen.getByRole('button', { name: 'Alle auswählen' }))
        expect(firstClass).toBeChecked()
        expect(secondClass).toBeChecked()
        expect(announcedClass).not.toBeChecked()

        await fireEvent.click(screen.getByRole('button', { name: 'Ausschreiben' }))
        expect(screen.getByRole('dialog')).toBeInTheDocument()
        expect(screen.getByText(/Schuljahr: 2030\/31/)).toBeInTheDocument()
        expect(screen.getByText(/Klassen: 1A, 2B/)).toBeInTheDocument()
        expect(screen.getByRole('button', { name: 'Per E-Mail versenden' })).toBeDisabled()
        await fireEvent.click(screen.getByRole('button', { name: 'Schließen' }))
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
        expect(screen.getByRole('status', { name: '1A: nicht ausgeschrieben' })).toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Alle abwählen' }))
        expect(firstClass).not.toBeChecked()
        expect(secondClass).not.toBeChecked()
    })

    it('opens Matura directly and preserves unrelated query parameters when switching back', async () => {
        const { router } = await renderPage('/admin/helpers?panel=matura&filter=active')

        expect(screen.getByRole('heading', { name: 'Helpers · Matura' })).toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Klassensprecherwahl 2030/31' }))
        await waitFor(() => expect(router.currentRoute.value.fullPath).toBe('/admin/helpers?filter=active'))
        expect(screen.getByRole('heading', { name: 'Helpers · Klassensprecherwahl' })).toBeInTheDocument()
    })

    it('switches between empty submenu choices and resets to the first choice on a panel change', async () => {
        const { router } = await renderPage()

        await fireEvent.click(screen.getByRole('button', { name: 'Auswahl 2' }))
        await waitFor(() => expect(router.currentRoute.value.fullPath).toBe('/admin/helpers?selection=2'))
        expect(screen.queryByRole('table')).not.toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Matura' }))
        await waitFor(() => expect(router.currentRoute.value.fullPath).toBe('/admin/helpers?panel=matura'))
        expect(screen.getByRole('button', { name: 'Überblick' })).toHaveAttribute('aria-pressed', 'true')
    })

    it('reloads the class overview when the personal schoolyear changes', async () => {
        const { store, getClasses } = await renderPage()
        await waitFor(() => expect(getClasses).toHaveBeenCalledTimes(1))

        store.selected_schoolyear = { id: 2, name: '2031/32' }

        await waitFor(() => expect(getClasses).toHaveBeenCalledTimes(2))
        expect(screen.getByRole('heading', { name: 'Klassen · 2031/32' })).toBeInTheDocument()
    })
})
