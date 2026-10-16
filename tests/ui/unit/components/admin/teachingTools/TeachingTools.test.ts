import { afterEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter, RouterLink, RouterView } from 'vue-router'
import { routes } from '../../../../../../resources/routes/admin.js'

vi.mock('@/pages/admin/teachingTools/MultipleChoiceWorkspace.vue', () => ({
    default: { template: '<div data-test="mc-workspace">Vorhandener MC-Workflow</div>' },
}))
vi.mock('@/pages/admin/teachingTools/NumberSystemsGame.vue', () => ({
    default: { template: '<div data-test="number-systems">Signal Null · Neues Abenteuer</div>' },
}))

const wrappers = []
const navigationButton = defineComponent({
    props: ['to'],
    setup(props, { slots, attrs }) {
        return () => h(RouterLink, { ...attrs, to: props.to }, slots.default)
    },
})

afterEach(() => wrappers.splice(0).forEach(wrapper => wrapper.unmount()))

async function renderTools(path) {
    const router = createRouter({ history: createMemoryHistory(), routes: routes.filter(route => route.path.startsWith('/admin/teaching-tools')) })
    await router.push(path)
    await router.isReady()
    const wrapper = mount(RouterView, {
        global: { plugins: [router], stubs: { AdminPageHeader: true, 'v-btn': navigationButton,
            'v-sheet': { template: '<div><slot /></div>' }, 'v-container': { template: '<div><slot /></div>' } } },
    })
    wrappers.push(wrapper)
    await flushPromises()
    return { wrapper, router }
}

describe('Teaching tools games navigation', () => {
    it('keeps Multiple Choice as the default and opens the existing workspace', async () => {
        const { wrapper, router } = await renderTools('/admin/teaching-tools')

        expect(router.currentRoute.value.path).toBe('/admin/teaching-tools/multiple-choice')
        expect(wrapper.find('[data-test="mc-workspace"]').exists()).toBe(true)
        expect(wrapper.find('a[href="/admin/teaching-tools/multiple-choice"]').attributes('aria-pressed')).toBe('true')
        expect(wrapper.find('a[href="/admin/teaching-tools/games"]').text()).toBe('Spiele')
    })

    it('offers Zahlensysteme and opens the playable adventure workspace', async () => {
        const { wrapper, router } = await renderTools('/admin/teaching-tools/games')

        expect(wrapper.find('[data-test="mc-workspace"]').exists()).toBe(false)
        expect(wrapper.find('a[href="/admin/teaching-tools/games"]').attributes('aria-pressed')).toBe('true')
        expect(wrapper.findAll('[aria-label="Verfügbare Spiele"] a')).toHaveLength(1)
        expect(wrapper.find('[aria-label="Zahlensysteme öffnen"]').text()).toContain('Zahlensysteme')
        await wrapper.find('[aria-label="Zahlensysteme öffnen"]').trigger('click')
        await flushPromises()

        expect(router.currentRoute.value.path).toBe('/admin/teaching-tools/games/number-systems')
        expect(wrapper.get('[data-test="number-systems"]').text()).toContain('Neues Abenteuer')
        const back = wrapper.findAll('a').find(link => link.text() === 'Zur Spieleübersicht')
        await back.trigger('click')
        await flushPromises()
        expect(wrapper.get('h1').text()).toBe('Spiele')
        expect(wrapper.find('[aria-label="Zahlensysteme öffnen"]').exists()).toBe(true)
    })
})
