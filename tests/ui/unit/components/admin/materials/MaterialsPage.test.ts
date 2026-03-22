import { beforeEach, describe, expect, it, vi } from 'vitest'
import Materials from '@/pages/admin/materials/Materials.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useMaterialCardStore } from '@/stores/admin/materials/MaterialCardStore'
import { shallowMount } from '@vue/test-utils'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/admin/materials/MaterialCardStore', () => ({
    useMaterialCardStore: vi.fn(),
}))

describe('Materials page navigation', () => {
    beforeEach(() => {
        vi.mocked(useAdminStore).mockReset()
        vi.mocked(useMaterialCardStore).mockReset()
    })

    it('accepts only enabled main actions from route query', () => {
        const method = (Materials as any).methods.applyRouteSelection

        const ctxAllowed: any = {
            $route: { path: '/admin/materials', query: { main_action: 'shared' } },
            main_action: 'overview',
            syncRouteMainAction: vi.fn(),
        }
        method.call(ctxAllowed)
        expect(ctxAllowed.main_action).toBe('shared')
        expect(ctxAllowed.syncRouteMainAction).not.toHaveBeenCalled()

        const ctxLegacyAllowed: any = {
            $route: { path: '/admin/materials', query: { main_action: 'teilen' } },
            main_action: 'overview',
            syncRouteMainAction: vi.fn(),
        }
        method.call(ctxLegacyAllowed)
        expect(ctxLegacyAllowed.main_action).toBe('shared')
        expect(ctxLegacyAllowed.syncRouteMainAction).toHaveBeenCalledWith('shared')

        const ctxBlocked: any = {
            $route: { path: '/admin/materials', query: { main_action: 'shares' } },
            main_action: 'overview',
            syncRouteMainAction: vi.fn(),
        }
        method.call(ctxBlocked)
        expect(ctxBlocked.main_action).toBe('overview')
        expect(ctxBlocked.syncRouteMainAction).not.toHaveBeenCalled()

        const ctxSubjectsBlocked: any = {
            $route: { path: '/admin/materials', query: { main_action: 'subjects' } },
            main_action: 'overview',
            syncRouteMainAction: vi.fn(),
        }
        method.call(ctxSubjectsBlocked)
        expect(ctxSubjectsBlocked.main_action).toBe('overview')
        expect(ctxSubjectsBlocked.syncRouteMainAction).not.toHaveBeenCalled()
    })

    it('initializes stores and applies route selection in beforeMount', async () => {
        const adminStoreMock = { is_navigation_locked: false }
        const materialCardStoreMock = {
            config: {
                workspace: {
                    id: 7,
                    name: 'Workspace',
                },
            },
            loadConfig: vi.fn(async () => true),
        }
        vi.mocked(useAdminStore).mockReturnValue(adminStoreMock as never)
        vi.mocked(useMaterialCardStore).mockReturnValue(materialCardStoreMock as never)

        const ctx: any = {
            $route: { path: '/admin/materials', query: { main_action: 'inbox' } },
            main_action: 'overview',
            isMenuLocked: false,
            isWorkspaceCheckLoading: true,
            applyRouteSelection() {
                return (Materials as any).methods.applyRouteSelection.call(this)
            },
            setMenuLocked(value: boolean) {
                return (Materials as any).methods.setMenuLocked.call(this, value)
            },
        }

        await (Materials as any).beforeMount.call(ctx)

        expect(ctx.adminStore).toBe(adminStoreMock)
        expect(ctx.materialCardStore).toBe(materialCardStoreMock)
        expect(ctx.main_action).toBe('overview')
        expect(ctx.isMenuLocked).toBe(false)
        expect(ctx.isWorkspaceCheckLoading).toBe(false)
        expect(materialCardStoreMock.loadConfig).toHaveBeenCalledTimes(1)
    })

    it('syncs main action to route query so refresh keeps selected tab', async () => {
        const method = (Materials as any).methods.syncRouteMainAction
        const replace = vi.fn(async () => undefined)

        const ctx: any = {
            $route: {
                path: '/admin/materials',
                query: {
                    settings_action: 'overview_settings',
                },
            },
            $router: {
                replace,
            },
        }

        method.call(ctx, 'shared')

        expect(replace).toHaveBeenCalledWith({
            path: '/admin/materials',
            query: {
                settings_action: 'overview_settings',
                main_action: 'shared',
            },
        })
    })

    it('does not sync route query when selected main action is already active', () => {
        const method = (Materials as any).methods.syncRouteMainAction
        const replace = vi.fn(async () => undefined)

        const ctx: any = {
            $route: {
                path: '/admin/materials',
                query: {
                    main_action: 'overview',
                },
            },
            $router: {
                replace,
            },
        }

        method.call(ctx, 'overview')

        expect(replace).not.toHaveBeenCalled()
    })

    it('does not render the workspace rename button when a workspace exists', async () => {
        vi.mocked(useAdminStore).mockReturnValue({
            is_navigation_locked: false,
            is_struktur_modus: false,
        } as never)
        vi.mocked(useMaterialCardStore).mockReturnValue({
            config: {
                workspace: {
                    id: 7,
                    name: 'Mein Workspace',
                },
            },
            loadConfig: vi.fn(async () => true),
            createWorkspace: vi.fn(),
            renameWorkspace: vi.fn(),
        } as never)

        const wrapper = shallowMount(Materials, {
            global: {
                mocks: {
                    $route: {
                        path: '/admin/materials',
                        query: {
                            main_action: 'overview',
                        },
                    },
                    $router: {
                        replace: vi.fn(async () => undefined),
                    },
                },
                stubs: {
                    'v-container': { template: '<div><slot /></div>' },
                    'v-row': { template: '<div><slot /></div>' },
                    'v-col': { template: '<div><slot /></div>' },
                    'v-card': { template: '<div><slot /></div>' },
                    'v-btn': { template: '<button><slot /></button>' },
                    'v-dialog': { template: '<div><slot /></div>' },
                    'v-card-title': { template: '<div><slot /></div>' },
                    'v-card-text': { template: '<div><slot /></div>' },
                    'v-card-actions': { template: '<div><slot /></div>' },
                    'v-text-field': { template: '<input />' },
                    'v-spacer': { template: '<div />' },
                    'v-skeleton-loader': { template: '<div />' },
                    MaterialsMenu: { template: '<div />' },
                    MaterialsOverviewView: { template: '<div />' },
                    MaterialsFreigabeView: { template: '<div />' },
                    MaterialsNewView: { template: '<div />' },
                    MaterialsSettingsView: { template: '<div />' },
                },
            },
        })

        await wrapper.vm.$nextTick()

        expect(wrapper.text()).toContain('Workspace: Mein Workspace')
        expect(wrapper.text()).not.toContain('Workspace umbenennen')
    })
})
