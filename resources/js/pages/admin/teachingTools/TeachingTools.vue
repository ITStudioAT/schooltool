<template>
    <v-container fluid class="teaching-page ma-0 w-100 pa-2">
        <AdminPageHeader class="mb-3" location="Unterricht Tools" />

        <v-sheet class="teaching-nav mb-2" aria-label="Unterricht Tools">
            <v-btn-toggle :model-value="section" mandatory divided color="primary" class="teaching-nav__buttons">
                <v-btn
                    value="multiple-choice"
                    to="/admin/teaching-tools/multiple-choice"
                    prepend-icon="mdi-format-list-checks"
                    :aria-pressed="section === 'multiple-choice'"
                    class="teaching-nav__button">
                    <span class="teaching-nav__button-copy">
                        <span class="teaching-nav__button-title">Multiple Choice</span>
                    </span>
                </v-btn>
                <v-btn
                    value="games"
                    to="/admin/teaching-tools/games"
                    prepend-icon="mdi-controller-classic-outline"
                    :aria-pressed="section === 'games'"
                    class="teaching-nav__button">
                    <span class="teaching-nav__button-copy">
                        <span class="teaching-nav__button-title">Spiele</span>
                    </span>
                </v-btn>
                <v-btn
                    value="explanations"
                    to="/admin/teaching-tools/explanations"
                    prepend-icon="mdi-lightbulb-outline"
                    :aria-pressed="section === 'explanations'"
                    class="teaching-nav__button">
                    <span class="teaching-nav__button-copy">
                        <span class="teaching-nav__button-title">Erklärungen</span>
                    </span>
                </v-btn>
                <v-btn v-if="simulatorAvailable" value="simulator" to="/admin/teaching-tools/simulator" prepend-icon="mdi-flask-outline"
                    :aria-pressed="section === 'simulator'" class="teaching-nav__button">Aufgaben-Simulator</v-btn>
            </v-btn-toggle>
        </v-sheet>
        <TeachingGames v-if="section === 'games'" :game="game" />
        <TeachingExplanations v-else-if="section === 'explanations'" :category="category" :explanation="explanation" />
        <template v-else-if="section === 'simulator'">
            <TeachingSimulation v-if="simulatorAvailable" />
            <v-alert v-else type="info" variant="tonal">Der Aufgaben-Simulator ist nur in der lokalen Testumgebung verfügbar.</v-alert>
        </template>
        <MultipleChoiceWorkspace v-else />
    </v-container>
</template>

<script setup>
import { computed } from 'vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import AdminPageHeader from '@/pages/admin/components/AdminPageHeader.vue'
import MultipleChoiceWorkspace from './MultipleChoiceWorkspace.vue'
import TeachingGames from './TeachingGames.vue'
import TeachingExplanations from './TeachingExplanations.vue'
import TeachingSimulation from './TeachingSimulation.vue'

const admin = useAdminStore()
const simulatorAvailable = computed(() => admin.config?.teaching_email_delivery?.mode === 'mailpit')

defineProps({
    section: { type: String, default: 'multiple-choice' },
    game: { type: String, default: null },
    category: { type: String, default: null },
    explanation: { type: String, default: null },
})
</script>

<style scoped>
.teaching-nav {
    border-radius: 16px;
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.8);
    padding: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.teaching-nav__buttons {
    width: 100%;
    flex-wrap: wrap;
    row-gap: 6px;
    height: auto !important;
    min-width: 0;
}

.teaching-nav__button {
    min-height: 56px !important;
    height: auto !important;
    text-transform: none;
    letter-spacing: 0;
    font-weight: 650;
}

.teaching-nav__button-copy {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.15;
    gap: 4px;
}

.teaching-nav__button-title {
    font-weight: 650;
}
</style>
