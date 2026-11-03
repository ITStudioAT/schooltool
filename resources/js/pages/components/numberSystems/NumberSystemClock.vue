<template>
    <div v-if="clock && clock.mode !== 'none'" class="ns-clock" :class="{ 'ns-clock-expired': expired }" aria-label="Spielzeit" role="timer">
        <div><span>{{ clock.mode === 'countdown' ? 'Verbleibend' : 'Verstrichen' }}</span><strong>{{ formatGameTime(clock.mode === 'countdown' ? Math.max(0, clock.limit_seconds - seconds) : seconds) }}</strong></div>
        <p v-if="clock.mode === 'countdown'">Verstrichen {{ formatGameTime(seconds) }}<template v-if="expired"> · Zeitlimit erreicht. Der Fortschritt bleibt erhalten.</template></p>
        <p v-if="!compact">{{ clock.ticking ? 'Seit Rundenstart · ohne Pausen' : 'Uhr steht · gespeicherter Stand' }}</p>
    </div>
</template>
<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { formatGameTime, gameClockSeconds } from './numberSystemTime'
const props = defineProps({ clock: Object, compact: Boolean })
const current = ref(performance.now())
let anchor = current.value
watch(() => props.clock, () => { anchor = performance.now(); current.value = anchor }, { immediate: true })
const seconds = computed(() => gameClockSeconds(props.clock, anchor, current.value))
const expired = computed(() => props.clock?.mode === 'countdown' && seconds.value >= props.clock.limit_seconds)
const tick = setInterval(() => { current.value = performance.now() }, 500)
onBeforeUnmount(() => clearInterval(tick))
</script>
<style scoped>
.ns-clock { padding: 12px 16px; border: 1px solid #53647c; border-radius: 12px; background: #192435; color: #e8edf5; }
.ns-clock > div { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 6px 20px; }
.ns-clock strong { color: #efd18a; font: 600 1.6rem/1.2 Consolas, ui-monospace, monospace; font-variant-numeric: tabular-nums; }
.ns-clock p { font-size: .8rem; margin: 5px 0 0; color: #c7d1df; }
.ns-clock-expired { border-color: #ad925a; }
</style>
