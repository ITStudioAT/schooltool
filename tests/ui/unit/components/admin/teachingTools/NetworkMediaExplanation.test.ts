import { afterEach, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import NetworkMediaExplanation from '@/pages/admin/teachingTools/NetworkMediaExplanation.vue'

const wrappers: ReturnType<typeof mount>[] = []

function render() {
    const wrapper = mount(NetworkMediaExplanation)
    wrappers.push(wrapper)
    return wrapper
}

function button(wrapper, name: string) {
    const match = wrapper.findAll('button').find(item => item.text().trim() === name
        || (item.find('strong').exists() && item.find('strong').text() === name))
    expect(match, `Button ${name} must exist`).toBeDefined()
    return match!
}

afterEach(() => { wrappers.splice(0).forEach(wrapper => wrapper.unmount()) })

it('restores the selected chapter and explanation from the address and keeps other parameters', async () => {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/lesson', component: NetworkMediaExplanation }] })
    await router.push('/lesson?preview=1&chapter=fiber&selection=Singlemode#details')
    await router.isReady()
    const wrapper = mount(NetworkMediaExplanation, { global: { plugins: [router] } })
    wrappers.push(wrapper)
    expect(wrapper.get('h2').text()).toBe('Wie funktioniert Glasfaser?')
    expect(wrapper.get('.detail-panel h3').text()).toBe('Singlemode')
    await button(wrapper, 'Längere Strecken').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query).toMatchObject({ preview: '1', chapter: 'fiber', selection: 'Längere Strecken' })
    expect(router.currentRoute.value.hash).toBe('#details')
    await button(wrapper, 'Kupfer').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query.chapter).toBe('copper')
    router.back()
    await flushPromises()
    expect(wrapper.get('.detail-panel h3').text()).toBe('Längere Strecken')
    const address = router.currentRoute.value.fullPath
    wrapper.unmount()
    const reloadRouter = createRouter({ history: createMemoryHistory(), routes: [{ path: '/lesson', component: NetworkMediaExplanation }] })
    await reloadRouter.push(address)
    await reloadRouter.isReady()
    const reloaded = mount(NetworkMediaExplanation, { global: { plugins: [reloadRouter] } })
    wrappers.push(reloaded)
    expect(reloaded.get('.detail-panel h3').text()).toBe('Längere Strecken')
})

it('falls back safely for unknown chapter or selection values', async () => {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/lesson', component: NetworkMediaExplanation }] })
    await router.push('/lesson?chapter=unknown&selection=unknown')
    await router.isReady()
    const wrapper = mount(NetworkMediaExplanation, { global: { plugins: [router] } })
    wrappers.push(wrapper)
    expect(wrapper.get('h2').text()).toBe('Wie kommen Signale ans Ziel?')
    expect(wrapper.get('.detail-panel h3').text()).toBe('Kupfer')
})

it('navigates between the ten topics and respects the ends', async () => {
    const wrapper = render()
    expect(button(wrapper, '← Zurück').attributes('disabled')).toBeDefined()
    await button(wrapper, 'Weiter →').trigger('click')
    expect(wrapper.get('h2').text()).toBe('Was steckt im Kupferkabel?')
    await button(wrapper, 'Sicherheit').trigger('click')
    expect(wrapper.get('h2').text()).toBe('Wer darf ins WLAN?')
    expect(button(wrapper, 'Weiter →').attributes('disabled')).toBeDefined()
})

it('changes the illustrated transmission path and the description', async () => {
    const wrapper = render()
    expect(wrapper.get('.detail-panel h3').text()).toBe('Kupfer')
    await wrapper.get('.visual-panel button:nth-child(2)').trigger('click')
    expect(wrapper.get('.detail-panel h3').text()).toBe('Glasfaser')
    expect(wrapper.get('[role="img"]').attributes('aria-label')).toContain('Glasfaserleitung')
    expect(wrapper.get('.detail-panel').text()).toContain('Lichtsignale')
    await button(wrapper, 'Funk').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('Funkwellen')
    expect(wrapper.get('[role="img"]').attributes('aria-label')).toContain('per Funk')
})

it('explains the specific part of structured cabling selected', async () => {
    const wrapper = render()
    await button(wrapper, 'Verkabelung').trigger('click')
    await button(wrapper, 'Patchpanel').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('nicht aktiv wie ein Switch')
    await button(wrapper, 'Netzwerkdose').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('fest installierte Leitung')
})

it('explains the path through glass fibre and both types of fibre', async () => {
    const wrapper = render()
    await button(wrapper, 'Glasfaser').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('elektrische Signale')
    await button(wrapper, 'Glasfaserkabel').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('eine oder mehrere')
    expect(wrapper.get('.detail-panel').text()).toContain('Kern')
    await button(wrapper, 'Empfänger').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('elektrische Signale')
    await button(wrapper, 'Multimode').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('größeren Kern')
    expect(wrapper.get('.detail-panel').text()).toContain('300 m')
    await button(wrapper, 'Singlemode').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('kleineren Kern')
    expect(wrapper.get('.detail-panel').text()).toContain('40 km')
    await button(wrapper, 'Längere Strecken').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('neuen Glasfaserabschnitt')
    expect(wrapper.get('.detail-panel').text()).toContain('Switch benötigt Strom')
})

it('changes PoE and WLAN coverage descriptions without promising availability', async () => {
    const wrapper = render()
    await button(wrapper, 'PoE').trigger('click')
    expect(wrapper.get('.poe-line').text()).toContain('Daten + Strom')
    await button(wrapper, 'Kein PoE').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('andere geeignete Stromquelle')
    await button(wrapper, 'Abdeckung').trigger('click')
    await button(wrapper, 'Mesh').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('drahtlos oder kabelgebunden')
    expect(wrapper.get('[role="img"]').attributes('aria-label')).toContain('Mesh-Knoten')
})

it('distinguishes WLAN bands, generations and guest-network separation', async () => {
    const wrapper = render()
    await button(wrapper, 'Frequenzen').trigger('click')
    await button(wrapper, '6 GHz').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('vom Land und von den Geräten')
    await button(wrapper, 'Wi-Fi').trigger('click')
    await button(wrapper, 'Wi-Fi 6E').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('6-GHz-Bereich')
    await button(wrapper, 'Sicherheit').trigger('click')
    await button(wrapper, 'WPA2 & WPA3').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('Wi-Fi Protected Access')
    await button(wrapper, 'AES').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('Advanced Encryption Standard')
    await button(wrapper, 'Gäste-WLAN').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('Ein anderer WLAN-Name oder ein eigenes Passwort allein genügt nicht')
})
