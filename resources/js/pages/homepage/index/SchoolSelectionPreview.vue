<template>
    <section class="school-selection" aria-labelledby="school-selection-title">
        <div class="school-selection__art" aria-hidden="true">
            <div class="school-selection__wall-brand">
                <img src="/storage/images/schooltool/schooltool-mark.svg" alt="" />
                <span>schooltool<span class="school-selection__brand-dot">.</span></span>
            </div>
        </div>

        <div class="school-selection__content">
            <header class="school-selection__header">
                <a class="school-selection__brand" href="/" aria-label="Schooltool Startseite">
                    <img src="/storage/images/schooltool/schooltool-mark.svg" alt="" width="44" height="44" />
                    <span>schooltool<span class="school-selection__brand-dot">.</span></span>
                </a>
                <a class="school-selection__documentation" href="/documentation" target="_blank" rel="noopener noreferrer">Dokumentation</a>
            </header>

            <div class="school-selection__hero">
                <h1 id="school-selection-title"><span>Mehr Zeit für das</span><strong>Wesentliche.</strong></h1>
                <p>Wir machen Schulprozesse schneller,<br class="school-selection__desktop-break" /> einfacher und übersichtlicher.</p>
            </div>

            <div class="school-selection__schools" aria-labelledby="school-selection-schools">
                <h2 id="school-selection-schools">Wählen Sie Ihre Schule</h2>
                <label v-if="schools.length > 6" class="school-selection__search">
                    <span>Schule suchen</span>
                    <input v-model="search" type="search" placeholder="Name oder Kürzel Ihrer Schule" autocomplete="off" />
                </label>
                <div class="school-selection__grid">
                    <button
                        v-for="school in matchingSchools"
                        :key="school.id"
                        type="button"
                        class="school-selection__card"
                        :aria-label="`${school.long_name} auswählen`"
                        @click="$emit('select', school)">
                        <span class="school-selection__school-info">
                            <span class="school-selection__logo">
                                <img v-if="school.logo" :src="resolveSelectedSchoolLogoSrc(school.logo)" :alt="school.long_name" />
                                <v-icon v-else icon="mdi-school-outline" size="40" aria-hidden="true" />
                            </span>
                            <span class="school-selection__school-copy">
                                <span class="school-selection__school-name">{{ school.long_name }}</span>
                                <span class="school-selection__school-short">{{ school.short_name }}</span>
                            </span>
                        </span>
                        <span class="school-selection__action"><span>Auswählen</span><span aria-hidden="true">→</span></span>
                    </button>
                </div>
                <p v-if="!matchingSchools.length" class="school-selection__empty">{{ schools.length ? 'Keine Schule für diese Suche gefunden.' : 'Derzeit steht keine Schule zur Auswahl.' }}</p>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { resolveSelectedSchoolLogoSrc } from '@/helpers/adminSchoolLogo'

const props = defineProps({ schools: { type: Array, default: () => [] } })
defineEmits(['select'])

const search = ref('')
const matchingSchools = computed(() => {
    const query = search.value.trim().toLocaleLowerCase('de-AT')
    if (!query) return props.schools

    return props.schools.filter((school) => `${school.long_name} ${school.short_name || ''}`.toLocaleLowerCase('de-AT').includes(query))
})
</script>

<style scoped>
.school-selection {
    --selection-ink: #f5f8fa;
    --selection-muted: #a4b9c2;
    --selection-accent: #00ef87;
    position: relative;
    isolation: isolate;
    min-height: calc(100svh - 64px);
    overflow: hidden;
    color: var(--selection-ink);
    background: #000d18;
    font-family: 'Roboto', sans-serif;
}

.school-selection__art {
    position: absolute;
    z-index: -1;
    inset: 0 0 auto;
    aspect-ratio: 3 / 2;
    background: url('/images/backgrounds/schooltool-corridor-v4.jpg') center top / 100% auto no-repeat;
    pointer-events: none;
}

.school-selection__content {
    width: min(100%, 1536px);
    margin: 0 auto;
    padding: clamp(24px, 3vw, 44px) clamp(24px, 4.5vw, 72px) 44px;
}

.school-selection__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
}

.school-selection__brand,
.school-selection__wall-brand {
    display: flex;
    align-items: center;
    gap: 16px;
    color: #f5f8fa;
    font-family: Inter, -apple-system, system-ui, sans-serif;
    font-weight: 700;
    letter-spacing: -0.035em;
    line-height: 1;
    white-space: nowrap;
    text-decoration: none;
}

.school-selection__brand {
    font-size: clamp(26px, 3vw, 44px);
}

.school-selection__brand img {
    width: 0.95em;
    height: 0.95em;
}

.school-selection__brand-dot {
    color: #ff5a00;
}

.school-selection__wall-brand {
    position: absolute;
    top: 20%;
    left: 66%;
    font-size: clamp(28px, 4.7vw, 88px);
    gap: 0.22em;
    transform: perspective(800px) rotateY(-24deg) skewY(-10deg);
    transform-origin: right center;
    text-shadow: 0 5px 4px rgba(0, 40, 20, 0.35);
}

.school-selection__wall-brand img {
    width: 0.82em;
    height: 0.82em;
    box-shadow: 0 5px 5px rgba(0, 40, 20, 0.2);
}

.school-selection__documentation {
    color: var(--selection-ink);
    font-size: clamp(14px, 1.5vw, 21px);
    font-weight: 600;
    text-decoration: none;
}

.school-selection__documentation:hover {
    color: var(--selection-accent);
}

.school-selection__hero {
    margin: clamp(56px, 5.2vw, 80px) 0 clamp(52px, 5.4vw, 84px);
    max-width: 68%;
}

.school-selection__hero h1 {
    margin: 0;
    font-size: clamp(48px, 6.2vw, 100px);
    font-weight: 900;
    line-height: 1.03;
    letter-spacing: -0.055em;
}

.school-selection__hero h1 > span,
.school-selection__hero strong {
    display: block;
}

.school-selection__hero strong {
    color: var(--selection-accent);
    font-weight: 900;
}

.school-selection__hero p {
    margin: 20px 0 0;
    color: #e0e9ed;
    font-size: clamp(18px, 2vw, 29px);
    line-height: 1.28;
    letter-spacing: -0.02em;
}

.school-selection__schools h2 {
    margin: 0 0 28px;
    font-size: clamp(26px, 3.3vw, 48px);
    font-weight: 800;
    line-height: 1.15;
    letter-spacing: -0.035em;
}

.school-selection__grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
}

.school-selection__card {
    display: flex;
    flex-direction: column;
    min-width: 0;
    min-height: clamp(194px, 16.5vw, 250px);
    padding: clamp(20px, 1.8vw, 28px);
    border: 1px solid #7d9faa;
    border-radius: 18px;
    background: rgba(0, 27, 37, 0.72);
    backdrop-filter: blur(12px);
    color: var(--selection-ink);
    text-align: left;
    cursor: pointer;
    transition: border-color 160ms ease, background-color 160ms ease;
}

.school-selection__card:hover,
.school-selection__card:focus-visible {
    border-color: var(--selection-accent);
    background: rgba(0, 43, 43, 0.86);
    box-shadow: 0 0 0 1px var(--selection-accent), 0 0 22px rgba(0, 239, 135, 0.13);
}

.school-selection__school-info {
    display: flex;
    align-items: center;
    gap: 20px;
    flex: 1;
    padding-bottom: 24px;
}

.school-selection__logo {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 clamp(64px, 6.5vw, 100px);
    height: 88px;
}

.school-selection__logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.school-selection__school-copy {
    display: grid;
    min-width: 0;
    gap: 10px;
}

.school-selection__school-name {
    font-size: clamp(18px, 1.55vw, 24px);
    font-weight: 700;
    line-height: 1.2;
    overflow-wrap: anywhere;
}

.school-selection__school-short {
    color: var(--selection-muted);
    font-size: clamp(16px, 1.45vw, 22px);
}

.school-selection__action {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    border-top: 1px solid #668792;
    padding-top: 20px;
    font-size: clamp(18px, 1.8vw, 26px);
    font-weight: 700;
    line-height: 1.2;
}

.school-selection__action > :last-child {
    font-size: 1.3em;
    font-weight: 400;
}

.school-selection a:focus-visible,
.school-selection button:focus-visible,
.school-selection input:focus-visible {
    outline: 3px solid var(--selection-accent);
    outline-offset: 5px;
}

.school-selection__search {
    display: grid;
    gap: 8px;
    max-width: 480px;
    margin-bottom: 24px;
    font-weight: 600;
}

.school-selection__search input {
    width: 100%;
    padding: 14px 16px;
    border: 1px solid #7d9faa;
    border-radius: 10px;
    background: #052431;
    color: var(--selection-ink);
}

.school-selection__search input::placeholder {
    color: var(--selection-muted);
}

.school-selection__empty {
    padding: 24px 0;
    color: var(--selection-muted);
}

@media (min-width: 1537px) {
    .school-selection__art {
        max-width: 1800px;
        left: 50%;
        transform: translateX(-50%);
    }
}

@media (max-width: 1000px) {
    .school-selection__hero {
        max-width: 76%;
    }

    .school-selection__grid {
        gap: 14px;
    }

    .school-selection__school-info {
        align-items: flex-start;
        flex-direction: column;
        gap: 16px;
    }

    .school-selection__logo {
        flex: none;
        width: 84px;
        height: 64px;
    }
}

@media (min-width: 1001px) and (max-height: 800px) {
    .school-selection__content {
        padding-top: 24px;
        padding-bottom: 24px;
    }

    .school-selection__brand {
        font-size: 32px;
    }

    .school-selection__hero {
        margin: 36px 0;
    }

    .school-selection__hero h1 {
        font-size: clamp(54px, 5.4vw, 72px);
    }

    .school-selection__hero p {
        margin-top: 16px;
        font-size: 22px;
    }

    .school-selection__schools h2 {
        margin-bottom: 22px;
        font-size: 36px;
    }

    .school-selection__card {
        min-height: 178px;
        padding: 22px;
    }

    .school-selection__school-info {
        padding-bottom: 18px;
    }

    .school-selection__logo {
        height: 68px;
    }

    .school-selection__action {
        padding-top: 16px;
        font-size: 22px;
    }
}

@media (max-width: 680px) {
    .school-selection__art {
        width: 1080px;
        left: auto;
        right: -360px;
        opacity: 0.6;
        mask-image: linear-gradient(#000 25%, transparent 88%);
    }

    .school-selection__wall-brand {
        top: 24%;
        font-size: 46px;
    }

    .school-selection__header {
        align-items: flex-start;
        gap: 16px;
    }

    .school-selection__brand {
        gap: 10px;
        font-size: clamp(24px, 6vw, 32px);
    }

    .school-selection__documentation {
        padding-top: 5px;
        font-size: 13px;
    }

    .school-selection__hero {
        max-width: none;
        margin: 68px 0 64px;
        text-shadow: 0 2px 16px #000d18;
    }

    .school-selection__hero h1 {
        font-size: clamp(36px, 8.6vw, 58px);
    }

    .school-selection__hero p {
        max-width: 340px;
        font-size: 18px;
        line-height: 1.4;
    }

    .school-selection__desktop-break {
        display: none;
    }

    .school-selection__grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }

    .school-selection__card {
        min-height: 190px;
        padding: 24px;
    }

    .school-selection__school-info {
        flex-direction: row;
        align-items: center;
        gap: 20px;
    }

    .school-selection__school-name {
        font-size: 20px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .school-selection__card {
        transition: none;
    }
}

@media (max-width: 380px) {
    .school-selection__school-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
}
</style>
