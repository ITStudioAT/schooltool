import { expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WorkSimulationBadge from '@/pages/admin/teaching/overview/components/WorkSimulationBadge.vue'

const work = { id: 86, teaching_course_id: 16, groups: [{ student_ids: [492] }],
    simulation_collection: { origin: 'mailpit_simulation', simulation: true, run_id: 'test-run', work_id: 86, course_id: 16,
        student_ids: [492], collected_count: 0, non_submission_count: 0, processed_count: 0, complete: false, completed_at: null } }

it('marks a confirmed simulator run before any return or collection and supports compact cards', async () => {
    const wrapper = mount(WorkSimulationBadge, { props: { work } })
    try {
        expect(wrapper.get('[role="note"]').text()).toContain('Simulation')
        expect(wrapper.get('[role="note"]').attributes('aria-label')).toContain('lokaler Testlauf')
        expect(wrapper.get('v-icon').attributes('icon')).toBe('mdi-flask-outline')
        await wrapper.setProps({ compact: true })
        expect(wrapper.get('[role="note"]').attributes('title')).toContain('Simulation')
        expect(wrapper.get('v-icon').attributes('icon')).toBe('mdi-flask-outline')
    } finally { wrapper.unmount() }
})

it.each([null, { ...work, simulation_collection: null }, { ...work, id: 64 },
    { ...work, groups: [{ student_ids: [493] }] }])('does not mark real or unrelated work as a simulation', actual => {
    const wrapper = mount(WorkSimulationBadge, { props: { work: actual } })
    try { expect(wrapper.find('[role="note"]').exists()).toBe(false) } finally { wrapper.unmount() }
})
