export function isInitialWork(work) {
    const hasValue = value => value !== null && value !== undefined && value !== ''
    const hasStatus = Object.entries(work?.status || {}).some(([key, value]) =>
        !['automation_enabled', 'multiple_choice_tests'].includes(key) && (Array.isArray(value) ? value.length > 0 : hasValue(value)))
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

export function workNeedsPreparation(work, folderState) {
    const status = { ...work?.status }
    delete status.communicated_deadline_at
    delete status.communicated_deadline_date
    return isInitialWork({ ...work, status }) && folderState?.has_import !== true
}

export function workPreparationLabel(checked) {
    if (checked.status === 'found') {
        if (checked.data?.exercise?.email_dispatch?.status === 'sent') return 'Abgaben einsammeln'
        const preparation = checked.data?.exercise?.email_preparation
        return preparation?.status === 'ready' ? 'Versandbereit'
            : preparation?.status === 'unknown' ? 'Vorbereitung prüfen' : 'E-Mails vorbereiten'
    }
    return checked.status === 'missing' ? 'LF erstellen' : 'LF nicht bestätigt'
}
