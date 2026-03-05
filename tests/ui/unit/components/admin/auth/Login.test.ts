import { createTestingPinia } from '@pinia/testing'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import Login from '@/pages/admin/auth/Login.vue'

const VBtnStub = {
    props: ['disabled'],
    template: '<button type="button" :disabled="disabled" v-bind="$attrs"><slot /></button>',
}

const vuetifyStubs = {
    'v-icon': { template: '<i><slot /></i>' },
    VIcon: { template: '<i><slot /></i>' },
    'v-form': { template: '<form><slot /></form>' },
    VForm: { template: '<form><slot /></form>' },
    'v-text-field': { template: '<input />' },
    VTextField: { template: '<input />' },
    'v-autocomplete': { template: '<input />' },
    VAutocomplete: { template: '<input />' },
    'v-checkbox': { template: '<input type="checkbox" />' },
    VCheckbox: { template: '<input type="checkbox" />' },
    'v-alert': { template: '<div><slot /></div>' },
    VAlert: { template: '<div><slot /></div>' },
    'v-otp-input': { template: '<input />' },
    VOtpInput: { template: '<input />' },
    'v-btn': VBtnStub,
    VBtn: VBtnStub,
}

const warningText = '!Anmelden ohne Kennwort derzeit nicht möglich'

function mountLogin(queueWorking: boolean) {
    return mount(Login, {
        global: {
            plugins: [
                createTestingPinia({
                    stubActions: true,
                    initialState: {
                        AdminAdminStore: {
                            config: {
                                is_auth: true,
                                logo: null,
                                version: 'test',
                                register_admin_allowed: false,
                                health: {
                                    queue_working: queueWorking,
                                },
                            },
                            data: {},
                        },
                    },
                }),
            ],
            stubs: vuetifyStubs,
            mocks: {
                $router: { push: vi.fn() },
                $route: { path: '/admin/login' },
            },
        },
    })
}

describe('Admin login unknown password availability', () => {
    beforeEach(() => {
        globalThis.axios = {
            get: vi.fn().mockResolvedValue({}),
        } as never
    })

    it('shows warning and disables unknown password when queue is down', async () => {
        const wrapper = mountLogin(false)
        await flushPromises()

        expect(wrapper.get('[data-testid="admin-login-unknown-password"]').attributes('disabled')).toBeDefined()
        expect(wrapper.text()).toContain(warningText)
    })

    it('keeps unknown password enabled when queue is working', async () => {
        const wrapper = mountLogin(true)
        await flushPromises()

        expect(wrapper.get('[data-testid="admin-login-unknown-password"]').attributes('disabled')).toBeUndefined()
        expect(wrapper.text()).not.toContain(warningText)
    })
})
