import { expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import WorkExerciseStatus from '@/pages/admin/teaching/overview/components/WorkExerciseStatus.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'

const work = { id: 65, teaching_course_id: 2 }
const state = { userId: 7, workId: 65, courseId: 2, date: '2026-10-20', folder: { name: 'Bestätigter Ordner' } }
function render(status = 'checking') {
    return mount(WorkExerciseStatus, { props: { work, date: state.date, state: { ...state, status } }, global: {
        plugins: [createTestingPinia({ createSpy: vi.fn, initialState: { AdminAdminStore: { config: { user: { id: 7 } } } } })],
    } })
}

it.each([['found', 'success', 'erstellt: Bestätigter Ordner'], ['missing', 'error', 'noch nicht erstellt'],
    ['checking', 'grey', 'wird geprüft'], ['unknown', 'grey', 'konnte nicht geprüft']])('distinguishes %s without another lookup', (status, color, label) => {
    const wrapper = render(status)
    expect(wrapper.attributes('color')).toBe(color)
    expect(wrapper.attributes('aria-label')).toContain(label)
    expect(wrapper.text()).toBe('mdi-clipboard-text-outline')
    expect(wrapper.find('button').exists()).toBe(false)
    wrapper.unmount()
})

it('keeps previous work, date and account results neutral', async () => {
    const wrapper = render('found')
    await wrapper.setProps({ work: { ...work, id: 66 } })
    expect(wrapper.attributes('color')).toBe('grey')
    await wrapper.setProps({ work, date: '2026-10-21' })
    expect(wrapper.attributes('color')).toBe('grey')
    await wrapper.setProps({ date: state.date })
    expect(wrapper.attributes('color')).toBe('success')
    useAdminStore().config.user.id = 8
    await wrapper.vm.$nextTick()
    expect(wrapper.attributes('color')).toBe('grey')
    wrapper.unmount()
})
