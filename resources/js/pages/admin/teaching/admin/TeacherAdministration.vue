<template>
    <slot
        name="navigation"
        :panels="panels"
        :selected-panel="selectedPanel"
        :activate-panel="activatePanel"
        :active-schoolyear-label="activeSchoolyearLabel"
        :has-missing-school-hours="hasMissingSchoolHours">
        <v-col cols="12" class="pb-1">
            <section class="teaching-administration-toolbar">
                <v-btn-toggle v-model="selectedPanel" mandatory divided color="primary" class="teaching-administration-panels" :disabled="navigationLocked">
                    <v-btn
                        v-for="panel in panels"
                        :key="panel.id"
                        :value="panel.id"
                        :prepend-icon="panel.icon"
                        class="teaching-administration-button">
                        <span class="teaching-administration-button-copy">
                            <span class="teaching-administration-button-title">{{ panel.label }}</span>
                            <span class="teaching-administration-button-meta">{{ activeSchoolyearLabel }}</span>
                        </span>
                        <span
                            v-if="panel.id === 'school_hours' && hasMissingSchoolHours"
                            class="teaching-administration-warning"
                            aria-label="Keine Schulstunden vorhanden">!</span>
                    </v-btn>
                </v-btn-toggle>
            </section>
        </v-col>
    </slot>

    <v-row class="w-100 ma-0" dense>
        <Teachers v-if="selectedPanel === 'teachers'" :hide-back-button="true" />
        <v-col v-else cols="12" md="6" lg="7" xl="4">
            <section class="teaching-administration-content">
                <v-row class="w-100 ma-0" dense>
                    <Import116 v-if="selectedPanel === 'import'" />
                    <Holidays v-if="selectedPanel === 'holidays'" />
                    <SchoolHours v-if="selectedPanel === 'school_hours'" />
                </v-row>
            </section>
        </v-col>
    </v-row>
</template>

<script setup>
import { computed, defineAsyncComponent, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useSchoolHourStore } from '@/stores/admin/teaching/SchoolHourStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { administration as teachingAdministration } from '@/routes/admin/teaching'

const props = defineProps({
    navigationLocked: { type: Boolean, default: false },
})

const Teachers = defineAsyncComponent(() => import('@/pages/admin/superAdmin/components/Teachers.vue'))
const Import116 = defineAsyncComponent(() => import('./import116/Import116.vue'))
const Holidays = defineAsyncComponent(() => import('./holidays/Holidays.vue'))
const SchoolHours = defineAsyncComponent(() => import('./schoolhours/SchoolHours.vue'))
const route = useRoute()
const router = useRouter()
const schoolHourStore = useSchoolHourStore()
const adminStore = useAdminStore()
const activeSchoolyearLabel = computed(() => adminStore.config?.selected_schoolyear?.name
    || adminStore.config?.selected_schoolyear?.concerns
    || 'Kein Schuljahr gewählt')
const panels = [
    { id: 'teachers', label: 'Lehrer', icon: 'mdi-account-tie' },
    { id: 'import', label: 'Import 116', icon: 'mdi-import' },
    { id: 'holidays', label: 'Ferien', icon: 'mdi-beach' },
    { id: 'school_hours', label: 'Schulstunden', icon: 'mdi-clock-time-four-outline' },
]
const hasMissingSchoolHours = computed(() => schoolHourStore.school_hours_loaded && schoolHourStore.school_hours.length === 0)
const selectedPanel = computed({
    get() {
        return panels.some((panel) => panel.id === route.query.panel) ? route.query.panel : 'teachers'
    },
    set(panel) {
        if (props.navigationLocked) {
            return
        }

        if (panels.some((item) => item.id === panel) && panel !== route.query.panel) {
            router.push({ path: teachingAdministration.url(), query: { ...route.query, panel } })
        }
    },
})

function activatePanel(panel) {
    selectedPanel.value = panel
}

watch(() => route.query.panel, () => {
    if (route.path === teachingAdministration.url() && route.query.panel !== selectedPanel.value) {
        router.replace({ path: route.path, query: { ...route.query, panel: selectedPanel.value } })
    }
}, { immediate: true })
</script>

<style scoped>
.teaching-administration-toolbar {
    width: 100%;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    gap: 8px;
    border-radius: 16px;
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.8);
    padding: 10px;
}

.teaching-administration-panels {
    width: 100%;
    flex-wrap: wrap;
    height: auto !important;
    row-gap: 6px;
}

.teaching-administration-button {
    text-transform: none;
    letter-spacing: 0;
    font-weight: 650;
    height: auto !important;
    min-height: 56px !important;
}

.teaching-administration-button-copy {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.15;
    gap: 4px;
}

.teaching-administration-button-meta {
    color: rgba(255, 255, 255, 0.98);
    font-size: 0.76rem;
    font-weight: 700;
    background: rgba(15, 23, 42, 0.35);
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 999px;
    padding: 2px 8px;
}

.teaching-administration-warning {
    display: inline-grid;
    place-items: center;
    width: 20px;
    height: 20px;
    margin-left: 6px;
    border-radius: 50%;
    background: rgb(var(--v-theme-error));
    color: rgb(var(--v-theme-on-error));
    font-weight: 850;
}

.teaching-administration-content {
    border-radius: 16px;
    border: 1px solid rgba(16, 38, 58, 0.08);
    background: rgba(255, 255, 255, 0.66);
    padding: 8px;
}
</style>
