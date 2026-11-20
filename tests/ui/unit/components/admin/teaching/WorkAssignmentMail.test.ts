import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import WorkAssignmentMail from '@/pages/admin/teaching/overview/components/WorkAssignmentMail.vue'

const work = { id: 113, title: 'Dateiverwaltung', date_for_all_groups: '2026-11-20', groups: [], status: {} }
const stubs = {
    'v-btn': { template: '<button><slot /></button>' },
    'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' },
    'v-checkbox': { props: ['label', 'modelValue'], emits: ['update:modelValue'], template: '<label><input type="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" />{{ label }}</label>' },
    'v-select': { props: ['items', 'modelValue'], emits: ['update:modelValue'], template: '<select :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><option v-for="item in items" :value="item.value">{{ item.title }}</option></select>' },
}
const m = { a: { to: 'a@example.test', subject: 'Blatt A', html: '<p>Persönlich A</p>', text: 'Text A' },
    b: { to: 'b@example.test', subject: 'Blatt B', html: '<p>Persönlich B</p>', text: 'Text B' } }
const draft = { batch: { id: 'draft', transport: 'postmark', state: 'draft', recipients: [{ key: 'a', state: 'draft' }, { key: 'b', state: 'draft' }] },
    from: 'Lehrkraft', reply_to: 'lehrkraft@example.test', messages: m, attachments: ['Angaben.pdf'] }
const response = (preview: unknown) => ({ data: { data: work, preview, local: false } })
const button = (wrapper: ReturnType<typeof mount>, label: string) => wrapper.findAll('button').find(b => b.text() === label)!
afterEach(() => vi.unstubAllGlobals())

it('supports opening from the provision choice while keeping its own activator hidden', async () => {
    const get = vi.fn().mockResolvedValue(response(draft))
    const post = vi.fn()
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkAssignmentMail, { props: { work, hideActivator: true }, global: { stubs } })
    try {
        expect(wrapper.find('button').exists()).toBe(false)
        expect(get).not.toHaveBeenCalled()
        wrapper.vm.open()
        await flushPromises()
        expect(get).toHaveBeenCalledTimes(1)
        expect(wrapper.get('iframe').attributes('srcdoc')).toBe(m.a.html)
        await button(wrapper, 'Schließen').trigger('click')
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
        expect(post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('opens with a saved read, allows inspection of every draft, and queues only on a separate send click', async () => {
    const get = vi.fn().mockResolvedValue(response(null))
    const post = vi.fn().mockResolvedValueOnce(response(draft)).mockResolvedValueOnce(response({ ...draft, batch: { ...draft.batch, state: 'sending' } }))
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkAssignmentMail, { props: { work }, global: { stubs } })
    try {
        expect(get).not.toHaveBeenCalled()
        await button(wrapper, 'E-Mails vorbereiten').trigger('click')
        await flushPromises()
        expect(get).toHaveBeenCalledTimes(1)
        expect(post).not.toHaveBeenCalled()
        await button(wrapper, 'Entwürfe vorbereiten').trigger('click')
        await flushPromises()
        expect(post).toHaveBeenCalledTimes(1)
        expect(wrapper.get('iframe').attributes('srcdoc')).toBe(m.a.html)
        expect(wrapper.text()).toContain('Angaben.pdf')
        expect(wrapper.get('a[download]').attributes('href')).toContain('/assignment-mail/draft/attachments/0')
        await wrapper.get('select').setValue('b')
        expect(wrapper.get('iframe').attributes('srcdoc')).toBe(m.b.html)
        expect(wrapper.text()).toContain('Text B')
        await button(wrapper, 'An Schüler:innen senden').trigger('click')
        await flushPromises()
        expect(post.mock.calls[1][1]).toEqual({ date: '2026-11-20', batch_id: 'draft', action: 'send', confirmed: true })
        expect(wrapper.text()).toContain('Versand läuft')
    } finally { wrapper.unmount() }
})

it('requires a separate selection before sending to a previously notified recipient', async () => {
    const previous = { ...draft, batch: { ...draft.batch, recipients: [{ key: 'a', state: 'draft', previously_sent_at: '2026-11-12' }] } }
    const post = vi.fn().mockResolvedValue(response(previous))
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue(response(previous)), post })
    const wrapper = mount(WorkAssignmentMail, { props: { work }, global: { stubs } })
    try {
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(button(wrapper, 'An Schüler:innen senden')).toBeUndefined()
        await wrapper.get('input[type=checkbox]').setValue(true)
        await button(wrapper, 'An Schüler:innen senden').trigger('click')
        expect(post.mock.calls[0][1].include_previously_sent).toBe(true)
    } finally { wrapper.unmount() }
})

it('keeps confirmed historical sends, can withdraw access without sending, and hides a repeat-send action', async () => {
    const completed = { ...draft, batch: { ...draft.batch, state: 'sent', recipients: draft.batch.recipients.map(r => ({ ...r, state: 'accepted' })) } }
    const get = vi.fn().mockResolvedValue(response(completed))
    const post = vi.fn().mockResolvedValue(response({ ...completed, batch: { ...completed.batch, withdrawn_at: '2026-11-20' } }))
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkAssignmentMail, { props: { work }, global: { stubs } })
    try {
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(button(wrapper, 'Entwürfe verwerfen')).toBeUndefined()
        expect(button(wrapper, 'An Schüler:innen senden')).toBeUndefined()
        await button(wrapper, 'Verteilung zurückziehen').trigger('click')
        await flushPromises()
        expect(post.mock.calls[0][1].action).toBe('withdraw')
        expect(wrapper.text()).toContain('2 von 2')
        expect(button(wrapper, 'Verteilung wieder öffnen')).toBeDefined()
    } finally { wrapper.unmount() }
})
