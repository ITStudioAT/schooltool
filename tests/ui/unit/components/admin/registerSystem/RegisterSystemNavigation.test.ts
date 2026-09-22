import { createTestingPinia } from '@pinia/testing'
import { fireEvent, render, screen, waitFor } from '@testing-library/vue'
import { createMemoryHistory, createRouter, isNavigationFailure, NavigationFailureType } from 'vue-router'
import { describe, expect, it } from 'vitest'
import RegisterSystem from '@/pages/admin/registerSystem/RegisterSystem.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useUserStore } from '@/stores/admin/UserStore20'

async function renderPage(path = '/admin/register_system', stubUsers = true) {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/admin/register_system', component: RegisterSystem },
            { path: '/admin/settings', component: { template: '<div>Anmeldetool-Einstellungen</div>' } },
        ],
    })
    await router.push(path)
    await router.isReady()
    const pinia = createTestingPinia({
        initialState: {
            AdminAdminStore: {
                config: { is_auth: true, roles: ['register_admin'] },
                selected_schoolyear: { id: 1 },
            },
        },
    })

    render({ template: '<router-view />' }, {
        global: {
            plugins: [pinia, router],
            stubs: {
                VContainer: { template: '<div><slot /></div>' },
                VSheet: { template: '<div><slot /></div>' },
                VDivider: true,
                VBtn: { template: '<button type="button"><slot /></button>' },
                'v-btn': { template: '<button type="button"><slot /></button>' },
                AdminSectionHero: true,
                Schoolyears: { template: '<div>Schuljahrauswahl</div>' },
                ActiveRegisters: true,
                Registers: { template: '<div>Anmeldetool-Liste</div>' },
                RegisterUsers: stubUsers ? { template: '<div>Benutzerverwaltung</div>' } : false,
            },
        },
    })

    return { router, store: useAdminStore(pinia), userStore: useUserStore(pinia) }
}

describe('Register system user navigation', () => {
    it('opens users inside the register tool and supports browser back', async () => {
        const { router } = await renderPage()
        expect(screen.getByText('Anmeldetool-Liste')).toBeInTheDocument()
        expect(screen.queryByText('Benutzerverwaltung')).not.toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Benutzer', exact: true }))
        await waitFor(() => expect(screen.getByText('Benutzerverwaltung')).toBeInTheDocument())
        expect(router.currentRoute.value.path).toBe('/admin/register_system')
        expect(screen.queryByText('Schuljahrauswahl')).not.toBeInTheDocument()

        router.back()
        await waitFor(() => expect(screen.getByText('Anmeldetool-Liste')).toBeInTheDocument())
        expect(screen.queryByText('Benutzerverwaltung')).not.toBeInTheDocument()
    })

    it('opens a users deep link without mounting the register overview', async () => {
        await renderPage('/admin/register_system?panel=users')
        expect(screen.getByText('Benutzerverwaltung')).toBeInTheDocument()
        expect(screen.queryByText('Anmeldetool-Liste')).not.toBeInTheDocument()
        expect(screen.getByRole('button', { name: 'Benutzer', exact: true })).toHaveAttribute('aria-pressed', 'true')
    })

    it('returns to the overview and preserves unrelated query parameters', async () => {
        const { router } = await renderPage('/admin/register_system?panel=users&filter=active')

        await fireEvent.click(screen.getByRole('button', { name: 'Anmeldesysteme' }))
        await waitFor(() => expect(screen.getByText('Anmeldetool-Liste')).toBeInTheDocument())
        expect(router.currentRoute.value.query).toEqual({ filter: 'active' })

        await fireEvent.click(screen.getByRole('button', { name: 'Benutzer', exact: true }))
        await waitFor(() => expect(router.currentRoute.value.query).toEqual({ filter: 'active', panel: 'users' }))
    })

    it('uses the overview for an unknown panel', async () => {
        await renderPage('/admin/register_system?panel=unknown')
        expect(screen.getByText('Anmeldetool-Liste')).toBeInTheDocument()
        expect(screen.getByRole('button', { name: 'Anmeldesysteme' })).toHaveAttribute('aria-pressed', 'true')
    })

    it('loads the existing register user management and clears its selection when leaving', async () => {
        const { userStore } = await renderPage('/admin/register_system?panel=users', false)
        await waitFor(() => expect(userStore.index).toHaveBeenCalledOnce())
        expect(userStore.role).toBe('register_user')
        await waitFor(() => expect(screen.getByRole('heading', { name: 'Benutzer verwalten' })).toBeInTheDocument())
        userStore.selected_users = [42]

        await fireEvent.click(screen.getByRole('button', { name: 'Anmeldesysteme' }))
        await waitFor(() => expect(screen.getByText('Anmeldetool-Liste')).toBeInTheDocument())
        expect(userStore.role).toBe('')
        expect(userStore.selected_users).toEqual([])
    })

    it('preserves an open user dialog on browser back and allows navigation after closing it', async () => {
        const { router, store } = await renderPage()
        await router.push('/admin/register_system?panel=users')
        store.action = 'edit_user'

        const navigation = new Promise((resolve) => {
            const removeHook = router.afterEach((_to, _from, failure) => {
                removeHook()
                resolve(failure)
            })
        })
        router.back()
        expect(isNavigationFailure(await navigation, NavigationFailureType.aborted)).toBe(true)
        expect(router.currentRoute.value.query.panel).toBe('users')
        expect(screen.getByText('Benutzerverwaltung')).toBeInTheDocument()
        expect(store.action).toBe('edit_user')

        store.action = ''
        await router.push('/admin/register_system')
        expect(screen.getByText('Anmeldetool-Liste')).toBeInTheDocument()
    })

    it('prevents leaving the page while editing and keeps the settings shortcut working afterwards', async () => {
        const { router, store } = await renderPage()
        store.action = 'edit_register'
        const failure = await router.push('/admin/settings?tab=register')
        expect(isNavigationFailure(failure, NavigationFailureType.aborted)).toBe(true)
        expect(router.currentRoute.value.path).toBe('/admin/register_system')

        store.action = ''
        await waitFor(() => expect(screen.getByTitle('Anmeldetool-Einstellungen')).toBeEnabled())
        await fireEvent.click(screen.getByTitle('Anmeldetool-Einstellungen'))
        await waitFor(() => expect(router.currentRoute.value.fullPath).toBe('/admin/settings?tab=register'))
    })

    it('keeps navigation disabled while an edit dialog is open', async () => {
        const { store } = await renderPage()
        store.action = 'edit_register'
        await waitFor(() => expect(screen.getByRole('button', { name: 'Benutzer', exact: true })).toBeDisabled())
        expect(screen.getByRole('button', { name: 'Anmeldesysteme' })).toBeDisabled()
        expect(screen.getByTitle('Anmeldetool-Einstellungen')).toBeDisabled()
    })
})
