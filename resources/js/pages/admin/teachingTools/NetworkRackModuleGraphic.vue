<template>
    <span class="rack-module-graphic" :class="`is-${type}`" aria-hidden="true">
        <span v-if="type === 'server'" class="rack-module-graphic__drives">
            <i /><i /><i />
        </span>
        <span class="rack-module-graphic__ports">
            <i v-for="port in portCount" :key="port" :data-rack-port="port - 1"
                :class="{ 'is-connected': connectedPorts.includes(port - 1), 'is-patched': patchedPorts.includes(port - 1) }" />
        </span>
        <span v-if="type !== 'patch-panel'" class="rack-module-graphic__status" />
    </span>
</template>

<script setup>
import { computed } from 'vue'
import { rackModulePorts } from './networkRackModules'

const props = defineProps({
    type: { type: String, required: true },
    connectedPorts: { type: Array, default: () => [] },
    patchedPorts: { type: Array, default: () => [] },
})
const portCount = computed(() => Math.max(rackModulePorts[props.type], ...props.connectedPorts.map(port => port + 1),
    ...props.patchedPorts.map(port => port + 1)))
</script>

<style scoped>
.rack-module-graphic {
    display: inline-flex;
    flex: 0 1 104px;
    inline-size: 104px;
    min-inline-size: 54px;
    block-size: 24px;
    align-items: center;
    justify-content: space-between;
    gap: 4px;
    padding-inline: 5px;
    border: 2px solid #485565;
    border-inline-width: 4px;
    border-radius: 2px;
    background: #d9e1e9;
}

.rack-module-graphic.is-patch-panel {
    block-size: auto;
    min-block-size: 24px;
    padding-block: 5px;
    background: #edf0f3;
}
.rack-module-graphic.is-switch { background: #a7bdcf; }
.rack-module-graphic.is-router { background: #bdcbd5; }
.rack-module-graphic.is-server { background: #778a9d; }
.rack-module-graphic.is-server .rack-module-graphic__ports { flex: 0 0 16px; }

.rack-module-graphic__ports,
.rack-module-graphic__drives {
    display: flex;
    min-inline-size: 0;
    flex: 1;
    align-items: center;
    gap: 2px;
}

.rack-module-graphic__ports > i {
    flex: 0 1 7px;
    block-size: 7px;
    border-block-end: 2px solid #8797a6;
    background: #263544;
}

.rack-module-graphic.is-patch-panel .rack-module-graphic__ports {
    display: grid;
    grid-template-columns: repeat(8, minmax(0, 7px));
    row-gap: 5px;
}

.rack-module-graphic__ports > i.is-connected {
    position: relative;
    border-block-end-color: #a17cd4;
    background: #67458a;
}

.rack-module-graphic__ports > i.is-connected::before {
    position: absolute;
    inset-block-start: -4px;
    inset-inline: 30%;
    block-size: 8px;
    border-radius: 1px;
    background: #a17cd4;
    content: '';
}

.rack-module-graphic__ports > i.is-patched { box-shadow: inset 0 0 0 2px #64dcc2; }

.rack-module-graphic.is-router .rack-module-graphic__ports > i {
    flex-basis: 10px;
    block-size: 8px;
}

.rack-module-graphic__drives > i {
    flex: 0 1 19px;
    block-size: 11px;
    border: 1px solid #203040;
    border-block-start: 3px solid #9babb9;
    background: #3b4b5c;
}

.rack-module-graphic__status {
    inline-size: 5px;
    block-size: 5px;
    flex: none;
    border: 1px solid #1e5b45;
    border-radius: 50%;
    background: #61d1a0;
}
</style>
