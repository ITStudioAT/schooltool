import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import Synchronisation from '@/pages/admin/restaurant/components/Synchronisation.vue'

vi.mock('@/stores/admin/restaurant/RestaurantStore', () => ({
    useRestaurantStore: () => ({ loadSettings: vi.fn() }),
}))

function mountSynchronisation() {
    const post = vi.fn()
    vi.stubGlobal('axios', { post })
    const wrapper = mount(Synchronisation, {
        global: {
            stubs: {
                ItsGridBox: { template: '<div><slot /></div>' },
                'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                'v-checkbox': {
                    props: ['modelValue'],
                    emits: ['update:modelValue'],
                    template: '<input type="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" />',
                },
            },
        },
    })
    return { wrapper, post }
}

function reviewedPreview(removedLinks = 0) {
    return { data: { data: {
        token: 'reviewed-token', school: 'Fixture School', captured_at: '2026-09-29T12:00:00Z',
        summary: [], files: 0, reused_student_accounts: 0, removed_student_links: removedLinks,
    } } }
}

afterEach(() => vi.unstubAllGlobals())

describe('Restaurant synchronisation preflight', () => {
    it('shows all reported conflicts and offers no import when preview validation fails', async () => {
        const { wrapper, post } = mountSynchronisation()
        post.mockRejectedValueOnce({ response: { data: {
            message: 'Mehrere Konflikte gefunden.', conflicts: ['Schuljahr fehlt.', 'Bilddatei wurde verändert.'],
        } } })
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(wrapper.findAll('li').map(item => item.text())).toEqual(['Schuljahr fehlt.', 'Bilddatei wurde verändert.'])
        expect(wrapper.text()).not.toContain('Geprüften Stand übernehmen')
        expect(post).toHaveBeenCalledTimes(1)
    })

    it('explains planned student link removals while waiting for confirmation', async () => {
        const { wrapper, post } = mountSynchronisation()
        post.mockResolvedValueOnce(reviewedPreview(2))
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('Bei 2 Benutzerkonten wird die lokale Schülerzuordnung entfernt')
        expect(wrapper.text()).toContain('Die Schülerdatensätze und Unterrichtsdaten bleiben erhalten')
        expect(wrapper.findAll('button')[1].attributes('disabled')).toBeDefined()
        expect(post).toHaveBeenCalledTimes(1)
    })

    it('invalidates a reviewed preview when the import recheck returns new conflicts', async () => {
        const { wrapper, post } = mountSynchronisation()
        post.mockResolvedValueOnce(reviewedPreview())
        await wrapper.get('button').trigger('click')
        await flushPromises()
        await wrapper.get('input').setValue(true)
        post.mockRejectedValueOnce({ response: { data: {
            message: 'Die Prüfung hat mehrere Konflikte gefunden.', conflicts: ['Zuordnung wurde verändert.', 'Bilddatei wurde verändert.'],
        } } })
        await wrapper.findAll('button')[1].trigger('click')
        await flushPromises()
        expect(wrapper.findAll('li')).toHaveLength(2)
        expect(wrapper.find('input').exists()).toBe(false)
        expect(wrapper.text()).not.toContain('Geprüften Stand übernehmen')
        post.mockResolvedValueOnce(reviewedPreview())
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(wrapper.findAll('li')).toHaveLength(0)
        expect(wrapper.get('input').element.checked).toBe(false)
    })

    it('keeps ordinary single errors readable', async () => {
        const { wrapper, post } = mountSynchronisation()
        post.mockRejectedValueOnce({ response: { data: { message: 'Die geschützte Leseverbindung fehlt.' } } })
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('Die geschützte Leseverbindung fehlt.')
        expect(wrapper.findAll('li')).toHaveLength(0)
    })
})
