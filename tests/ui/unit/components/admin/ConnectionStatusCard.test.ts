import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import ConnectionStatusCard from '@/pages/admin/components/ConnectionStatusCard.vue'

let wrapper
afterEach(() => { wrapper?.unmount(); vi.unstubAllGlobals() })

function mountCard() {
    wrapper = mount(ConnectionStatusCard, { global: { stubs: {
        'v-card': { template: '<div><slot /></div>' },
        'v-btn': { props: ['loading', 'disabled'], template: '<button :disabled="loading || disabled"><slot /></button>' },
        'v-progress-circular': { template: '<span role="progressbar" />' },
    } } })
}

function snapshot() {
    return { dropbox: { configured: true, installed: true, connected: true, read_granted: true, write_granted: true },
        can_connect_dropbox: true, postmark: { configured: true, used_for_mail: false },
        office: { message: 'Keine direkte Office-/Microsoft-Verbindung in Schooltool eingerichtet.' } }
}

it('automatically checks read access independently and reserves write and mail checks for a deliberate click', async () => {
    const data = snapshot()
    let finishDropbox
    let finishPostmark
    const get = vi.fn().mockResolvedValueOnce({ data })
        .mockImplementationOnce(() => new Promise(resolve => { finishDropbox = resolve }))
        .mockImplementationOnce(() => new Promise(resolve => { finishPostmark = resolve }))
    const post = vi.fn().mockResolvedValue({ data: { ...data, postmark: { ...data.postmark, checked_at: '2026-10-21T12:00:00Z',
        test_mail: { accepted: true, message: 'Testmail angenommen.' } } } })
    vi.stubGlobal('axios', { get, post })
    mountCard()

    expect(wrapper.findAll('[role="progressbar"]')).toHaveLength(3)
    await flushPromises()

    expect(get.mock.calls).toEqual([['/api/admin/connections'], ['/api/admin/connections?probe=dropbox'], ['/api/admin/connections?probe=postmark']])
    expect(wrapper.findAll('[role="progressbar"]')).toHaveLength(2)
    expect(wrapper.text()).toContain('API-Zugang wird geprüft')
    expect(wrapper.text()).toContain('Keine direkte Office-/Microsoft-Verbindung')
    expect(wrapper.findAll('button')[0].attributes('disabled')).toBeDefined()
    expect(post).not.toHaveBeenCalled()

    finishDropbox({ data: { dropbox: { ...data.dropbox, read_checked_at: '2026-10-21T12:00:00Z' } } })
    await flushPromises()
    expect(wrapper.findAll('[role="progressbar"]')).toHaveLength(1)
    expect(wrapper.text()).toContain('Ordnerzugriff bestätigt')
    expect(wrapper.text()).toContain('Schreiben: freigegeben, noch nicht geprüft')
    expect(wrapper.emitted('status-change')?.at(-1)?.[0]).toEqual({ loading: true, hasError: false })

    finishPostmark({ data: { postmark: { ...data.postmark, checked_at: '2026-10-21T12:00:00Z' } } })
    await flushPromises()
    expect(wrapper.findAll('[role="progressbar"]')).toHaveLength(0)
    expect(wrapper.text()).toContain('API-Zugang bestätigt')
    expect(wrapper.text()).toContain('Mailversand hier noch nicht geprüft')
    expect(wrapper.emitted('status-change')?.at(-1)?.[0]).toEqual({ loading: false, hasError: false })
    expect(post).not.toHaveBeenCalled()

    await wrapper.findAll('button')[0].trigger('click')
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/admin/connections/check')
    expect(post).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('Letzter manueller Mailtest: Testmail angenommen.')
    expect(wrapper.text()).toContain('Schreiben: freigegeben, noch nicht geprüft')
})

it('shows a failed probe without hiding the other result or retaining a stale success', async () => {
    const data = snapshot()
    const get = vi.fn().mockResolvedValueOnce({ data })
        .mockRejectedValueOnce(new Error('timeout'))
        .mockResolvedValueOnce({ data: { postmark: { ...data.postmark, error: 'Server-API-Token abgelehnt.' } } })
    const post = vi.fn()
    vi.stubGlobal('axios', { get, post })
    mountCard()
    await flushPromises()

    expect(wrapper.text()).toContain('Die Prüfung konnte nicht abgeschlossen werden')
    expect(wrapper.text()).toContain('Server-API-Token abgelehnt.')
    expect(wrapper.text()).not.toContain('API-Zugang bestätigt')
    expect(wrapper.findAll('[role="progressbar"]')).toHaveLength(0)
    expect(wrapper.emitted('status-change')?.at(-1)?.[0]).toEqual({ loading: false, hasError: true })
    expect(post).not.toHaveBeenCalled()
})

it('reports an unavailable overview and ends every pending state', async () => {
    vi.stubGlobal('axios', { get: vi.fn().mockRejectedValue(new Error('offline')), post: vi.fn() })
    mountCard()
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Verbindungsinformationen konnten nicht geladen werden')
    expect(wrapper.text()).toContain('Dropbox-Status konnte nicht geladen werden.')
    expect(wrapper.text()).toContain('Postmark-Status konnte nicht geladen werden.')
    expect(wrapper.text()).not.toContain('Nicht eingerichtet')
    expect(wrapper.findAll('[role="progressbar"]')).toHaveLength(0)
    expect(wrapper.emitted('status-change')?.at(-1)?.[0]).toEqual({ loading: false, hasError: true })
    expect(wrapper.findAll('button')[0].attributes('disabled')).toBeUndefined()
})

it('includes a failed deliberate mail test in the summary without repeating it automatically', async () => {
    const data = snapshot()
    data.dropbox.configured = false
    data.postmark.configured = false
    const post = vi.fn().mockResolvedValue({ data: { ...data,
        postmark: { configured: true, checked_at: '2026-10-21T12:00:00Z', test_mail: { accepted: false, message: 'Testmail abgelehnt.' } } } })
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data }), post })
    mountCard()
    await flushPromises()
    expect(wrapper.emitted('status-change')?.at(-1)?.[0]).toEqual({ loading: false, hasError: false })

    await wrapper.findAll('button')[0].trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Letzter manueller Mailtest: Testmail abgelehnt.')
    expect(wrapper.emitted('status-change')?.at(-1)?.[0]).toEqual({ loading: false, hasError: true })
    expect(post).toHaveBeenCalledTimes(1)
})

it('shows missing setup and a disconnected Dropbox without making provider requests', async () => {
    const data = snapshot()
    data.dropbox.connected = false
    data.postmark.configured = false
    const get = vi.fn().mockResolvedValue({ data })
    vi.stubGlobal('axios', { get, post: vi.fn() })
    mountCard()
    await flushPromises()

    expect(get).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('Nicht verbunden – bitte erneut freigeben')
    expect(wrapper.text()).toContain('Nicht eingerichtet')
    expect(wrapper.emitted('status-change')?.at(-1)?.[0]).toEqual({ loading: false, hasError: true })
})
