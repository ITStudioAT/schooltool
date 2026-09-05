import { createTestingPinia } from '@pinia/testing'
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import UnknownPassword from '@/pages/admin/auth/UnknownPassword.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'

const slotStub = { template: '<div><slot /></div>' }
const stubs = {
    VContainer: slotStub,
    VCard: slotStub,
    VCardSubtitle: slotStub,
    VCardTitle: slotStub,
    VCardText: slotStub,
    VForm: slotStub,
    VAlert: slotStub,
    VBtnToggle: slotStub,
    VBtn: { template: '<button><slot /></button>' },
    VImg: { template: '<img />' },
    VTextField: { template: '<input />' },
    VAutocomplete: { template: '<input />' },
    VOtpInput: { template: '<input />' },
}

async function mountUnknownPassword() {
    const router = { push: vi.fn() }
    const wrapper = mount(UnknownPassword, {
        global: {
            plugins: [createTestingPinia({
                initialState: {
                    AdminAdminStore: {
                        config: { logo: null, version: 'test', register_admin_allowed: false },
                        data: {},
                    },
                },
            })],
            stubs,
            mocks: { $router: router },
        },
    })
    await flushPromises()
    const store = useAdminStore()
    vi.mocked(store.loadConfig).mockClear()
    store.data = { step: 'PASSWORD_UNKNOWN_ENTER_TOKEN', token_2fa: '123456' }
    await wrapper.setData({ step: 'PASSWORD_UNKNOWN_ENTER_TOKEN' })
    return { wrapper, store, router }
}

describe('Admin login through unknown password', () => {
    beforeEach(() => {
        globalThis.axios = { get: vi.fn().mockResolvedValue({}) } as never
        vi.spyOn(window.location, 'replace').mockImplementation(() => {})
    })

    afterEach(() => vi.restoreAllMocks())

    it.each(['primary', 'secondary'])('opens admin after the %s code without requesting a new password', async factor => {
        const { wrapper, store, router } = await mountUnknownPassword()
        const action = factor === 'primary' ? store.passwordUnknownStepToken : store.passwordUnknownStepToken2
        vi.mocked(action).mockImplementation(async () => {
            store.data = { step: 'LOGIN_SUCCESS', auth: true, redirect_url: '/admin' }
            return true
        })
        store.data.token_2fa_2 = '654321'
        vi.mocked(store.loadConfig).mockImplementation(() => new Promise(() => {}))

        const vm = wrapper.vm as any
        await (factor === 'primary' ? vm.passwordUnknownStepToken() : vm.passwordUnknownStepToken2())

        expect(store.loadConfig).not.toHaveBeenCalled()
        expect(router.push).not.toHaveBeenCalled()
        expect(window.location.replace).toHaveBeenCalledWith('/admin')
        expect(wrapper.text()).not.toContain('neue Kennwort')
        expect(store.passwordUnknownStepPassword).not.toHaveBeenCalled()
    })

    it('keeps the code form after a rejected code', async () => {
        const { wrapper, store, router } = await mountUnknownPassword()
        vi.mocked(store.passwordUnknownStepToken).mockResolvedValue(false)
        await (wrapper.vm as any).passwordUnknownStepToken()
        expect((wrapper.vm as any).step).toBe('PASSWORD_UNKNOWN_ENTER_TOKEN')
        expect(window.location.replace).not.toHaveBeenCalled()
        expect(store.loadConfig).not.toHaveBeenCalled()
        expect(router.push).not.toHaveBeenCalled()
    })

    it.each(['PASSWORD_UNKNOWN_ENTER_TOKEN_2', 'LOGIN_ENTER_TWO_FACTOR'])('preserves the required %s challenge', async step => {
        const { wrapper, store, router } = await mountUnknownPassword()
        vi.mocked(store.passwordUnknownStepToken).mockImplementation(async () => {
            store.data = { step }
            return true
        })
        await (wrapper.vm as any).passwordUnknownStepToken()
        expect((wrapper.vm as any).step).toBe(step)
        expect(window.location.replace).not.toHaveBeenCalled()
        expect(store.loadConfig).not.toHaveBeenCalled()
        expect(router.push).not.toHaveBeenCalled()
        if (step === 'LOGIN_ENTER_TWO_FACTOR') {
            expect(wrapper.text()).toContain('Authenticator-Code')
        }
    })

    it('does not submit an incomplete secondary code', async () => {
        const { wrapper, store } = await mountUnknownPassword()
        store.data.token_2fa_2 = '123'
        await (wrapper.vm as any).passwordUnknownStepToken2()
        expect(store.passwordUnknownStepToken2).not.toHaveBeenCalled()
    })

    it.each(['code', 'recovery'])('completes the %s challenge using only that factor', async mode => {
        const { wrapper, store, router } = await mountUnknownPassword()
        await wrapper.setData({ twoFactorMode: mode, twoFactorCode: ' 123 456 ', recoveryCode: ' recovery-code ' })
        vi.mocked(store.loginTwoFactorChallenge).mockResolvedValue({ redirect_url: '/admin/teaching' })

        await (wrapper.vm as any).submitTwoFactorChallenge()

        expect(store.loginTwoFactorChallenge).toHaveBeenCalledWith(mode === 'code'
            ? { code: '123456' }
            : { recovery_code: 'recovery-code' })
        expect(store.loadConfig).not.toHaveBeenCalled()
        expect(router.push).not.toHaveBeenCalled()
        expect(window.location.replace).toHaveBeenCalledWith('/admin')
        expect((wrapper.vm as any).twoFactorCode).toBe('')
        expect((wrapper.vm as any).recoveryCode).toBe('')
    })

    it('keeps the authenticator form after a rejected factor', async () => {
        const { wrapper, store, router } = await mountUnknownPassword()
        await wrapper.setData({ twoFactorCode: '123456' })
        vi.mocked(store.loginTwoFactorChallenge).mockResolvedValue(false)
        await (wrapper.vm as any).submitTwoFactorChallenge()
        expect((wrapper.vm as any).step).toBe('LOGIN_ENTER_TWO_FACTOR')
        expect(window.location.replace).not.toHaveBeenCalled()
        expect(router.push).not.toHaveBeenCalled()
    })
})
