<template>
    <v-col
        cols="12"
        :md="isWideLayoutSelected ? 12 : 6"
        :lg="isWideLayoutSelected ? 12 : 7"
        :xl="isWideLayoutSelected ? 12 : 4"
        :class="{ 'teaching-more-col--full': isWideLayoutSelected }"
        data-testid="teaching-more-col">
        <v-card rounded="xl" class="teaching-more-card" data-testid="teaching-more-card">
            <v-card-item>
                <v-card-title>Mehr</v-card-title>
                <v-card-subtitle>Auswertungen</v-card-subtitle>
            </v-card-item>

            <v-card-text>
                <div v-if="semesterCount === 2" class="d-flex flex-wrap align-center ga-2 mb-3">
                    <v-btn-toggle
                        v-model="activeSemester"
                        mandatory
                        density="compact"
                        color="primary"
                        data-testid="teaching-more-semester-toggle">
                        <v-btn :value="1" size="small" data-testid="teaching-more-semester-1">Sem 1</v-btn>
                        <v-btn :value="2" size="small" data-testid="teaching-more-semester-2">Sem 2</v-btn>
                        <v-btn :value="3" size="small" data-testid="teaching-more-semester-3">Sem 1+2</v-btn>
                    </v-btn-toggle>
                </div>

                <v-btn-toggle
                    v-model="active_menu"
                    divided
                    color="primary"
                    class="teaching-more-menu mb-3"
                    data-testid="teaching-more-menu">
                    <v-btn
                        v-for="item in menu_items"
                        :key="item.id"
                        :value="item.id"
                        :data-testid="`teaching-more-menu-${item.id}`"
                        size="small"
                        variant="tonal">
                        {{ item.label }}
                    </v-btn>
                </v-btn-toggle>

                <AttendanceMatrix
                    v-if="active_menu === 'dummy_1'"
                    :selected-course="selected_course"
                    :active-semester="activeSemester"
                    :semester-count="semesterCount"
                    :sem2-start-date="sem2StartDate" />
                <PerformancesDummy
                    v-else-if="active_menu === 'dummy_2'"
                    :selected-course="selected_course"
                    :active-semester="activeSemester"
                    :semester-count="semesterCount"
                    :sem2-start-date="sem2StartDate" />
                <PerformancesPlusDummy
                    v-else-if="active_menu === 'dummy_3'"
                    :selected-course="selected_course"
                    :active-semester="activeSemester"
                    :semester-count="semesterCount"
                    :sem2-start-date="sem2StartDate" />

                <v-alert v-else type="info" variant="tonal" data-testid="teaching-more-content">
                    {{ activeMenuContent }}
                </v-alert>
            </v-card-text>
        </v-card>
    </v-col>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import AttendanceMatrix from './components/AttendanceMatrix.vue'
import PerformancesDummy from './components/PerformancesDummy.vue'
import PerformancesPlusDummy from './components/PerformancesPlusDummy.vue'

export default {
    components: { AttendanceMatrix, PerformancesDummy, PerformancesPlusDummy },

    async beforeMount() {
        this.teachingStore = useTeachingStore()
        if (!this.settings) {
            await this.teachingStore.loadSettings()
        }
        this.activeSemester = Number(this.config?.user?.teaching_active_semester) || 1
    },
    data() {
        return {
            teachingStore: null,
            active_menu: null,
            activeSemester: 1,
            menu_items: [
                { id: 'dummy_1', label: 'Anwesenheiten' },
            ],
        }
    },
    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        ...mapWritableState(useCourseStore, ['selected_course']),
        ...mapWritableState(useTeachingStore, ['settings']),
        teachingSchemas() {
            return this.config?.user?.teaching_schemas || this.settings?.teaching_schemas || []
        },
        selectedSchema() {
            const schemaId = this.selected_course?.teaching_schema_id
            if (!schemaId) {
                return null
            }
            return this.teachingSchemas.find((schema) => String(schema.id) === String(schemaId)) || null
        },
        semesterCount() {
            const grading = this.selectedSchema?.grading || {}
            return Number(grading.semester_count) || 1
        },
        sem2StartDate() {
            return this.config?.selected_schoolyear?.sem_2_start || this.config?.user?.teaching_count_for_semester_2_date || null
        },
        isWideLayoutSelected() {
            return this.active_menu === 'dummy_1' || this.active_menu === 'dummy_2' || this.active_menu === 'dummy_3'
        },
        activeMenuContent() {
            return 'Bitte einen Bereich auswählen.'
        },
    },
    watch: {
        activeSemester(val) {
            if (val !== this.config?.user?.teaching_active_semester) {
                this.teachingStore?.saveActiveSemester(val)
            }
        },
        'config.user.teaching_active_semester'(val) {
            if (val) {
                this.activeSemester = Number(val) || 1
            }
        },
    },
}
</script>

<style scoped>
.teaching-more-card {
    border: 1px dashed rgba(16, 38, 58, 0.2);
    background: rgba(255, 255, 255, 0.88);
    width: 100%;
}

.teaching-more-menu {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.teaching-more-col--full {
    max-width: 100%;
}
</style>
