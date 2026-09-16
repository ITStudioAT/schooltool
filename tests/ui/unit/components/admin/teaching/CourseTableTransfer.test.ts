import { afterEach, describe, expect, it, vi } from 'vitest'
import { createTestingPinia } from '@pinia/testing'
import { flushPromises, mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import { VBtn } from 'vuetify/components/VBtn'
import CourseTable from '@/pages/admin/teaching/overview/components/CourseTable.vue'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useCourseStudentEntryStore } from '@/stores/admin/teaching/CourseStudentEntryStore'
import { useCourseBehaviourEntryStore } from '@/stores/admin/teaching/CourseBehaviourEntryStore'

const source = { id: 501, user_id: 10, teaching_course_id: 18, date: '2026-09-14', type: 'M', grade: '+', source: 'manual' }
const occupied = { ...source, id: 502, user_id: 20, grade: '++' }
let wrapper

async function renderTable() {
    const pinia = createTestingPinia({ createSpy: vi.fn })
    const courseStore = useCourseStore(pinia)
    courseStore.selected_course = {
        id: 18, title: 'Testkurs',
        students_info: [
            { id: 110, user_id: 10, first_name: 'Anna', last_name: 'A' },
            { id: 120, user_id: 20, first_name: 'Ben', last_name: 'B' },
            { id: 130, user_id: 30, first_name: 'Clara', last_name: 'C' },
            { id: 140, user_id: null, first_name: 'Dora', last_name: 'D' },
        ],
        course_dates: [{ id: 1, date: '2026-09-14' }, { id: 2, date: '2026-09-21' }],
    } as never
    const entryStore = useCourseStudentEntryStore(pinia)
    entryStore.courseEntries = [{ ...source }, { ...occupied }] as never
    wrapper = mount(CourseTable, {
        props: { view: 'entries' },
        global: {
            plugins: [pinia, createVuetify({ components: { VBtn } })],
            components: { 'v-btn': VBtn },
            stubs: {
                'v-btn': false, VBtn: false,
                'v-autocomplete': true, 'v-checkbox': true, 'v-date-input': true,
                'v-divider': true, 'v-list-subheader': true, 'v-tab': true,
                'v-tabs': true, 'v-textarea': true, CourseStudentNotes: true,
                CourseStudentIndicators: true, ItsRichTextEditor: true,
                ItsGridBox: { template: '<div><slot /></div>' },
            },
        },
    })
    await flushPromises()
    const cells = () => wrapper.findAll('.course-table-row .course-table-entry-cell')
    const start = async () => {
        await cells()[0].trigger('click')
        await wrapper.get('[data-testid="course-table-transfer-entry-assessment-501"]').trigger('click')
    }
    return { courseStore, entryStore, behaviourStore: useCourseBehaviourEntryStore(pinia), cells, start }
}

afterEach(() => wrapper?.unmount())

describe('CourseTable entry transfer', () => {
    it('toggles target cells with mouse and keyboard and cancels without creating entries', async () => {
        const { entryStore, cells, start } = await renderTable()
        await start()
        const confirm = () => wrapper.get('[data-testid="course-table-transfer-confirm"]')
        expect(wrapper.vm.entryDialog.open).toBe(false)
        expect(confirm().attributes('disabled')).toBeDefined()
        for (const index of [0, 1, 3, 6]) {
            expect(cells()[index].attributes('aria-disabled')).toBe('true')
            await cells()[index].trigger('click')
        }
        expect(wrapper.vm.entryTransfer.userIds).toEqual([])
        await cells()[2].trigger('click')
        expect(cells()[2].attributes('aria-pressed')).toBe('true')
        expect(cells()[2].classes()).toContain('course-table-entry-cell--transfer-selected')
        expect(cells()[2].text()).toContain('Ausgewählt')
        expect(wrapper.get('[data-testid="course-table-transfer-bar"]').text()).toContain('1 ausgewählt')
        await cells()[2].trigger('click')
        expect(cells()[2].attributes('aria-pressed')).toBe('false')
        expect(confirm().attributes('disabled')).toBeDefined()
        await cells()[2].trigger('keydown', { key: 'Enter' })
        await cells()[4].trigger('keydown', { key: ' ' })
        expect(wrapper.get('[data-testid="course-table-transfer-bar"]').text()).toContain('2 ausgewählt')
        expect(entryStore.transfer).not.toHaveBeenCalled()
        await wrapper.get('[data-testid="course-table-transfer-cancel"]').trigger('click')
        expect(wrapper.find('[data-testid="course-table-transfer-bar"]').exists()).toBe(false)
        expect(entryStore.transfer).not.toHaveBeenCalled()
        expect(entryStore.courseEntries).toEqual([source, occupied])
        await cells()[2].trigger('click')
        expect(wrapper.vm.entryDialog.open).toBe(true)
    })

    it('submits once after confirmation, preserves existing entries and resumes normal cell editing', async () => {
        const { entryStore, cells, start } = await renderTable()
        let complete
        vi.mocked(entryStore.transfer).mockImplementation(() => new Promise((resolve) => { complete = resolve }))
        await start()
        await cells()[2].trigger('click')
        await cells()[4].trigger('click')
        await wrapper.get('[data-testid="course-table-transfer-confirm"]').trigger('click')
        await wrapper.vm.confirmEntryTransfer()
        await cells()[2].trigger('click')
        await wrapper.vm.cancelEntryTransfer()
        expect(entryStore.transfer).toHaveBeenCalledTimes(1)
        expect(entryStore.transfer).toHaveBeenCalledWith(501, { course_date_id: 1, user_ids: ['20', '30'] }, 'assessment')
        expect(wrapper.vm.entryTransfer.userIds).toEqual(['20', '30'])
        expect(wrapper.get('[data-testid="course-table-transfer-cancel"]').attributes('disabled')).toBeDefined()
        const copies = [{ ...source, id: 503, user_id: 20 }, { ...source, id: 504, user_id: 30 }]
        complete({ data: copies })
        await flushPromises()
        expect(entryStore.courseEntries).toEqual([...copies, source, occupied])
        expect(wrapper.find('[data-testid="course-table-transfer-bar"]').exists()).toBe(false)
        await cells()[2].trigger('click')
        expect(wrapper.vm.cellEntries.map((entry) => entry.id)).toEqual(expect.arrayContaining([502, 503]))
    })

    it('keeps the selection after a server rejection and clears it when changing view', async () => {
        const { entryStore, cells, start } = await renderTable()
        vi.mocked(entryStore.transfer).mockResolvedValue(false)
        await start()
        await cells()[2].trigger('click')
        await wrapper.get('[data-testid="course-table-transfer-confirm"]').trigger('click')
        await flushPromises()
        expect(wrapper.get('[role="alert"]').text()).toContain('Auswahl bleibt erhalten')
        expect(cells()[2].attributes('aria-pressed')).toBe('true')
        expect(entryStore.courseEntries).toEqual([source, occupied])
        await wrapper.setProps({ view: 'attendance' })
        expect(wrapper.vm.entryTransfer).toBeNull()
    })

    it('excludes generated work entries and reminders without a type', async () => {
        const { cells } = await renderTable()
        await cells()[0].trigger('click')
        expect(wrapper.vm.canTransferCellEntry({ ...source, source: 'course_work' })).toBe(false)
        expect(wrapper.vm.canTransferCellEntry({ id: 5, kind: 'notification', type: null })).toBe(false)
    })

    it.each(['semester', 'view', 'course', 'unmount'])('handles a pending transfer while changing %s', async (context) => {
        const { entryStore, courseStore, cells, start } = await renderTable()
        let complete
        vi.mocked(entryStore.transfer).mockImplementation(() => new Promise((resolve) => { complete = resolve }))
        await start()
        await cells()[2].trigger('click')
        await wrapper.get('[data-testid="course-table-transfer-confirm"]').trigger('click')
        if (context === 'semester') await wrapper.setProps({ activeSemester: 1 })
        if (context === 'view') await wrapper.setProps({ view: 'attendance' })
        if (context === 'course') {
            courseStore.selected_course = { id: 99, course_dates: [], students_info: [] } as never
            await flushPromises()
        }
        if (context === 'unmount') wrapper.unmount()
        complete({ data: [{ ...source, id: 503, user_id: 20 }] })
        await flushPromises()
        expect(entryStore.courseEntries.map((entry) => entry.id)).toEqual(
            ['semester', 'view'].includes(context) ? [503, 501, 502] : [501, 502],
        )
    })

    it('updates legacy behaviour entries through their own collection', async () => {
        const { entryStore, behaviourStore, cells } = await renderTable()
        const behaviour = { ...source, id: 51, kind: 'behaviour', type: 'V' }
        behaviourStore.courseEntries = [behaviour] as never
        vi.mocked(entryStore.transfer).mockResolvedValue({ data: [{ ...behaviour, id: 52, user_id: 20 }] })
        await cells()[0].trigger('click')
        await wrapper.get('[data-testid="course-table-transfer-entry-behaviour-51"]').trigger('click')
        await cells()[2].trigger('click')
        await wrapper.get('[data-testid="course-table-transfer-confirm"]').trigger('click')
        await flushPromises()
        expect(entryStore.transfer).toHaveBeenCalledWith(51, { course_date_id: 1, user_ids: ['20'] }, 'behaviour')
        expect(behaviourStore.courseEntries).toHaveLength(2)
        expect(entryStore.courseEntries).toEqual([source, occupied])
    })
})
