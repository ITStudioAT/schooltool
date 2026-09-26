import { fireEvent, render, screen, waitFor } from '@testing-library/vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { describe, expect, it } from 'vitest'
import Helpers from '@/pages/admin/helpers/Helpers.vue'
import { resolveAdminRouteAccess } from '../../../../../../resources/routes/admin.js'

async function renderPage(path = '/admin/helpers') {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [{ path: '/admin/helpers', component: Helpers }],
    })
    await router.push(path)
    await router.isReady()

    render({ template: '<router-view />' }, {
        global: {
            plugins: [router],
            stubs: {
                VContainer: { template: '<div><slot /></div>' },
                VSheet: { template: '<div><slot /></div>' },
                VBtn: { template: '<button type="button"><slot /></button>' },
                'v-btn': { template: '<button type="button"><slot /></button>' },
                VCard: { template: '<div><slot /></div>' },
                VCardText: { template: '<div><slot /></div>' },
                AdminPageHeader: { props: ['location', 'section'], template: '<h1>{{ location }} · {{ section }}</h1>' },
            },
        },
    })

    return router
}

describe('Helpers navigation', () => {
    it('uses the existing admin shell capability', () => {
        expect(resolveAdminRouteAccess('/admin/helpers')).toEqual({ public: false, capability: 'home' })
    })

    it('opens Klassensprecherwahl by default and switches to Matura with a deep link', async () => {
        const router = await renderPage()

        expect(screen.getByRole('heading', { name: 'Klassensprecherwahl', level: 2 })).toBeInTheDocument()
        expect(screen.getByText('Dieser Bereich ist in Vorbereitung.')).toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Matura' }))
        await waitFor(() => expect(router.currentRoute.value.fullPath).toBe('/admin/helpers?panel=matura'))
        expect(screen.getByRole('heading', { name: 'Matura', level: 2 })).toBeInTheDocument()
        expect(screen.queryByRole('heading', { name: 'Klassensprecherwahl', level: 2 })).not.toBeInTheDocument()
    })

    it('opens Matura directly and preserves unrelated query parameters when switching back', async () => {
        const router = await renderPage('/admin/helpers?panel=matura&filter=active')

        expect(screen.getByRole('heading', { name: 'Matura', level: 2 })).toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Klassensprecherwahl' }))
        await waitFor(() => expect(router.currentRoute.value.fullPath).toBe('/admin/helpers?filter=active'))
        expect(screen.getByRole('heading', { name: 'Klassensprecherwahl', level: 2 })).toBeInTheDocument()
    })
})
