import { expect, it } from 'vitest'
import { defineComponent, h } from 'vue'
import { mount } from '@vue/test-utils'
import NumberSystemInvitation from '@/pages/components/numberSystems/NumberSystemInvitation.vue'

it('shows the exact invitation link and local-only warning and opens a larger QR', async () => {
    const dialog = defineComponent({ props: ['modelValue'], setup(props, { slots }) { return () => props.modelValue ? h('div', { role: 'dialog' }, slots.default?.()) : null } })
    const invitation = { url: 'http://localhost/student/games/number-systems?code=ABCDEF1234&team_code=1234ABCD', qr_data_url: 'data:image/svg+xml;base64,PHN2Zy8+', local_only: true }
    const wrapper = mount(NumberSystemInvitation, { props: { invitation, privateTeam: true }, global: { stubs: { 'v-dialog': dialog } } })
    expect(wrapper.get('a').attributes('href')).toBe(invitation.url)
    expect(wrapper.get('img').attributes('src')).toBe(invitation.qr_data_url)
    expect(wrapper.text()).toContain('von Schülerhandys aus nicht erreichbar')
    expect(wrapper.text()).toContain('nur mit deiner Gruppe')
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    await wrapper.get('button').trigger('click')
    expect(wrapper.get('[role="dialog"] img').attributes('src')).toBe(invitation.qr_data_url)
    await wrapper.findAll('button').find(button => button.text() === 'Schließen').trigger('click')
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    await wrapper.setProps({ invitation: { ...invitation, local_only: false } })
    expect(wrapper.text()).not.toContain('von Schülerhandys aus nicht erreichbar')
    await wrapper.setProps({ localProbe: true })
    expect(wrapper.text()).toContain('keine Schüleranmeldung erforderlich')
    expect(wrapper.text()).not.toContain('persönlichen Schülerkonto')
    wrapper.unmount()
})
