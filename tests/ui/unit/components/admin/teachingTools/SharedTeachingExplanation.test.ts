import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter, RouterView } from 'vue-router'
import SharedTeachingExplanation from '@/pages/components/SharedTeachingExplanation.vue'

const wrappers = []
afterEach(() => { wrappers.splice(0).forEach(wrapper => wrapper.unmount()); vi.unstubAllGlobals() })

async function renderShared(suffix = '') {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/explanations/:token', component: SharedTeachingExplanation }] })
    await router.push(`/explanations/${'a'.repeat(48)}${suffix}`)
    await router.isReady()
    const wrapper = mount(RouterView, { attachTo: document.body, global: { plugins: [router] } })
    wrappers.push(wrapper)
    await flushPromises()
    return { wrapper, router }
}

it.each([
    ['decimal-to-binary', 'Dezimal auf Dual', '13'],
    ['decimal-to-hexadecimal', 'Dezimal auf Hexadezimal', '254'],
    ['binary-to-hexadecimal', 'Dual auf Hexadezimal', '110101'],
    ['hexadecimal-to-binary', 'Hexadezimal auf Dual', '#AF'],
    ['binary-to-decimal', 'Dual auf Dezimal', '1101'],
    ['hexadecimal-to-decimal', 'Hexadezimal auf Dezimal', '1A3'],
])('renders the server-authorized %s explanation without teacher management', async (key, title, initialInput) => {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { explanation: key } }) })
    const { wrapper } = await renderShared()
    await vi.waitFor(() => expect(wrapper.get('h1').text()).toBe(title))
    expect(wrapper.get('input').element.value).toBe(initialInput)
    expect(wrapper.text()).not.toContain('Veröffentlichen')
    expect(wrapper.text()).not.toContain('Schüleransicht testen')
})

it.each([
    ['network-fundamentals', 'Netzwerkgrundlagen und Geräte'],
    ['network-media', 'Übertragungsmedien, LAN und WLAN'],
    ['network-communication', 'Datenkommunikation und Internet'],
])('renders the authorized %s learning area in the student view', async (explanation, title) => {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { explanation } }) })
    const { wrapper } = await renderShared()
    await vi.waitFor(() => expect(wrapper.get('h1').text()).toBe(title))
    expect(wrapper.text()).not.toContain('Veröffentlichen')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
})

it('renders the authorized neural network explanation in an isolated interactive frame', async () => {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { explanation: 'neural-network-learning' } }) })
    const { wrapper } = await renderShared()
    await vi.waitFor(() => expect(wrapper.get('iframe').attributes('title')).toBe('Wie ein neuronales Netz lernt'))
    expect(wrapper.get('iframe').attributes('sandbox')).toBe('allow-scripts')
    expect(wrapper.get('iframe').attributes('srcdoc')).toContain('Alle inneren Neuronen rechnen')
    expect(wrapper.text()).not.toContain('Veröffentlichen')
})

it('accepts frame size updates only from the explanation itself', async () => {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { explanation: 'neural-network-learning' } }) })
    const { wrapper } = await renderShared()
    await vi.waitFor(() => expect(wrapper.find('iframe').exists()).toBe(true))
    const frame = wrapper.get('iframe')
    expect(frame.element.contentWindow).not.toBeNull()

    window.dispatchEvent(new MessageEvent('message', { data: { type: 'schooltool-neural-network-height', height: 2100 } }))
    await flushPromises()
    expect(frame.element.style.height).toBe('1400px')

    window.dispatchEvent(new MessageEvent('message', { source: frame.element.contentWindow, data: { type: 'schooltool-neural-network-height', height: 2100 } }))
    await flushPromises()
    expect(frame.element.style.height).toBe('2100px')
})

it('restores the requested step and keeps other URL state while navigating', async () => {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { explanation: 'neural-network-learning' } }) })
    const { wrapper, router } = await renderShared('?lernschritt=8&other=kept#section')
    await vi.waitFor(() => expect(wrapper.find('iframe').exists()).toBe(true))
    const frame = wrapper.get('iframe')
    const postMessage = vi.spyOn(frame.element.contentWindow, 'postMessage')

    await frame.trigger('load')
    expect(postMessage).toHaveBeenCalledWith({ type: 'schooltool-neural-network-show-step', step: 8 }, '*')

    window.dispatchEvent(new MessageEvent('message', { source: frame.element.contentWindow, data: { type: 'schooltool-neural-network-step', step: 9 } }))
    await vi.waitFor(() => expect(router.currentRoute.value.query.lernschritt).toBe('9'))
    expect(router.currentRoute.value.query.other).toBe('kept')
    expect(router.currentRoute.value.hash).toBe('#section')
    expect(window.axios.get).toHaveBeenCalledTimes(1)

    window.dispatchEvent(new MessageEvent('message', { data: { type: 'schooltool-neural-network-step', step: 5 } }))
    window.dispatchEvent(new MessageEvent('message', { source: frame.element.contentWindow, data: { type: 'schooltool-neural-network-step', step: 22 } }))
    await flushPromises()
    expect(router.currentRoute.value.query.lernschritt).toBe('9')

    await router.push({ query: { ...router.currentRoute.value.query, lernschritt: '4' }, hash: '#section' })
    await vi.waitFor(() => expect(postMessage).toHaveBeenCalledWith({ type: 'schooltool-neural-network-show-step', step: 4 }, '*'))
    window.dispatchEvent(new MessageEvent('message', { source: frame.element.contentWindow, data: { type: 'schooltool-neural-network-step', step: 21 } }))
    await vi.waitFor(() => expect(router.currentRoute.value.query.lernschritt).toBe('21'))

    const restored = await renderShared(router.currentRoute.value.fullPath.split('a'.repeat(48))[1])
    await vi.waitFor(() => expect(restored.wrapper.find('iframe').exists()).toBe(true))
    const restoredFrame = restored.wrapper.get('iframe')
    const restoredMessage = vi.spyOn(restoredFrame.element.contentWindow, 'postMessage')
    await restoredFrame.trigger('load')
    expect(restoredMessage).toHaveBeenCalledWith({ type: 'schooltool-neural-network-show-step', step: 21 }, '*')
})

it.each(['0', '22', '-1', '1.5', 'abc'])('starts at the first step for invalid URL value %s', async invalidStep => {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { explanation: 'neural-network-learning' } }) })
    const { wrapper } = await renderShared(`?lernschritt=${invalidStep}`)
    await vi.waitFor(() => expect(wrapper.find('iframe').exists()).toBe(true))
    const frame = wrapper.get('iframe')
    const postMessage = vi.spyOn(frame.element.contentWindow, 'postMessage')
    await frame.trigger('load')
    expect(postMessage).toHaveBeenCalledWith({ type: 'schooltool-neural-network-show-step', step: 1 }, '*')
})

it.each([401, 404])('does not render the explanation when access is refused with %s', async status => {
    vi.stubGlobal('axios', { get: vi.fn().mockRejectedValue({ response: { status } }) })
    const { wrapper } = await renderShared()
    expect(wrapper.find('.binary-explanation').exists()).toBe(false)
    expect(wrapper.get('[role="alert"]').text()).toContain(status === 401 ? 'Schooltool-Konto' : 'nicht mehr verfügbar')
})

it('does not use an older access response after the link changes', async () => {
    let resolveOld
    vi.stubGlobal('axios', { get: vi.fn().mockImplementationOnce(() => new Promise(resolve => { resolveOld = resolve })).mockRejectedValue({ response: { status: 404 } }) })
    const { wrapper, router } = await renderShared()
    await router.push(`/explanations/${'b'.repeat(48)}`)
    await flushPromises()
    resolveOld({ data: { explanation: 'decimal-to-binary' } })
    await flushPromises()
    expect(wrapper.find('.binary-explanation').exists()).toBe(false)
    expect(wrapper.get('[role="alert"]').text()).toContain('nicht mehr verfügbar')
})

it.each(['curriculum', 'teaching', 'course'])('loads the protected %s assignment context and renders hexadecimal', async materialContext => {
    const returnUrl = materialContext === 'course' ? '/student/course/21' : materialContext === 'curriculum' ? '/admin/teaching/curricula?curriculum=9' : '/admin/teaching?course=21&panel=table'
    const get = vi.fn().mockResolvedValue({ data: { explanation: 'decimal-to-hexadecimal', return_url: returnUrl } })
    vi.stubGlobal('axios', { get })
    const path = `/learning-materials/${materialContext}/73`
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/learning-materials/:context/:material', component: SharedTeachingExplanation, props: { materialContext } }] })
    await router.push(path)
    const wrapper = mount(RouterView, { global: { plugins: [router] } })
    wrappers.push(wrapper)
    await vi.waitFor(() => expect(wrapper.get('h1').text()).toBe('Dezimal auf Hexadezimal'))
    expect(get).toHaveBeenCalledWith(path, { headers: { Accept: 'application/json' } })
    expect(wrapper.get('input').element.value).toBe('254')
    expect(wrapper.get('[aria-label="Erklärung schließen"]').attributes('href')).toBe(returnUrl)
})
