export function courseDateHours(courseDate) {
    return [...new Set((courseDate?.hours || []).map(Number).filter(Number.isFinite))].sort((first, second) => first - second)
}

export function cancelledCourseDateHours(courseDate) {
    const hours = courseDateHours(courseDate)
    const status = Array.isArray(courseDate?.status) ? courseDate.status : []
    if (status.some((value) => ['free', 'frei', 'entfaellt', 'entfällt', 'entfallen'].includes(value))) return hours
    const cancelled = Array.isArray(courseDate?.cancelled_hours) ? courseDate.cancelled_hours.map(Number) : []
    return hours.filter((hour) => cancelled.includes(hour) || status.includes(`cancelled_hour:${hour}`))
}

export function activeCourseDateHours(courseDate) {
    const cancelled = cancelledCourseDateHours(courseDate)
    return courseDateHours(courseDate).filter((hour) => !cancelled.includes(hour))
}

export function isCourseDateHourCancelled(courseDate, hour) {
    return cancelledCourseDateHours(courseDate).includes(Number(hour))
}
