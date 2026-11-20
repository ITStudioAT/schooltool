export function canEditWorkTitle(config, course, work) {
    const user = config?.user
    const roles = Array.isArray(config?.roles) ? config.roles : []
    if (!user?.id || !course?.id || !work?.id || !work?.teaching_course_id
        || !course.school_id || !course.schoolyear_id || !config?.selected_school?.id || !config?.selected_schoolyear?.id) return false
    if (String(work.teaching_course_id) !== String(course.id)
        || String(course.school_id) !== String(config.selected_school.id)
        || String(course.schoolyear_id) !== String(config.selected_schoolyear.id)) return false
    if (roles.includes('admin') || roles.includes('teaching_admin')) return true
    return roles.includes('teacher') && Boolean(course.user_id) && String(course.user_id) === String(user.id)
}
