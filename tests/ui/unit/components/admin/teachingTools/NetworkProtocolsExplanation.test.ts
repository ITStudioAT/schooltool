import { afterEach, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import NetworkProtocolsExplanation from '@/pages/admin/teachingTools/NetworkProtocolsExplanation.vue'

const wrappers: ReturnType<typeof mount>[] = []

async function render(path = '/lesson') {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/lesson', component: NetworkProtocolsExplanation }] })
    await router.push(path)
    await router.isReady()
    const wrapper = mount(NetworkProtocolsExplanation, { global: { plugins: [router] } })
    wrappers.push(wrapper)
    return { wrapper, router }
}

function button(wrapper, name: string) {
    const match = wrapper.findAll('button').find(item => item.text().trim() === name
        || (item.find('strong').exists() && item.find('strong').text() === name))
    expect(match, `Button ${name} must exist`).toBeDefined()
    return match!
}

afterEach(() => { wrappers.splice(0).forEach(wrapper => wrapper.unmount()) })

it('moves through all chapters and disables navigation at the boundaries', async () => {
    const { wrapper } = await render()
    expect(button(wrapper, '← Zurück').attributes('disabled')).toBeDefined()
    for (let index = 0; index < 7; index++) {
        await button(wrapper, 'Weiter →').trigger('click')
        await flushPromises()
    }
    expect(wrapper.get('h2').text()).toBe('Wer darf welche Datei öffnen?')
    expect(button(wrapper, 'Weiter →').attributes('disabled')).toBeDefined()
    await button(wrapper, '← Zurück').trigger('click')
    expect(wrapper.get('h2').text()).toBe('Wo läuft der Dienst?')
})

it('updates diagrams, ports and explanations for DNS, DHCP and ICMP', async () => {
    const { wrapper } = await render()
    await button(wrapper, 'DNS & DHCP').trigger('click')
    expect(wrapper.get('.port-details').text()).toContain('UDP 53 oder TCP 53')
    expect(wrapper.get('.detail-panel').text()).toContain('AAAA-Eintrag')
    await button(wrapper, 'DHCP für IPv4').trigger('click')
    expect(wrapper.get('.service-flow').text()).toContain('Netzwerkeinstellungen')
    expect(wrapper.get('.port-details').text()).toContain('UDP 67 · Server / UDP 68 · Client')
    await button(wrapper, 'IP & Ports').trigger('click')
    await button(wrapper, 'ICMP').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('keine TCP- oder UDP-Portnummer')
    expect(wrapper.find('.port-details').exists()).toBe(false)
})

it('distinguishes HTTPS, QUIC, mail transport and file transfer protection', async () => {
    const { wrapper } = await render()
    await button(wrapper, 'Web & TLS').trigger('click')
    await button(wrapper, 'HTTPS und TLS').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('HTTPS beweist nicht')
    await button(wrapper, 'HTTP/3 und QUIC').trigger('click')
    expect(wrapper.get('.port-details').text()).toContain('UDP 443')
    expect(wrapper.get('.detail-panel').text()).toContain('UDP selbst garantiert sie nicht')
    await button(wrapper, 'E-Mail').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('Einlieferung')
    await button(wrapper, 'IMAP').trigger('click')
    expect(wrapper.get('.port-details').text()).toContain('TCP 993')
    expect(wrapper.get('.caption').text()).toContain('Ende-zu-Ende')
    await button(wrapper, 'Dateien & SSH').trigger('click')
    await button(wrapper, 'FTPS').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('FTP um TLS')
    await button(wrapper, 'SFTP').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('keine Variante von FTP über TLS')
})

it('distinguishes synchronisation from backup and login from permission', async () => {
    const { wrapper } = await render()
    await button(wrapper, 'Server & Cloud').trigger('click')
    await button(wrapper, 'Synchronisierung und Backup').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('getrennt geschützte Sicherungskopien')
    await button(wrapper, 'Konten & Rechte').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('nicht automatisch jede Handlung')
    await button(wrapper, 'Autorisierung').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('Lesen, Ändern und Verwalten')
})

it('restores chapter and selection, preserves preview parameters and supports browser back', async () => {
    const { wrapper, router } = await render('/lesson?preview=1&chapter=files&selection=SFTP#details')
    expect(wrapper.get('.detail-panel h3').text()).toBe('SFTP')
    await button(wrapper, 'SMB').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query).toMatchObject({ preview: '1', chapter: 'files', selection: 'SMB' })
    expect(router.currentRoute.value.hash).toBe('#details')
    await button(wrapper, 'E-Mail').trigger('click')
    await flushPromises()
    router.back()
    await flushPromises()
    expect(wrapper.get('.detail-panel h3').text()).toBe('SMB')
})

it('falls back to the first valid example for unknown chapter and selection values', async () => {
    const { wrapper, router } = await render('/lesson?chapter=missing&selection=missing')
    expect(wrapper.get('.detail-panel h3').text()).toBe('Client und Server')
    await router.push('/lesson?chapter=files&selection=missing')
    expect(wrapper.get('.detail-panel h3').text()).toBe('FTP')
})
