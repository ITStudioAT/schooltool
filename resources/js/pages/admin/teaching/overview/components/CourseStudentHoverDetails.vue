<template>
    <v-tooltip v-model="isOpen" :disabled="dismissed" location="bottom" interactive open-on-focus
        :open-on-click="false" :eager="false" :open-delay="openDelay" :close-delay="200" :max-width="460"
        :content-class="['student-hover-tooltip', contentClass]">
        <template #activator="{ props: tooltipProps }">
            <slot name="activator" :props="activatorBindings(tooltipProps)" />
        </template>
        <div class="student-hover-header" @click.stop>
            <div>
                <strong>{{ title }}</strong>
                <div v-if="subtitle">{{ subtitle }}</div>
            </div>
            <button type="button" class="student-hover-close" aria-label="Detailfenster schließen" title="Schließen"
                @click.stop="close" @keydown.esc.prevent.stop="close">×</button>
        </div>
        <div ref="details" class="student-hover-body" tabindex="0" role="region" :aria-label="detailsLabel || title"
            @click.stop @keydown.esc.prevent.stop="close">
            <slot />
        </div>
    </v-tooltip>
</template>

<script setup>
import { mergeProps, nextTick, ref } from 'vue'
import { VTooltip } from 'vuetify/components/VTooltip'

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    detailsLabel: { type: String, default: '' },
    contentClass: { type: String, default: '' },
    openDelay: { type: Number, default: 250 },
})

const isOpen = ref(false)
const dismissed = ref(false)
const details = ref(null)
let activator = null

function activatorBindings(tooltipProps) {
    return mergeProps(tooltipProps, {
        onMouseenter: onMouseenter,
        onFocus: rememberActivator,
        onBlur: resetDismissal,
        onKeydown: onKeydown,
    })
}

function rememberActivator(event) {
    activator = event.currentTarget
}

function onMouseenter(event) {
    rememberActivator(event)
    resetDismissal()
}

function resetDismissal() {
    dismissed.value = false
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        event.preventDefault()
        event.stopPropagation()
        close()
    } else if (event.key === 'ArrowDown' && isOpen.value) {
        event.preventDefault()
        event.stopPropagation()
        details.value?.focus()
    }
}

async function close() {
    dismissed.value = true
    isOpen.value = false
    await nextTick()
    activator?.focus()
}
</script>

<style scoped>
.student-hover-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 12px 10px 16px;
    border-bottom: 1px solid #dbe3ee;
    background: #f1f5f9;
}

.student-hover-close {
    flex: 0 0 28px;
    width: 28px;
    height: 28px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #ffffff;
    color: #334155;
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
}

.student-hover-close:hover {
    background: #e2e8f0;
}

.student-hover-close:focus-visible,
.student-hover-body:focus-visible {
    outline: 2px solid #2563eb;
    outline-offset: -2px;
}

.student-hover-body {
    max-height: min(360px, 50vh);
    overflow-y: auto;
    overscroll-behavior: contain;
    padding: 8px 16px;
}
</style>

<style>
.v-tooltip > .v-overlay__content.student-hover-tooltip {
    width: min(460px, calc(100vw - 32px));
    padding: 0;
    overflow: hidden;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    background: #ffffff;
    color: #1e293b;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.18);
    font-size: 0.8125rem;
    line-height: 1.5;
    overflow-wrap: anywhere;
}
</style>
