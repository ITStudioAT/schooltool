import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import axios from 'axios'
import { rateLimitRetrySeconds } from './rateLimitRetry'
import { activity } from '@/actions/App/Http/Controllers/MultipleChoiceParticipantController'

export function useMcExamMode(attempt, active, requestConfig, serverOffset) {
    const fullscreen = ref(false)
    const fullscreenAvailable = ref(false)
    const monitoringError = ref('')
    const enabled = computed(() => Boolean(attempt.value?.exam_mode && !attempt.value?.teacher_preview))
    let session = ''
    let sequence = 0
    let queue = []
    let interval
    let sending = false
    let retryUntil = 0
    let disposed = false
    let windowFocused = document.hasFocus()
    let currentAttemptId

    function sample(leaving = false) {
        fullscreen.value = Boolean(document.fullscreenElement)
        if (!enabled.value || !active.value || disposed) return
        if (currentAttemptId !== attempt.value.id) {
            currentAttemptId = attempt.value.id
            session = crypto.randomUUID()
            sequence = 0
            queue = []
        }
        queue.push({ sequence: ++sequence, at: new Date(Date.now() + serverOffset.value).toISOString(),
            visible: !leaving && document.visibilityState !== 'hidden', focused: !leaving && windowFocused, fullscreen: fullscreen.value })
        void send(leaving || document.visibilityState === 'hidden')
    }

    async function send(keepalive) {
        if (!queue.length || Date.now() < retryUntil || (sending && !keepalive)) return
        const id = currentAttemptId
        const batch = queue.slice(0, 100)
        const body = { session, events: batch }
        sending = true
        let succeeded = false
        try {
            if (keepalive) {
                const csrf = document.cookie.split('; ').find(cookie => cookie.startsWith('XSRF-TOKEN='))?.slice(11)
                const headers = { ...requestConfig().headers, Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                if (csrf) headers['X-XSRF-TOKEN'] = decodeURIComponent(csrf)
                const response = await fetch(activity.url(id), { method: 'POST', credentials: 'same-origin', keepalive: true, headers, body: JSON.stringify(body) })
                if (response.status === 429) {
                    const failure = new Error('monitoring throttled')
                    failure.response = { status: 429, headers: response.headers }
                    throw failure
                }
                if (!response.ok) throw new Error('monitoring failed')
            } else {
                await axios.post(activity.url(id), body, requestConfig())
            }
            if (id === currentAttemptId) {
                queue = queue.filter(event => event.sequence > batch[batch.length - 1].sequence)
                monitoringError.value = ''
                succeeded = true
            }
        } catch (failure) {
            if (failure.response?.status === 429) retryUntil = Date.now() + rateLimitRetrySeconds(failure.response.headers) * 1000
            monitoringError.value = 'Das Prüfungsprotokoll konnte gerade nicht aktualisiert werden. Deine Bearbeitung läuft weiter.'
        } finally {
            sending = false
            if (succeeded && queue.length && !disposed) void send(keepalive)
        }
    }

    async function enterFullscreen() {
        monitoringError.value = ''
        try {
            await document.documentElement.requestFullscreen()
            fullscreen.value = Boolean(document.fullscreenElement)
        } catch {
            monitoringError.value = 'Vollbild konnte nicht gestartet werden. Bitte versuche es erneut.'
        }
    }

    function changed() { sample() }
    function visibilityChanged() {
        windowFocused = document.visibilityState !== 'hidden'
        sample()
    }
    function focusGained() {
        windowFocused = true
        sample()
    }
    function focusLost() {
        windowFocused = false
        sample()
    }
    function leaving() { sample(true) }

    watch([enabled, active, () => attempt.value?.id], () => {
        if (enabled.value && active.value) sample()
    })
    onMounted(() => {
        fullscreenAvailable.value = typeof document.documentElement.requestFullscreen === 'function'
        fullscreen.value = Boolean(document.fullscreenElement)
        document.addEventListener('visibilitychange', visibilityChanged)
        document.addEventListener('fullscreenchange', changed)
        window.addEventListener('focus', focusGained)
        window.addEventListener('blur', focusLost)
        window.addEventListener('pageshow', visibilityChanged)
        window.addEventListener('pagehide', leaving)
        window.addEventListener('online', changed)
        interval = setInterval(changed, 10000)
    })
    onUnmounted(() => {
        sample(true)
        disposed = true
        clearInterval(interval)
        document.removeEventListener('visibilitychange', visibilityChanged)
        document.removeEventListener('fullscreenchange', changed)
        window.removeEventListener('focus', focusGained)
        window.removeEventListener('blur', focusLost)
        window.removeEventListener('pageshow', visibilityChanged)
        window.removeEventListener('pagehide', leaving)
        window.removeEventListener('online', changed)
    })

    return { enabled, fullscreen, fullscreenAvailable, monitoringError, enterFullscreen, leave: leaving }
}
