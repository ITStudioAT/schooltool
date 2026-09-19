import { EditorContent } from '@tiptap/vue-3'
import { mount, type VueWrapper } from '@vue/test-utils'
import { nextTick } from 'vue'
import { afterEach, describe, expect, it } from 'vitest'
import ItsRichTextEditor from '@/components/ItsRichTextEditor.vue'

const wrappers: VueWrapper[] = []

async function mountEditor(modelValue: string) {
    const wrapper = mount(ItsRichTextEditor, {
        attachTo: document.body,
        props: { modelValue },
        global: {
            stubs: {
                'v-btn-group': { template: '<div><slot /></div>' },
                'v-btn': { template: '<button v-bind="$attrs"><slot /></button>' },
            },
        },
    })
    wrappers.push(wrapper)
    await nextTick()

    return { wrapper, editor: wrapper.getComponent(EditorContent).props('editor') }
}

afterEach(() => {
    for (const wrapper of wrappers.splice(0)) {
        wrapper.unmount()
        wrapper.element.remove()
    }
})

describe('ItsRichTextEditor with the installed Tiptap extensions', () => {
    it('loads plain text and preserves HTML supplied through the model', async () => {
        const { wrapper, editor } = await mountEditor('Erste Zeile\nZweite Zeile')

        expect(editor.getHTML()).toBe('<p>Erste Zeile</p><p>Zweite Zeile</p>')

        await wrapper.setProps({ modelValue: '<p><strong>Neuer</strong> <u>Inhalt</u></p>' })

        expect(editor.getHTML()).toBe('<p><strong>Neuer</strong> <u>Inhalt</u></p>')
        expect(wrapper.get('.ProseMirror strong').text()).toBe('Neuer')
        expect(wrapper.get('.ProseMirror u').text()).toBe('Inhalt')
    })

    it('formats a selection and emits the list HTML from the toolbar', async () => {
        const { wrapper, editor } = await mountEditor('<p>Unterricht</p>')
        editor.commands.selectAll()

        await wrapper.get('[title="Fett"]').trigger('click')
        await wrapper.get('[title="Unterstrichen"]').trigger('click')
        await wrapper.get('[title="Aufzählung"]').trigger('click')

        expect(wrapper.get('.ProseMirror ul li strong').text()).toBe('Unterricht')
        expect(wrapper.get('.ProseMirror ul li u').text()).toBe('Unterricht')
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([editor.getHTML()])

        await wrapper.get('[title="Nummerierte Liste"]').trigger('click')

        expect(wrapper.find('.ProseMirror ul').exists()).toBe(false)
        expect(wrapper.get('.ProseMirror ol li').text()).toBe('Unterricht')
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([editor.getHTML()])
    })

    it('moves a following paragraph into the last list item with Tab at its start', async () => {
        const { wrapper, editor } = await mountEditor('<ul><li><p>Erster</p></li></ul><p>Zweiter</p>')
        editor.commands.setTextSelection(editor.state.doc.firstChild.nodeSize + 1)

        editor.commands.keyboardShortcut('Tab')
        await nextTick()

        expect(wrapper.findAll('.ProseMirror ul li')).toHaveLength(1)
        expect(wrapper.findAll('.ProseMirror ul li p').map((paragraph) => paragraph.text())).toEqual([
            'Erster',
            'Zweiter',
        ])
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([editor.getHTML()])
    })

    it('preserves a following paragraph when Tab is pressed in its middle', async () => {
        const { editor } = await mountEditor('<ul><li><p>Erster</p></li></ul><p>Zweiter</p>')
        const html = editor.getHTML()
        editor.commands.setTextSelection(editor.state.doc.firstChild.nodeSize + 3)

        editor.commands.keyboardShortcut('Tab')

        expect(editor.getHTML()).toBe(html)
    })
})
