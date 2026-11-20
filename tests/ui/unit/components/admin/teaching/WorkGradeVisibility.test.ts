import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, expect, it, vi } from 'vitest'
import WorkGradeVisibility from '@/pages/admin/teaching/overview/components/WorkGradeVisibility.vue'

const work = { id: 86, teaching_course_id: 16, status: { grade_visibility: { visible: false } } }
const released = { ...work, status: { grade_visibility: { visible: true, changed_at: '2026-11-20T10:00:00Z' } } }
const mountPanel = (entry: object = work, mode = 'both') => mount(WorkGradeVisibility, {
    props: { work: entry, mode },
    global: { stubs: {
        'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
        'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' },
    } },
})
const button = (panel: ReturnType<typeof mountPanel>, label: string) =>
    panel.findAll('button').find(node => node.text() === label)!

afterEach(() => vi.unstubAllGlobals())

it('confirms release and separately announces or retracts the in-course notice', async () => {
    const announced = { ...released, status: { grade_visibility: { ...released.status.grade_visibility,
        student_notice: { student_ids: [101, 102], changed_at: '2026-11-20T10:05:00Z' } } } }
    const patch = vi.fn().mockResolvedValue({ data: { data: released } })
    const put = vi.fn().mockResolvedValueOnce({ data: { data: announced } }).mockResolvedValueOnce({ data: { data: released } })
    vi.stubGlobal('axios', { patch, put })
    const panel = mountPanel()
    try {
        expect(button(panel, 'Im Kurs verständigen').element.disabled).toBe(true)
        await button(panel, 'Bewertungen freigeben').trigger('click')
        expect(patch).not.toHaveBeenCalled()
        await button(panel, 'Jetzt freigeben').trigger('click')
        await flushPromises()
        expect(patch).toHaveBeenCalledWith(expect.stringContaining('/grade-visibility'), { visible: true, expected_changed_at: null })
        expect(panel.emitted('saved')).toEqual([[released]])
        expect(put).not.toHaveBeenCalled()
        await panel.setProps({ work: released })
        await button(panel, 'Im Kurs verständigen').trigger('click')
        await button(panel, 'Mitteilung anzeigen').trigger('click')
        await flushPromises()
        expect(put).toHaveBeenCalledWith(expect.stringContaining('/grade-notice'), {
            publish: true, expected_changed_at: released.status.grade_visibility.changed_at, expected_notice_at: null,
        })
        await panel.setProps({ work: announced })
        expect(panel.text()).toContain('Mitteilung im Kurs für 2 Personen')
        await button(panel, 'Mitteilung zurückziehen').trigger('click')
        await button(panel, 'Jetzt zurückziehen').trigger('click')
        await flushPromises()
        expect(put.mock.calls[1][1]).toMatchObject({ publish: false, expected_notice_at: announced.status.grade_visibility.student_notice.changed_at })
        expect(panel.emitted('saved')).toEqual([[released], [announced], [released]])
    } finally { panel.unmount() }
})

it('keeps existing visible work visible, allows cancellation, and displays an outdated-state response', async () => {
    const patch = vi.fn().mockRejectedValue({ response: { data: { message: 'Die Ergebnisfreigabe hat sich geändert.' } } })
    vi.stubGlobal('axios', { patch })
    const panel = mountPanel({ id: 9, teaching_course_id: 16, status: {} }, 'visibility')
    try {
        expect(panel.text()).not.toContain('Im Kurs verständigen')
        await button(panel, 'Bewertungen verbergen').trigger('click')
        await button(panel, 'Abbrechen').trigger('click')
        expect(patch).not.toHaveBeenCalled()
        await button(panel, 'Bewertungen verbergen').trigger('click')
        await button(panel, 'Jetzt verbergen').trigger('click')
        await flushPromises()
        expect(panel.get('[role=alert]').text()).toContain('geändert')
        expect(panel.emitted('saved')).toBeUndefined()
    } finally { panel.unmount() }
})

it('ignores a stale save response after leaving and returning to a work', async () => {
    let finish: (response: unknown) => void = () => {}
    vi.stubGlobal('axios', { patch: vi.fn(() => new Promise(resolve => { finish = resolve })) })
    const panel = mountPanel()
    try {
        await button(panel, 'Bewertungen freigeben').trigger('click')
        await button(panel, 'Jetzt freigeben').trigger('click')
        await panel.setProps({ work: { ...work, id: 10 } })
        await panel.setProps({ work })
        finish({ data: { data: released } })
        await flushPromises()
        expect(panel.emitted('saved')).toBeUndefined()
        expect(button(panel, 'Bewertungen freigeben').element.disabled).toBe(false)
    } finally { panel.unmount() }
})
