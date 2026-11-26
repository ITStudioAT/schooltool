<template>
    <span class="rack-module-graphic" :class="`is-${type}`" aria-hidden="true">
        <span v-if="type === 'server'" class="rack-module-graphic__drives">
            <i /><i /><i />
        </span>
        <span v-else class="rack-module-graphic__ports">
            <i v-for="port in type === 'router' ? 3 : 8" :key="port" />
        </span>
        <span v-if="type !== 'patch-panel'" class="rack-module-graphic__status" />
    </span>
</template>

<script setup>
defineProps({ type: { type: String, required: true } })
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

.rack-module-graphic.is-patch-panel { background: #edf0f3; }
.rack-module-graphic.is-switch { background: #a7bdcf; }
.rack-module-graphic.is-router { background: #bdcbd5; }
.rack-module-graphic.is-server { background: #778a9d; }

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
