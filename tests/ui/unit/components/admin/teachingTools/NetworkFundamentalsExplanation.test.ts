import { afterEach, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import NetworkFundamentalsExplanation from '@/pages/admin/teachingTools/NetworkFundamentalsExplanation.vue'

const wrappers: ReturnType<typeof mount>[] = []

function render() {
    const wrapper = mount(NetworkFundamentalsExplanation)
    wrappers.push(wrapper)
    return wrapper
}

function button(wrapper, name: string) {
    const match = wrapper.findAll('button').find(item => item.text().trim() === name)
    expect(match, `Button ${name} must exist`).toBeDefined()
    return match!
}

afterEach(() => { wrappers.splice(0).forEach(wrapper => wrapper.unmount()) })

it('navigates between short topics and stops at the beginning and end', async () => {
    const wrapper = render()

    expect(button(wrapper, '← Zurück').attributes('disabled')).toBeDefined()
    expect(button(wrapper, 'Netzwerk').attributes('aria-pressed')).toBe('true')
    await button(wrapper, 'Weiter →').trigger('click')
    expect(wrapper.get('h2').text()).toBe('Welche Arten von Netzwerken gibt es?')
    expect(button(wrapper, 'Netzarten').attributes('aria-pressed')).toBe('true')

    await button(wrapper, 'Gebäude').trigger('click')
    expect(wrapper.get('h2').text()).toBe('Von der Dose ins Netzwerk')
    expect(button(wrapper, 'Weiter →').attributes('disabled')).toBeDefined()
    await button(wrapper, '← Zurück').trigger('click')
    expect(button(wrapper, 'Topologien').attributes('aria-pressed')).toBe('true')
    expect(wrapper.get('h2').text()).toBe('Wie sind Geräte verbunden?')
})

it('shows what a selected network device does and explains a disconnected internet connection', async () => {
    const wrapper = render()

    await button(wrapper, 'Router').trigger('click')
    expect(wrapper.get('h3').text()).toBe('Router')
    expect(wrapper.text()).toContain('verbindet verschiedene IP-Netze')
    await button(wrapper, 'Internetverbindung trennen').trigger('click')
    expect(wrapper.get('[role="status"]').text()).toContain('das lokale Netzwerk bleibt bestehen')

    await button(wrapper, 'Internetverbindung herstellen').trigger('click')
    expect(wrapper.get('[role="status"]').text()).toBe('Internetverbindung besteht')
})

it('shows the request, response and peer-to-peer roles separately', async () => {
    const wrapper = render()

    await button(wrapper, 'Dienste').trigger('click')
    expect(wrapper.get('h3').text()).toBe('Der Client fragt an')
    await button(wrapper, 'Antwort zeigen').trigger('click')
    expect(wrapper.get('h3').text()).toBe('Der Server antwortet')
    expect(wrapper.text()).toContain('Der Webserver sendet die Inhalte zurück')
    await button(wrapper, 'Peer-to-Peer').trigger('click')
    expect(wrapper.get('h3').text()).toBe('Direkter Austausch')
    expect(wrapper.text()).toContain('Geräte können Ressourcen direkt austauschen')
})

it('changes the topology illustration and its explanation', async () => {
    const wrapper = render()

    await button(wrapper, 'Topologien').trigger('click')
    expect(wrapper.get('[role="img"]').attributes('aria-label')).toContain('Stern:')
    await button(wrapper, 'Mesh').trigger('click')
    expect(wrapper.get('h3').text()).toBe('Mesh')
    expect(wrapper.get('[role="img"]').attributes('aria-label')).toContain('mehrere Wege')
    expect(wrapper.text()).toContain('alternative Wege verfügbar')
})

it('opens the explanation of MAC addresses and their displayed format', async () => {
    const wrapper = render()

    await button(wrapper, 'Switch & VLAN').trigger('click')
    const macButton = wrapper.findAll('button').find(item => item.text().includes('MAC-Adresse'))
    expect(macButton).toBeDefined()
    await macButton!.trigger('click')
    expect(wrapper.get('h3').text()).toBe('MAC-Adresse')
    expect(wrapper.text()).toContain('00:1A:2B:3C:4D:5E')
    expect(wrapper.text()).toContain('zufällig gewählt')
})

it('updates data rate and latency independently', async () => {
    const wrapper = render()

    await button(wrapper, 'Übertragung').trigger('click')
    expect(wrapper.text()).toContain('12,5 MB/s')
    expect(wrapper.text()).toContain('mindestens ca. 8 Sekunden')
    expect(wrapper.text()).toContain('40 ms = 0,040 s')
    await wrapper.get('#transfer-rate').setValue(400)
    expect(wrapper.text()).toContain('400 Mbit/s')
    expect(wrapper.text()).toContain('50 MB/s')
    expect(wrapper.text()).toContain('mindestens ca. 2 Sekunden')
    expect(wrapper.text()).toContain('40 ms = 0,040 s')
    await wrapper.get('#transfer-latency').setValue(125)
    expect(wrapper.text()).toContain('125 ms = 0,125 s')
    expect(wrapper.text()).toContain('mindestens ca. 2 Sekunden')
    expect(wrapper.text()).toContain('50 MB/s')
})
