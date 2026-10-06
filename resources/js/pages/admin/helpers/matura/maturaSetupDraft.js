export function setupDraftScope(userId, schoolId, schoolyearId) {
    const ids = [userId, schoolId, schoolyearId].map(Number)
    return ids.every((id) => Number.isSafeInteger(id) && id > 0)
        ? `schooltool:matura-setup:v1:${ids.join(':')}` : null
}

export function setupDraftKey(scope, sessionId = null) {
    return scope ? `${scope}:${sessionId ?? 'new'}` : null
}

function readStored(key, isValid) {
    if (!key) return null
    const records = ['localStorage', 'sessionStorage'].map((storageName) => {
        try {
            const raw = window[storageName].getItem(key)
            if (!raw || raw.length > 1024 * 1024) return null
            const stored = JSON.parse(raw)
            return stored.version === 1 && Number.isFinite(stored.updatedAt) && stored.updatedAt >= 0 && isValid(stored.data)
                ? { ...stored, raw, storageName } : null
        } catch { return null }
    }).filter(Boolean).sort((first, second) => second.updatedAt - first.updatedAt)
    const record = records[0]
    if (!record) return null
    if (record.storageName === 'sessionStorage') {
        try { window.localStorage.setItem(key, record.raw) } catch { /* Keep the existing tab draft if migration is unavailable. */ }
    }
    return record.data
}

export function writeSetupDraft(key, data) {
    if (!key) return false
    const raw = JSON.stringify({ version: 1, updatedAt: Date.now(), data })
    try {
        window.localStorage.setItem(key, raw)
        return true
    } catch {
        try { window.sessionStorage.setItem(key, raw) } catch { /* Keep working when browser storage is unavailable. */ }
        return false
    }
}

export function removeSetupDraft(key) {
    if (!key) return
    for (const storageName of ['localStorage', 'sessionStorage']) {
        try { window[storageName].removeItem(key) } catch { /* Storage may be unavailable. */ }
    }
}

export function readActiveSetup(scope) {
    const active = readStored(scope ? `${scope}:active` : null, (data) => data && (data.sessionId === null || (Number.isSafeInteger(data.sessionId) && data.sessionId > 0)))
    if (!active) return null
    return readSetupDraft(setupDraftKey(scope, active.sessionId)) ? active : null
}

export function readSetupDraft(key) {
    return readStored(key, isSetupDraft)
}

function isSetupDraft(data) {
    const form = data?.form
    if (!form || typeof form.name !== 'string' || form.name.length > 160
        || typeof form.exam_date !== 'string' || form.exam_date.length > 32
        || !['number', 'string'].includes(typeof form.waiting_places)
        || (form.selected_classes !== undefined && (!Array.isArray(form.selected_classes) || form.selected_classes.length > 500 || !form.selected_classes.every((item) => typeof item === 'string' && item.length <= 100)))
        || !Array.isArray(form.rooms) || form.rooms.length < 1 || form.rooms.length > 40
        || !Array.isArray(data.completed) || data.completed.length !== 3 || !data.completed.every((value) => typeof value === 'boolean')
        || !(data.activeStep === null || (Number.isInteger(data.activeStep) && data.activeStep >= 0 && data.activeStep <= 2))
        || !Number.isInteger(data.roomIndex) || data.roomIndex < 0 || data.roomIndex >= form.rooms.length) return null
    if (form.rooms.some((room) => !room || typeof room.name !== 'string' || room.name.length > 80
        || !Array.isArray(room.student_ids) || room.student_ids.length > 10000
        || !room.student_ids.every((id) => Number.isSafeInteger(id) && id > 0)
        || typeof room.manualText !== 'string' || room.manualText.length > 40000
        || typeof room.classFilter !== 'string' || room.classFilter.length > 100
        || typeof room.search !== 'string' || room.search.length > 200
        || (room.adjustStudents !== undefined && typeof room.adjustStudents !== 'boolean'))) return null
    return true
}

export function selectedSetupClasses(form, roster) {
    const availableClasses = new Set(roster.map((student) => student.class).filter(Boolean))
    if (form.selected_classes) return [...new Set(form.selected_classes)].filter((item) => availableClasses.has(item))
    const ids = new Set(form.rooms.flatMap((room) => room.student_ids))
    return [...new Set(roster.filter((student) => ids.has(student.id)).map((student) => student.class).filter(Boolean))]
}

export function restoreSetupDraft(data, roster) {
    const selectedClasses = selectedSetupClasses(data.form, roster)
    const availableIds = new Set(roster.filter((student) => selectedClasses.includes(student.class)).map((student) => student.id))
    const assignedIds = new Set()
    let removedStudents = false
    const rooms = data.form.rooms.map((room) => ({ ...room, student_ids: room.student_ids.filter((id) => {
        if (!availableIds.has(id) || assignedIds.has(id)) { removedStudents = true; return false }
        assignedIds.add(id)
        return true
    }) }))
    const classesChanged = data.form.selected_classes?.some((item) => !selectedClasses.includes(item)) ?? false
    const basicsValid = !classesChanged && Boolean(data.form.name.trim()) && /^\d{4}-\d{2}-\d{2}$/.test(data.form.exam_date)
        && !Number.isNaN(Date.parse(data.form.exam_date))
        && new Date(data.form.exam_date).toISOString().slice(0, 10) === data.form.exam_date
    const roomsValid = !removedStudents && rooms.every((room) => {
        const names = room.manualText.split('\n').map((name) => name.trim()).filter(Boolean)
        return room.name.trim() && room.student_ids.length <= 500 && rooms.filter((other) => other.name.trim() === room.name.trim()).length === 1
            && names.length <= 200 && names.every((name) => name.length <= 180)
    })
    const waitingValid = Number.isInteger(data.form.waiting_places) && data.form.waiting_places >= 0 && data.form.waiting_places <= 10
    const completed = [data.completed[0] && basicsValid, false, false]
    completed[1] = completed[0] && data.completed[1] && roomsValid
    completed[2] = completed[1] && data.completed[2] && waitingValid
    let activeStep = data.activeStep
    if (removedStudents && completed[0]) activeStep = 1
    if (activeStep !== null && !completed.slice(0, activeStep).every(Boolean)) activeStep = completed.findIndex((value) => !value)
    return { form: { ...data.form, selected_classes: selectedClasses, rooms }, completed, activeStep, roomIndex: data.roomIndex, removedStudents }
}
