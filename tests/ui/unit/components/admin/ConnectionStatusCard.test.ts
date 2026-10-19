import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import ConnectionStatusCard from '@/pages/admin/components/ConnectionStatusCard.vue'

let wrapper
afterEach(() => { wrapper?.unmount(); vi.unstubAllGlobals() })

it('distinguishes granted rights from live checks and only checks after a deliberate click', async () => {
    const data = { dropbox: { configured: true, connected: true, read_granted: true, write_granted: true },
        can_connect_dropbox: true, postmark: { configured: true, used_for_mail: false }, office: { message: 'Keine direkte Verbindung.' } }
    const get = vi.fn().mockResolvedValue({ data })
    const post = vi.fn().mockResolvedValue({ data: { ...data, dropbox: { ...data.dropbox, read_checked_at: '2026-10-19T12:00:00Z' } } })
    vi.stubGlobal('axios', { get, post })
    wrapper = mount(ConnectionStatusCard, { global: { stubs: {
        'v-card': { template: '<div><slot /></div>' },
        'v-btn': { template: '<button><slot /></button>' },
    } } })
    await flushPromises()

    expect(wrapper.text()).toContain('Verbindungen')
    expect(wrapper.text()).toContain('Benutzerfreigabe gespeichert')
    expect(wrapper.text()).toContain('Schreiben: freigegeben, noch nicht geprüft')
    expect(wrapper.text()).toContain('Zugang noch nicht geprüft')
    expect(post).not.toHaveBeenCalled()

    await wrapper.findAll('button')[0].trigger('click')
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/admin/connections/check')
    expect(wrapper.text()).toContain('Lesen: erfolgreich geprüft')
    expect(wrapper.text()).toContain('Schreiben: freigegeben, noch nicht geprüft')
})
