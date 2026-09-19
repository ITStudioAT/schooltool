import { afterEach, describe, expect, it, vi } from 'vitest'
import { createTestingPinia } from '@pinia/testing'
import { render, screen, waitFor } from '@testing-library/vue'
import HomepageApp from '@/pages/homepage/App.vue'
import { useHomepageStore } from '@/stores/homepage/HomepageStore'

function renderHomepage(isPreview: boolean) {
    const appElement = document.createElement('div')
    appElement.id = 'app'
    appElement.dataset.featurePreview = String(isPreview)
    appElement.dataset.previewLiveUrl = isPreview ? 'https://live.example.test/' : ''
    document.body.append(appElement)

    const pinia = createTestingPinia({ createSpy: vi.fn })
    const store = useHomepageStore(pinia)
    render(HomepageApp, {
        global: {
            plugins: [pinia],
            mocks: { $route: { path: '/' } },
            stubs: {
                'v-app': { template: '<div><slot /></div>' },
                'v-layout': { template: '<div><slot /></div>' },
                'v-main': { template: '<main><slot /></main>' },
                'v-alert': { template: '<aside><slot /></aside>' },
                'v-footer': true,
                'v-overlay': true,
                'router-view': true,
                ItsNotification: true,
                LoadingAnimation: true,
            },
        },
    })

    return store
}

afterEach(() => document.getElementById('app')?.remove())

describe('Homepage preview', () => {
    it('shows the isolated test copy notice and homepage return link before login', () => {
        const store = renderHomepage(true)

        expect(screen.getByRole('status')).toHaveTextContent('Schooltool Vorschau')
        expect(screen.getByRole('status')).toHaveTextContent('getrennten Testkopie')
        expect(screen.getByRole('status')).toHaveTextContent('Änderungen gelten nur für die Vorschau')
        expect(screen.getByRole('link', { name: 'Zur Hauptanwendung' })).toHaveAttribute('href', 'https://live.example.test/')
        expect(store.loadImpersonationStatus).not.toHaveBeenCalled()
    })

    it('keeps the main application and its impersonation status unchanged', async () => {
        const store = renderHomepage(false)

        expect(screen.queryByText('Schooltool Vorschau')).not.toBeInTheDocument()
        expect(screen.queryByRole('link', { name: 'Zur Hauptanwendung' })).not.toBeInTheDocument()
        await waitFor(() => expect(store.loadImpersonationStatus).toHaveBeenCalledOnce())
    })
})
