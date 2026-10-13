import { afterEach, describe, expect, it, vi } from 'vitest'
import axios from 'axios'
import { screen, waitFor } from '@testing-library/vue'
import { mount } from '@vue/test-utils'
import { impersonationTransition, startImpersonationTransition } from '@/helpers/impersonationTransition'
import ImpersonationTransitionOverlay from '@/pages/components/ImpersonationTransitionOverlay.vue'

const disposers: (() => void)[] = []
const wrappers: ReturnType<typeof mount>[] = []

afterEach(() => {
    disposers.splice(0).forEach(stop => stop())
    wrappers.splice(0).forEach(wrapper => wrapper.unmount())
    document.body.innerHTML = ''
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

    it('clears a completed transition on restoration from the browser back cache', async () => {
        const pending = pendingClient()
        const request = pending.client.post('/api/admin/impersonation/stop')
        await waitFor(() => expect(pending.adapter).toHaveBeenCalledOnce())
        pending.succeed()
        await request
        const restored = new Event('pageshow')
        Object.defineProperty(restored, 'persisted', { value: true })
        window.dispatchEvent(restored)
        expect(impersonationTransition.value).toBe('')
    })
})
