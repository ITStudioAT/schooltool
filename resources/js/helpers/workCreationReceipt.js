function key(userId, courseId) {
    if (!userId || !courseId) throw new Error('Konto oder Kurs ist nicht eindeutig. Bitte die Seite neu laden.')
    return `schooltool:work-creation:${userId}:${courseId}`
}

export function pendingWorkCreation(userId, courseId) {
    return sessionStorage.getItem(key(userId, courseId))
}

export function workCreationRequest(userId, courseId) {
    const storageKey = key(userId, courseId)
    let requestId = sessionStorage.getItem(storageKey)
    if (!requestId) {
        requestId = crypto.randomUUID()
        sessionStorage.setItem(storageKey, requestId)
    }
    return requestId
}

export function clearWorkCreation(userId, courseId) {
    sessionStorage.removeItem(key(userId, courseId))
}
