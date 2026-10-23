<template>
    <div class="ns-game">
        <header class="ns-stack">
            <div class="ns-toolbar"><p class="ns-gold">Lokal ausprobieren</p><button v-if="info" class="ns-button ns-button-quiet" :disabled="busy" @click="removeDialog = true">Proberunde löschen</button></div>
            <p class="ns-small ns-muted">Spiele dein Abenteuer aus der Schüleransicht. Dein Fortschritt wird gespeichert.</p>
        </header>
        <div v-if="error" class="ns-alert ns-alert-error" role="alert">{{ error }} <button class="ns-button ns-button-secondary" :disabled="busy" @click="load">Erneut laden</button></div>
        <p v-if="loading" role="status" style="margin-top:20px">Dein Abenteuer wird geladen …</p>
        <template v-else-if="info">
            <div v-if="['waiting', 'paused'].includes(info.session.status)" class="ns-toolbar" style="margin-top:20px"><button class="ns-button" :disabled="busy" @click="start">{{ info.session.status === 'paused' ? 'Weiterspielen' : 'Jetzt selbst spielen' }}</button></div>
            <NumberSystemPlayer v-if="info.run" :key="probe" :run-id="info.run.id" :probe-id="probe" :participant="1" @back="back" />
            <section v-else class="ns-panel ns-stack" style="margin-top:20px"><h1>{{ info.session.title }}</h1><p>{{ info.session.status === 'ended' ? 'Diese Proberunde wurde beendet. Lege ein neues Abenteuer an, um selbst zu spielen.' : 'Deine gespeicherte Proberunde ist bereit.' }}</p><RouterLink class="ns-button ns-button-quiet" to="/admin/teaching-tools/games/number-systems">Zur Abenteuerübersicht</RouterLink></section>
        </template>
        <v-dialog v-model="removeDialog" persistent max-width="520"><v-card class="ns-dialog"><form @submit.prevent="remove"><h2>„{{ info?.session.title }}“ löschen?</h2><p>Deine Proberunde und ihr gespeicherter Fortschritt werden endgültig gelöscht.</p><p v-if="error" role="alert">{{ error }}</p><div class="ns-toolbar"><button type="button" class="ns-button ns-button-quiet" :disabled="busy" @click="removeDialog = false">Abbrechen</button><button class="ns-button" :disabled="busy">{{ busy ? 'Wird gelöscht …' : 'Proberunde endgültig löschen' }}</button></div></form></v-card></v-dialog>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { player, join as joinProbe, control as controlProbe, remove as removeProbe } from '@/actions/App/Http/Controllers/Admin/NumberSystemLocalProbeController'
import NumberSystemPlayer from '@/pages/components/numberSystems/NumberSystemPlayer.vue'
import '@/pages/components/numberSystems/game.css'
const route = useRoute(), router = useRouter()
const probe = computed(() => String(route.params.probe))
const info = ref(null), loading = ref(false), busy = ref(false), error = ref(''), removeDialog = ref(false)
let generation = 0, disposed = false
const previousTitle = document.title
const parameters = () => ({ probe: probe.value, participant: 1 })
const message = failure => failure.response?.data?.message || 'Deine Proberunde konnte nicht geladen werden. Versuche es erneut.'
async function load() {
    const ticket = ++generation
    loading.value = true; error.value = ''
    try {
        const { data } = await axios.get(player.url(parameters()))
        if (disposed || ticket !== generation) { return }
        info.value = data
        document.title = `${data.session.title} · Lokal ausprobieren`
    } catch (failure) {
        if (!disposed && ticket === generation) { error.value = message(failure); if (failure.response?.status === 404) { info.value = null } }
    } finally { if (!disposed && ticket === generation) { loading.value = false } }
}
async function start() {
    if (busy.value) { return }
    const ticket = ++generation, currentProbe = probe.value
    const action = info.value.session.status === 'paused' ? 'resume' : 'start'
    const joinData = { code: info.value.session.join_code, team_name: info.value.session.mode === 'teams' ? 'Mein Probespiel' : null }
    busy.value = true; error.value = ''
    try {
        if (!info.value.run) {
            await axios.post(joinProbe.url({ probe: currentProbe, participant: 1 }), joinData)
            if (disposed || ticket !== generation) { return }
        }
        await axios.post(controlProbe.url(currentProbe), { action })
        if (!disposed && ticket === generation) { await load() }
    } catch (failure) { if (!disposed && ticket === generation) { error.value = message(failure) } }
    finally { if (!disposed && probe.value === currentProbe) { busy.value = false } }
}
async function remove() {
    if (busy.value) { return }
    const ticket = ++generation
    busy.value = true; error.value = ''
    try { await axios.delete(removeProbe.url(probe.value)); if (!disposed && ticket === generation) { await back() } }
    catch (failure) { if (!disposed && ticket === generation) { error.value = message(failure) } }
    finally { if (!disposed && ticket === generation) { busy.value = false } }
}
function back() { return router.push('/admin/teaching-tools/games/number-systems') }
watch(probe, () => { info.value = null; busy.value = false; removeDialog.value = false; void load() }, { immediate: true })
onBeforeUnmount(() => { disposed = true; ++generation; document.title = previousTitle })
</script>
