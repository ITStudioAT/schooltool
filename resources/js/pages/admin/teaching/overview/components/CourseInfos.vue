<template>
    <ItsGridBox color="primary" :title="selected_course.title + ' (' + selectedCourseClasses + ')'" icon="mdi-information-box" class="w-100" v-if="selected_course">
        <v-card tile flat color="transparent" class="w-100">
            <v-card-text class="text-body-1 d-flex flex-column ga-2" v-if="action_2 == ''">
                <div class="text-body-2" style="white-space: pre-line">
                    {{ selected_course.description ? selected_course.description : 'Keine Fachinfos vorhanden.' }}
                </div>
                <div class="w-100 text-right">
                    <v-btn flat tile size="small" color="primary" icon="mdi-pencil" @click="editDescription" />
                </div>
            </v-card-text>
            <v-card-text v-if="action_2 == 'edit_description'">
                TODO: Beschreibung bearbeiten

                <div class="d-flex flex-row align-center justify-space-between mt-4">
                    <v-btn color="warning" flat tile @click="abortEditDescription">Abbruch</v-btn>
                    <v-btn color="success" flat tile type="submit">Speichern</v-btn>
                </div>
            </v-card-text>
            <v-card-text>
                {{ action }}
            </v-card-text>
        </v-card>
    </ItsGridBox>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsGridBox, ItsMenuButton },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.courseStore = useCourseStore()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            courseStore: null,
            is_valid: false,
            data: {
                selected_classes: [],
            },

            delete_level: 0,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2', 'config']),
        ...mapWritableState(useCourseStore, ['courses', 'classes', 'selected_course']),

        selectedCourseClasses() {
            if (!this.selected_course?.classes?.length) return ''
            // If it's already a string with commas
            if (typeof this.selected_course.classes === 'string') {
                return this.selected_course.classes.replace(/,/g, ', ')
            }
            return this.selected_course.classes.join(', ')
        },
    },

    watch: {},

    methods: {
        editDescription() {
            this.action_2 = 'edit_description'
        },
        abortEditDescription() {
            this.action_2 = ''
        },
    },
}
</script>
