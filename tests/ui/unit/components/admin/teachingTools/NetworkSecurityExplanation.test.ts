import { afterEach, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import NetworkSecurityExplanation from '@/pages/admin/teachingTools/NetworkSecurityExplanation.vue'

const wrappers: ReturnType<typeof mount>[] = []

async function render(path = '/lesson') {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/lesson', component: NetworkSecurityExplanation }] })
    await router.push(path)
    await router.isReady()
    const wrapper = mount(NetworkSecurityExplanation, { global: { plugins: [router] } })
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

it('navigates all chapters, selects a protection goal and stops at the boundaries', async () => {
    const { wrapper } = await render()
    expect(wrapper.get('nav').text()).not.toContain('Schulalltag')
    expect(button(wrapper, '← Zurück').attributes('disabled')).toBeDefined()
    await button(wrapper, 'Integrität').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('Veränderungen sind erkennbar')
    expect(wrapper.get('.security-flow').text()).toContain('Änderung prüfen')
    for (let index = 0; index < 6; index++) {
        await button(wrapper, 'Weiter →').trigger('click')
        await flushPromises()
    }
    expect(wrapper.get('h2').text()).toBe('Welche Daten gehören zu einer Person?')
    expect(button(wrapper, 'Weiter →').attributes('disabled')).toBeDefined()
    await button(wrapper, '← Zurück').trigger('click')
    expect(wrapper.get('h2').text()).toBe('Sicherheit braucht laufende Pflege')
})

it('distinguishes authentication, permission and genuinely different MFA factors', async () => {
    const { wrapper } = await render()
    await button(wrapper, 'Konten & MFA').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('nicht automatisch Zugriff')
    await button(wrapper, 'Autorisierung').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('erforderlichen Rechte')
    await button(wrapper, 'MFA').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('keine zwei unterschiedlichen Faktoren')
    expect(wrapper.get('.security-flow').text()).toContain('Sicherheitsschlüssel · Besitz')
})

it('explains network boundaries and the limits of VPN and encryption', async () => {
    const { wrapper } = await render()
    await button(wrapper, 'Netzgrenzen').trigger('click')
    expect(wrapper.get('.security-flow').text()).toContain('RADIUS-Server')
    await button(wrapper, 'Segmentierung und VLAN').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('keine vollständige Sicherheitslösung')
    await button(wrapper, 'VPN').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('garantiert keine Anonymität')
    await button(wrapper, 'WLAN & TLS').trigger('click')
    await button(wrapper, 'Gäste-WLAN').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('WLAN-Name allein beweist keine sichere Trennung')
    await button(wrapper, 'TLS und HTTPS').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('beweist nicht, dass der Webseiteninhalt vertrauenswürdig ist')
})

it('shows attack impacts, recovery limits and personal data in network logs', async () => {
    const { wrapper } = await render()
    await button(wrapper, 'Angriffe').trigger('click')
    await button(wrapper, 'Ransomware').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('zusätzlich Daten gestohlen')
    await button(wrapper, 'DoS und DDoS').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('mehreren Quellen')
    await button(wrapper, 'Schutz im Betrieb').trigger('click')
    await button(wrapper, 'Backup und Wiederherstellung').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('Synchronisierung ist nicht automatisch ein Backup')
    await button(wrapper, 'Datenschutz').trigger('click')
    expect(wrapper.get('.detail-panel').text()).toContain('IP-Adressen, Gerätekennungen, Anmeldezeiten')
})

it('restores links and browser history while preserving preview, unrelated query values and hash', async () => {
    const { wrapper, router } = await render('/lesson?preview=1&other=kept&chapter=boundaries&selection=VPN#details')
    expect(wrapper.get('.detail-panel h3').text()).toBe('VPN')
    await button(wrapper, 'Firewall').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query).toMatchObject({ preview: '1', other: 'kept', chapter: 'boundaries', selection: 'Firewall' })
    expect(router.currentRoute.value.hash).toBe('#details')
    await button(wrapper, 'Datenschutz').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query.selection).toBeUndefined()
    router.back()
    await flushPromises()
    expect(wrapper.get('.detail-panel h3').text()).toBe('Firewall')
})

it('uses the first valid example when a chapter or selection is unknown', async () => {
    const { wrapper, router } = await render('/lesson?chapter=school&selection=BYOD')
    expect(wrapper.get('.detail-panel h3').text()).toBe('Vertraulichkeit')
    await router.push('/lesson?chapter=attacks&selection=unknown')
    expect(wrapper.get('.detail-panel h3').text()).toBe('Malware und Trojaner')
})
