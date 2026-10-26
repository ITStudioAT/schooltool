import { afterEach, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import BinaryToDecimalExplanation from '@/pages/admin/teachingTools/BinaryToDecimalExplanation.vue'

const wrappers: ReturnType<typeof mount>[] = []
function render() { const wrapper = mount(BinaryToDecimalExplanation); wrappers.push(wrapper); return wrapper }
function button(wrapper, name) { return wrapper.findAll('button').find(item => item.text().includes(name))! }
afterEach(() => { wrappers.splice(0).forEach(wrapper => wrapper.unmount()) })

it('explains each binary place from right to left and adds the values of the ones', async () => {
    const wrapper = render()
    expect(wrapper.get('h1').text()).toBe('Dual auf Dezimal')
    expect(wrapper.findAll('.place-number').map(cell => cell.text())).toEqual(['8', '4', '2', '1'])
    expect(wrapper.findAll('.place-power').map(cell => cell.text())).toEqual(['23', '22', '21', '20'])
    expect(wrapper.text()).toContain('2 hoch 0 ist 1')
    expect(button(wrapper, 'Zurück').attributes('disabled')).toBeDefined()
    await button(wrapper, 'Weiter').trigger('click')
    expect(wrapper.get('.place-instruction').text()).toContain('Die 1 steht an der Stelle 1.')
    expect(wrapper.get('.place-instruction').text()).toContain('20 = 1')
    expect(wrapper.get('.place-sum').text()).toContain('1 = 1')
    await button(wrapper, 'Weiter').trigger('click')
    expect(wrapper.get('.place-instruction').text()).toContain('wird nicht mitgezählt')
    expect(wrapper.get('.place-instruction').text()).toContain('21 = 2')
    expect(wrapper.get('.place-sum').text()).toContain('1 = 1')
    await button(wrapper, 'Weiter').trigger('click')
    await button(wrapper, 'Weiter').trigger('click')
    await button(wrapper, 'Weiter').trigger('click')
    expect(wrapper.get('.place-result').text()).toBe('11012 = 13')
    expect(wrapper.get('.place-sum').text()).toContain('8 + 4 + 1 = 13')
    expect(button(wrapper, 'Weiter').attributes('disabled')).toBeDefined()
    await button(wrapper, 'Zurück').trigger('click')
    expect(wrapper.find('.place-result').exists()).toBe(false)
    await button(wrapper, 'Neu starten').trigger('click')
    expect(wrapper.get('.place-instruction').text()).toContain('Jede Stelle hat einen Wert')
})

it('accepts zero, leading zeroes and the largest supported number while rejecting invalid input', async () => {
    const wrapper = render()
    for (const value of ['0', '00101', '1111111111111111']) {
        await wrapper.get('input').setValue(value)
        await wrapper.get('form').trigger('submit')
        for (let index = 0; index < value.length + 1; index++) await button(wrapper, 'Weiter').trigger('click')
        expect(wrapper.get('.place-result').text()).toBe(`${value}2 = ${parseInt(value, 2)}`)
    }
    await wrapper.get('input').setValue('102')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.get('[role="alert"]').text()).toContain('nur 0 und 1')
    expect(wrapper.get('.place-result').text()).toBe('11111111111111112 = 65535')
    await wrapper.get('input').setValue('1'.repeat(17))
    await wrapper.get('form').trigger('submit')
    expect(wrapper.get('[role="alert"]').text()).toContain('1 bis 16')
})
