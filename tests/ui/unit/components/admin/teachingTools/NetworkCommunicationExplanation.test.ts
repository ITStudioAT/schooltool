import { afterEach, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import NetworkCommunicationExplanation from '@/pages/admin/teachingTools/NetworkCommunicationExplanation.vue'

const wrappers: ReturnType<typeof mount>[] = []

function render() {
    const wrapper = mount(NetworkCommunicationExplanation)
    wrappers.push(wrapper)
    return wrapper
}

function button(wrapper, name: string) {
    const match = wrapper.findAll('button').find(item => item.text().trim() === name
        || (item.find('strong').exists() && item.find('strong').text() === name))
    expect(match, `Button ${name} must exist`).toBeDefined()
    return match!
}

async function openAnimation(wrapper) {
    const details = wrapper.get('details.encapsulation-animation-details')
    details.element.open = true
    await details.trigger('toggle')
}

afterEach(() => { wrappers.splice(0).forEach(wrapper => wrapper.unmount()) })

it('changes chapter and explains the selected step of the website request', async () => {
    const wrapper = render()
    await button(wrapper, 'Webseite').trigger('click')
    expect(wrapper.get('h2').text()).toContain('Browser')
    await button(wrapper, 'DNS').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('Zwischenspeicher')
    expect(wrapper.get('.detail-panel').text()).toContain('Domain Name System')
    expect(wrapper.get('.detail-panel').text()).toContain('schooltool.at')
    expect(wrapper.get('.detail-panel').text()).toContain('Webserver übertragen')
    await button(wrapper, 'HTTPS').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('Zertifikat')
})

it('distinguishes protocols and displays what QUIC contributes to HTTP/3', async () => {
    const wrapper = render()
    await button(wrapper, 'TCP & UDP').trigger('click')
    await button(wrapper, 'UDP').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('verloren gehen oder in einer anderen Reihenfolge ankommen')
    expect(wrapper.get('.detail-panel').text()).toContain('UDP fordert fehlende Daten nicht selbst erneut an')
    await button(wrapper, 'UDP-Einsatzbereiche').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('Live-Audio und Video')
    expect(wrapper.get('.detail-panel').text()).toContain('Online-Spielen')
    expect(wrapper.get('.detail-panel').text()).toContain('DNS kann aber ebenso TCP nutzen')
    expect(wrapper.get('.detail-panel').text()).not.toContain('HTTP/3')
    await button(wrapper, 'HTTP/3').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('QUIC verschickt die Daten mit UDP')
    expect(wrapper.get('.detail-panel').text()).toContain('keine Abkürzung')
    expect(wrapper.get('.detail-panel').text()).toContain('QUIC erkennt fehlende Daten')
    expect(wrapper.get('.detail-panel').text()).toContain('erneut gesendet werden')
})

it('shows the encapsulation layers in order and explains the local frame', async () => {
    const wrapper = render()
    await button(wrapper, 'Kapselung').trigger('click')
    expect(wrapper.get('.two-columns').isVisible()).toBe(true)
    expect(wrapper.get('details.encapsulation-animation-details').element.open).toBe(false)
    expect(wrapper.get('.two-columns').element.compareDocumentPosition(wrapper.get('details').element) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
    await button(wrapper, 'Ethernet-Rahmen').trigger('click')
    expect(wrapper.get('.envelope').text()).toContain('Ethernet-RahmenIP-PaketTransportDaten')
    expect(wrapper.get('.detail-panel').text()).toContain('lokalen MAC-Adressen')
})

it('animates the packet being encapsulated and unpacked in the selected chapter', async () => {
    const wrapper = render()
    await button(wrapper, 'Kapselung').trigger('click')
    await openAnimation(wrapper)
    const nextStep = wrapper.get('[aria-label="Nächster Animationsschritt"]')
    expect(wrapper.get('.animation-label').text()).toBe('Anwendungsdaten')
    await nextStep.trigger('click')
    expect(wrapper.get('.animation-packet').text()).toContain('TCP-Kopf')
    await nextStep.trigger('click')
    expect(wrapper.get('.animation-packet').text()).toContain('IP-Kopf')
    await nextStep.trigger('click')
    expect(wrapper.get('.animation-packet').text()).toContain('Ethernet-Kopf')
    expect(wrapper.get('.animation-packet').text()).toContain('Prüfsumme')
    for (let index = 0; index < 5; index++) await nextStep.trigger('click')
    expect(wrapper.get('.animation-label').text()).toBe('Anwendungsdaten')
    expect(wrapper.get('.animation-packet').text()).not.toContain('TCP-Kopf')
})

it('restores the animation position and preserves other query parameters and hash', async () => {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/lesson', component: NetworkCommunicationExplanation }] })
    await router.push('/lesson?preview=1&chapter=encapsulation&encapStep=3#details')
    await router.isReady()
    const wrapper = mount(NetworkCommunicationExplanation, { global: { plugins: [router] } })
    wrappers.push(wrapper)
    expect(wrapper.get('.animation-label').text()).toBe('Ethernet-Rahmen')
    await wrapper.get('[aria-label="Nächster Animationsschritt"]').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query).toMatchObject({ preview: '1', chapter: 'encapsulation', encapStep: '4' })
    expect(router.currentRoute.value.hash).toBe('#details')
    router.back()
    await flushPromises()
    expect(wrapper.get('.animation-label').text()).toBe('Ethernet-Rahmen')
    await button(wrapper, 'Webseite').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query.encapStep).toBeUndefined()
})

it('keeps layer details visible for existing links', async () => {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/lesson', component: NetworkCommunicationExplanation }] })
    await router.push('/lesson?preview=1&chapter=encapsulation&encapDetails=1')
    await router.isReady()
    const wrapper = mount(NetworkCommunicationExplanation, { global: { plugins: [router] } })
    wrappers.push(wrapper)
    expect(wrapper.get('.two-columns').isVisible()).toBe(true)
    expect(wrapper.get('.two-columns').text()).toContain('Anwendungsdaten')
})

it('keeps the latest animation position when the next button is clicked quickly', async () => {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/lesson', component: NetworkCommunicationExplanation }] })
    await router.push('/lesson?chapter=encapsulation&preview=1')
    await router.isReady()
    const wrapper = mount(NetworkCommunicationExplanation, { global: { plugins: [router] } })
    wrappers.push(wrapper)
    await openAnimation(wrapper)
    const next = wrapper.get('[aria-label="Nächster Animationsschritt"]')
    const clicks = [next.trigger('click'), next.trigger('click'), next.trigger('click')]
    await Promise.all(clicks)
    await flushPromises()
    expect(wrapper.get('.animation-label').text()).toBe('Ethernet-Rahmen')
    expect(router.currentRoute.value.query.encapStep).toBe('3')
})

it('shows the animation below the explanation and offers only manual navigation', async () => {
    const wrapper = render()
    await button(wrapper, 'Kapselung').trigger('click')
    await openAnimation(wrapper)
    expect(wrapper.get('.animation-label').text()).toBe('Anwendungsdaten')
    expect(wrapper.findAll('button').some(item => /Animation abspielen|Pause/.test(item.text()))).toBe(false)
    await wrapper.get('[aria-label="Nächster Animationsschritt"]').trigger('click')
    expect(wrapper.get('.animation-label').text()).toBe('TCP-Segment')
    await wrapper.get('[aria-label="Vorheriger Animationsschritt"]').trigger('click')
    expect(wrapper.get('.animation-label').text()).toBe('Anwendungsdaten')
})

it('restores a chapter and selection from the address and preserves the preview and hash', async () => {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/lesson', component: NetworkCommunicationExplanation }] })
    await router.push('/lesson?preview=1&chapter=routing&selection=Ziel%20au%C3%9Ferhalb#details')
    await router.isReady()
    const wrapper = mount(NetworkCommunicationExplanation, { global: { plugins: [router] } })
    wrappers.push(wrapper)
    expect(wrapper.get('.detail-panel h3').text()).toBe('Ziel außerhalb')
    expect(wrapper.get('.detail-panel').text()).toContain('NAT')
    await button(wrapper, 'Switch').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query).toMatchObject({ preview: '1', chapter: 'routing' })
    expect(router.currentRoute.value.hash).toBe('#details')
    await button(wrapper, 'Webseite').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query.chapter).toBe('web')
    router.back()
    await flushPromises()
    expect(wrapper.get('.detail-panel h3').text()).toBe('Switch')
})
