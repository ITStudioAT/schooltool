import { describe, expect, it, vi } from 'vitest'
import CourseLists from '@/pages/admin/teaching/overview/components/CourseLists.vue'

describe('CourseLists', () => {
    it('exports active course students with course name and safely quoted CSV fields', () => {
        const selectedCourse = {
            id: 18,
            title: 'Biologie, 5A',
            students_info: [
                { course_student_id: 12, first_name: 'Berta', last_name: 'Zweite', email: 'berta@example.test', class: '5B' },
                { course_student_id: 11, first_name: 'Anna', last_name: 'Muster, "A"', email: 'anna@example.test', schoolclass: '5A' },
                { course_student_id: 13, first_name: 'Cora', last_name: 'Abgemeldet', canceled_at: '2026-09-01' },
                { course_student_id: 14, first_name: 'Dora', last_name: 'Gelöscht', deleted_at: '2026-09-01' },
                { first_name: 'Eva', last_name: 'Ohne Kurszuordnung' },
            ],
        }
        const component = CourseLists as any
        const activeStudents = component.computed.activeStudents.call({ selected_course: selectedCourse })
        const courseName = component.computed.courseName.call({ selected_course: selectedCourse })
        const csv = component.methods.studentListCsv.call({ activeStudents, courseName })

        expect(csv).toBe('\uFEFF"Vorname","Nachname","E-Mail","Kurs","Klasse"\r\n'
            + '"Anna","Muster, ""A""","anna@example.test","Biologie, 5A","5A"\r\n'
            + '"Berta","Zweite","berta@example.test","Biologie, 5A","5B"\r\n')
    })

    it('uses a readable course name and date in the downloaded filename', () => {
        vi.useFakeTimers()
        vi.setSystemTime(new Date('2026-09-26T12:00:00Z'))

        try {
            const filename = (CourseLists as any).methods.studentListFilename.call({
                courseName: 'Biologie 5A / Größere Übung',
                selected_course: { id: 18 },
            })

            expect(filename).toBe('schuelerliste-biologie-5a-grossere-ubung-2026-09-26.csv')
        } finally {
            vi.useRealTimers()
        }
    })

    it('starts a CSV download when creating a list', () => {
        const createObjectURL = vi.fn().mockReturnValue('blob:student-list')
        const revokeObjectURL = vi.fn()
        const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})
        vi.stubGlobal('URL', { createObjectURL, revokeObjectURL })
        vi.useFakeTimers()

        try {
            ;(CourseLists as any).methods.downloadStudentList.call({
                selected_course: { id: 18 },
                activeStudents: [{ first_name: 'Anna' }],
                studentListCsv: () => 'student-list-csv',
                studentListFilename: () => 'schuelerliste-biologie-2026-09-26.csv',
            })

            expect(createObjectURL).toHaveBeenCalledWith(expect.any(Blob))
            expect(click).toHaveBeenCalledOnce()
            expect(click.mock.instances[0].download).toBe('schuelerliste-biologie-2026-09-26.csv')
            vi.runAllTimers()
            expect(revokeObjectURL).toHaveBeenCalledWith('blob:student-list')
        } finally {
            vi.useRealTimers()
            vi.unstubAllGlobals()
            click.mockRestore()
        }
    })
})
