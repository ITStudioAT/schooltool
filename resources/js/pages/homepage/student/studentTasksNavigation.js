import { numberSystemLoginReturn } from '@/pages/components/numberSystems/numberSystemLoginReturn'

export function studentTasksCourse(value) {
    return typeof value === 'string' && /^[1-9]\d{0,18}$/.test(value) ? value : null
}

export function studentTasksUrl(course, path = '/student/overview') {
    const courseId = studentTasksCourse(course)
    return `${path}?panel=tasks${courseId ? `&course=${courseId}` : ''}`
}

export function studentLoginReturn(query) {
    return query?.panel === 'tasks' ? studentTasksUrl(query.course) : numberSystemLoginReturn(query)
}
