import { shallowReactive } from 'vue'
import { show } from '@/actions/App/Http/Controllers/Admin/Teaching/WorkDropboxController'

export const workDropboxStates = shallowReactive(new Map())
const pending = new Map()
let generation = 0

export function workDropboxKey(userId, work) {
    return userId && work?.id && work?.teaching_course_id ? `${userId}:${work.teaching_course_id}:${work.id}` : null
}

export async function loadWorkDropbox(key, workId, fresh = false) {
    if (!key) return null
    if (!fresh && workDropboxStates.has(key)) return workDropboxStates.get(key)
    if (pending.has(key)) return pending.get(key)
    const requestGeneration = generation
    const request = axios.get(show.url(workId)).then(response => {
        if (generation === requestGeneration) workDropboxStates.set(key, response.data)
        return response.data
    }).finally(() => { if (pending.get(key) === request) pending.delete(key) })
    pending.set(key, request)
    return request
}

export function clearWorkDropboxStates() {
    generation++
    pending.clear()
    workDropboxStates.clear()
}
