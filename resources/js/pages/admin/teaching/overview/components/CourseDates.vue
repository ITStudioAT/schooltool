<template>
    <ItsGridBox color="primary" title="Termine" icon="mdi-calendar" class="w-100" v-if="selected_course" :disabled="action_2 != ''">
        <v-card tile flat color="transparent" class="w-100">
            <v-card-text class="text-body-1 d-flex flex-column ga-2">
                <!-- Anzeige ausgewählter Kurs -->
                <v-card tile flat color="transparent" class="d-flex flex-row align-center justify-space-between">
                    <div>
                        <div class="text-body-1 font-weight-medium">{{ selected_course.title }}</div>
                        <div class="d-flex flex-wrap ga-1 mt-1">
                            <v-chip v-for="cls in selected_course.classes" :key="cls" size="small" variant="tonal">
                                {{ cls }}
                            </v-chip>
                        </div>
                    </div>
                    <v-btn icon="mdi-plus" size="small" color="primary" variant="tonal" @click="newDate" />
                </v-card>
            </v-card-text>
        </v-card>
        <v-card>selected_course: {{ selected_course }}</v-card>
    </ItsGridBox>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useCourseDateStore } from '@/stores/admin/teaching/CourseDateStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.courseStore = useCourseStore()
        this.courseDateStore = useCourseDateStore()
    },

    unmounted() {
        this.courseDateStore.clearDates()
    },

    data() {
        return {
            adminStore: null,
            courseStore: null,
            courseDateStore: null,
            is_valid: false,
            data: {
                date: '',
                hours: '',
                content: '',
            },
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2']),
        ...mapWritableState(useCourseStore, ['selected_course']),
        ...mapWritableState(useCourseDateStore, ['courseDates', 'selected_courseDate']),
    },

    watch: {},

    methods: {},
}
</script>
