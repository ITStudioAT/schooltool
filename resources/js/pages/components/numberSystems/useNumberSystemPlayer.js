import { computed, onBeforeUnmount, ref, watch } from 'vue'
import axios from 'axios'
import { show, update, presence } from '@/actions/App/Http/Controllers/NumberSystemPlayerController'
import { player as probePlayer, update as probeUpdate, presence as probePresence } from '@/actions/App/Http/Controllers/Admin/NumberSystemLocalProbeController'

export function useNumberSystemPlayer(runId, context = {}) {
    const run = ref(null)
    const draft = ref({})
    const loading = ref(false)
    const saving = ref(false)
    const busy = ref(false)
    const dirty = ref(false)
    const conflict = ref(false)
    const error = ref('')
    const storageUnavailable = ref(false)
    let timer = null
    let poll = null
    let disposed = false
    let generation = 0
    let savingPromise = null
    let presencePending = false
    const cacheKey = () => context.probeId ? `schooltool-number-system-probe-${context.probeId}-${context.participant}-${runId.value}` : `schooltool-number-system-draft-${runId.value}`
    const showUrl = () => context.probeId ? probePlayer.url({ probe: context.probeId, participant: context.participant }) : show.url(Number(runId.value))
    const updateUrl = () => context.probeId ? probeUpdate.url({ probe: context.probeId, participant: context.participant }) : update.url(Number(runId.value))
    const message = failure => failure.response?.data?.message || Object.values(failure.response?.data?.errors ?? {}).flat()[0] || 'Verbindung unterbrochen. Dein Entwurf bleibt in diesem Fenster erhalten. Versuche erneut zu speichern.'
    const canPlay = computed(() => run.value?.session.status === 'running' && !run.value.completed && !run.value.solved)

    function cache(revision = run.value?.revision) {
        try {
            localStorage.setItem(cacheKey(), JSON.stringify({ mission: run.value?.mission?.id, revision, draft: draft.value }))
        } catch { storageUnavailable.value = true }
    }
    function clearCache() { try { localStorage.removeItem(cacheKey()) } catch { storageUnavailable.value = true } }
    function apply(value, replaceDraft = true) {
        run.value = value
        if (replaceDraft) { draft.value = structuredClone(value.draft ?? {}); dirty.value = false; conflict.value = false; clearCache() }
    }
    async function load(discardLocal = false) {
        if (!runId.value) { run.value = null; return }
        const currentGeneration = ++generation
        loading.value = true; error.value = ''
        try {
            const { data } = await axios.get(showUrl())
            if (disposed || generation !== currentGeneration) { return }
            let cached = null
            if (!discardLocal) {
                try { cached = JSON.parse(localStorage.getItem(cacheKey()) || 'null') } catch { storageUnavailable.value = true }
            }
            apply(data.run)
            if (!discardLocal && !data.run.solved && !data.run.completed) {
                if (cached && data.run.mission && cached.mission === data.run.mission.id && JSON.stringify(cached.draft) !== JSON.stringify(data.run.draft)) {
                    draft.value = cached.draft
                    dirty.value = true
                    conflict.value = cached.revision !== data.run.revision
                    cache(cached.revision)
                    if (conflict.value) { error.value = 'Es gibt einen neueren Spielstand. Lade ihn bewusst, bevor du deinen Entwurf überschreibst.' }
                    else { queueSave() }
                }
            }
        } catch (failure) { if (generation === currentGeneration) { error.value = message(failure) } }
        finally { if (generation === currentGeneration) { loading.value = false } }
    }
    async function refresh() {
        if (!run.value || saving.value || busy.value || loading.value || document.hidden) { return }
        const currentGeneration = generation
        try {
            const { data } = await axios.get(showUrl())
            if (disposed || currentGeneration !== generation || saving.value || busy.value || data.run.revision < run.value.revision) { return }
            if (dirty.value) {
                run.value.session = data.run.session
                run.value.clock = data.run.clock
                run.value.ranking = data.run.ranking
                if (data.run.revision !== run.value.revision) { conflict.value = true; error.value = 'Ein anderes Teammitglied oder Gerät hat den Stand verändert. Lade den gespeicherten Stand.' }
            } else { apply(data.run); error.value = '' }
        } catch (failure) { error.value = message(failure) }
    }
    function edit(value) { draft.value = value; dirty.value = true; cache(); queueSave() }
    function queueSave() { clearTimeout(timer); if (!conflict.value) { timer = setTimeout(() => void flush(), 650) } }
    async function flush() {
        clearTimeout(timer)
        if (savingPromise) { await savingPromise; return dirty.value && !conflict.value ? flush() : !conflict.value && !dirty.value }
        if (!dirty.value) { return true }
        if (!run.value?.mission || conflict.value || busy.value) { return false }
        const payload = JSON.parse(JSON.stringify(draft.value))
        const currentGeneration = ++generation
        saving.value = true; error.value = ''
        savingPromise = axios.post(updateUrl(), { action: 'draft', revision: run.value.revision, mission_id: run.value.mission.id, draft: payload })
            .then(({ data }) => {
                if (disposed || generation !== currentGeneration) { return false }
                const unchanged = JSON.stringify(draft.value) === JSON.stringify(payload)
                apply(data.run, unchanged)
                if (!unchanged) { cache(); queueSave() }
                return unchanged
            }).catch(failure => { error.value = message(failure); if (failure.response?.status === 409) { conflict.value = true } return false })
            .finally(() => { saving.value = false; savingPromise = null })
        return savingPromise
    }
    async function act(action) {
        if (busy.value || conflict.value || !run.value) { return false }
        if (!await flush()) { return false }
        busy.value = true; error.value = ''; ++generation
        try {
            const { data } = await axios.post(updateUrl(), { action, revision: run.value.revision, mission_id: run.value.mission.id,
                ...(action === 'answer' ? { draft: draft.value } : {}) })
            if (!disposed) { apply(data.run) }
            return true
        } catch (failure) { error.value = message(failure); if (failure.response?.status === 409) { conflict.value = true } return false }
        finally { busy.value = false }
    }
    function beforeUnload(event) {
        if ((dirty.value || saving.value) && !run.value?.completed && run.value?.session.status !== 'ended') { event.preventDefault(); event.returnValue = '' }
    }
    async function signalPresence() {
        if (!run.value || disposed || presencePending) { return }
        presencePending = true
        try {
            await axios.post(context.probeId ? probePresence.url({ probe: context.probeId, participant: context.participant }) : presence.url(Number(runId.value)))
        } catch { /* The next signal retries independently of the saved game state. */ }
        finally { presencePending = false }
    }
    function visibilityChanged() { if (!document.hidden) { void signalPresence() } }
    watch(runId, () => { run.value = null; dirty.value = false; void load() }, { immediate: true })
    poll = setInterval(() => void refresh(), 3500)
    const presencePoll = setInterval(() => void signalPresence(), 15000)
    document.addEventListener('visibilitychange', visibilityChanged)
    window.addEventListener('beforeunload', beforeUnload)
    onBeforeUnmount(() => { disposed = true; ++generation; clearTimeout(timer); clearInterval(poll); clearInterval(presencePoll); document.removeEventListener('visibilitychange', visibilityChanged); window.removeEventListener('beforeunload', beforeUnload) })
    return { run, draft, loading, saving, busy, dirty, conflict, error, storageUnavailable, canPlay, load, edit, flush, act }
}
