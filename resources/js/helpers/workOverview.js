export function isInitialWork(work) {
    const hasValue = value => value !== null && value !== undefined && value !== ''
    const hasStatus = Object.entries(work?.status || {}).some(([key, value]) =>
        key !== 'automation_enabled' && (Array.isArray(value) ? value.length > 0 : hasValue(value)))
    const hasAssessment = (work?.groups || []).some(group =>
        hasValue(group.grade) || hasValue(group.comment)
        || ['grades', 'points', 'comments'].some(key => {
            const values = group[key]
            if (typeof values !== 'object') return hasValue(values)
            return Object.values(values || {}).some(item =>
                typeof item === 'object' && item !== null
                    ? hasValue(item.grade ?? item.points ?? item.comment)
                    : hasValue(item))
        }))

    return !hasStatus && !hasAssessment
}

export function workConnectionLabel(folderState, failed = false) {
    if (folderState) return 'Noch kein Ordner verbunden.'
    return failed ? 'Ordnerverbindung konnte nicht geprüft werden.' : 'Ordnerverbindung wird geprüft …'
}
