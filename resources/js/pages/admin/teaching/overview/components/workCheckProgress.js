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

    function start(workId, progressId) {
        stop()
        const operation = sequence
        state.value = { state: 'running', steps: [], completed: [], active: null }
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

    return { state, unavailable, start, finish, reset }
}
