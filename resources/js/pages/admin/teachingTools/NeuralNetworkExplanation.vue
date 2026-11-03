<template>
    <iframe
        ref="animationFrame"
        class="neural-network-explanation"
        :srcdoc="animationDocument"
        :style="{ height: `${frameHeight}px` }"
        title="Wie ein neuronales Netz lernt"
        sandbox="allow-scripts"
        referrerpolicy="no-referrer"
        @load="showStepFromRoute"
    />
</template>

<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import animationDocument from './NeuralNetworkAnimation.html?raw'

const route = useRoute()
const router = useRouter()
const animationFrame = ref(null)
const frameHeight = ref(1400)
const maximumStep = 21
let pendingStep = null
let savingStep = false
let routeChangeDuringSave = false

function showStepFromRoute() {
    const rawStep = route.query.lernschritt
    const step = typeof rawStep === 'string' && /^[1-9]\d*$/.test(rawStep) && Number(rawStep) <= maximumStep ? Number(rawStep) : 1
    animationFrame.value?.contentWindow?.postMessage({ type: 'schooltool-neural-network-show-step', step }, '*')
}

async function saveStep(step) {
    pendingStep = step
    if (savingStep) return
    savingStep = true
    try {
        while (pendingStep !== null) {
            const nextStep = pendingStep
            pendingStep = null
            if (route.query.lernschritt !== String(nextStep)) {
                await router.replace({ query: { ...route.query, lernschritt: String(nextStep) }, hash: route.hash })
            }
        }
    } finally {
        savingStep = false
        if (routeChangeDuringSave) {
            routeChangeDuringSave = false
            showStepFromRoute()
        }
    }
}

function handleFrameMessage(event) {
    const frameWindow = animationFrame.value?.contentWindow
    if (!frameWindow || event.source !== frameWindow) return
    if (event.data?.type === 'schooltool-neural-network-height') {
        const height = Number(event.data.height)
        if (Number.isFinite(height) && height >= 400 && height <= 10000) frameHeight.value = Math.ceil(height)
    }
    if (event.data?.type === 'schooltool-neural-network-step' && Number.isInteger(event.data.step)
        && event.data.step >= 1 && event.data.step <= maximumStep) {
        void saveStep(event.data.step)
    }
}

watch(() => route.query.lernschritt, () => {
    if (savingStep) routeChangeDuringSave = true
    else showStepFromRoute()
})
onMounted(() => window.addEventListener('message', handleFrameMessage))
onUnmounted(() => window.removeEventListener('message', handleFrameMessage))
</script>

<style scoped>
.neural-network-explanation { display: block; width: 100%; border: 0; background: #f3f8f7; }
</style>
