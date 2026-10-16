import { afterEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import axios from 'axios'
import { screen, waitFor } from '@testing-library/vue'
import { mount } from '@vue/test-utils'
import { impersonationTransition, startImpersonationTransition } from '@/helpers/impersonationTransition'
import ImpersonationTransitionOverlay from '@/pages/components/ImpersonationTransitionOverlay.vue'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

const disposers: (() => void)[] = []
const wrappers: ReturnType<typeof mount>[] = []

afterEach(() => {
    disposers.splice(0).forEach(stop => stop())
    wrappers.splice(0).forEach(wrapper => wrapper.unmount())
    document.body.innerHTML = ''
    vi.restoreAllMocks()
})

function pendingClient() {
    let succeed: () => void
    let fail: (error: any) => void
    const adapter = vi.fn(config => new Promise((resolve, reject) => {
        succeed = () => resolve({ config, data: {}, status: 200, statusText: 'OK', headers: {} })
        fail = error => reject(Object.assign(error, { config }))
    }))
    const client = axios.create({ baseURL: window.location.origin, adapter: adapter as any })
    const observer = startImpersonationTransition({ axiosClient: client })
    disposers.push(() => observer.stop())
    return { client, adapter, observer, succeed: () => succeed!(), fail: (error = new Error('Request failed')) => fail!(error) }
}

function renderOverlay() {
    const wrapper = mount(ImpersonationTransitionOverlay, {
        attachTo: document.body,
        global: {
            stubs: {
                'v-dialog': {
                    props: ['modelValue'],
                    template: '<div v-if="modelValue" role="dialog" aria-modal="true"><slot /></div>',
                },
                'v-card': { template: '<div><slot /></div>' },
            },
        },
    })
    wrappers.push(wrapper)
    return wrapper
}

describe('Impersonation transition feedback', () => {
    it.each([
        ['/api/admin/impersonation/start', 'Benutzer-Übernahme wird gestartet …'],
        ['/api/admin/impersonation/stop', 'Sie wechseln zurück …'],
        ['/api/admin/students-timetables/robot/students/impersonate', 'Benutzer-Übernahme wird gestartet …'],
        ['/api/admin/hopper_accounts/switch', 'Konto wird gewechselt …'],
        ['/api/admin/schools/switch_school', 'Konto wird gewechselt …'],
    ])('shows a modal status throughout %s and keeps it visible until navigation', async (url, message) => {
        const pending = pendingClient()
        renderOverlay()
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
        const request = pending.client.post(url)
        await waitFor(() => expect(pending.adapter).toHaveBeenCalledOnce())
        expect(await screen.findByRole('dialog')).toHaveTextContent(message)
        expect(screen.getByRole('dialog')).toHaveAttribute('aria-modal', 'true')
        pending.succeed()
        await request
        expect(screen.getByRole('dialog')).toHaveTextContent(message)
    })

    it.each(['/api/admin/impersonation/start', '/api/admin/impersonation/stop'])(
        'restores the screen when %s fails', async url => {
            const pending = pendingClient()
            renderOverlay()
            const request = pending.client.post(url)
            const failure = request.catch(error => error)
            await waitFor(() => expect(pending.adapter).toHaveBeenCalledOnce())
            expect(await screen.findByRole('dialog')).toBeInTheDocument()
            pending.fail()
            expect(await failure).toHaveProperty('message', 'Request failed')
            await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
        },
    )

    it.each([
        ['get', '/api/admin/impersonation/start'],
        ['post', '/api/admin/config'],
        ['post', 'https://external.example/api/admin/impersonation/start'],
    ])('keeps ordinary and external requests unobstructed: %s %s', async (method, url) => {
        const pending = pendingClient()
        const request = pending.client.request({ method, url })
        await waitFor(() => expect(pending.adapter).toHaveBeenCalledOnce())
        expect(impersonationTransition.value).toBe('')
        pending.succeed()
        await request
    })

    it('keeps the status visible while a failed CSRF request is retried successfully', async () => {
        const adapter = vi.fn()
            .mockImplementationOnce(config => Promise.reject({ config, response: { status: 419 } }))
            .mockImplementationOnce(config => Promise.resolve({ config, data: {}, status: 200, headers: {} }))
        const client = axios.create({ baseURL: window.location.origin, adapter })
        client.interceptors.response.use(response => response, error => client.request(error.config))
        const observer = startImpersonationTransition({ axiosClient: client })
        disposers.push(() => observer.stop())

        await client.post('/api/admin/impersonation/start')

        expect(adapter).toHaveBeenCalledTimes(2)
        expect(impersonationTransition.value).toBe('start')
    })

    it('reloads a completed transition instead of exposing cached account stores on browser back', async () => {
        const reload = vi.spyOn(window.location, 'reload').mockImplementation(() => {})
        const pending = pendingClient()
        const request = pending.client.post('/api/admin/impersonation/stop')
        await waitFor(() => expect(pending.adapter).toHaveBeenCalledOnce())
        pending.succeed()
        await request
        const restored = new Event('pageshow')
        Object.defineProperty(restored, 'persisted', { value: true })
        window.dispatchEvent(restored)
        expect(reload).toHaveBeenCalledOnce()
        expect(impersonationTransition.value).toBe('stop')
    })
})

function contextClient() {
    const requests: any[] = []
    const adapter = vi.fn(config => new Promise((resolve, reject) => {
        requests.push({
            config,
            succeed: (data = {}) => resolve({ config, data, status: 200, statusText: 'OK', headers: {} }),
            fail: (status = 401) => reject({ config, response: { status, data: { message: 'Unauthenticated' } } }),
        })
    }))
    const client = axios.create({ baseURL: window.location.origin, adapter })
    const observer = startImpersonationTransition({ axiosClient: client })
    disposers.push(() => observer.stop())
    setActivePinia(createPinia())
    const notification = useNotificationStore()
    const notifyError = (error: any) => notification.notify({ status: error.response.status, message: error.response.data.message, type: 'error' })
    return { client, requests, adapter, notification, notifyError }
}

describe('account context request isolation', () => {
    it.each(['/api/admin/hopper_accounts/switch', '/api/admin/schools/switch_school', '/api/admin/impersonation/start', '/api/admin/impersonation/stop'])(
        'ignores an old unauthorized request during %s while keeping the successful switch', async path => {
            const state = contextClient()
            const old = state.client.get('/api/admin/teaching/courses').catch(state.notifyError)
            await waitFor(() => expect(state.requests).toHaveLength(1))
            const change = state.client.post(path)
            await waitFor(() => expect(state.requests).toHaveLength(2))
            state.requests[0].fail(401)
            await old
            expect(state.notification.show).toBe(false)
            state.requests[1].succeed()
            await change
        },
    )

    it('never applies a late successful response from the old account', async () => {
        const state = contextClient()
        const applyCourses = vi.fn()
        const old = state.client.get('/api/admin/teaching/courses').then(applyCourses).catch(state.notifyError)
        await waitFor(() => expect(state.requests).toHaveLength(1))
        const change = state.client.post('/api/admin/hopper_accounts/switch')
        await waitFor(() => expect(state.requests).toHaveLength(2))
        state.requests[1].succeed()
        await change
        state.requests[0].succeed({ courses: [{ id: 16 }] })
        await old
        expect(applyCourses).not.toHaveBeenCalled()
        expect(state.notification.show).toBe(false)
    })

    it('does not send old document business requests after a successful switch', async () => {
        const state = contextClient()
        const change = state.client.post('/api/admin/hopper_accounts/switch')
        await waitFor(() => expect(state.requests).toHaveLength(1))
        state.requests[0].succeed()
        await change
        await state.client.get('/api/admin/teaching/course_works?course_id=16').catch(state.notifyError)
        expect(state.adapter).toHaveBeenCalledOnce()
        expect(state.notification.show).toBe(false)
    })

    it('reports a real 401 from the current navigation config instead of hiding an expired session', async () => {
        const state = contextClient()
        const change = state.client.post('/api/admin/hopper_accounts/switch')
        await waitFor(() => expect(state.requests).toHaveLength(1))
        state.requests[0].succeed()
        await change
        const config = state.client.get('/api/admin/config', { __authContextNavigation: true } as any).catch(state.notifyError)
        await waitFor(() => expect(state.requests).toHaveLength(2))
        state.requests[1].fail(401)
        await config
        expect(state.notification.status).toBe(401)
        expect(state.notification.message).toBe('Unauthenticated')
        expect(state.notification.show).toBe(true)
    })

    it('reports a refused switch and still reports current errors after the failed transition', async () => {
        const state = contextClient()
        const change = state.client.post('/api/admin/hopper_accounts/switch').catch(state.notifyError)
        await waitFor(() => expect(state.requests).toHaveLength(1))
        state.requests[0].fail(403)
        await change
        expect(state.notification.status).toBe(403)
        expect(impersonationTransition.value).toBe('')
        state.notification.close()
        const current = state.client.get('/api/admin/teaching/courses').catch(state.notifyError)
        await waitFor(() => expect(state.requests).toHaveLength(2))
        state.requests[1].fail(401)
        await current
        expect(state.notification.status).toBe(401)
        expect(state.notification.show).toBe(true)
    })

    it('reports an ordinary 401 when there is no account switch', async () => {
        const state = contextClient()
        const current = state.client.get('/api/admin/config').catch(state.notifyError)
        await waitFor(() => expect(state.requests).toHaveLength(1))
        state.requests[0].fail(401)
        await current
        expect(state.notification.status).toBe(401)
        expect(state.notification.show).toBe(true)
    })

    it('rejects an obsolete CSRF response before the retry handler can resend its request', async () => {
        const state = contextClient()
        const retry = vi.fn()
        state.client.interceptors.response.use(response => response, error => {
            if (error.response?.status === 419) retry()
            return Promise.reject(error)
        })
        const old = state.client.get('/api/admin/teaching/courses').catch(state.notifyError)
        await waitFor(() => expect(state.requests).toHaveLength(1))
        const change = state.client.post('/api/admin/hopper_accounts/switch')
        await waitFor(() => expect(state.requests).toHaveLength(2))
        state.requests[0].fail(419)
        await old
        expect(retry).not.toHaveBeenCalled()
        expect(state.notification.show).toBe(false)
        state.requests[1].succeed()
        await change
    })
})
