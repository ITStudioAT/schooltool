import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import Matura from '@/pages/admin/matura/Matura.vue'

describe('Matura page', () => {
    it('renders only the Matura heading', () => {
        const wrapper = mount(Matura)

        expect(wrapper.get('h1').text()).toBe('Matura')
        expect(wrapper.text()).toBe('Matura')
        expect(wrapper.findAll('form, input, button, a')).toHaveLength(0)

        wrapper.unmount()
    })
})
