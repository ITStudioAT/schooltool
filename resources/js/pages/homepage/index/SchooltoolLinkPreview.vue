<template>
    <section class="school-entry" aria-labelledby="school-entry-title">
        <SchoolEntryHeader show-documentation />

        <div class="school-entry-shell school-entry-content">
            <div class="school-entry-hero link-preview-hero">
                <h1 id="school-entry-title">Mehr Zeit für das <span>Wesentliche.</span></h1>
                <p>Wir machen Schulprozesse schneller, einfacher und übersichtlicher.</p>

                <RouterLink
                    :to="{ name: 'schooltool-introduction' }"
                    class="preview-link"
                    :class="`preview-link--${selectedVariant.id}`"
                    :aria-label="selectedVariant.id === 'einblick' ? 'Einblick – Schooltool kennenlernen' : undefined">
                    <template v-if="selectedVariant.id === 'pfeilkachel'">
                        <span class="preview-link-text">Schooltool kennenlernen</span>
                        <span class="preview-icon-tile" aria-hidden="true">
                            <v-icon icon="mdi-chevron-right" size="22" />
                        </span>
                    </template>

                    <template v-else-if="selectedVariant.id === 'schriftzug'">
                        <span class="preview-wordmark">Schooltool <strong>kennenlernen</strong></span>
                    </template>

                    <template v-else-if="selectedVariant.id === 'schnittkante'">
                        <span class="preview-link-text">Schooltool kennenlernen</span>
                        <span class="preview-edge" aria-hidden="true">
                            <v-icon icon="mdi-chevron-right" size="22" />
                        </span>
                    </template>

                    <template v-else>
                        <span class="preview-book" aria-hidden="true">
                            <v-icon icon="mdi-book-open-page-variant-outline" size="25" />
                        </span>
                        <span class="preview-link-text">Einblick</span>
                    </template>
                </RouterLink>
            </div>

            <fieldset class="preview-variants">
                <legend>Linkvarianten</legend>
                <div class="preview-options">
                    <button
                        v-for="variant in variants"
                        :key="variant.id"
                        type="button"
                        :aria-pressed="selectedVariant.id === variant.id"
                        @click="selectVariant(variant.id)">
                        {{ variant.label }}
                    </button>
                </div>
            </fieldset>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import SchoolEntryHeader from './SchoolEntryHeader.vue'

const variants = [
    { id: 'pfeilkachel', label: 'Pfeilkachel' },
    { id: 'schriftzug', label: 'Schriftzug' },
    { id: 'schnittkante', label: 'Schnittkante' },
    { id: 'einblick', label: 'Einblick' },
]

const route = useRoute()
const router = useRouter()
const selectedVariant = computed(() => variants.find(({ id }) => id === route.query.variante) ?? variants[0])

function selectVariant(id) {
    router.replace({ query: { variante: id } })
}
</script>

<style scoped>
.link-preview-hero {
    --preview-paper: #fff3de;
    --preview-paper-active: #ffdfac;
    padding-block: clamp(22px, 3.7vw, 44px);
}

.preview-link {
    position: relative;
    isolation: isolate;
    display: inline-flex;
    width: fit-content;
    max-width: 100%;
    min-height: 48px;
    align-items: center;
    gap: 12px;
    margin-top: 16px;
    color: var(--entry-ink);
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.15;
    text-decoration: none;
}

.preview-link:focus-visible,
.preview-options button:focus-visible {
    outline: 3px solid var(--entry-ink);
    outline-offset: 4px;
}

.preview-link--pfeilkachel .preview-link-text,
.preview-link--einblick .preview-link-text,
.preview-wordmark strong {
    text-decoration: underline;
    text-decoration-thickness: 1.5px;
    text-underline-offset: 0.26em;
}

.preview-icon-tile,
.preview-edge,
.preview-book {
    display: grid;
    flex: none;
    place-items: center;
}

.preview-icon-tile {
    width: 34px;
    height: 34px;
    background: var(--preview-paper);
    clip-path: polygon(0 0, calc(100% - 8px) 0, 100% 8px, 100% 100%, 0 100%);
}

.preview-wordmark {
    display: grid;
    gap: 1px;
    padding-left: 11px;
    border-left: 2px solid currentColor;
    font-size: 0.79rem;
    font-weight: 650;
}

.preview-wordmark strong {
    font-size: 1.3rem;
    font-weight: 800;
    letter-spacing: -0.035em;
}

.preview-link--schnittkante {
    gap: 10px;
    padding-left: 14px;
    padding-right: 10px;
}

.preview-link--schnittkante::before,
.preview-edge {
    clip-path: polygon(0 0, 100% 0, calc(100% - 12px) 100%, 0 100%);
}

.preview-link--schnittkante::before {
    position: absolute;
    z-index: -1;
    inset: 0;
    background: var(--preview-paper);
    content: '';
}

.preview-edge {
    align-self: stretch;
    min-width: 41px;
    padding-right: 8px;
    background: var(--entry-ink);
    color: var(--preview-paper);
}

.preview-book {
    width: 44px;
    height: 44px;
    border: 1.5px solid currentColor;
    border-radius: 50%;
}

.preview-link--pfeilkachel:is(:hover, :focus-visible) .preview-icon-tile,
.preview-link--schnittkante:is(:hover, :focus-visible)::before,
.preview-link--einblick:is(:hover, :focus-visible) .preview-book {
    background: var(--preview-paper-active);
}

.preview-link:is(:hover, :focus-visible) :is(.preview-link-text, .preview-wordmark strong) {
    text-decoration-thickness: 2px;
}

.preview-variants {
    min-width: 0;
    margin-block-start: 24px;
    padding: 0;
    border: 0;
}

.preview-variants legend {
    margin-bottom: 10px;
    font-size: 0.9375rem;
    font-weight: 700;
}

.preview-options {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.preview-options button {
    min-height: 44px;
    padding: 8px 14px;
    border: 1px solid var(--entry-muted);
    border-radius: 6px;
    background: #fff;
    color: var(--entry-ink);
    font-size: 0.875rem;
    font-weight: 650;
}

.preview-options button:hover,
.preview-options button[aria-pressed='true'] {
    background: #e7eff5;
}

.preview-options button[aria-pressed='true'] {
    border-color: var(--entry-ink);
    box-shadow: inset 0 -2px var(--entry-ink);
}

@media (max-width: 479px) {
    .preview-options {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (hover: hover) and (prefers-reduced-motion: no-preference) {
    .preview-icon-tile .v-icon,
    .preview-edge .v-icon {
        transition: transform 150ms ease-out;
    }

    .preview-link:is(:hover, :focus-visible) :is(.preview-icon-tile, .preview-edge) .v-icon {
        transform: translateX(3px);
    }
}
</style>
