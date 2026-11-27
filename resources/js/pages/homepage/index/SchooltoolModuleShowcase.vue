<template>
    <section v-if="modules.length" class="module-showcase" :aria-labelledby="`${sectionId}-title`">
        <h2 :id="`${sectionId}-title`">Schooltool im Einsatz</h2>

        <ul class="module-showcase-grid" role="list">
            <li v-for="module in modules" :key="module.id" class="module-showcase-card">
                <h3>{{ module.title }}</h3>
                <img class="module-showcase-image" :src="module.image" width="800" height="800" alt="" loading="lazy" decoding="async" />
                <p>{{ module.description }}</p>
            </li>
        </ul>
    </section>
</template>

<script setup>
import { useId } from 'vue'

defineProps({
    modules: { type: Array, required: true },
})

const sectionId = useId()
</script>

<style scoped>
.module-showcase {
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
    grid-template-columns: repeat(3, minmax(0, 1fr));
    grid-auto-rows: 1fr;
    gap: clamp(14px, 2vw, 28px);
    margin: 0;
    padding: 0;
    list-style: none;
}

.module-showcase-card {
    display: grid;
    min-width: 0;
    grid-template-rows: auto minmax(0, 1fr) auto;
    justify-items: center;
    align-items: start;
    gap: 10px;
    padding: clamp(22px, 2.8vw, 38px) clamp(14px, 1.9vw, 25px) clamp(22px, 2.65vw, 38px);
    border: 1px solid #ece5da;
    border-radius: 14px;
    background: #fffefd;
    text-align: center;
}

.module-showcase-card h3 {
    margin: 0;
    overflow-wrap: anywhere;
    font-size: clamp(1.45rem, 2.3vw, 2.05rem);
    font-weight: 750;
    letter-spacing: -0.035em;
    line-height: 1.12;
}

.module-showcase-card p {
    max-width: 31ch;
    min-height: 3em;
    margin: 0;
    color: #384857;
    font-size: 0.95rem;
    line-height: 1.5;
}

.module-showcase-image {
    display: block;
    width: min(100%, 21rem);
    height: auto;
    align-self: start;
    object-fit: contain;
}

@media (max-width: 860px) {
    .module-showcase-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 580px) {
    .module-showcase-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .module-showcase-image {
        width: min(100%, 19rem);
    }
}
</style>
