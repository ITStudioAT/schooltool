import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import axios from 'axios'
import { useMcExamMode } from './useMcExamMode'
import { rateLimitRetrySeconds } from './rateLimitRetry'
import { access, index, ready, save, show, start, submit } from '@/actions/App/Http/Controllers/MultipleChoiceParticipantController'

export function useMultipleChoiceSession(route) {
    const attempts = ref([])
    const attempt = ref(null)
    const answers = ref({})
    const pending = ref({})
    const loading = ref(true)
    const saving = ref(false)
    const submitting = ref(false)
    const error = ref('')
    const saveError = ref(false)
    const storageUnavailable = ref(false)
    const expiredPending = ref(false)
    const now = ref(Date.now())
    const accessBlocked = ref(false)
    const retryUntil = ref(0)
    const retrySeconds = computed(() => Math.max(0, Math.ceil((retryUntil.value - now.value) / 1000)))
    const serverOffset = ref(0)
    let token = ''
    let pollTimer
    let clockTimer
    let saveTimer
    let saveTask = null
    let generation = 0
    let answerRevision = 0
    let clockInitialized = false
    let disposed = false

    const finished = computed(() => ['submitted', 'timeout'].includes(attempt.value?.status))
    const hasPending = computed(() => Object.keys(pending.value).length > 0)
    const remainingSeconds = computed(() => remaining(attempt.value?.ends_at))
    const countdownSeconds = computed(() => remaining(attempt.value?.starts_at))
    const active = computed(() => Boolean(attempt.value?.questions?.length) && !attempt.value?.teacher_preview && !finished.value
        && !attempt.value?.paused_at && Boolean(attempt.value?.ends_at) && remainingSeconds.value > 0 && countdownSeconds.value === 0)
    const answeredCount = computed(() => (attempt.value?.questions || []).filter(question => answers.value[question.id]?.length).length)
    const isPublic = computed(() => route.path === '/multiple-choice')

    const exam = useMcExamMode(attempt, active, requestConfig, serverOffset)
    const canAnswer = computed(() => active.value && (!exam.enabled.value || exam.fullscreen.value))

    function remaining(value) {
        if (!value) {
            return 0
        }
        return Math.max(0, Math.ceil((Date.parse(value) - (attempt.value?.paused_at ? Date.parse(attempt.value.paused_at) : now.value + serverOffset.value)) / 1000))
    }

    function requestConfig() {
        return { headers: token ? { 'X-MC-Access': token } : {}, __skipGlobalLoading: true }
    }

    function storageKey(id) {
        return `schooltool:multiple-choice:pending:${id}`
    }

    function persistPending() {
        if (!attempt.value || attempt.value.teacher_preview) {
            return
        }
        try {
            if (hasPending.value && !finished.value) {
                window.localStorage.setItem(storageKey(attempt.value.id), JSON.stringify(pending.value))
            } else {
                window.localStorage.removeItem(storageKey(attempt.value.id))
            }
        } catch {
            storageUnavailable.value = true
        }
    }

    function acceptAttempt(value, restore = false) {
        if (value.server_now) {
            const offset = Date.parse(value.server_now) - Date.now()
            if (Number.isFinite(offset)) {
                serverOffset.value = clockInitialized ? Math.max(serverOffset.value, offset) : offset
                clockInitialized = true
            }
        }
        now.value = Date.now()
        if (value.teacher_preview) {
            pending.value = {}
        }
        if (restore && !value.teacher_preview) {
            pending.value = {}
            try {
                const stored = JSON.parse(window.localStorage.getItem(storageKey(value.id)) || '{}')
                if (stored && typeof stored === 'object' && !Array.isArray(stored)) {
                    pending.value = Object.fromEntries(Object.entries(stored).filter(([, selected]) =>
                        Array.isArray(selected) && selected.every(option => ['string', 'number'].includes(typeof option))))
                }
            } catch {
                storageUnavailable.value = true
            }
        }
        const wasFinished = finished.value
        attempt.value = value
        if (wasFinished && !finished.value) expiredPending.value = false
        if (finished.value) {
            expiredPending.value ||= hasPending.value
            pending.value = {}
        }
        answers.value = { ...(value.answers || {}), ...pending.value }
        persistPending()
    }

    function explainFailure(failure) {
        if ([401, 403, 404].includes(failure.response?.status)) {
            persistPending()
            accessBlocked.value = true
            attempt.value = null
            answers.value = {}
            pending.value = {}
        }
        if (failure.response?.status === 429) {
            retryUntil.value = Math.max(retryUntil.value, Date.now() + rateLimitRetrySeconds(failure.response.headers) * 1000)
            return 'Die Verbindung wird kurz gedrosselt. Wir verbinden dich nach der Wartezeit automatisch erneut. Deine gespeicherten Antworten bleiben erhalten.'
        }
        if (failure.response?.status === 403 && failure.response?.data?.message) {
            return failure.response.data.message
        }
        if ([401, 403, 404].includes(failure.response?.status)) {
            return token ? 'Dieser Zugang ist ungültig oder nicht mehr verfügbar. Bitte wende dich an deine Lehrperson.'
                : 'Dieser Test ist für dein Konto nicht verfügbar. Bitte melde dich mit deinem Schülerkonto an.'
        }
        return failure.response?.data?.message || 'Die Verbindung zum Server ist unterbrochen. Bitte versuche es erneut.'
    }

    async function load() {
        if (Date.now() < retryUntil.value) return
        accessBlocked.value = false
        const currentGeneration = ++generation
        loading.value = true
        error.value = ''
        attempt.value = null
        pending.value = {}
        token = new URLSearchParams(String(route.hash || '').replace(/^#/, '')).get('access') || ''
        try {
            if (isPublic.value) {
                if (!token) {
                    error.value = 'Bitte öffne den vollständigen persönlichen Zugangslink aus deiner Einladung.'
                    return
                }
                const { data } = await axios.get(access.url(), requestConfig())
                if (currentGeneration === generation && !disposed) {
                    acceptAttempt(data.attempt, true)
                }
            } else if (route.params.attempt) {
                await openAttempt(route.params.attempt, currentGeneration)
            } else {
                const { data } = await axios.get(index.url(), requestConfig())
                if (currentGeneration === generation && !disposed) {
                    attempts.value = data.attempts
                }
            }
        } catch (failure) {
            if (currentGeneration === generation && !disposed) {
                error.value = explainFailure(failure)
            }
        } finally {
            if (currentGeneration === generation && !disposed) {
                loading.value = false
                schedulePoll()
                if (hasPending.value && active.value) {
                    void flush()
                }
            }
        }
    }

    async function openAttempt(id, expectedGeneration = generation) {
        if (attempt.value && String(attempt.value.id) !== String(id)) {
            expectedGeneration = ++generation
        }
        error.value = ''
        try {
            const { data } = await axios.get(show.url(id), requestConfig())
            if (expectedGeneration === generation && !disposed) {
                acceptAttempt(data.attempt, !attempt.value || String(attempt.value.id) !== String(id))
                schedulePoll()
                if (hasPending.value && active.value) {
                    void flush()
                }
            }
        } catch (failure) {
            error.value = explainFailure(failure)
        }
    }

    async function markReady() {
        if (!attempt.value || attempt.value.teacher_preview || submitting.value || Date.now() < retryUntil.value) {
            return
        }
        submitting.value = true
        error.value = ''
        try {
            const { data } = await axios.post(ready.url(attempt.value.id), {}, requestConfig())
            answerRevision++
            acceptAttempt(data.attempt)
        } catch (failure) {
            error.value = explainFailure(failure)
        } finally {
            submitting.value = false
        }
    }

    async function startAttempt() {
        if (!attempt.value || attempt.value.teacher_preview || attempt.value.started_at || finished.value || submitting.value || Date.now() < retryUntil.value || (exam.enabled.value && !exam.fullscreen.value)) return
        submitting.value = true
        error.value = ''
        try {
            const { data } = await axios.post(start.url(attempt.value.id), {}, requestConfig())
            answerRevision++
            acceptAttempt(data.attempt)
        } catch (failure) {
            error.value = explainFailure(failure)
        } finally {
            submitting.value = false
        }
    }

    function selectAnswer(question, optionId, checked) {
        if (!canAnswer.value || submitting.value) {
            return
        }
        const current = answers.value[question.id] || []
        const multiple = (question.type || question.answer_type) === 'multiple'
        const next = multiple ? (checked ? [...new Set([...current, optionId])]
            : current.filter(id => String(id) !== String(optionId))) : [optionId]
        pending.value = { ...pending.value, [question.id]: next }
        answers.value = { ...answers.value, [question.id]: next }
        answerRevision++
        persistPending()
        clearTimeout(saveTimer)
        saveTimer = setTimeout(() => void flush(), 350)
    }

    async function flush() {
        clearTimeout(saveTimer)
        if (Date.now() < retryUntil.value) return !hasPending.value
        if (saveTask) {
            await saveTask
            return hasPending.value && !saveError.value && active.value ? flush() : !hasPending.value
        }
        if (!hasPending.value || !active.value) {
            return !hasPending.value
        }
        const id = attempt.value.id
        const currentGeneration = generation
        const sent = JSON.parse(JSON.stringify(pending.value))
        saving.value = true
        saveError.value = false
        saveTask = (async () => {
            try {
                const { data } = await axios.put(save.url(id), { answers: sent }, requestConfig())
                if (currentGeneration !== generation || disposed) {
                    return
                }
                const remainingPending = { ...pending.value }
                for (const [questionId, selection] of Object.entries(sent)) {
                    if (JSON.stringify(remainingPending[questionId]) === JSON.stringify(selection)) {
                        delete remainingPending[questionId]
                    }
                }
                pending.value = remainingPending
                answerRevision++
                acceptAttempt(data.attempt)
            } catch (failure) {
                if (currentGeneration === generation && !disposed) {
                    saveError.value = true
                    if ([401, 403, 404, 429].includes(failure.response?.status)) error.value = explainFailure(failure)
                    if ([409, 422].includes(failure.response?.status)) {
                        error.value = explainFailure(failure)
                    }
                }
            } finally {
                saving.value = false
            }
        })()
        await saveTask
        saveTask = null
        if (hasPending.value && !saveError.value && active.value) {
            return flush()
        }
        return !hasPending.value
    }

    async function submitAttempt() {
        if (!canAnswer.value || submitting.value || Date.now() < retryUntil.value) {
            return false
        }
        submitting.value = true
        error.value = ''
        try {
            if (!await flush()) {
                error.value = 'Deine Antworten sind noch nicht gespeichert. Stelle die Verbindung wieder her und versuche die Abgabe erneut.'
                return false
            }
            const { data } = await axios.post(submit.url(attempt.value.id), {}, requestConfig())
            answerRevision++
            acceptAttempt(data.attempt)
            return true
        } catch (failure) {
            error.value = explainFailure(failure)
            return false
        } finally {
            submitting.value = false
        }
    }

    async function refresh(manual = false) {
        if (manual === true && Date.now() >= retryUntil.value) accessBlocked.value = false
        if (accessBlocked.value) return
        if (disposed || loading.value || saving.value || submitting.value || Date.now() < retryUntil.value) {
            return
        }
        if (!attempt.value) {
            if (isPublic.value || route.params.attempt) {
                await load()
                return
            }
            try {
                const { data } = await axios.get(index.url(), requestConfig())
                if (!disposed && !attempt.value) {
                    attempts.value = data.attempts
                    error.value = ''
                }
            } catch (failure) {
                error.value = explainFailure(failure)
            }
            return
        }
        const currentGeneration = generation
        const id = attempt.value.id
        const requestRevision = answerRevision
        try {
            const { data } = await axios.get(show.url(id), requestConfig())
            if (currentGeneration === generation && requestRevision === answerRevision && !saving.value
                && String(attempt.value?.id) === String(id) && !disposed) {
                acceptAttempt(data.attempt)
                error.value = ''
                if (hasPending.value && active.value) {
                    await flush()
                }
            }
        } catch (failure) {
            if (currentGeneration === generation && !disposed) {
                error.value = explainFailure(failure)
            }
        }
    }

    function schedulePoll() {
        clearTimeout(pollTimer)
        if (disposed || accessBlocked.value) {
            return
        }
        pollTimer = setTimeout(async () => {
            await refresh()
            schedulePoll()
        }, Math.max(attempt.value && !finished.value ? 2000 : 5000, retryUntil.value - Date.now()))
    }

    function preventLostWork(event) {
        if (hasPending.value) {
            event.preventDefault()
            event.returnValue = ''
        }
    }

    watch(() => route.fullPath, () => void load())
    watch(active, (value, previous) => {
        if (!value && previous) {
            void refresh()
        }
        if (value && hasPending.value) void flush()
    })
    watch(countdownSeconds, (value, previous) => {
        if (value === 0 && previous > 0 && !finished.value) {
            void refresh()
        }
    })
    onMounted(() => {
        void load()
        clockTimer = setInterval(() => { now.value = Date.now() }, 250)
        window.addEventListener('online', refresh)
        window.addEventListener('beforeunload', preventLostWork)
    })
    onUnmounted(() => {
        disposed = true
        generation++
        clearTimeout(pollTimer)
        clearTimeout(saveTimer)
        clearInterval(clockTimer)
        window.removeEventListener('online', refresh)
        window.removeEventListener('beforeunload', preventLostWork)
    })

    return { exam, retrySeconds, attempts, attempt, answers, pending, loading, saving, submitting, error, saveError, storageUnavailable, expiredPending,
        finished, hasPending, active, canAnswer, remainingSeconds, countdownSeconds, answeredCount, isPublic,
        load, openAttempt, markReady, startAttempt, selectAnswer, flush, submitAttempt, refresh }
}
