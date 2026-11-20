import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import WorkHandshakeDraft from '@/pages/admin/teaching/overview/components/WorkHandshakeDraft.vue'

vi.mock('vue-filepond/dist/vue-filepond.js', () => ({ default: () => ({
    name: 'FilePond', props: ['disabled', 'labelIdle', 'allowMultiple', 'maxFiles', 'allowPaste', 'dropValidation', 'beforeDropFile'], emits: ['updatefiles', 'warning'],
    template: '<div data-testid="handshake-filepond" />',
}) }))

const contents = JSON.stringify({ schema_version: 1, module_id: 'a263b873-4f27-4167-90f0-7d94ea0f67fa' })
const file = (name = 'handshake.json', text = contents, size = text.length) => ({ name, size, text: async () => text })

async function drop(wrapper: ReturnType<typeof mount>, files: unknown[]) {
    wrapper.getComponent({ name: 'FilePond' }).vm.$emit('updatefiles', files.map(item => ({ file: item })))
    await flushPromises()
}

describe('work handshake draft', () => {
    it('uses the existing selection surface and accepts local files rather than dropped links', () => {
        const wrapper = mount(WorkHandshakeDraft)
        const pond = wrapper.getComponent({ name: 'FilePond' })
        expect(pond.props('labelIdle')).toContain('Datei auswählen')
        expect(pond.props('allowMultiple')).toBe(false)
        expect(pond.props('allowPaste')).toBe(false)
        expect(pond.props('dropValidation')).toBe(true)
        const accepts = pond.props('beforeDropFile') as (value: unknown) => boolean
        expect(accepts(new File([contents], 'handshake.json', { type: 'application/json' }))).toBe(true)
        expect(accepts('https://example.test/handshake.json')).toBe(false)
    })
    it('accepts one valid handshake and emits its actual contents without saving anything', async () => {
        const wrapper = mount(WorkHandshakeDraft)
        await drop(wrapper, [file()])
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([{ filename: 'handshake.json', contents }])
        expect(wrapper.emitted('state')?.some(event => event[0].busy)).toBe(true)
        expect(wrapper.emitted('state')?.at(-1)).toEqual([{ busy: false, error: '' }])
        expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    })

    it('also accepts a file selected using the file input', async () => {
        const wrapper = mount(WorkHandshakeDraft)
        wrapper.getComponent({ name: 'FilePond' }).vm.$emit('updatefiles', [{ file: file() }])
        await flushPromises()
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([{ filename: 'handshake.json', contents }])
    })

    it('reports invalid names, oversized or invalid content and multiple files', async () => {
        const wrapper = mount(WorkHandshakeDraft)
        await drop(wrapper, [file('andere.json')])
        expect(wrapper.get('[role="alert"]').text()).toContain('handshake.json')
        await drop(wrapper, [file('handshake.json', contents, 4097)])
        expect(wrapper.get('[role="alert"]').text()).toContain('4 KB')
        await drop(wrapper, [file('handshake.json', '{}')])
        expect(wrapper.get('[role="alert"]').text()).toContain('Modulkennung')
        await drop(wrapper, [file(), file()])
        expect(wrapper.get('[role="alert"]').text()).toContain('genau eine')
        expect(wrapper.emitted('update:modelValue')?.every(event => event[0] === null)).toBe(true)
        expect(wrapper.emitted('state')?.at(-1)?.[0].error).toContain('genau eine')
    })

    it('reports a multiple-file drop rejected by FilePond and clears the draft', async () => {
        const wrapper = mount(WorkHandshakeDraft, { props: { modelValue: { filename: 'handshake.json', contents } } })
        wrapper.getComponent({ name: 'FilePond' }).vm.$emit('warning')
        await flushPromises()
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([null])
        expect(wrapper.get('[role="alert"]').text()).toContain('genau eine')
    })

    it('ignores a late result after selecting a different file', async () => {
        const wrapper = mount(WorkHandshakeDraft)
        let finish!: (value: string) => void
        const slow = { name: 'handshake.json', size: contents.length, text: () => new Promise<string>(resolve => { finish = resolve }) }
        wrapper.getComponent({ name: 'FilePond' }).vm.$emit('updatefiles', [{ file: slow }])
        const replacement = contents.replace('a263b873', 'b263b873')
        await drop(wrapper, [file('Handshake.json', replacement)])
        finish(contents)
        await flushPromises()
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([{ filename: 'Handshake.json', contents: replacement }])
        expect(wrapper.emitted('state')?.at(-1)).toEqual([{ busy: false, error: '' }])
    })

    it('discards a pending read when the component is closed', async () => {
        const changed = vi.fn()
        const wrapper = mount(WorkHandshakeDraft, { props: { 'onUpdate:modelValue': changed } })
        let finish!: (value: string) => void
        const slow = { name: 'handshake.json', size: contents.length, text: () => new Promise<string>(resolve => { finish = resolve }) }
        wrapper.getComponent({ name: 'FilePond' }).vm.$emit('updatefiles', [{ file: slow }])
        wrapper.unmount()
        finish(contents)
        await flushPromises()
        expect(changed.mock.calls.every(event => event[0] === null)).toBe(true)
        expect(changed).toHaveBeenCalledTimes(1)
    })

    it('clears a selected draft and disallows drops while disabled', async () => {
        const wrapper = mount(WorkHandshakeDraft, { props: { modelValue: { filename: 'handshake.json', contents } } })
        await wrapper.get('button').trigger('click')
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([null])
        await wrapper.setProps({ modelValue: null, disabled: true })
        const count = wrapper.emitted('update:modelValue')?.length
        await drop(wrapper, [file()])
        expect(wrapper.emitted('update:modelValue')?.length).toBe(count)
        expect(wrapper.getComponent({ name: 'FilePond' }).props('disabled')).toBe(true)
    })
})
