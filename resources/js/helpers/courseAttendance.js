export function attendanceChecked(courseDate) {
    if (typeof courseDate?.attendance_checked === 'boolean') return courseDate.attendance_checked
    return Array.isArray(courseDate?.status) && courseDate.status.includes('att_checked:1')
}

export function attendanceState(attendance, studentId, checked) {
    if (Object.prototype.hasOwnProperty.call(attendance, String(studentId))) return attendance[String(studentId)]
    return checked ? true : null
}

export function attendancePercentage(states) {
    const knownStates = states.filter(state => state === true || state === false)
    if (!knownStates.length) return null
    return Math.round(knownStates.filter(state => state === true).length / knownStates.length * 100)
}
