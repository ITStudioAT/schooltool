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

it('restores the selected stage and preserves preview parameters while stepping through DHCP', async () => {
    const { wrapper, router } = await render('?preview=1&ipStep=2&other=kept#example')
    expect(wrapper.get('.step-explanation h3').text()).toContain('DHCP Offer')
    expect(wrapper.get('[aria-label="Aktuelle Einstellungen des PCs"]').text()).toContain('noch nicht eingerichtet')
    await wrapper.get('[aria-label="Nächster Ablaufschritt"]').trigger('click')
    await wrapper.get('[aria-label="Nächster Ablaufschritt"]').trigger('click')
    await flushPromises()
    expect(wrapper.get('.step-explanation h3').text()).toContain('DHCP ACK')
    expect(wrapper.get('[aria-label="Aktuelle Einstellungen des PCs"]').text()).toContain('255.255.255.0')
    expect(router.currentRoute.value.query).toMatchObject({ preview: '1', other: 'kept', ipStep: '4' })
    expect(router.currentRoute.value.hash).toBe('#example')
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
