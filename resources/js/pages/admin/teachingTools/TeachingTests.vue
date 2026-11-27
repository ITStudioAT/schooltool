<template>
    <section :aria-labelledby="isGroundFloorPlan ? 'ground-floor-title' : 'teaching-tests-title'"
        class="teaching-tests teaching-tool-workspace py-4">
        <v-btn v-if="currentPage.back" :to="currentPage.back.to" prepend-icon="mdi-arrow-left"
            variant="text" color="primary" class="mb-2">
            {{ currentPage.back.label }}
        </v-btn>
        <h1 v-if="!isGroundFloorPlan" id="teaching-tests-title" class="text-h5 font-weight-bold mb-4">
            {{ currentPage.title }}
        </h1>

        <ul v-if="page === 'overview'" class="teaching-tool-card-grid">
            <li>
                <TeachingToolCard title="Netzwerke" heading-tag="h2" :to="networksPath">
                    <template #status>
                        <v-chip size="small" variant="tonal">In Vorbereitung</v-chip>
                    </template>
                </TeachingToolCard>
            </li>
        </ul>
        <ul v-else-if="page === 'networks'" class="teaching-tool-card-grid teaching-tests__sections">
            <li>
                <TeachingToolCard title="Netzwerk bauen" heading-tag="h2" :to="networkBuildPath" />
            </li>
            <li>
                <TeachingToolCard title="Fragen zu Geräten" heading-tag="h2" :to="networkDeviceQuestionsPath" />
            </li>
            <li>
                <TeachingToolCard title="Weitere Fragen" heading-tag="h2" :to="networkAdditionalQuestionsPath" />
            </li>
        </ul>
        <ul v-else-if="page === 'build-network'" class="teaching-tool-card-grid">
            <li v-for="planNumber in networkRoomPlanNumbers" :key="planNumber">
                <TeachingToolCard :title="`Raumplan ${planNumber}`" heading-tag="h2" :to="networkRoomPlanPath(planNumber)" />
            </li>
        </ul>
        <NetworkDeviceQuestions v-else-if="page === 'device-questions'" :key="schoolId" />
        <NetworkAdditionalQuestions v-else-if="page === 'additional-questions'" :key="schoolId" />
        <NetworkGroundFloorPlan v-else-if="isGroundFloorPlan" :key="roomPlanDraftKey" :draft-key="roomPlanDraftKey" />
    </section>
</template>

<script setup>
import { computed } from 'vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import NetworkAdditionalQuestions from './NetworkAdditionalQuestions.vue'
import NetworkDeviceQuestions from './NetworkDeviceQuestions.vue'
import NetworkGroundFloorPlan from './NetworkGroundFloorPlan.vue'
import TeachingToolCard from './TeachingToolCard.vue'
import {
    networkAdditionalQuestionsPath, networkBuildPath, networkDeviceQuestionsPath, networkRoomPlanNumbers, networkRoomPlanPath,
} from './networkRoomPlans'
import './teachingToolCards.css'

const props = defineProps({
    page: { type: String, default: 'overview' },
    roomPlanNumber: { type: Number, default: null },
})

const admin = useAdminStore()
const isGroundFloorPlan = computed(() => props.page === 'room-plan' && props.roomPlanNumber === 1)
const schoolId = computed(() => (admin.selected_school ?? admin.config?.selected_school)?.id ?? admin.config?.user?.school_id)
const roomPlanDraftKey = computed(() => {
    const user = admin.config?.user
    return user?.id ? `schooltool:network-room-plan:v1:${user.id}:${schoolId.value ?? 'none'}:${props.roomPlanNumber}` : null
})

const testsPath = '/admin/teaching-tools/tests'
const networksPath = `${testsPath}/networks`
const pages = {
    overview: { title: 'Tests' },
    networks: { title: 'Netzwerke', back: { to: testsPath, label: 'Zur Testübersicht' } },
    'build-network': { title: 'Netzwerk bauen', back: { to: networksPath, label: 'Zurück zu Netzwerke' } },
    'device-questions': { title: 'Fragen zu Geräten', back: { to: networksPath, label: 'Zurück zu Netzwerke' } },
    'additional-questions': { title: 'Weitere Fragen', back: { to: networksPath, label: 'Zurück zu Netzwerke' } },
}
const currentPage = computed(() => props.page === 'room-plan'
    ? { title: `Raumplan ${props.roomPlanNumber}`, back: { to: networkBuildPath, label: 'Zu den Raumplänen' } }
    : pages[props.page])
</script>

<style scoped>
.teaching-tests > h1 {
    margin-inline: 6px;
}

.teaching-tests__sections {
    grid-template-columns: minmax(0, 1fr);
    max-width: 960px;
}
</style>
