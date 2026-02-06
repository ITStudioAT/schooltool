<template>
    <ItsGridBox
        color="primary"
        :title="selected_course.title + ' (' + selectedCourseClasses + ')'"
        icon="mdi-information-box"
        class="w-100"
        v-if="selected_course"
        :disabled="action != '' && action != 'edit_description'">
        <v-card tile flat color="transparent" class="w-100">
            <v-card-text class="text-body-1 d-flex flex-column ga-2" v-if="action != 'edit_description'">
                <div class="text-caption text-medium-emphasis">{{ schemaName }}</div>
                <div class="text-body-2 course-description" v-if="selected_course.description" v-html="descriptionHtml"></div>
                <div class="text-body-2" v-else>Keine Fachinfos vorhanden.</div>
                <div class="w-100 text-right">
                    <v-btn flat tile size="small" color="primary" icon="mdi-pencil" @click="editDescription" />
                </div>
            </v-card-text>
            <v-card-text v-if="action == 'edit_description'">
                <v-form ref="form" @submit.prevent="saveDescription">
                    <div class="mb-4">
                        <label class="text-caption text-medium-emphasis">Fachinfos</label>
                        <its-rich-text-editor v-model="edit_description" />
                    </div>

                    <div class="d-flex flex-row align-center justify-space-between mt-4">
                        <v-btn color="warning" flat tile @click="abortEditDescription">Abbruch</v-btn>
                        <v-btn color="success" flat tile type="submit">Speichern</v-btn>
                    </div>
                </v-form>
            </v-card-text>
        </v-card>
    </ItsGridBox>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsRichTextEditor from '@/components/ItsRichTextEditor.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsGridBox, ItsMenuButton, ItsRichTextEditor },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.courseStore = useCourseStore()
        this.teachingStore = useTeachingStore()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            courseStore: null,
            teachingStore: null,
            is_valid: false,
            data: {
                selected_classes: [],
            },
            edit_description: '',
            delete_level: 0,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2', 'config']),
        ...mapWritableState(useCourseStore, ['courses', 'classes', 'selected_course']),

        schemaName() {
            const schemaId = this.selected_course?.teaching_schema_id
            if (!schemaId) return 'Kein Schema zugewiesen'
            const schema = this.teachingStore?.schemaById(schemaId)
            return schema ? `Schema: ${schema.name}` : 'Kein Schema zugewiesen'
        },
        selectedCourseClasses() {
            if (!this.selected_course?.classes?.length) return ''
            // If it's already a string with commas
            if (typeof this.selected_course.classes === 'string') {
                return this.selected_course.classes.replace(/,/g, ', ')
            }
            return this.selected_course.classes.join(', ')
        },

        descriptionHtml() {
            const text = this.selected_course?.description
            if (!text) return ''
            // If already HTML, return as-is
            if (text.includes('<p>') || text.includes('<br')) return text
            // Convert plain text line breaks to HTML paragraphs
            return text
                .split('\n')
                .map((line) => `<p>${line || '<br>'}</p>`)
                .join('')
        },
    },

    watch: {},

    methods: {
        editDescription() {
            this.edit_description = this.selected_course.description || ''
            this.action = 'edit_description'
        },
        abortEditDescription() {
            this.action = ''
            this.edit_description = ''
        },
        async saveDescription() {
            const data = {
                ...this.selected_course,
                description: this.edit_description,
            }
            if (await this.courseStore.update(data)) {
                this.selected_course.description = this.edit_description
                this.action = ''
                this.edit_description = ''
            }
        },
    },
}
</script>

<style scoped>
.course-description :deep(p) {
    margin: 0;
    min-height: 1.2em;
}
</style>
