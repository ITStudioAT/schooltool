import { parseLocalDate } from '@/helpers/date'

export function isInTeachingSemester(value, semester, semesterTwoStart) {
    const selectedSemester = Number(semester)
    const boundary = String(semesterTwoStart || '').slice(0, 10)
    const date = String(value || '').slice(0, 10)

    if (![1, 2].includes(selectedSemester) || !/^\d{4}-\d{2}-\d{2}$/.test(boundary)) return true
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return true

    return selectedSemester === 1 ? date < boundary : date >= boundary
}

export function teachingDateKey(value) {
    if (value == null || value === '') return ''
    const key = String(value || '').slice(0, 10)
    if (!(value instanceof Date)) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(key)) return ''
        const calendarDate = new Date(`${key}T00:00:00Z`)
        if (Number.isNaN(calendarDate.getTime()) || calendarDate.toISOString().slice(0, 10) !== key) return ''
    }
    const parsed = parseLocalDate(value)
    if (Number.isNaN(parsed.getTime())) return ''
    return `${parsed.getFullYear()}-${String(parsed.getMonth() + 1).padStart(2, '0')}-${String(parsed.getDate()).padStart(2, '0')}`
}

export function teachingPerformanceDateScope(value, semester, semesterTwoStart, schoolyear) {
    const from = teachingDateKey(schoolyear?.from)
    const until = teachingDateKey(schoolyear?.until)
    const date = teachingDateKey(value)
    if (!from || !until || from > until || !date) return 'unassigned'
    if (date < from || date > until) return 'excluded'
    const boundary = teachingDateKey(semesterTwoStart)
    if ([1, 2].includes(Number(semester)) && (!boundary || boundary < from || boundary > until)) return 'unassigned'
    return isInTeachingSemester(date, semester, boundary) ? 'included' : 'excluded'
}

export function teachingCourseMatchesSchoolyear(course, schoolyear) {
    return schoolyear?.id != null && course?.schoolyear_id != null && String(course.schoolyear_id) === String(schoolyear.id)
}

export function teachingStarDateScope(value, semester, semesterTwoStart) {
    if (Number(semester) === 3) return 'included'
    const date = teachingDateKey(value)
    const boundary = teachingDateKey(semesterTwoStart)
    if (!date || !boundary) return 'unassigned'
    return isInTeachingSemester(date, semester, boundary) ? 'included' : 'excluded'
}
