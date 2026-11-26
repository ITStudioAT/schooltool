<template>
    <span v-if="device.id === 'workstation'" class="network-workstation-icon" aria-hidden="true">
        <span class="network-workstation-icon__monitor" />
        <span class="network-workstation-icon__desk" />
        <span class="network-workstation-icon__tower" />
    </span>
    <span v-else-if="device.id === 'server-rack'" class="network-rack-icon" aria-hidden="true">
        <span v-for="(module, index) in rackModules" :key="index" :data-module="module" />
    </span>
    <span v-else-if="device.id === 'standing-table'" class="network-standing-table-icon" aria-hidden="true">
        <span class="network-standing-table-icon__pedestal" />
        <span class="network-standing-table-icon__laptop" />
    </span>
    <span v-else-if="device.id === 'cable-duct'" class="network-cable-duct-icon" aria-hidden="true" />
    <span v-else-if="device.id === 'switch'" class="network-switch-icon" aria-hidden="true">
        <span /><span /><span /><span />
    </span>
    <span v-else-if="['network-outlet', 'double-network-outlet'].includes(device.id)"
        class="network-outlet-icon" aria-hidden="true">
        <span /><span v-if="device.id === 'double-network-outlet'" />
    </span>
    <v-icon v-else :icon="device.icon" size="23" aria-hidden="true" />
</template>

<script setup>
defineProps({
    device: { type: Object, required: true },
    rackModules: { type: Array, default: () => [] },
})
</script>

<style scoped>
.network-workstation-icon {
    position: relative;
    display: inline-block;
    inline-size: var(--desk-width, 30px);
    block-size: var(--desk-height, 22px);
    vertical-align: middle;
}

.network-workstation-icon__monitor,
.network-workstation-icon__desk,
.network-workstation-icon__tower {
    position: absolute;
}

.network-workstation-icon__monitor {
    inset: 1% 33% auto;
    block-size: 36%;
    border: 1px solid currentColor;
    border-radius: 2px;
    background: #d8e6f4;
}

.network-workstation-icon__monitor::after {
    position: absolute;
    inset: 100% 45% auto;
    block-size: 30%;
    background: currentColor;
    content: '';
}

.network-workstation-icon__desk {
    inset: 45% 1% 4%;
    border-block-start: 3px solid currentColor;
    border-inline: 2px solid currentColor;
    border-radius: 2px 2px 0 0;
}

.network-workstation-icon__tower {
    inset: 56% 11% 6% auto;
    inline-size: 18%;
    border: 1px solid currentColor;
    border-radius: 1px;
    background: #d8e6f4;
}

.network-workstation-icon__tower::after {
    position: absolute;
    inset: 22% 22% auto;
    border-block-start: 1px solid currentColor;
    content: '';
}

.network-rack-icon {
    display: inline-flex;
    flex-direction: column;
    gap: 1px;
    inline-size: var(--rack-width, 20px);
    block-size: var(--rack-height, 26px);
    padding: 3px 4px;
    border: 2px solid currentColor;
    border-radius: 2px;
    background: linear-gradient(to right, #a6b3be 2px, #edf3f8 2px calc(100% - 2px), #a6b3be calc(100% - 2px));
    overflow: hidden;
}

.network-rack-icon > span {
    position: relative;
    flex: 1 1 0;
    max-block-size: 12%;
    min-block-size: 0;
    border: 1px solid currentColor;
    background: #d8e6f4;
}

.network-rack-icon > [data-module="patch-panel"] { background: #edf0f3; }
.network-rack-icon > [data-module="router"] { background: #cdd5de; }
.network-rack-icon > [data-module="server"] { background: #b6c4d2; }

.network-rack-icon > span::after {
    position: absolute;
    inset: 1px 1px auto auto;
    inline-size: 2px;
    block-size: 2px;
    background: currentColor;
    content: '';
}

.network-rack-icon > span:not([data-module]) {
    visibility: hidden;
}

.network-standing-table-icon {
    position: relative;
    display: inline-block;
    inline-size: var(--standing-table-width, 24px);
    block-size: var(--standing-table-height, 25px);
}

.network-standing-table-icon::before {
    position: absolute;
    inset: 36% 4% auto;
    block-size: 24%;
    border: 2px solid currentColor;
    border-radius: 50%;
    background: #d8e6f4;
    content: '';
}

.network-standing-table-icon__pedestal {
    position: absolute;
    inset: 58% 47% 7%;
    background: currentColor;
}

.network-standing-table-icon__laptop {
    position: absolute;
    inset: 4% 28% auto;
    block-size: 37%;
    border: 2px solid currentColor;
    border-radius: 2px 2px 0 0;
    background: #eef3f9;
}

.network-standing-table-icon__laptop::after {
    position: absolute;
    inset: 100% -32% auto;
    block-size: max(2px, 20%);
    border: 1px solid currentColor;
    border-radius: 1px;
    background: #d8e6f4;
    transform: skewX(-14deg);
    content: '';
}

.network-standing-table-icon::after {
    position: absolute;
    inset: auto 20% 5%;
    border-block-end: 2px solid currentColor;
    content: '';
}

.network-cable-duct-icon {
    display: inline-block;
    inline-size: 26px;
    block-size: 12px;
    border: 2px solid currentColor;
    border-radius: 2px;
    background: linear-gradient(transparent 35%, currentColor 35% 65%, transparent 65%);
}

.network-switch-icon {
    display: inline-flex;
    align-items: center;
    gap: 1px;
    padding: 2px;
    border: 2px solid currentColor;
    border-radius: 2px;
    background: #d8e6f4;
}

.network-switch-icon > span {
    inline-size: 4px;
    block-size: 5px;
    border: 1px solid currentColor;
    background: white;
}

.network-outlet-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 2px;
    min-inline-size: 19px;
    block-size: 20px;
    padding: 2px;
    border: 2px solid currentColor;
    border-radius: 3px;
}

.network-outlet-icon > span {
    inline-size: 8px;
    block-size: 8px;
    border: 1px solid currentColor;
    border-block-end-width: 2px;
    background: #d8e6f4;
}
</style>
