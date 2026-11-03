import { ref } from 'vue'
import { refreshProgress } from '@/actions/App/Http/Controllers/Admin/Teaching/WorkDropboxController'

export function useWorkCheckProgress() {
    const state = ref(null)
    const unavailable = ref(false)
    let timer = null
    let controller = null
    let sequence = 0

    function stop(expected = null) {
        if (expected !== null && expected !== sequence) return false
        sequence++
        clearTimeout(timer)
        timer = null
        controller?.abort()
        controller = null
        unavailable.value = false
        return true
    }

    function start(workId, progressId, steps = [], streaming = false) {
        stop()
        const operation = sequence
        state.value = { state: 'running', steps, completed: [], active: null }
        if (streaming) return operation
        let failures = 0
        const poll = async () => {
            if (operation !== sequence) return
            controller = new AbortController()
            try {
                const response = await axios.get(refreshProgress.url({ course_work: workId, progress_id: progressId }),
                    { signal: controller.signal, timeout: 5000 })
                if (operation !== sequence) return
                if (!response.data || !['running', 'done', 'failed'].includes(response.data.state)
                    || !Array.isArray(response.data.steps) || !Array.isArray(response.data.completed)) throw new Error('Ungültiger Fortschrittsstatus')
                state.value = response.data
                failures = 0
                unavailable.value = false
            } catch {
                // A progress record may not exist yet; report no invented step status.
                if (operation === sequence && ++failures >= 3) unavailable.value = true
            }
            if (operation === sequence && state.value?.state === 'running') timer = setTimeout(poll, 1500)
        }
        timer = setTimeout(poll, 200)
        return operation
    }

    function update(operation, progress) {
        if (operation !== sequence || !progress || !['running', 'done', 'failed'].includes(progress.state)
            || !Array.isArray(progress.steps) || !Array.isArray(progress.completed)) return
        state.value = progress
        unavailable.value = false
    }

    function finish(operation, finalState = null, failed = false) {
        if (!stop(operation)) return false
        if (finalState) state.value = failed && finalState.state === 'done' ? { ...finalState, state: 'failed', active: null } : finalState
        else if (failed && state.value) state.value = { ...state.value, state: 'failed', active: state.value.state === 'failed' ? state.value.active : null }
        else if (!failed && state.value?.state === 'running') state.value = null
        return true
    }

    function reset() {
        stop()
        state.value = null
    }

    return { state, unavailable, start, update, finish, reset }
}

export function createWorkCheckStream(onProgress) {
    let consumed = 0
    let pending = ''
    let result = null
    let failure = null
    function read(text) {
        if (typeof text !== 'string' || text.length < consumed) return
        pending += text.slice(consumed)
        consumed = text.length
        const lines = pending.split('\n')
        pending = lines.pop()
        for (const line of lines) {
            if (!line.trim()) continue
            let event
            try {
                event = JSON.parse(line)
            } catch {
                failure = { body: { message: 'Die Fortschrittsdaten sind ungültig.' } }
                continue
            }
            if (event.type === 'progress') onProgress(event.data)
            if (event.type === 'result') result = event.data
            if (event.type === 'error') failure = event.data
        }
    }
    return {
        options: { responseType: 'text', onDownloadProgress: event => read(event.event?.target?.responseText) },
        result(response) {
            if (typeof response.data !== 'string') return response.data
            read(response.data)
            if (failure) {
                const error = new Error(failure.body?.message || 'Die Prüfung konnte nicht abgeschlossen werden.')
                error.response = { status: failure.status, data: failure.body }
                throw error
            }
            if (!result) throw new Error('Die Prüfung wurde ohne vollständiges Ergebnis unterbrochen.')
            return result
        },
    }
}
