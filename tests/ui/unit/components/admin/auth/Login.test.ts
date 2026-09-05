import { createTestingPinia } from '@pinia/testing'
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
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
    'v-progress-circular': { template: '<div />' },
    VProgressCircular: { template: '<div />' },
    'v-btn-toggle': { template: '<div><slot /></div>' },
    VBtnToggle: { template: '<div><slot /></div>' },
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
                $route: { path: '/admin/login', query: {} },
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
        expect(wrapper.find('[data-testid="admin-login-continue-password"]').exists()).toBe(true)
        expect(wrapper.find('[data-testid="admin-login-new-teacher"]').exists()).toBe(false)
        expect(wrapper.find('.alt-sep').exists()).toBe(false)
        expect(wrapper.text()).not.toContain(warningText)
    })
})

describe('Admin two-factor challenge', () => {
    const methods = (Login as any).methods

    beforeEach(() => {
        vi.spyOn(window.location, 'replace').mockImplementation(() => {})
    })

    afterEach(() => vi.restoreAllMocks())

    it('sends only a normalized authenticator code and immediately opens admin', async () => {
        const push = vi.fn()
        const loginTwoFactorChallenge = vi.fn().mockResolvedValue({
            step: 'LOGIN_SUCCESS',
            auth: true,
            redirect_url: '/admin/teaching?panel=entries',
        })
        const context: any = {
            twoFactorMode: 'code',
            twoFactorCode: ' 123 456 ',
            recoveryCode: 'must-not-be-sent',
            step: 'LOGIN_ENTER_TWO_FACTOR',
            adminStore: {
                loginTwoFactorChallenge,
                loadConfig: vi.fn().mockResolvedValue(true),
            },
            $router: { push },
        }

        await methods.submitTwoFactorChallenge.call(context)

        expect(loginTwoFactorChallenge).toHaveBeenCalledWith({ code: '123456' })
        expect(push).not.toHaveBeenCalled()
        expect(context.adminStore.loadConfig).not.toHaveBeenCalled()
        expect(window.location.replace).toHaveBeenCalledWith('/admin')
        expect(context.twoFactorCode).toBe('')
        expect(context.recoveryCode).toBe('')
    })

    it('sends a recovery code without resubmitting credentials', async () => {
        const loginTwoFactorChallenge = vi.fn().mockResolvedValue(false)
        const context: any = {
            twoFactorMode: 'recovery',
            twoFactorCode: '123456',
            recoveryCode: ' recovery-code ',
            step: 'LOGIN_ENTER_TWO_FACTOR',
            adminStore: { loginTwoFactorChallenge },
            $router: { push: vi.fn() },
        }

        await methods.submitTwoFactorChallenge.call(context)

        expect(loginTwoFactorChallenge).toHaveBeenCalledWith({ recovery_code: 'recovery-code' })
        expect(context.step).toBe('LOGIN_ENTER_TWO_FACTOR')
        expect(window.location.replace).not.toHaveBeenCalled()
    })

    it.each([true, false])('opens admin after the email factor only when successful: %s', async success => {
        const context: any = {
            data: { token_2fa: '123456', remember: true },
            step: 'LOGIN_ENTER_TOKEN',
            adminStore: {
                loginStep3: vi.fn().mockImplementation(async () => {
                    context.data.step = success ? 'LOGIN_SUCCESS' : 'LOGIN_ENTER_TOKEN'
                    return success
                }),
                loadConfig: vi.fn(() => new Promise(() => {})),
            },
            $router: { push: vi.fn() },
        }

        await methods.loginStep3.call(context)

        expect(context.adminStore.loadConfig).not.toHaveBeenCalled()
        expect(context.$router.push).not.toHaveBeenCalled()
        if (success) {
            expect(window.location.replace).toHaveBeenCalledWith('/admin')
        } else {
            expect(window.location.replace).not.toHaveBeenCalled()
            expect(context.step).toBe('LOGIN_ENTER_TOKEN')
        }
    })
})
