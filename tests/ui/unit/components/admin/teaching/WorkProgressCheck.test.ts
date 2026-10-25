import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, ref } from 'vue'
import { createTestingPinia } from '@pinia/testing'
import WorkProgressCheck from '@/pages/admin/teaching/overview/components/WorkProgressCheck.vue'
import WorkDeliveryStatus from '@/pages/admin/teaching/overview/components/WorkDeliveryStatus.vue'

afterEach(() => vi.unstubAllGlobals())

it.each([true, false])('refreshes collection evidence explicitly and accepts only the selected work: %s', async matching => {
    const get = vi.fn().mockResolvedValue({ data: { data: { id: matching ? 64 : 86, teaching_course_id: 16,
        collection_run: { completed_at: '2026-10-24T13:46:16Z' } } } })
    vi.stubGlobal('axios', { get })
    const wrapper = mount(WorkProgressCheck, { props: { work: { id: 64, teaching_course_id: 16 } },
        global: { stubs: { 'v-btn': { template: '<button><slot /></button>' } } } })
    try {
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(get).toHaveBeenCalledWith(expect.any(String), { params: { refresh_collection: true } })
        if (matching) {
            expect(wrapper.emitted('saved')?.[0]?.[0]).toMatchObject({ data: { id: 64,
                collection_run: { completed_at: '2026-10-24T13:46:16Z' } } })
        } else {
            expect(wrapper.emitted('saved')).toBeUndefined()
            expect(wrapper.get('[role="alert"]').text()).toBe('Arbeitsstand nicht bestätigt.')
        }
    } finally { wrapper.unmount() }
})

it('updates the Unterricht collection indicator and simulator time after an explicit recheck', async () => {
    const initial = { id: 86, teaching_course_id: 16, title: 'E-Mails', groups: [{ student_ids: [492, 493] }] }
    const refreshed = { ...initial, simulation_collection: { origin: 'mailpit_simulation', simulation: true,
        run_id: 'selected-simulation', work_id: 86, course_id: 16, student_ids: [492, 493],
        collected_count: 1, non_submission_count: 1, processed_count: 2, complete: true,
        completed_at: '2026-10-24T15:35:00Z', last_collected_at: '2026-10-24T15:35:00Z',
        evaluation: { simulation: true, run_id: 'selected-simulation', completed_count: 1, partial_count: 1, open_count: 0,
            rows: [{ student_id: 492, person: 'Person Eins', state: 'complete', result: '4,5 von 5 Punkten' },
                { student_id: 493, person: 'Person Zwei', state: 'partial', result: '2,5 von 5 Punkten (vorläufig) · Offen: PDF-Inhalt' }] } } }
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { data: refreshed } }) })
    const page = defineComponent({ components: { WorkProgressCheck, WorkDeliveryStatus },
        setup() { return { work: ref(initial) } },
        template: '<WorkProgressCheck :work="work" @saved="work = $event.data" /><WorkDeliveryStatus :work="work" />' })
    const wrapper = mount(page, { global: {
        plugins: [createTestingPinia({ createSpy: vi.fn })],
        stubs: { 'v-btn': { template: '<button><slot /></button>' },
            'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' } },
    } })
    try {
        await flushPromises()
        expect(wrapper.get('[data-workflow-stage="collection"]').attributes('data-workflow-state')).toBe('open')
        await wrapper.findAll('button').find(button => button.text().includes('Erneut prüfen')).trigger('click')
        await flushPromises()
        expect(wrapper.get('[data-workflow-stage="collection"]').attributes('data-workflow-state')).toBe('complete')
        expect(wrapper.get('[data-workflow-stage="evaluation"]').attributes('data-workflow-state')).toBe('partial')
        await wrapper.get('[data-workflow-stage="collection"]').trigger('click')
        expect(wrapper.get('[role="dialog"]').text()).toContain('abgeschlossen · Simulation')
        expect(wrapper.get('[role="dialog"]').text()).toContain('1 gesicherte Rückgaben, 1 vorgesehene Nichtabgaben')
        expect(wrapper.get('[role="dialog"]').text()).toContain('Letztes Einsammeln im Simulator: 24.10.2026, 17:35 Uhr')
        expect(wrapper.get('[role="dialog"]').text()).toContain('Simulator öffnen')
        expect(wrapper.get('[role="dialog"]').text()).not.toContain('Erneut einsammeln')
        await wrapper.get('[data-workflow-stage="evaluation"]').trigger('click')
        expect(wrapper.get('[aria-label="Testbewertungen"]').text()).toContain('Person Eins')
        expect(wrapper.get('[aria-label="Testbewertungen"]').text()).toContain('4,5 von 5 Punkten')
        expect(wrapper.get('[aria-label="Testbewertungen"]').text()).toContain('2,5 von 5 Punkten (vorläufig)')
        expect(wrapper.get('[role="dialog"]').text()).toContain('1 abgeschlossen, 1 teilweise bewertet, 0 offen')
    } finally { wrapper.unmount() }
})
