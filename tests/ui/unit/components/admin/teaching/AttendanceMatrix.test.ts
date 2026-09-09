import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AttendanceMatrix from '@/pages/admin/teaching/more/components/AttendanceMatrix.vue'

describe('AttendanceMatrix defaults', () => {
    it('defaults student sort mode to name', () => {
        const data = (AttendanceMatrix as any).data.call({})

        expect(data.sortMode).toBe('last_name_first_name')
    })

    it('keeps date columns compact with minimal horizontal padding', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/more/components/AttendanceMatrix.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('class="attendance-date-col"')
        expect(source).toContain(":class=\"['attendance-date-col', attendanceCellClass(student.id, courseDate)]\"")
        expect(source).toContain('width: max-content;')
        expect(source).toContain('min-width: max-content;')
        expect(source).toContain('.attendance-date-col {')
        expect(source).toContain('width: 1%;')
        expect(source).toContain('padding-right: 2px !important;')
        expect(source).toContain('padding-left: 2px !important;')
    })
})

function mountAttendance(courseDates, props = {}) {
    return mount(AttendanceMatrix, {
        props: {
            selectedCourse: {
                students: [492, 493],
                students_info: [
                    { id: 492, last_name: 'Alpha', first_name: 'Anna' },
                    { id: 493, last_name: 'Beta', first_name: 'Ben' },
                ],
                course_dates: courseDates,
            },
            ...props,
        },
        global: { stubs: { 'v-divider': true } },
    })
}

describe('AttendanceMatrix confirmed attendance', () => {
    it('renders unchecked cells empty and excludes them from individual percentages', () => {
        const wrapper = mountAttendance([
            { id: 1, date: '2020-01-01', attendance: {}, attendance_checked: false },
            { id: 2, date: '2020-01-02', attendance: { s_492: true }, attendance_checked: false },
            { id: 3, date: '2020-01-03', attendance: { s_492: false }, attendance_checked: false },
            { id: 4, date: '2020-01-04', attendance: { s_492: null, s_493: null }, attendance_checked: true },
        ])
        const rows = wrapper.findAll('tbody tr')
        const cells = rows[0].findAll('td.attendance-date-col')

        expect(cells.map((cell) => cell.text())).toEqual(['', 'mdi-check', 'mdi-close', ''])
        expect(rows[0].find('.attendance-percent-cell').text()).toBe('50%')
        expect(rows[1].find('.attendance-percent-cell').text()).toBe('–')
        expect(rows[1].findAll('td.attendance-date-col').every((cell) => cell.text() === '')).toBe(true)
        wrapper.unmount()
    })

    it('preserves individual overrides through whole-date confirmation, reset and reloaded payloads', async () => {
        const date = { id: 1, date: '2020-01-01', attendance: { s_492: null }, attendance_checked: false }
        const wrapper = mountAttendance([date])
        const course = wrapper.props('selectedCourse')

        await wrapper.setProps({ selectedCourse: { ...course, course_dates: [{ ...date, attendance_checked: true }] } })
        expect(wrapper.findAll('td.attendance-date-col').map((cell) => cell.text())).toEqual(['', 'mdi-check'])

        const reloadedDate = JSON.parse(JSON.stringify({ ...date, attendance: { s_492: true, s_493: false } }))
        await wrapper.setProps({ selectedCourse: { ...course, course_dates: [reloadedDate] } })
        expect(wrapper.findAll('td.attendance-date-col').map((cell) => cell.text())).toEqual(['mdi-check', 'mdi-close'])

        await wrapper.setProps({ selectedCourse: { ...course, course_dates: [date] } })
        expect(wrapper.findAll('td.attendance-date-col').map((cell) => cell.text())).toEqual(['', ''])
        wrapper.unmount()
    })

    it('honors dedicated empty maps and false checks over legacy metadata', () => {
        const wrapper = mountAttendance([
            { id: 1, date: '2020-01-01', attendance: [], attendance_checked: false, status: ['att:s_492:0', 'att_checked:1'] },
            { id: 2, date: '2020-01-02', status: ['att:s_492:null', 'att_checked:1'] },
            { id: 3, date: '2020-01-03', status: ['att:s_492:1', 'att:s_493:0'] },
        ])
        const rows = wrapper.findAll('tbody tr')

        expect(rows[0].findAll('td.attendance-date-col').map((cell) => cell.text())).toEqual(['', '', 'mdi-check'])
        expect(rows[1].findAll('td.attendance-date-col').map((cell) => cell.text())).toEqual(['', 'mdi-check', 'mdi-close'])
        expect(rows[0].find('.attendance-percent-cell').text()).toBe('100%')
        expect(rows[1].find('.attendance-percent-cell').text()).toBe('50%')
        wrapper.unmount()
    })

    it('retains semester, future-date and canceled-date exclusions', () => {
        const wrapper = mountAttendance([
            { id: 1, date: '2019-12-31', attendance: { s_492: false } },
            { id: 2, date: '2020-01-01', attendance: { s_492: true } },
            { id: 3, date: '2020-01-02', attendance: { s_492: false }, status: ['free'] },
            { id: 4, date: '2099-01-01', attendance: { s_492: false } },
        ], { semesterCount: 2, activeSemester: 2, sem2StartDate: '2020-01-01' })
        const row = wrapper.find('tbody tr')

        expect(row.findAll('td.attendance-date-col').map((cell) => cell.text())).toEqual(['mdi-check', 'E', 'mdi-minus'])
        expect(row.find('.attendance-percent-cell').text()).toBe('100%')
        wrapper.unmount()
    })
})
