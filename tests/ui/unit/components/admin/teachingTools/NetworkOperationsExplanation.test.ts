import { afterEach, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import NetworkOperationsExplanation from '@/pages/admin/teachingTools/NetworkOperationsExplanation.vue'

const wrappers: ReturnType<typeof mount>[] = []

async function render(path = '/lesson') {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/lesson', component: NetworkOperationsExplanation }] })
    await router.push(path)
    await router.isReady()
    const wrapper = mount(NetworkOperationsExplanation, { global: { plugins: [router] } })
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

it('visits all seven chapters and disables navigation at both boundaries', async () => {
    const { wrapper } = await render()
    expect(button(wrapper, '← Zurück').attributes('disabled')).toBeDefined()
    for (let index = 0; index < 6; index++) {
        await button(wrapper, 'Weiter →').trigger('click')
        await flushPromises()
    }
    expect(wrapper.get('h2').text()).toBe('Welche Frage beantwortet das Werkzeug?')
    expect(button(wrapper, 'Weiter →').attributes('disabled')).toBeDefined()
    await button(wrapper, '← Zurück').trigger('click')
    expect(wrapper.get('h2').text()).toBe('Wer oder was ist betroffen?')
})

it('switches between topology diagrams and explains their failure limits', async () => {
    const { wrapper } = await render()
    await button(wrapper, 'Topologien').trigger('click')
    expect(wrapper.get('svg[role="img"]').attributes('aria-label')).toContain('zentralen Switch')
    expect(wrapper.get('.detail-panel').text()).toContain('gemeinsamer Ausfallpunkt')
    await button(wrapper, 'Bus').trigger('click')
    expect(wrapper.get('svg[role="img"]').attributes('aria-label')).toContain('gemeinsames Übertragungsmedium')
    await button(wrapper, 'Ring').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('vorhandenen Ersatzwegen')
    await button(wrapper, 'Mesh').trigger('click')
    expect(wrapper.get('svg[role="img"]').attributes('aria-label')).toContain('vollständig vermaschten Beispiel')
    expect(wrapper.get('.detail-panel').text()).toContain('Nicht jedes Mesh verbindet jeden Knoten direkt')
    await button(wrapper, 'Gebäudeaufbau').trigger('click')
    expect(wrapper.find('svg[role="img"]').exists()).toBe(false)
    expect(wrapper.get('.network-flow').text()).toContain('Router und Firewall')
})

it('distinguishes performance limits from symptom-based diagnosis', async () => {
    const { wrapper } = await render()
    await button(wrapper, 'Leistung').trigger('click')
    await button(wrapper, 'Latenz und Jitter').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('Zeit, die ein Datenpaket vom Sender bis zum Empfänger braucht')
    expect(wrapper.get('.detail-panel').text()).toContain('20, 80 und 30 Millisekunden')
    await button(wrapper, 'Quality of Service').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('keine zusätzliche Internetbandbreite')
    await button(wrapper, 'Störungssuche').trigger('click')
    await button(wrapper, 'Lokal geht, Web nicht').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('beweist keinen funktionierenden Internetzugang')
    await button(wrapper, 'Nur eine Website').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('nicht automatisch einen allgemeinen Internetausfall')
})

it('displays diagnostic commands as text and explains missing responses and privacy', async () => {
    const { wrapper } = await render()
    await button(wrapper, 'Werkzeuge').trigger('click')
    expect(wrapper.get('code').text()).toBe('ipconfig /all')
    await button(wrapper, 'ping').trigger('click')
    expect(wrapper.get('code').text()).toBe('ping <Zieladresse>')
    expect(wrapper.get('.detail-panel').text()).toContain('ICMP kann gefiltert sein')
    await button(wrapper, 'tracert').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('nicht immer vollständig')
    await button(wrapper, 'nslookup').trigger('click')
    expect(wrapper.get('code').text()).toBe('nslookup <Domainname>')
    await button(wrapper, 'Wireshark').trigger('click')
    expect(wrapper.find('code').exists()).toBe(false)
    expect(wrapper.get('.detail-panel').text()).toContain('Aufzeichnung setzt Berechtigung')
})

it('restores deep links and browser history without dropping preview parameters or hash', async () => {
    const { wrapper, router } = await render('/lesson?preview=1&other=kept&chapter=topologies&selection=Mesh#details')
    expect(wrapper.get('.detail-panel h3').text()).toBe('Mesh')
    await button(wrapper, 'Ring').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query).toMatchObject({ preview: '1', other: 'kept', chapter: 'topologies', selection: 'Ring' })
    expect(router.currentRoute.value.hash).toBe('#details')
    await button(wrapper, 'Werkzeuge').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query.selection).toBeUndefined()
    router.back()
    await flushPromises()
    expect(wrapper.get('.detail-panel h3').text()).toBe('Ring')
})

it('falls back to valid chapter content for unknown location values', async () => {
    const { wrapper, router } = await render('/lesson?chapter=unknown&selection=unknown')
    expect(wrapper.get('.detail-panel h3').text()).toBe('Nutzung und Dienste')
    await router.push('/lesson?chapter=topologies&selection=unknown')
    expect(wrapper.get('.detail-panel h3').text()).toBe('Stern')
    expect(wrapper.get('svg[role="img"]').attributes('aria-label')).toContain('Vier Computer')
})
