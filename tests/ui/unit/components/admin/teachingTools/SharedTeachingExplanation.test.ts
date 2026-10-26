import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter, RouterView } from 'vue-router'
import SharedTeachingExplanation from '@/pages/components/SharedTeachingExplanation.vue'

const wrappers = []
afterEach(() => { wrappers.splice(0).forEach(wrapper => wrapper.unmount()); vi.unstubAllGlobals() })

async function renderShared() {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/explanations/:token', component: SharedTeachingExplanation }] })
    await router.push(`/explanations/${'a'.repeat(48)}`)
    await router.isReady()
    const wrapper = mount(RouterView, { global: { plugins: [router] } })
    wrappers.push(wrapper)
    await flushPromises()
    return { wrapper, router }
}

it.each([
    ['decimal-to-binary', 'Dezimal auf Dual', '13'],
    ['decimal-to-hexadecimal', 'Dezimal auf Hexadezimal', '254'],
    ['binary-to-hexadecimal', 'Dual auf Hexadezimal', '110101'],
    ['hexadecimal-to-binary', 'Hexadezimal auf Dual', '#AF'],
])('renders the server-authorized %s explanation without teacher management', async (key, title, initialInput) => {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { explanation: key } }) })
    const { wrapper } = await renderShared()
    await vi.waitFor(() => expect(wrapper.get('h1').text()).toBe(title))
    expect(wrapper.get('input').element.value).toBe(initialInput)
    expect(wrapper.text()).not.toContain('Veröffentlichen')
    expect(wrapper.text()).not.toContain('Schüleransicht testen')
})

it('renders the authorized network learning area in the student view', async () => {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { explanation: 'network-fundamentals' } }) })
    const { wrapper } = await renderShared()
    await vi.waitFor(() => expect(wrapper.get('h1').text()).toContain('Netzwerk'))
    expect(wrapper.text()).not.toContain('Veröffentlichen')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
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
