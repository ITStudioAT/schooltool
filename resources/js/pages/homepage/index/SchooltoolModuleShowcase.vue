<template>
    <section v-if="modules.length" class="module-showcase" :aria-labelledby="`${sectionId}-title`">
        <h2 :id="`${sectionId}-title`">Schooltool im Einsatz</h2>

        <div class="module-showcase-grid" :style="{ '--module-count': modules.length }">
            <div v-for="module in modules" :key="module.id" class="module-showcase-item">
                <h3 class="module-showcase-choice">
                    <button
                        type="button"
                        :aria-expanded="activeId === module.id"
                        :aria-disabled="activeId === module.id ? 'true' : undefined"
                        :aria-controls="`${sectionId}-${module.id}`"
                        @click="selectModule(module.id)">
                        {{ module.title }}
                        <span class="module-showcase-chevron" aria-hidden="true"></span>
                    </button>
                </h3>

                <div
                    v-show="activeId === module.id"
                    :id="`${sectionId}-${module.id}`"
                    class="module-showcase-panel"
                    :class="{ 'module-showcase-panel--selected': selectedId === module.id }"
                    role="region"
                    :aria-labelledby="`${sectionId}-${module.id}-title`">
                    <div>
                        <h4 :id="`${sectionId}-${module.id}-title`">{{ module.title }}</h4>
                        <p>{{ module.description }}</p>
                    </div>
                    <div class="module-showcase-art" :class="`module-showcase-art--${module.motif}`" aria-hidden="true">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, ref, useId } from 'vue'

const props = defineProps({
    modules: { type: Array, required: true },
})

const sectionId = useId()
const selectedId = ref(null)
const activeId = computed(() => (
    props.modules.some(({ id }) => id === selectedId.value) ? selectedId.value : props.modules[0]?.id
))

function selectModule(id) {
    if (id !== activeId.value) {
        selectedId.value = id
    }
}
</script>

<style scoped>
.module-showcase {
    --module-panel: #fff0dc;
    --module-highlight: #ffcc89;
    --module-outline: #a74d17;
    padding-block: clamp(24px, 4vw, 52px) clamp(46px, 8vw, 100px);
    border-top: 1px solid var(--intro-rule);
}

.module-showcase h2 {
    margin: 0 0 clamp(18px, 3vw, 32px);
    font-size: clamp(1.9rem, 4vw, 3.2rem);
    font-weight: 800;
    letter-spacing: -0.045em;
    line-height: 1.05;
}

.module-showcase-grid {
    display: grid;
}

.module-showcase-choice {
    margin: 0;
    font-size: 1rem;
}

.module-showcase-choice button {
    position: relative;
    display: flex;
    width: 100%;
    min-height: 58px;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 17px;
    border-block-end: 1px solid var(--intro-rule);
    color: var(--intro-ink);
    font-weight: 650;
    text-align: start;
}

.module-showcase-choice button:is(:hover, [aria-expanded='true']) {
    background: var(--module-panel);
}

.module-showcase-choice button[aria-expanded='true']::before {
    position: absolute;
    inset-block: 17%;
    inset-inline-start: 0;
    width: 3px;
    background: var(--module-outline);
    content: '';
}

.module-showcase-choice button:focus-visible {
    z-index: 1;
    outline: 3px solid var(--intro-ink);
    outline-offset: -3px;
}

.module-showcase-chevron {
    width: 0.55rem;
    height: 0.55rem;
    flex: none;
    border-right: 2px solid currentColor;
    border-bottom: 2px solid currentColor;
    transform: rotate(-45deg);
}

.module-showcase-panel {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 0.85fr);
    align-items: center;
    gap: clamp(12px, 3vw, 40px);
    padding: clamp(20px, 4vw, 52px);
    border-radius: 18px;
    background: var(--module-panel);
}

.module-showcase-panel h4 {
    margin: 0 0 9px;
    overflow-wrap: anywhere;
    font-size: clamp(1.45rem, 3.1vw, 2.6rem);
    font-weight: 800;
    letter-spacing: -0.045em;
    line-height: 1.05;
}

.module-showcase-panel p {
    max-width: 30ch;
    margin: 0;
    line-height: 1.5;
}

.module-showcase-art {
    position: relative;
    width: min(100%, 13rem);
    aspect-ratio: 1;
    justify-self: center;
}

.module-showcase-art span {
    position: absolute;
    display: block;
}

.module-showcase-art--registration span:nth-child(1) {
    inset: 11% 19% 14% 14%;
    border: 1px solid var(--module-outline);
    border-radius: 8px;
    background: var(--intro-paper);
    box-shadow: 7px 7px var(--module-highlight);
}

.module-showcase-art--registration span:nth-child(2) {
    top: 31%;
    left: 24%;
    width: 39%;
    height: 2px;
    background: var(--module-outline);
    box-shadow: 0 14px var(--module-outline), 0 28px var(--module-outline);
}

.module-showcase-art--registration span:nth-child(3) {
    right: 8%;
    bottom: 13%;
    display: grid;
    width: 32%;
    aspect-ratio: 1;
    place-items: center;
    border-radius: 6px;
    background: var(--intro-orange);
}

.module-showcase-art--registration span:nth-child(3)::after {
    width: 42%;
    height: 24%;
    border-bottom: 3px solid var(--intro-ink);
    border-left: 3px solid var(--intro-ink);
    content: '';
    transform: translateY(-12%) rotate(-45deg);
}

.module-showcase-art--books span {
    height: 17%;
    border: 1px solid var(--module-outline);
    border-inline-start: 7px solid var(--module-outline);
    border-radius: 3px;
}

.module-showcase-art--books span:nth-child(1) {
    inset: auto 9% 19% 23%;
    background: var(--intro-paper);
}

.module-showcase-art--books span:nth-child(2) {
    inset: auto 23% 37% 10%;
    background: var(--intro-orange);
}

.module-showcase-art--books span:nth-child(3) {
    inset: auto 22% 56% 20%;
    background: var(--module-highlight);
    transform: rotate(-8deg);
}

.module-showcase-art--timetable span:nth-child(1) {
    inset: 16% 11%;
    border: 1px solid var(--module-outline);
    border-top-width: 8px;
    border-radius: 6px;
    background:
        linear-gradient(90deg, transparent calc(100% - 1px), var(--module-outline) 0) 0 0 / 33.33% calc(100% - 1px) repeat-x,
        linear-gradient(transparent calc(100% - 1px), var(--module-outline) 0) 0 0 / 100% 33.33% repeat-y,
        var(--intro-paper);
}

.module-showcase-art--timetable span:nth-child(2) {
    inset: 26% 64% 59% 16%;
    border-radius: 2px;
    background: var(--intro-orange);
}

.module-showcase-art--timetable span:nth-child(3) {
    inset: 47% 12% 38% 68%;
    border-radius: 2px;
    background: var(--module-highlight);
}

@media (min-width: 761px) {
    .module-showcase-grid {
        grid-template-columns: minmax(12rem, 0.65fr) minmax(0, 1.35fr);
        grid-template-rows: repeat(var(--module-count), minmax(58px, max-content)) minmax(0, 1fr);
        column-gap: clamp(24px, 4vw, 56px);
    }

    .module-showcase-item {
        display: contents;
    }

    .module-showcase-choice {
        grid-column: 1;
    }

    .module-showcase-panel {
        min-height: 18.5rem;
        grid-area: 1 / 2 / -1;
    }
}

@media (max-width: 760px) {
    .module-showcase-chevron {
        transform: translateY(-20%) rotate(45deg);
    }

    [aria-expanded='true'] .module-showcase-chevron {
        transform: translateY(20%) rotate(225deg);
    }

    .module-showcase-panel {
        margin-block: 8px 12px;
    }
}

@media (max-width: 479px) {
    .module-showcase-panel {
        grid-template-columns: minmax(0, 1fr) minmax(0, 0.55fr);
    }
}

@media (max-width: 359px) {
    .module-showcase-panel {
        grid-template-columns: minmax(0, 1fr);
    }

    .module-showcase-art {
        width: min(100%, 8rem);
    }
}

@media (prefers-reduced-motion: no-preference) {
    .module-showcase-panel--selected .module-showcase-art {
        animation: module-appear 180ms ease-out both;
    }
}

@keyframes module-appear {
    from {
        opacity: 0.75;
        transform: translateX(7px);
    }
}
</style>
