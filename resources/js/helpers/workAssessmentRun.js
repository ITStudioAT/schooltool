import { reactive } from 'vue'
import { workDropboxKey } from './workDropbox'

const runs = reactive(new Map())
const preparing = reactive(new Set())
const pending = reactive(new Map())
const activeStates = ['queued', 'running']
const terminalStates = ['completed', 'failed', 'interrupted']

function storageKey(key) {
    return 'teaching:pending-assessment:' + key
}

export function pendingAssessmentRun(key) {
    if (!key) return null
    if (pending.has(key)) return pending.get(key)
    try {
        const request = JSON.parse(sessionStorage.getItem(storageKey(key)))
        return typeof request?.request_id === 'string' ? request : null
    } catch { return null }
}

export function rememberAssessmentRequest(key, request) {
    if (!key) return
    pending.set(key, request)
    try {
        if (request) sessionStorage.setItem(storageKey(key), JSON.stringify(request))
        else sessionStorage.removeItem(storageKey(key))
    } catch { /* The in-memory state still protects this work. */ }
}

export function forgetAssessmentRun(key) {
    if (!key) return
    runs.delete(key)
    preparing.delete(key)
    rememberAssessmentRequest(key, null)
    pending.delete(key)
}

export function prepareAssessmentRequest(key, starting) {
    if (!key) return
    if (starting) preparing.add(key)
    else preparing.delete(key)
}

export function observeAssessmentRun(key, run) {
    if (!key || typeof run?.id !== 'string' || ![...activeStates, ...terminalStates].includes(run.state)) return
    const request = pendingAssessmentRun(key)
    if (request) {
        const same = run.id === request.request_id || run.request_id === request.request_id
        if (!same && !activeStates.includes(run.state)) return
        rememberAssessmentRequest(key, null)
    }
    const last = runs.get(key)
    if (last?.id === run.id && terminalStates.includes(last.state) && activeStates.includes(run.state)) return
    if (last?.id !== run.id && last?.queued_at && run.queued_at && last.queued_at > run.queued_at) return
    if (last?.id !== run.id && activeStates.includes(last?.state) && terminalStates.includes(run.state)
        && !(run.queued_at && (!last.queued_at || run.queued_at > last.queued_at))) return
    runs.set(key, { id: run.id, state: run.state, date: run.date, queued_at: run.queued_at })
}

export function workAssessmentActivity(userId, work) {
    const key = workDropboxKey(userId, work)
    if (!key) return null
    if (preparing.has(key)) return { state: 'starting' }
    const observed = runs.get(key)
    const saved = work.assessment_run
    const savedIsNewer = saved?.queued_at && (!observed?.queued_at || saved.queued_at > observed.queued_at)
    const current = !observed || savedIsNewer || (saved?.id === observed.id && terminalStates.includes(saved.state)) ? saved : observed
    if (activeStates.includes(current?.state)) return current
    const request = pendingAssessmentRun(key)
    if (request) return { state: 'unconfirmed', date: request.date }

    return null
}

export function assessmentActivityLabel(activity) {
    return {
        starting: 'Erstellungsauftrag wird vorbereitet',
        unconfirmed: 'Erstellungsauftrag noch nicht bestätigt',
        queued: 'Leistungsfeststellung wartet auf den Start',
        running: 'Leistungsfeststellung wird im Hintergrund erstellt',
    }[activity?.state] || ''
}
