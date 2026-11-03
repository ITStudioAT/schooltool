import { afterEach, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import BinaryToDecimalExplanation from '@/pages/admin/teachingTools/BinaryToDecimalExplanation.vue'
import HexadecimalToDecimalExplanation from '@/pages/admin/teachingTools/HexadecimalToDecimalExplanation.vue'
import DecimalConversionExplanation from '@/pages/admin/teachingTools/DecimalConversionExplanation.vue'
import NibbleConversionExplanation from '@/pages/admin/teachingTools/NibbleConversionExplanation.vue'

const wrappers: ReturnType<typeof mount>[] = []
afterEach(() => wrappers.splice(0).forEach(wrapper => wrapper.unmount()))

const cases = [
    ['Dual auf Dezimal', BinaryToDecimalExplanation, {}, '1011'],
    ['Hexadezimal auf Dezimal', HexadecimalToDecimalExplanation, {}, 'A7'],
    ['Dezimal auf Dual', DecimalConversionExplanation, { base: 2 }, '25'],
    ['Dezimal auf Hexadezimal', DecimalConversionExplanation, { base: 16 }, '25'],
    ['Dual auf Hexadezimal', NibbleConversionExplanation, { sourceBase: 2 }, '1011'],
    ['Hexadezimal auf Dual', NibbleConversionExplanation, { sourceBase: 16 }, 'A7'],
]

it.each(cases)('%s restores the selected number and step, preserves other URL state, and supports browser back', async (_title, component, props, value) => {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/lesson', component }] })
    await router.push(`/lesson?preview=1&other=keep&value=${value}&step=2#part`)
    await router.isReady()
    const wrapper = mount(component, { props, global: { plugins: [router] } })
    wrappers.push(wrapper)
    expect((wrapper.get('input').element as HTMLInputElement).value.replace('#', '')).toBe(value)
    expect(wrapper.text()).toContain('Schritt 3 von')
    const next = wrapper.findAll('button').find(button => button.text().includes('Weiter'))!
    await next.trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query).toMatchObject({ preview: '1', other: 'keep', value, step: '3' })
    expect(router.currentRoute.value.hash).toBe('#part')
    router.back()
    await new Promise(resolve => setTimeout(resolve, 10))
    await flushPromises()
    expect(wrapper.text()).toContain('Schritt 3 von')
})

it('rejects an invalid number or an out-of-range step from the URL', async () => {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/lesson', component: BinaryToDecimalExplanation }] })
    await router.push('/lesson?value=102&step=999')
    await router.isReady()
    const wrapper = mount(BinaryToDecimalExplanation, { global: { plugins: [router] } })
    wrappers.push(wrapper)
    expect((wrapper.get('input').element as HTMLInputElement).value).toBe('1101')
    expect(wrapper.text()).toContain('Schritt 1 von')
    await router.push('/lesson?value=10&step=999')
    await flushPromises()
    expect((wrapper.get('input').element as HTMLInputElement).value).toBe('10')
    expect(wrapper.text()).toContain('Schritt 1 von')
})

it('stores an accepted number and keeps the latest step when advancing quickly', async () => {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/lesson', component: BinaryToDecimalExplanation }] })
    await router.push('/lesson?preview=1')
    await router.isReady()
    const wrapper = mount(BinaryToDecimalExplanation, { global: { plugins: [router] } })
    wrappers.push(wrapper)
    await wrapper.get('input').setValue('10101')
    await wrapper.get('form').trigger('submit')
    const next = wrapper.findAll('button').find(button => button.text().includes('Weiter'))!
    await next.trigger('click')
    await next.trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query).toMatchObject({ preview: '1', value: '10101', step: '2' })
    expect(wrapper.text()).toContain('Schritt 3 von')
})
