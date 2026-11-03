import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import axios from 'axios'
import NumberSystems from '@/pages/homepage/student/numberSystems/NumberSystems.vue'
import StudentPage from '@/pages/homepage/student/Student.vue'
import { numberSystemLoginReturn } from '@/pages/components/numberSystems/numberSystemLoginReturn'

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }))
vi.mock('@/stores/student/StudentStore', () => ({ useStudentStore: () => ({ user: { id: 42 } }) }))
vi.mock('@/pages/homepage/student/components/StudentNavigationDrawer.vue', () => ({ default: { template: '<div />' } }))
vi.mock('@/pages/components/numberSystems/NumberSystemPlayer.vue', () => ({ default: { props: ['runId'], template: '<div data-test="player">Spielstand {{ runId }}</div>' } }))
const wrappers = []

it('opens external invitations without login and sends the chosen display name on deliberate join', async () => {
    vi.mocked(axios.get).mockResolvedValue({ data: { guest: true, runs: [] } })
    vi.mocked(axios.post).mockImplementation(async url => ({ data: String(url).endsWith('/lookup')
        ? { session: { title: 'Externe Runde', status: 'waiting', mode: 'solo', guest: true } }
        : { run: { id: 23 } } }))
    const { wrapper, router } = await render('/student/games/number-systems?code=ABCDEF1234')
    expect(router.currentRoute.value.path).toBe('/student/games/number-systems')
    expect(wrapper.text()).toContain('Dein Anzeigename')
    expect(axios.post).toHaveBeenCalledTimes(1)
    await wrapper.get('input[autocomplete="nickname"]').setValue('Besucherin')
    await wrapper.get('form').trigger('submit'); await flushPromises()
    expect(vi.mocked(axios.post).mock.lastCall[1]).toMatchObject({ display_name: 'Besucherin', code: 'ABCDEF1234' })
    expect(router.currentRoute.value.path).toBe('/student/games/number-systems/23')
})
beforeEach(() => { vi.resetAllMocks(); vi.mocked(axios.get).mockResolvedValue({ data: { runs: [{ id: 17, title: 'Vorhandenes Abenteuer', total: 12, solved: 5, completed: false, status: 'running' }] } }) })
afterEach(() => wrappers.splice(0).forEach(wrapper => wrapper.unmount()))
async function render(path = '/student/games/number-systems') {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/student/games/number-systems/:run?', component: NumberSystems }, { path: '/student/overview', component: { template: '<div />' } }, { path: '/student', component: { template: '<div />' } }] })
    await router.push(path); await router.isReady()
    const wrapper = mount(NumberSystems, { global: { plugins: [router] } }); wrappers.push(wrapper)
    await flushPromises(); return { wrapper, router }
}
it('continues an existing adventure without creating a new run', async () => {
    const { wrapper, router } = await render()
    await wrapper.findAll('button').find(button => button.text() === 'Fortsetzen').trigger('click'); await flushPromises()
    expect(router.currentRoute.value.path).toBe('/student/games/number-systems/17')
    expect(wrapper.get('[data-test="player"]').text()).toBe('Spielstand 17')
    expect(axios.post).not.toHaveBeenCalled()
})

it('prefills the private team from a scanned link without joining automatically', async () => {
    vi.mocked(axios.post).mockImplementation(async url => ({ data: String(url).endsWith('/lookup') ? { session: { title: 'Teamrunde', status: 'waiting', mode: 'teams' } } : { run: { id: 23 } } }))
    const { wrapper } = await render('/student/games/number-systems?code=ABCDEF1234&team_code=1234ABCD')
    expect(wrapper.get('input[value="existing"]').element.checked).toBe(true)
    expect(wrapper.get('input[maxlength="12"]').element.value).toBe('1234ABCD')
    expect(axios.post).toHaveBeenCalledTimes(1)
    await wrapper.get('form').trigger('submit'); await flushPromises()
    expect(vi.mocked(axios.post).mock.calls[1][1]).toEqual({ code: 'ABCDEF1234', team_name: null, team_code: '1234ABCD' })
})

it('keeps both invitation codes in the login route when authentication is required', async () => {
    const unauthorized = { response: { status: 401, data: {} } }
    vi.mocked(axios.get).mockRejectedValue(unauthorized)
    vi.mocked(axios.post).mockRejectedValue(unauthorized)
    const { router } = await render('/student/games/number-systems?code=ABCDEF1234&team_code=1234ABCD')
    expect(router.currentRoute.value.path).toBe('/student')
    expect(router.currentRoute.value.query).toEqual({ number_systems_code: 'ABCDEF1234', number_systems_team: '1234ABCD' })
    expect(numberSystemLoginReturn(router.currentRoute.value.query)).toBe('/student/games/number-systems?code=ABCDEF1234&team_code=1234ABCD')
})

it('returns a successful password or code login to the invitation and rejects arbitrary return targets', async () => {
    const query = { number_systems_code: 'abcdef1234', number_systems_team: '1234abcd' }
    for (const method of ['submitPassword', 'submitCode']) {
        const push = vi.fn()
        const context = { $route: { query }, $router: { push }, isCodeLoginAvailable: true, canSubmitCode: true,
            data: { login_code: '123456' }, validatePasswordForm: async () => true,
            studentStore: { data: { status: 'login_ok' }, loginStepPassword: async () => true, loginStepCode: async () => true } }
        await StudentPage.methods[method].call(context)
        expect(push).toHaveBeenCalledWith('/student/games/number-systems?code=ABCDEF1234&team_code=1234ABCD')
    }
    expect(numberSystemLoginReturn({ number_systems_code: 'https://other.example', return: '//other.example' })).toBe('/student/overview')
    expect(numberSystemLoginReturn({ number_systems_code: ['ABCDEF1234'] })).toBe('/student/overview')
    expect(numberSystemLoginReturn({ number_systems_code: 'ABCDEF1234', number_systems_team: '//evil' })).toBe('/student/games/number-systems?code=ABCDEF1234')
})

it('replaces an earlier invitation and clears the old private code for a class-only QR', async () => {
    vi.mocked(axios.post).mockImplementation(async () => ({ data: { session: { title: 'Teamrunde', status: 'waiting', mode: 'teams' } } }))
    const { wrapper, router } = await render('/student/games/number-systems?code=ABCDEF1234&team_code=1234ABCD')
    await router.push('/student/games/number-systems?code=FEDCBA4321&team_code=8765DCBA'); await flushPromises()
    expect(wrapper.get('input[maxlength="12"]').element.value).toBe('8765DCBA')
    expect(vi.mocked(axios.post).mock.lastCall[1]).toEqual({ code: 'FEDCBA4321' })
    await router.push('/student/games/number-systems?code=ABCDEF1234'); await flushPromises()
    expect(wrapper.get('input[value="new"]').element.checked).toBe(true)
    expect(wrapper.find('input[maxlength="12"]').exists()).toBe(false)
    await wrapper.get('input[value="existing"]').setValue(true)
    expect(wrapper.get('input[maxlength="12"]').element.value).toBe('')
})

it('ignores a slow lookup from the previous scanned invitation', async () => {
    let resolveOld
    vi.mocked(axios.post).mockImplementationOnce(() => new Promise(resolve => { resolveOld = resolve }))
        .mockResolvedValue({ data: { session: { title: 'Neue Runde', status: 'running', mode: 'solo' } } })
    const { wrapper, router } = await render('/student/games/number-systems?code=ABCDEF1234')
    await router.push('/student/games/number-systems?code=FEDCBA4321'); await flushPromises()
    resolveOld({ data: { session: { title: 'Veraltete Runde', status: 'waiting', mode: 'teams' } } }); await flushPromises()
    expect(wrapper.text()).toContain('Neue Runde')
    expect(wrapper.text()).not.toContain('Veraltete Runde')
})

it('does not erase the invitation when a second unauthorized request arrives after login navigation', async () => {
    let rejectLateIndex
    vi.mocked(axios.get).mockImplementationOnce(() => new Promise((_, reject) => { rejectLateIndex = reject }))
    vi.mocked(axios.post).mockRejectedValue({ response: { status: 401, data: {} } })
    const { router } = await render('/student/games/number-systems?code=ABCDEF1234&team_code=1234ABCD')
    expect(router.currentRoute.value.path).toBe('/student')
    rejectLateIndex({ response: { status: 401, data: {} } }); await flushPromises()
    expect(router.currentRoute.value.query).toEqual({ number_systems_code: 'ABCDEF1234', number_systems_team: '1234ABCD' })
})
it('looks up a linked class without joining until the student explicitly chooses a private team', async () => {
    vi.mocked(axios.post).mockImplementation(async url => ({ data: String(url).endsWith('/lookup') ? { session: { title: 'Teamrunde', status: 'waiting', mode: 'teams' } } : { run: { id: 23 } } }))
    const { wrapper, router } = await render('/student/games/number-systems?code=ABC123')
    expect(vi.mocked(axios.post).mock.calls).toHaveLength(1)
    expect(vi.mocked(axios.post).mock.calls[0][0]).toMatch(/\/lookup$/)
    await wrapper.get('input[value="existing"]').setValue(true)
    await wrapper.get('input[maxlength="12"]').setValue('PRIVATE1')
    await wrapper.get('form').trigger('submit'); await flushPromises()
    expect(vi.mocked(axios.post).mock.calls[1][1]).toEqual({ code: 'ABC123', team_name: null, team_code: 'PRIVATE1' })
    expect(router.currentRoute.value.path).toBe('/student/games/number-systems/23')
})
