<template>
    <v-app><v-main class="zero-guest"><div class="zero-manager">
        <header class="zero-hero"><div><span class="zero-eyebrow">SCHOOLTOOL · MATURA</span><h1>00-Manager<span class="zero-hero-dot">.</span></h1><p>{{ state?.session.name || 'Ihr Zugang zur gemeinsamen Koordination' }}</p></div><div v-if="state" class="zero-live" :class="{ 'zero-live--offline': stale }"><span></span>{{ stale ? 'Verbindung unterbrochen' : 'Gemeinsamer Stand' }}<small>Aktualisiert {{ time(state.server_time) }}</small></div></header>
        <p v-if="error" class="zero-error" role="alert">{{ error }}</p>
        <section v-if="!state && !loading" class="zero-panel">
            <h2>Als Aufsicht anmelden</h2><p class="zero-help my-4">Öffnen Sie den persönlichen Link der Prüfungsleitung oder fügen Sie den Zugang hier ein.</p>
            <form class="zero-request-form" @submit.prevent="login"><label class="zero-grow">Persönlicher Zugang<input v-model="token" autocomplete="off" type="password" required></label><button class="zero-button" :disabled="busy">Anmelden</button></form>
            <p class="zero-help mt-4">Der Zugang gilt ausschließlich für die zugeordnete Matura und Station.</p>
        </section>
        <p v-if="loading" class="zero-loading" role="status">Ihr Stationszugang wird geprüft …</p>
        <MaturaBoard v-if="state" :state="state" :disabled="busy || stale" @action="runAction" />
        <button v-if="state" class="zero-link mt-5" @click="logout">Stationszugang abmelden</button>
    </div></v-main></v-app>
</template>
<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import axios from 'axios'
import MaturaBoard from './MaturaBoard.vue'
import { action, login as loginRoute, logout as logoutRoute, state as stateRoute } from '@/actions/App/Http/Controllers/MaturaStationController'
import { errorMessage, operationKey, time } from './maturaFormat'
import '../../../../../css/matura-manager.css'
const state = ref(null)
const token = ref('')
const error = ref('')
const loading = ref(true)
const busy = ref(false)
const stale = ref(false)
let timer
let destroyed = false
async function refresh() {
    try { state.value = (await axios.get(stateRoute.url(), { timeout: 12000 })).data; if (stale.value) error.value = ''; stale.value = false }
    catch (failure) { stale.value = true; if ([401, 403].includes(failure.response?.status)) state.value = null; if (state.value || failure.response?.status !== 401) error.value = errorMessage(failure) }
}
async function login() {
    busy.value = true; error.value = ''
    try { state.value = (await axios.post(loginRoute.url(), { token: token.value.split('#').pop().trim() })).data; token.value = ''; stale.value = false }
    catch (failure) { error.value = errorMessage(failure) }
    finally { busy.value = false }
}
async function runAction(data) {
    if (busy.value) return
    busy.value = true; error.value = ''
    try { state.value = (await axios.post(action.url(), { ...data, operation_key: operationKey() })).data; stale.value = false }
    catch (failure) { error.value = errorMessage(failure); await refresh() }
    finally { busy.value = false }
}
async function logout() { try { await axios.post(logoutRoute.url()); state.value = null } catch (failure) { error.value = errorMessage(failure) } }
async function poll() { if (destroyed) return; if (!document.hidden && !busy.value && state.value) await refresh(); timer = setTimeout(poll, 3000) }
onMounted(async () => {
    if (window.location.hash) { token.value = window.location.hash.slice(1); window.history.replaceState(null, '', window.location.pathname); await login() }
    else await refresh()
    loading.value = false; poll()
})
onBeforeUnmount(() => { destroyed = true; clearTimeout(timer) })
</script>
