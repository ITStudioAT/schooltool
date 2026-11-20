import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, expect, it, vi } from 'vitest'
import WorkProvisionMethod from '@/pages/admin/teaching/overview/components/WorkProvisionMethod.vue'

const work = { id: 42, status: { submission_method: { channel: 'portal' } } }
const saved = { ...work, status: { ...work.status, provision_method: { channel: 'email' } } }
afterEach(() => vi.unstubAllGlobals())

function render(put: ReturnType<typeof vi.fn>, portalAvailable = true) {
    vi.stubGlobal('axios', { put })
    const wrapper = mount(WorkProvisionMethod, { props: { work, portalAvailable }, global: { stubs: {
        'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
    } } })
    const click = (text: string) => wrapper.findAll('button').find(b => b.text() === text)!.trigger('click')
    return { wrapper, click }
}

it('offers one starting action, stores the delivery choice, then opens only its preparation', async () => {
    const put = vi.fn().mockResolvedValue({ data: { data: saved } })
    const { wrapper, click } = render(put)
    await click('Bereitstellungsweg vorbereiten')
    expect(put).not.toHaveBeenCalled()
    expect(wrapper.findAll('input:checked')).toHaveLength(0)
    await wrapper.get('input[value=email]').setValue(true)
    await click('Weiter')
    await flushPromises()
    expect(put).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/42/provision-method', { channel: 'email', expected: null })
    expect(wrapper.emitted('saved')?.[0]).toEqual([saved])
    expect(wrapper.emitted('mail')).toHaveLength(1)
    expect(wrapper.emitted('portal')).toBeUndefined()
    await wrapper.setProps({ work: saved })
    await click('Bereitstellungsweg vorbereiten')
    expect((wrapper.get('input[value=email]').element as HTMLInputElement).checked).toBe(true)
    await click('Auswahl entfernen')
    await flushPromises()
    expect(put).toHaveBeenLastCalledWith(expect.any(String), { channel: null, expected: 'email' })
    expect(wrapper.emitted('mail')).toHaveLength(1)
    wrapper.unmount()
})

it.each([true, false])('opens the student preparation only when available (%s)', async portalAvailable => {
    const { wrapper, click } = render(vi.fn().mockResolvedValue({ data: { data: work } }), portalAvailable)
    await click('Bereitstellungsweg vorbereiten')
    await wrapper.get('input[value=portal]').setValue(true)
    await click(portalAvailable ? 'Weiter' : 'Auswahl speichern')
    await flushPromises()
    expect(Boolean(wrapper.emitted('portal'))).toBe(portalAvailable)
    expect(wrapper.emitted('saved')).toHaveLength(1)
    expect(wrapper.emitted('mail')).toBeUndefined()
    wrapper.unmount()
})

it('shows a concurrent selection for review without opening or changing a distribution', async () => {
    const put = vi.fn().mockRejectedValueOnce({ response: { status: 409, data: { current: 'portal', message: 'Auswahl geändert' } } })
    const { wrapper, click } = render(put)
    await click('Bereitstellungsweg vorbereiten')
    await wrapper.get('input[value=email]').setValue(true)
    await click('Weiter')
    await flushPromises()
    expect(wrapper.text()).toContain('Auswahl geändert')
    expect((wrapper.get('input[value=portal]').element as HTMLInputElement).checked).toBe(true)
    expect(wrapper.emitted('portal')).toBeUndefined()
    expect(wrapper.emitted('mail')).toBeUndefined()
    wrapper.unmount()
})
