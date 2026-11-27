<template>
    <section ref="preview" class="intro-preview" aria-labelledby="intro-preview-title" tabindex="-1">
        <div class="intro-preview-opening">
            <header class="intro-preview-header">
                <div class="intro-preview-shell intro-preview-nav">
                    <img
                        class="intro-preview-logo"
                        src="/storage/images/schooltool/schooltool-wordmark.svg"
                        alt="Schooltool"
                        width="420"
                        height="64" />
                    <RouterLink to="/" class="intro-preview-back">
                        <v-icon icon="mdi-arrow-left" size="18" aria-hidden="true" />
                        <span>Zur Startseite</span>
                    </RouterLink>
                </div>
            </header>

            <div class="intro-preview-shell intro-preview-hero">
                <h1 id="intro-preview-title">
                    Mehr Zeit fürs <span>Wesentliche</span>
                </h1>
            </div>
        </div>

        <SchooltoolModuleShowcase class="intro-preview-shell" :modules="modules" />
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import SchooltoolModuleShowcase from './SchooltoolModuleShowcase.vue'

const modules = [
    {
        id: 'anmeldesystem',
        title: 'Anmeldesystem',
        description: 'Anmeldungen für Schulveranstaltungen und Elternabende organisieren.',
        motif: 'registration',
    },
    {
        id: 'lehrertool',
        title: 'Lehrertool',
        description: 'Unterricht, Aufgaben und Beurteilungen verwalten.',
        motif: 'books',
    },
    {
        id: 'sepp',
        title: 'SEPP',
        description: 'Persönliche Stundenpläne zusammenstellen.',
        motif: 'timetable',
    },
]

const preview = ref(null)

onMounted(() => preview.value?.focus({ preventScroll: true }))
</script>

<style scoped>
.intro-preview {
    --intro-paper: #fffcf7;
    --intro-ink: #10263a;
    --intro-rule: #e6ded3;
    --intro-orange: #f7901e;
    min-height: calc(100svh - var(--v-layout-bottom, 52px));
    background: var(--intro-paper);
    color: var(--intro-ink);
}

.intro-preview-opening {
    display: grid;
    grid-template-rows: auto 1fr;
    min-height: min(43rem, calc(100svh - var(--v-layout-bottom, 52px)));
}

.intro-preview:focus {
    outline: none;
}

.intro-preview-shell {
    width: min(1280px, calc(100% - 2 * clamp(18px, 5vw, 80px)));
    margin-inline: auto;
}

.intro-preview-header {
    border-bottom: 1px solid var(--intro-rule);
}

.intro-preview-nav {
    display: flex;
    min-height: 76px;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.intro-preview-logo {
    display: block;
    width: clamp(153px, 15vw, 210px);
    height: auto;
}

.intro-preview-back {
    display: inline-flex;
    min-height: 44px;
    align-items: center;
    gap: 7px;
    padding-inline: 4px;
    border-radius: 4px;
    color: var(--intro-ink);
    font-size: 0.875rem;
    font-weight: 700;
    text-decoration: none;
    white-space: nowrap;
}

.intro-preview-back span {
    text-decoration: underline;
    text-underline-offset: 0.25em;
}

.intro-preview-back:focus-visible {
    outline: 3px solid var(--intro-ink);
    outline-offset: 3px;
}

.intro-preview-back:hover span {
    text-decoration-thickness: 2px;
}

.intro-preview-hero {
    display: grid;
    min-height: 17rem;
    align-content: center;
    padding-block: clamp(32px, 7vw, 74px);
}

.intro-preview-hero h1 {
    margin: 0;
    font-size: clamp(2.625rem, 5.6vw, 5.5rem);
    font-weight: 800;
    letter-spacing: -0.055em;
    line-height: 0.99;
}

.intro-preview-hero h1 span {
    position: relative;
    isolation: isolate;
    display: block;
    width: fit-content;
    margin-top: 0.025em;
}

.intro-preview-hero h1 span::after {
    position: absolute;
    z-index: -1;
    inset: auto -0.07em -0.065em 0.025em;
    height: 0.12em;
    background: var(--intro-orange);
    clip-path: polygon(0 35%, 95% 0, 100% 18%, 98% 73%, 0 100%);
    content: '';
    transform-origin: left;
}

@media (max-width: 760px) {
    .intro-preview-opening {
        min-height: min(31rem, calc(100svh - var(--v-layout-bottom, 52px)));
    }

    .intro-preview-nav {
        min-height: 62px;
    }

    .intro-preview-hero {
        padding-block-end: clamp(72px, 22vw, 120px);
    }

    .intro-preview-hero h1 {
        font-size: clamp(2.45rem, 8.7vw, 4.4rem);
    }
}

@media (prefers-reduced-motion: no-preference) {
    .intro-preview-hero h1 span::after {
        animation: intro-preview-draw 540ms cubic-bezier(0.2, 0.65, 0.35, 1) 120ms both;
    }
}

@keyframes intro-preview-draw {
    from {
        transform: scaleX(0);
    }

    to {
        transform: scaleX(1);
    }
}
</style>
