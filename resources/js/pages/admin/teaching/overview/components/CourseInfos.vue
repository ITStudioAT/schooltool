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
                <v-card variant="outlined" class="mt-2">
                    <v-card-title class="text-subtitle-2 d-flex align-center ga-2 flex-wrap">
                        <v-icon size="18">mdi-bell-alert</v-icon>
                        Offene Verständigungen
                        <v-chip size="x-small" color="warning" variant="flat">{{ openNotifications.length }}</v-chip>
                    </v-card-title>
                    <v-divider />
                    <v-card-text class="pa-0">
                        <v-list density="compact">
                            <v-list-item v-for="entry in openNotifications" :key="entry.id">
                                <div class="notification-row d-flex flex-wrap align-start ga-2 w-100">
                                    <v-chip v-if="entry.date" size="x-small" variant="tonal" color="primary">{{ formatDate(entry.date) }}</v-chip>
                                    <v-chip v-if="entry.due_date" size="x-small" variant="tonal" :color="dueDateColor(entry.due_date)">Fällig bis {{ formatDate(entry.due_date) }}</v-chip>
                                    <v-chip v-if="entry.type" size="x-small" variant="outlined" color="secondary" class="chip-truncate">{{ notificationTypeLabel(entry.type) }}</v-chip>
                                    <v-chip size="x-small" variant="outlined" class="chip-truncate">{{ studentLabel(entry.user_id) }}</v-chip>
                                    <div class="notification-description text-caption w-100">{{ entry.description || '' }}</div>
                                    <v-btn
                                        icon="mdi-check"
                                        size="x-small"
                                        color="success"
                                        variant="tonal"
                                        class="notification-action"
                                        @click.stop="completeNotification(entry)" />
                                </div>
                            </v-list-item>
                            <v-list-item v-if="!openNotifications.length">
                                <v-list-item-title class="text-caption text-medium-emphasis">Keine offenen Verständigungen.</v-list-item-title>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
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
import { parseLocalDate } from '@/helpers/date'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useCourseBehaviourEntryStore } from '@/stores/admin/teaching/CourseBehaviourEntryStore'
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
        this.behaviourEntryStore = useCourseBehaviourEntryStore()
        this.teachingStore = useTeachingStore()
        if (this.selected_course?.id) {
            await this.behaviourEntryStore.indexByCourse(this.selected_course.id)
        }
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            courseStore: null,
            behaviourEntryStore: null,
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
        openNotifications() {
            const entries = this.behaviourEntryStore?.courseEntries || []
            return entries
                .filter((entry) => entry.kind === 'notification' && !!entry.due_date && !entry.done_date)
                .sort((a, b) => {
                    const dueA = a.due_date || '9999-12-31'
                    const dueB = b.due_date || '9999-12-31'
                    if (dueA !== dueB) return dueA.localeCompare(dueB)
                    const dateA = a.date || '9999-12-31'
                    const dateB = b.date || '9999-12-31'
                    return dateA.localeCompare(dateB)
                })
        },
        notificationTypesByShort() {
            const map = new Map()
            const list = this.teachingStore?.settings?.teaching_notifications || []
            list.forEach((item) => {
                if (item?.short_name) map.set(item.short_name, item.name || '')
            })
            return map
        },
    },

    watch: {
        selected_course: {
            async handler(course) {
                if (!course?.id) {
                    if (this.behaviourEntryStore) this.behaviourEntryStore.courseEntries = []
                    return
                }
                await this.behaviourEntryStore?.indexByCourse(course.id)
            },
        },
    },

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
        notificationTypeLabel(type) {
            if (!type) return ''
            const name = this.notificationTypesByShort.get(type)
            return name ? `${type} - ${name}` : type
        },
        studentLabel(userId) {
            const student = (this.selected_course?.students_info || []).find((s) => s.id === userId)
            if (!student) return 'Schüler:in'
            return `${student.last_name || ''}, ${student.first_name || ''}`.trim().replace(/^,\s*/, '')
        },
        formatDate(date) {
            if (!date) return ''
            const d = parseLocalDate(date)
            if (isNaN(d.getTime())) return ''
            return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },
        dueDateColor(date) {
            const due = parseLocalDate(date)
            if (isNaN(due.getTime())) return 'warning'
            due.setHours(0, 0, 0, 0)
            const today = new Date()
            today.setHours(0, 0, 0, 0)
            return due < today ? 'error' : 'warning'
        },
        toDateString(date) {
            const d = parseLocalDate(date)
            const year = d.getFullYear()
            const month = String(d.getMonth() + 1).padStart(2, '0')
            const day = String(d.getDate()).padStart(2, '0')
            return `${year}-${month}-${day}`
        },
        async completeNotification(entry) {
            if (!entry?.id || !entry?.type) return
            const payload = {
                id: entry.id,
                kind: 'notification',
                type: entry.type,
                date: entry.date || null,
                description: entry.description || null,
                is_due: !!entry.due_date,
                due_date: entry.due_date || null,
                is_done: true,
                done_date: this.toDateString(new Date()),
            }
            await this.behaviourEntryStore.update(payload)
        },
    },
}
</script>

<style scoped>
.course-description :deep(p) {
    margin: 0;
    min-height: 1.2em;
}

.notification-row {
    min-width: 0;
}

.chip-truncate {
    max-width: 100%;
}

.chip-truncate :deep(.v-chip__content) {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.notification-description {
    word-break: break-word;
}

.notification-action {
    margin-left: auto;
}
</style>
