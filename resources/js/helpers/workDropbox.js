import { shallowReactive } from 'vue'
import { folders, show, refresh } from '@/actions/App/Http/Controllers/Admin/Teaching/WorkDropboxController'

export const workDropboxStates = shallowReactive(new Map())
const pending = new Map()
const suggestions = new Map()
const exerciseChecks = shallowReactive(new Map())
const exercisePending = new Map()
const exerciseStoragePrefix = 'schooltool:work-preparation:v2:'
let generation = 0

export function workDropboxKey(userId, work) {
    return userId && work?.id && work?.teaching_course_id ? `${userId}:${work.teaching_course_id}:${work.id}` : null
}

export function workDropboxBinding(state) {
    return JSON.stringify([state?.connected, state?.link_version, state?.module_link, state?.folder?.id])
}

function exerciseKey(key, work, date) {
    return JSON.stringify([key, date, work?.date_for_all_groups, work?.title, work?.finish_until_date,
        work?.finish_until_time, work?.communicated_deadline, (work?.groups || []).map(group => [group.date, group.student_ids])])
}

export function workExerciseCheck(key, work, date = '') {
    if (!key) return null
    const cacheKey = exerciseKey(key, work, date)
    let entry = exerciseChecks.get(cacheKey)
    if (!entry) {
        try {
            const saved = JSON.parse(localStorage.getItem(exerciseStoragePrefix + cacheKey) || 'null')
            if (saved && ['found', 'missing', 'unknown'].includes(saved.status) && typeof saved.binding === 'string'
                && Number.isFinite(Date.parse(saved.checkedAt)) && saved.data && saved.binding === workDropboxBinding(saved.data)) {
                entry = saved
                exerciseChecks.set(cacheKey, entry)
            }
        } catch { /* A blocked browser cache must not prevent checking Dropbox. */ }
    }
    return entry && (!workDropboxStates.has(key) || entry.binding === workDropboxBinding(workDropboxStates.get(key))) ? entry : null
}

function saveExerciseCheck(cacheKey, entry) {
    exerciseChecks.set(cacheKey, entry)
    try { localStorage.setItem(exerciseStoragePrefix + cacheKey, JSON.stringify(entry)) } catch { /* Keep the in-memory result. */ }
}

export async function loadWorkExercise(key, work, date = '', fresh = false, deadlineDraft = null, refreshCollection = false, progressId = null, stream = null) {
    if (!key) return null
    const cached = workExerciseCheck(key, work, date)
    if (cached && !fresh) {
        if (!workDropboxStates.has(key)) workDropboxStates.set(key, cached.data)
        return cached.data
    }
    const cacheKey = exerciseKey(key, work, date)
    const binding = workDropboxBinding(workDropboxStates.get(key))
    const requestKey = JSON.stringify([cacheKey, binding, fresh, deadlineDraft, refreshCollection, progressId])
    if (exercisePending.has(requestKey)) return exercisePending.get(requestKey)
    const requestGeneration = generation
    const request = (fresh ? axios.post(refresh.url(work.id), {
        ...(date ? { date } : {}),
        finish_until_date: deadlineDraft ? deadlineDraft.date || null : work.finish_until_date || null,
        finish_until_time: deadlineDraft ? deadlineDraft.time || null : work.finish_until_time || null,
        expected: { date: work.finish_until_date || null, time: work.finish_until_time || null },
        ...(refreshCollection ? { refresh_collection: true } : {}),
        ...(progressId ? { progress_id: progressId } : {}),
        ...(stream ? { stream_progress: true } : {}),
    }, { timeout: 120000, ...(stream?.options || {}) }) : axios.get(show.url(work.id), { timeout: 45000,
        params: { include_exercise: 1, ...(date ? { date } : {}) },
    })).then(response => {
        const data = stream ? stream.result(response) : response.data
        const currentBinding = workDropboxBinding(workDropboxStates.get(key))
        if (generation !== requestGeneration || (currentBinding !== binding && currentBinding !== workDropboxBinding(data))) {
            throw new Error('Die Verbindung wurde während der Prüfung geändert.')
        }
        const status = data.connected && data.module_link?.folder?.id ? data.exercise?.status : 'missing'
        saveExerciseCheck(exerciseKey(key, data.saved_work || work, date), { binding: workDropboxBinding(data), status: ['found', 'missing'].includes(status) ? status : 'unknown',
            data, folder: status === 'found' ? data.exercise?.folder : null,
            checkedAt: Number.isFinite(Date.parse(data.exercise?.checked_at)) ? data.exercise.checked_at : new Date().toISOString() })
        workDropboxStates.set(key, data)
        return data
    }).catch(error => {
        if (generation === requestGeneration && workDropboxBinding(workDropboxStates.get(key)) === binding) {
            const data = { ...workDropboxStates.get(key), exercise: { status: 'unknown', folder: null,
                message: 'Der Anlagezustand konnte nicht geprüft werden. Bitte erneut prüfen.' } }
            saveExerciseCheck(cacheKey, { binding, status: 'unknown', data, folder: null, checkedAt: new Date().toISOString() })
            workDropboxStates.set(key, data)
        }
        throw error
    }).finally(() => {
        if (exercisePending.get(requestKey) === request) exercisePending.delete(requestKey)
        if (pending.get(key)?.request === request) pending.delete(key)
    })
    exercisePending.set(requestKey, request)
    if (!pending.has(key)) pending.set(key, { request, exercise: true })
    return request
}

export async function loadWorkDropbox(key, workId, fresh = false, options = {}) {
    if (!key) return null
    const cached = workDropboxStates.get(key)
    if (!fresh && cached && ('folder' in cached || 'module_link' in cached)) return cached
    const inFlight = pending.get(key)
    if (inFlight && !inFlight.signal?.aborted && !(fresh && inFlight.exercise)) {
        if (!inFlight.exercise) return inFlight.request
        const requestGeneration = generation
        try {
            return await inFlight.request
        } catch (error) {
            if (generation !== requestGeneration || options.signal?.aborted) throw error
            return loadWorkDropbox(key, workId, true, options)
        }
    }
    const requestGeneration = generation
    const request = axios.get(show.url(workId), { timeout: 45000, ...options }).then(response => {
        if (generation === requestGeneration && !options.signal?.aborted) workDropboxStates.set(key, response.data)
        return response.data
    }).finally(() => { if (pending.get(key)?.request === request) pending.delete(key) })
    pending.set(key, { request, signal: options.signal })
    return request
}

export async function loadWorkDropboxSuggestions(key, work, options = {}) {
    const cacheKey = JSON.stringify([key, work.title, work.date_for_all_groups, (work.groups || []).map(group => group.date)])
    const cached = suggestions.get(cacheKey)
    if (cached && Date.now() - cached.at < 60000) return cached.data
    const requestGeneration = generation
    const response = await axios.get(folders.url(work.id, { query: { suggest: true } }), { timeout: 45000, ...options })
    if (generation === requestGeneration && !options.signal?.aborted) suggestions.set(cacheKey, { data: response.data, at: Date.now() })
    return response.data
}

export function clearWorkDropboxStates(forgetChecks = true) {
    generation++
    pending.clear()
    suggestions.clear()
    exerciseChecks.clear()
    exercisePending.clear()
    workDropboxStates.clear()
    if (forgetChecks) {
        try {
            for (const key of Object.keys(localStorage)) {
                if (key.startsWith(exerciseStoragePrefix)) localStorage.removeItem(key)
            }
        } catch { /* Browser storage may be unavailable. */ }
    }
}
