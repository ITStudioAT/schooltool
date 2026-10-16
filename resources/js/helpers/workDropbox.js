import { shallowReactive } from 'vue'
import { folders, show } from '@/actions/App/Http/Controllers/Admin/Teaching/WorkDropboxController'

export const workDropboxStates = shallowReactive(new Map())
const pending = new Map()
const suggestions = new Map()
let generation = 0

export function workDropboxKey(userId, work) {
    return userId && work?.id && work?.teaching_course_id ? `${userId}:${work.teaching_course_id}:${work.id}` : null
}

export async function loadWorkDropbox(key, workId, fresh = false, options = {}) {
    if (!key) return null
    if (!fresh && workDropboxStates.has(key)) return workDropboxStates.get(key)
    if (pending.has(key) && !pending.get(key).signal?.aborted) return pending.get(key).request
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

export function clearWorkDropboxStates() {
    generation++
    pending.clear()
    suggestions.clear()
    workDropboxStates.clear()
}
