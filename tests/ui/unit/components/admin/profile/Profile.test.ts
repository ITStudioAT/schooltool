import { createTestingPinia } from '@pinia/testing'
import { fireEvent, render, screen, waitFor } from '@testing-library/vue'
import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import Profile from '@/pages/admin/profile/Profile.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'

const vuetifyStubs = {
    'v-container': { template: '<div><slot /></div>' },
    VContainer: { template: '<div><slot /></div>' },
    'v-sheet': { template: '<div><slot /></div>' },
    VSheet: { template: '<div><slot /></div>' },
    'v-btn': { template: '<button type="button" @click="$emit(\'click\')"><slot /></button>' },
    VBtn: { template: '<button type="button" @click="$emit(\'click\')"><slot /></button>' },
    'v-icon': { template: '<i><slot /></i>' },
    VIcon: { template: '<i><slot /></i>' },
    'v-row': { template: '<div><slot /></div>' },
    VRow: { template: '<div><slot /></div>' },
    'v-col': { template: '<div><slot /></div>' },
    VCol: { template: '<div><slot /></div>' },
    'v-card': { template: '<section><slot /></section>' },
    VCard: { template: '<section><slot /></section>' },
    'v-card-text': { template: '<div><slot /></div>' },
    VCardText: { template: '<div><slot /></div>' },
    'v-card-actions': { template: '<div><slot /></div>' },
    VCardActions: { template: '<div><slot /></div>' },
    'v-form': { template: '<form><slot /></form>' },
    VForm: { template: '<form><slot /></form>' },
    'v-text-field': { template: '<input />' },
    VTextField: { template: '<input />' },
    'v-switch': { template: '<input type="checkbox" />' },
    VSwitch: { template: '<input type="checkbox" />' },
    'v-alert': { template: '<div><slot /></div>' },
    VAlert: { template: '<div><slot /></div>' },
    'v-otp-input': { template: '<input />' },
    VOtpInput: { template: '<input />' },
}

describe('Admin profile page', () => {
    it('styles selected and non-selected profile menu labels differently', () => {
        const source = readFileSync(join(process.cwd(), 'resources/js/pages/admin/profile/Profile.vue'), 'utf8')

        expect(source).toContain('profile-nav__button--selected')
        expect(source).toContain('profile-nav__button--idle')
        expect(source).toContain('.profile-nav__button--selected .profile-nav__button-title {')
        expect(source).toContain('.profile-nav__button--idle .profile-nav__button-title {')
        expect(source).toContain('color: #10263a;')
    })

    it('shows the Hopper Schulen section when the new profile button is clicked', async () => {
        render(Profile, {
            props: {
                embedded: true,
            },
            global: {
                plugins: [
                    createTestingPinia({
                        stubActions: true,
                        initialState: {
                            AdminAdminStore: {
                                config: {
                                    user: {
                                        id: 1,
                                        last_name: 'Teacher',
                                        first_name: 'Anna',
                                        email: 'anna@example.com',
                                        use_school_color_for_admin_ui: true,
                                    },
                                },
                            },
                            AdminUserStore: {
                                item: {
                                    id: 1,
                                    last_name: 'Teacher',
                                    first_name: 'Anna',
                                    email: 'anna@example.com',
                                    is_2fa: false,
                                    use_school_color_for_admin_ui: true,
                                },
                                api_answer: null,
                            },
                        },
                    }),
                ],
                mocks: {
                    $router: {
                        replace: vi.fn(),
                    },
                },
                stubs: {
                    ...vuetifyStubs,
                    AdminSectionHero: { template: '<div>Admin Hero</div>' },
                    HopperSchools: { template: '<div>HopperSchools Component</div>' },
                },
            },
        })

        expect(screen.getAllByText('Profildaten').length).toBeGreaterThan(0)
        expect(screen.getByText('Kennwort')).toBeInTheDocument()
        expect(screen.getByText('2-Faktor-Auth')).toBeInTheDocument()
        expect(screen.getByText('Hopper Schulen')).toBeInTheDocument()

        await fireEvent.click(screen.getByText('Hopper Schulen'))

        await waitFor(() => {
            expect(screen.getByText('HopperSchools Component')).toBeInTheDocument()
        })
    })

    it('lets the logged-in user select the semantic primary color for their own admin view', async () => {
        render(Profile, {
            props: {
                embedded: true,
            },
            global: {
                plugins: [
                    createTestingPinia({
                        stubActions: true,
                        initialState: {
                            AdminAdminStore: {
                                config: {
                                    user: {
                                        id: 1,
                                        last_name: 'Teacher',
                                        first_name: 'Anna',
                                        email: 'anna@example.com',
                                        use_school_color_for_admin_ui: true,
                                    },
                                    selected_school: {
                                        color: '#F57C00',
                                    },
                                },
                            },
                            AdminUserStore: {
                                item: {
                                    id: 1,
                                    last_name: 'Teacher',
                                    first_name: 'Anna',
                                    email: 'anna@example.com',
                                    is_2fa: false,
                                    use_school_color_for_admin_ui: true,
                                },
                                api_answer: null,
                            },
                        },
                    }),
                ],
                mocks: {
                    $router: {
                        replace: vi.fn(),
                    },
                },
                stubs: {
                    ...vuetifyStubs,
                    AdminSectionHero: { template: '<div>Admin Hero</div>' },
                    HopperSchools: { template: '<div>HopperSchools Component</div>' },
                },
            },
        })

        const adminStore = useAdminStore()
        vi.mocked(adminStore.saveAdminShellColorPreference).mockImplementation(async (useSchoolColorForAdminUi) => {
            adminStore.config.user.use_school_color_for_admin_ui = useSchoolColorForAdminUi

            return true
        })

        await fireEvent.click(screen.getByText('Darstellung'))
        const schoolColorButton = (await screen.findByText('Schulfarbe verwenden')).closest('button')
        const primaryColorButton = (await screen.findByText('Standardfarbe (Primary) verwenden')).closest('button')

        expect(schoolColorButton).toHaveAttribute('color', '#F57C00')
        expect(schoolColorButton).toHaveAttribute('variant', 'flat')
        expect(primaryColorButton).toHaveAttribute('color', 'primary')
        expect(primaryColorButton).toHaveAttribute('variant', 'flat')

        await fireEvent.click(primaryColorButton as HTMLButtonElement)

        expect(adminStore.saveAdminShellColorPreference).toHaveBeenCalledWith(false)
        await waitFor(() => {
            expect(screen.getByText('Standard (Primary)')).toBeInTheDocument()
        })
    })
})
