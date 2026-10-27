import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import NetworkAddressingExplanation from '@/pages/admin/teachingTools/NetworkAddressingExplanation.vue'

const wrappers: ReturnType<typeof mount>[] = []
async function render(query = '') {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/lesson', component: NetworkAddressingExplanation }] })
    await router.push(`/lesson${query}`)
    const wrapper = mount(NetworkAddressingExplanation, { global: { plugins: [router] } })
    wrappers.push(wrapper)
    return { wrapper, router }
}
function button(wrapper, text: string) { return wrapper.findAll('button').find(item => item.text().trim() === text)! }
afterEach(() => { wrappers.splice(0).forEach(wrapper => wrapper.unmount()); vi.useRealTimers() })

it('explains DHCP, DNS and VLAN when they appear in the current step', async () => {
    const { wrapper } = await render('?preview=1&ipScenario=internet')

    expect(wrapper.get('[aria-label="Fachbegriffe kurz erklärt"]').text()).toContain('Dynamic Host Configuration Protocol')
    expect(wrapper.get('[aria-label="Fachbegriffe kurz erklärt"]').text()).toContain('Domain Name System')
    expect(wrapper.get('[aria-label="Fachbegriffe kurz erklärt"]').text()).toContain('Virtual Local Area Network')

    await wrapper.get('[aria-label="Nächster Ablaufschritt"]').trigger('click')
    expect(wrapper.get('[aria-label="Fachbegriffe kurz erklärt"]').text()).toContain('Dynamic Host Configuration Protocol')
    expect(wrapper.get('[aria-label="Fachbegriffe kurz erklärt"]').text()).toContain('Eine Nachricht an alle Geräte im selben lokalen Netzwerk')
    expect(wrapper.get('[aria-label="Fachbegriffe kurz erklärt"]').text()).not.toContain('Domain Name System')
    await wrapper.get('[aria-label="Nächster Ablaufschritt"]').trigger('click')
    expect(wrapper.get('.step-explanation').text()).toContain('Anhand einer Kennung erkennt der PC')
    expect(wrapper.get('[aria-label="Fachbegriffe kurz erklärt"]').text()).toContain('Eine Nachricht an alle Geräte im selben lokalen Netzwerk')

    await wrapper.findAll('.step-timeline button').find(item => item.text().includes('Gateway-MAC ist bereits im Cache'))!.trigger('click')
    expect(wrapper.get('[aria-label="Fachbegriffe kurz erklärt"]').text()).toContain('Ein Zwischenspeicher für bereits bekannte Informationen')

    await wrapper.findAll('.step-timeline button').find(item => item.text().includes('NAT mit Portübersetzung'))!.trigger('click')
    expect(wrapper.find('[aria-label="Fachbegriffe kurz erklärt"]').exists()).toBe(false)
})

it('restores the selected stage and preserves preview parameters while stepping through DHCP', async () => {
    const { wrapper, router } = await render('?preview=1&ipStep=2&other=kept#example')
    expect(wrapper.get('.step-explanation h3').text()).toContain('DHCP Offer')
    expect(wrapper.get('.step-progress strong').text()).toContain('Schritt 3 / ')
    expect(wrapper.get('[aria-label="Fortschritt im Ablauf"]').attributes('value')).toBe('3')
    expect(wrapper.get('[aria-label="Aktuelle Einstellungen des PCs"]').text()).toContain('noch nicht eingerichtet')
    expect(wrapper.get('.pc-address').text()).toBe('keine IP')
    await wrapper.get('[aria-label="Nächster Ablaufschritt"]').trigger('click')
    expect(wrapper.get('.pc-address').text()).toBe('keine IP')
    await wrapper.get('[aria-label="Nächster Ablaufschritt"]').trigger('click')
    await flushPromises()
    expect(wrapper.get('.step-explanation h3').text()).toContain('DHCP ACK')
    expect(wrapper.get('.step-progress strong').text()).toContain('Schritt 5 / ')
    expect(wrapper.get('[aria-label="Fortschritt im Ablauf"]').attributes('value')).toBe('5')
    expect(wrapper.get('[aria-label="Aktuelle Einstellungen des PCs"]').text()).toContain('255.255.255.0')
    expect(wrapper.get('.pc-address').text()).toBe('.10.20')
    expect(router.currentRoute.value.query).toMatchObject({ preview: '1', other: 'kept', ipStep: '4' })
    expect(router.currentRoute.value.hash).toBe('#example')
    await wrapper.get('[aria-label="Vorheriger Ablaufschritt"]').trigger('click')
    expect(wrapper.get('.pc-address').text()).toBe('keine IP')
})

it('shows the correct packet direction in the compact diagram for the outward and return paths', async () => {
    const { wrapper } = await render('?preview=1&ipScenario=internet&ipStep=1')

    expect(wrapper.get('.network-map--compact .active-path').attributes('d')).toBe('M85 130 L250 130 L250 325')
    await wrapper.get('[aria-label="Nächster Ablaufschritt"]').trigger('click')
    expect(wrapper.get('.network-map--compact .active-path').attributes('d')).toBe('M250 325 L250 130 L85 130')

    await wrapper.findAll('.step-timeline button').find(item => item.text().includes('Weitere Router bringen die Anfrage'))!.trigger('click')
    expect(wrapper.get('.network-map--compact .active-path').attributes('d')).toBe('M250 325 L100 460 L100 585')
    await wrapper.get('[aria-label="Nächster Ablaufschritt"]').trigger('click')
    expect(wrapper.get('.network-map--compact .active-path').attributes('d')).toBe('M100 585 L100 460 L250 325')

    await wrapper.findAll('.scenario-choices button')[1].trigger('click')
    await wrapper.findAll('.step-timeline button').find(item => item.text().includes('Ein neuer Rahmen erreicht'))!.trigger('click')
    expect(wrapper.get('.network-map--compact .active-path').attributes('d')).toBe('M250 325 L295 325 L295 450 L400 450')
    await wrapper.get('[aria-label="Nächster Ablaufschritt"]').trigger('click')
    expect(wrapper.get('.network-map--compact .active-path').attributes('d')).toBe('M400 450 L295 450 L295 325 L250 325')
})

it('demonstrates how prefixes alter the comparison without joining separate VLANs', async () => {
    const { wrapper } = await render('?chapter=mask')
    expect(wrapper.get('.lab-decision').text()).toContain('Router')
    await wrapper.get('[aria-label="Präfixlänge auswählen"]').setValue('16')
    await flushPromises()
    expect(wrapper.get('.mask-value').text()).toContain('255.255.0.0')
    expect(wrapper.get('.lab-decision').text()).toContain('lokal erreichbar')
    expect(wrapper.get('.warning-note').text()).toContain('werden VLAN 10 und VLAN 20 aber nicht verbunden')
    await wrapper.get('[aria-label="Präfixlänge auswählen"]').setValue('26')
    await wrapper.get('[aria-label="Zieladresse vergleichen"]').setValue('192.168.10.130')
    await flushPromises()
    expect(wrapper.get('.lab-results').text()).toContain('192.168.10.128')
    expect(wrapper.get('.lab-decision').text()).toContain('Router')
})

it('changes the routing decision and explains IPv6 and the NAT distinction', async () => {
    const { wrapper } = await render('?chapter=routing')
    expect(wrapper.get('.route-table .chosen').text()).toContain('192.168.20.0/24')
    await wrapper.get('[aria-label="Routingziel auswählen"]').setValue('203.0.113.80')
    expect(wrapper.get('.route-table .chosen').text()).toContain('0.0.0.0/0')
    await button(wrapper, 'IPv4 & IPv6').trigger('click')
    await flushPromises()
    expect(wrapper.get('.chapter').text()).toContain('128 Bit')
    expect(wrapper.get('.chapter').text()).toContain('Nachbarerkennung statt ARP')
    await button(wrapper, 'NAT & VLAN').trigger('click')
    await flushPromises()
    expect(wrapper.get('.chapter').text()).toContain('192.168.10.20:53000')
})

it('advances only through manual navigation and keeps restart available', async () => {
    vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] })
    const { wrapper } = await render('?ipScenario=local&ipConfig=static')
    expect(button(wrapper, 'Automatisch abspielen')).toBeUndefined()
    expect(wrapper.find('[aria-label="Zeit je Schritt"]').exists()).toBe(false)
    expect(wrapper.get('[aria-label="Vorheriger Ablaufschritt"]').element.disabled).toBe(true)
    await vi.advanceTimersByTimeAsync(7000)
    await flushPromises()
    expect(wrapper.get('.step-explanation h3').text()).toContain('lokale Verbindung')
    await wrapper.get('[aria-label="Nächster Ablaufschritt"]').trigger('click')
    await flushPromises()
    expect(wrapper.get('.step-explanation h3').text()).toContain('von Hand')
    expect(vi.getTimerCount()).toBe(0)
    await button(wrapper, 'Neustart').trigger('click')
    await flushPromises()
    expect(wrapper.get('.step-explanation h3').text()).toContain('lokale Verbindung')
    await button(wrapper, 'DHCP').trigger('click')
    await flushPromises()
    expect(vi.getTimerCount()).toBe(0)
})
