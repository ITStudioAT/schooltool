<template>
    <ul class="ns-presence" aria-label="Verbindung je Person">
        <li v-for="(person, index) in people" :key="index" :class="`ns-presence-${person.status}`">
            <span class="ns-presence-dot" aria-hidden="true" /><div><strong>{{ person.name }}{{ person.external ? ' (extern)' : '' }}</strong><span>{{ labels[person.status] || labels.unknown }}</span><small v-if="person.last_seen_at">Letzter Kontakt {{ lastContact(person.last_seen_at) }}</small></div>
        </li>
    </ul>
</template>
<script setup>
defineProps({ people: { type: Array, default: () => [] } })
const labels = { connected: 'Verbunden', delayed: 'Signal verzögert', disconnected: 'Verbindung unterbrochen', unknown: 'Noch kein Verbindungssignal' }
const lastContact = value => new Date(value).toLocaleTimeString('de-AT', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
</script>
<style scoped>
.ns-presence { display: grid; gap: 12px; margin: 12px 0 0; padding: 0; list-style: none; }
.ns-presence li { display: flex; align-items: baseline; gap: 8px; }
.ns-presence-dot { width: 8px; height: 8px; border-radius: 50%; background: #99a6b8; flex: 0 0 8px; }
.ns-presence-connected .ns-presence-dot { background: #83dca8; }
.ns-presence-delayed .ns-presence-dot { background: #efd18a; }
.ns-presence-disconnected .ns-presence-dot { background: #ffae97; }
.ns-presence strong { font-size: .85rem; }
.ns-presence span:not(.ns-presence-dot), .ns-presence small { display: block; color: #c7d1df; font-size: .8rem; }
</style>
