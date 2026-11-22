import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, expect, it, vi } from 'vitest'
import WorkProvisionMethod from '@/pages/admin/teaching/overview/components/WorkProvisionMethod.vue'

const portal = { channel: 'portal', email: null }
const email = { channel: 'email', email: 'abgabe@example.test' }
const work = { id: 42, status: {} }
const combinations: [string, typeof portal | typeof email][] = [['portal', portal], ['portal', email], ['email', portal], ['email', email]]

afterEach(() => vi.unstubAllGlobals())

function render(put: ReturnType<typeof vi.fn>, portalAvailable = true, initialWork = work) {
    vi.stubGlobal('axios', { put })
    const wrapper = mount(WorkProvisionMethod, { props: { work: initialWork, portalAvailable }, global: { stubs: {
        'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
        'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' },
        'v-text-field': { props: ['modelValue'], emits: ['update:modelValue'],
            template: '<input type="email" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
    } } })
    const button = (text: string) => wrapper.findAll('button').find(item => item.text() === text)!
    const provision = (channel: string) => wrapper.get(`.provision-choices input[value=${channel}]`)
    const submission = (channel: string) => wrapper.get(`.submission-methods input[value=${channel}]`)
    return { wrapper, button, provision, submission }
}

it.each(combinations)(
    'stores provision %s with submission %j before opening the matching preparation',
    async (channel, method) => {
        const saved = { ...work, status: { provision_method: { channel }, submission_method: method } }
        const put = vi.fn().mockResolvedValue({ data: { data: saved } })
        const { wrapper, button, provision, submission } = render(put)
        try {
            await button('Bereitstellungsweg vorbereiten').trigger('click')
            await provision(channel).setValue()
            expect(button('Weiter').attributes('disabled')).toBeDefined()
            await button('Weiter').trigger('click')
            expect(put).not.toHaveBeenCalled()
            await submission(method.channel).setValue()
            expect((provision(channel).element as HTMLInputElement).checked).toBe(true)
            if (method.email) {
                expect(button('Weiter').attributes('disabled')).toBeDefined()
                await wrapper.get('input[type=email]').setValue(method.email)
            }
            await button('Weiter').trigger('click')
            await flushPromises()
            expect(put).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/42/provision-method',
                { channel, expected: null, submission: method, expected_submission: null })
            expect(wrapper.emitted('saved')?.[0]).toEqual([saved])
            expect(wrapper.emitted(channel === 'portal' ? 'portal' : 'mail')).toHaveLength(1)
            expect(wrapper.emitted(channel === 'portal' ? 'mail' : 'portal')).toBeUndefined()
        } finally { wrapper.unmount() }
    },
)

it('reopens a saved choice without a remove action and lets the teacher change it before continuing', async () => {
    const initial = { ...work, status: { provision_method: { channel: 'portal' }, submission_method: portal } }
    const saved = { ...work, status: { provision_method: { channel: 'email' }, submission_method: portal } }
    const put = vi.fn().mockResolvedValue({ data: { data: saved } })
    const { wrapper, button, provision, submission } = render(put, true, initial)
    try {
        await button('Bereitstellungsweg vorbereiten').trigger('click')
        await button('Schließen').trigger('click')
        await button('Bereitstellungsweg vorbereiten').trigger('click')
        expect((provision('portal').element as HTMLInputElement).checked).toBe(true)
        expect((submission('portal').element as HTMLInputElement).checked).toBe(true)
        expect(wrapper.get('[role="dialog"]').findAll('button').map(action => action.text())).toEqual(['Schließen', 'Weiter'])
        expect(put).not.toHaveBeenCalled()
        await provision('email').setValue()
        expect((submission('portal').element as HTMLInputElement).checked).toBe(true)
        await button('Weiter').trigger('click')
        await flushPromises()
        expect(put).toHaveBeenCalledExactlyOnceWith(expect.any(String), {
            channel: 'email', expected: 'portal', submission: portal, expected_submission: portal,
        })
        expect(wrapper.emitted('saved')?.[0]).toEqual([saved])
        expect(wrapper.emitted('mail')).toHaveLength(1)
    } finally { wrapper.unmount() }
})

it('shows validation and concurrent changes and continues only with a matching receipt', async () => {
    const put = vi.fn()
        .mockRejectedValueOnce({ response: { status: 422, data: { errors: { 'submission.email': ['Adresse prüfen'] } } } })
        .mockRejectedValueOnce({ response: { status: 409, data: { current: 'portal', current_submission: portal, message: 'Auswahl geändert' } } })
        .mockResolvedValueOnce({ data: { data: { ...work, status: { provision_method: portal } } } })
        .mockResolvedValueOnce({ data: { data: { ...work, status: { provision_method: portal, submission_method: portal } } } })
    const { wrapper, button, provision, submission } = render(put)
    try {
        await button('Bereitstellungsweg vorbereiten').trigger('click')
        await provision('email').setValue()
        await submission('email').setValue()
        await wrapper.get('input[type=email]').setValue('ungültig')
        await button('Weiter').trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('Adresse prüfen')
        await wrapper.get('input[type=email]').setValue(email.email)
        await button('Weiter').trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('Auswahl geändert')
        expect((provision('portal').element as HTMLInputElement).checked).toBe(true)
        expect((submission('portal').element as HTMLInputElement).checked).toBe(true)
        await button('Weiter').trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('konnte nicht bestätigt werden')
        expect(wrapper.emitted('saved')).toBeUndefined()
        expect(wrapper.emitted('mail')).toBeUndefined()
        expect(wrapper.emitted('portal')).toBeUndefined()
        await button('Weiter').trigger('click')
        await flushPromises()
        expect(put).toHaveBeenLastCalledWith(expect.any(String), { channel: 'portal', expected: 'portal',
            submission: portal, expected_submission: portal })
        expect(wrapper.emitted('portal')).toHaveLength(1)
    } finally { wrapper.unmount() }
})
