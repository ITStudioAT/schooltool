import { afterEach, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import HexadecimalToDecimalExplanation from '@/pages/admin/teachingTools/HexadecimalToDecimalExplanation.vue'

const wrappers: ReturnType<typeof mount>[] = []
function render() { const wrapper = mount(HexadecimalToDecimalExplanation); wrappers.push(wrapper); return wrapper }
function button(wrapper, name) { return wrapper.findAll('button').find(item => item.text().includes(name))! }
afterEach(() => wrappers.splice(0).forEach(wrapper => wrapper.unmount()))

it('shows each positional value from right to left and adds them', async () => {
    const wrapper = render()
    expect(wrapper.get('h1').text()).toBe('Hexadezimal auf Dezimal')
    expect(wrapper.text()).toContain('A = 10')
    expect(wrapper.get('.instruction').text()).toContain('16 × 16 = 256')
    expect(wrapper.findAll('.place-label').map(label => label.text())).toEqual(['162 = 256', '161 = 16', '160 = 1'])
    expect(wrapper.findAll('.place-label sup').map(exponent => exponent.text())).toEqual(['2', '1', '0'])
    await button(wrapper, 'Weiter').trigger('click')
    expect(wrapper.get('.instruction').text()).toContain('3 × 1 = 3')
    expect(wrapper.get('.instruction sup').text()).toBe('0')
    await button(wrapper, 'Weiter').trigger('click')
    expect(wrapper.get('.instruction').text()).toContain('A entspricht dem Wert 10')
    expect(wrapper.get('.instruction').text()).toContain('10 × 16 = 160')
    expect(wrapper.get('.instruction sup').text()).toBe('1')
    await button(wrapper, 'Weiter').trigger('click')
    expect(wrapper.get('.instruction').text()).toContain('1 × 256 = 256')
    await button(wrapper, 'Weiter').trigger('click')
    expect(wrapper.get('.instruction').text()).toContain('256 + 160 + 3 = 419')
    expect(wrapper.get('.result').text()).toContain('#1A3 = 419')
    expect(button(wrapper, 'Weiter').attributes('disabled')).toBeDefined()
    await button(wrapper, 'Zurück').trigger('click')
    expect(wrapper.find('.result').exists()).toBe(false)
    await button(wrapper, 'Neu starten').trigger('click')
    expect(button(wrapper, 'Zurück').attributes('disabled')).toBeDefined()
})

it.each([['0', '0'], ['f', '15'], ['10', '16'], ['00FF', '255'], ['FFFF', '65535']])('converts %s to %s', async (input, expected) => {
    const wrapper = render()
    await wrapper.get('input').setValue(input)
    await wrapper.get('form').trigger('submit')
    for (let index = 0; index <= input.length; index++) await button(wrapper, 'Weiter').trigger('click')
    expect(wrapper.get('.result').text()).toContain(`= ${expected} im Dezimalsystem`)
})

it('rejects invalid or overly long input without changing the current number', async () => {
    const wrapper = render()
    for (const input of ['', 'G', '-1', '10000', '1 0']) {
        await wrapper.get('input').setValue(input)
        await wrapper.get('form').trigger('submit')
        expect(wrapper.get('[role="alert"]').text()).toContain('1 bis 4 Stellen')
    }
    await button(wrapper, 'Weiter').trigger('click')
    expect(wrapper.get('.instruction').text()).toContain('3 × 1 = 3')
})
