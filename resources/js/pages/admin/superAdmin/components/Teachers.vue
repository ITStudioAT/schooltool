<template>
    <v-col cols="12" xl="11">
        <section class="crud-shell admin-card ai-glass-panel" :class="{ 'is-disabled': action != '' }">
            <div class="admin-card-head crud-head mb-4">
                <div>
                    <div class="admin-card-eyebrow">Verwaltung</div>
                    <h2 class="admin-card-title crud-title">Lehrer</h2>
                </div>

                <div class="admin-kpi-grid crud-kpis">
                    <div class="kpi-card ai-glass-panel">
                        <div class="kpi-label">Gesamt</div>
                        <div class="kpi-value">{{ totalTeachersCount }}</div>
                    </div>
                </div>
            </div>

            <div class="crud-content-grid">
                <section class="admin-card ai-glass-panel crud-main-card pa-3">
                    <div class="d-grid ga-3 mb-3">
                        <div class="empty-state crud-search-panel">
                            <SearchField :store="teacherStore" selected_field="selected_teachers" />
                        </div>

                        <div class="d-flex flex-wrap ga-2" :disabled="action != ''">
                            <v-btn color="primary" variant="tonal" rounded="lg" class="text-caption" @click="selectAll">
                                Alle auswählen [{{ Math.max(0, teachers.length - selected_teachers.length) }}]
                            </v-btn>
                            <v-btn color="primary" variant="text" rounded="lg" class="text-caption" @click="unselectAll">
                                Alle abwählen [{{ selected_teachers.length }}]
                            </v-btn>
                        </div>
                    </div>

                    <div class="empty-state pa-2" v-if="teachers.length === 0">Keine Lehrer gefunden.</div>
                    <div class="empty-state pa-2" v-else>
                        <v-list
                            dense
                            variant="flat"
                            class="crud-list"
                            select-strategy="leaf"
                            v-model:selected="selected_teachers"
                            color="success-lighten-2">
                            <v-list-item
                                v-for="item in teachers"
                                :key="item.id"
                                :value="item.id"
                                class="crud-list-item"
                                :class="{ 'is-selected': isSelectedTeacher(item.id) }">
                                <template #title>
                                    <div class="person-row crud-item-row">
                                        <div class="d-flex align-start" style="min-width: 0">
                                                <div class="person-body" style="min-width: 0">
                                                    <div class="person-name d-flex align-center ga-1">
                                                        <v-icon v-if="!item.is_active" color="error" size="14" icon="mdi-lock" />
                                                        <span>
                                                            {{ item.last_name }} {{ item.first_name }}<span v-if="item.short"> ({{ item.short }})</span>
                                                        </span>
                                                    </div>
                                                    <div class="person-roles">{{ item.email || '-' }}</div>
                                                </div>
                                            </div>
                                        </div>
                                </template>
                            </v-list-item>
                        </v-list>
                    </div>

                    <div class="empty-state crud-pagination mt-3 pa-3">
                        <Pagination :meta="meta" :store="teacherStore" selected_field="selected_teachers" />
                    </div>
                </section>

                <aside class="crud-side-stack">
                    <section class="admin-card ai-glass-panel">
                        <div class="admin-card-head mb-2">
                            <div>
                                <div class="admin-card-eyebrow">Aktionen</div>
                                <h3 class="admin-card-title">Lehrer verwalten</h3>
                            </div>
                        </div>
                        <div class="kpi-sub" style="margin-top: -2px">Verfügbare Schritte für die aktuelle Auswahl.</div>

                        <div class="crud-actions-primary">
                            <v-btn block color="primary" variant="flat" rounded="lg" prepend-icon="mdi-plus" @click="createTeacher">
                                Hinzufügen
                            </v-btn>
                        </div>

                        <template v-if="selected_teachers.length >= 1">
                            <v-divider class="crud-actions-divider" />
                            <div class="crud-actions-secondary">
                                <template v-if="selected_teachers.length == 1">
                                    <v-btn
                                        block
                                        color="primary"
                                        variant="tonal"
                                        rounded="lg"
                                        prepend-icon="mdi-pencil"
                                        @click="editTeacher(selected_teachers[0])">
                                        Ändern
                                    </v-btn>

                                    <v-btn
                                        v-if="selectedTeacher(selected_teachers[0])?.is_active"
                                        block
                                        color="error"
                                        variant="tonal"
                                        rounded="lg"
                                        class="crud-action-btn-offset"
                                        prepend-icon="mdi-lock"
                                        @click="toggleIsActive(selected_teachers[0])">
                                        Sperren
                                    </v-btn>
                                    <v-btn
                                        v-else
                                        block
                                        color="success"
                                        variant="tonal"
                                        rounded="lg"
                                        class="crud-action-btn-offset"
                                        prepend-icon="mdi-lock-open"
                                        @click="toggleIsActive(selected_teachers[0])">
                                        Entsperren
                                    </v-btn>
                                </template>

                                <v-btn
                                    block
                                    color="warning"
                                    variant="tonal"
                                    rounded="lg"
                                    class="crud-action-btn-offset"
                                    prepend-icon="mdi-delete"
                                    @click="deleteTeacher">
                                    Löschen
                                </v-btn>
                            </div>
                        </template>

                        <template v-if="!hideBackButton">
                            <v-divider class="crud-actions-divider" />
                            <div class="crud-actions-secondary">
                                <v-btn block color="secondary" variant="tonal" rounded="lg" prepend-icon="mdi-arrow-left" @click="abortReturn">
                                    Zur Übersicht
                                </v-btn>
                            </div>
                        </template>
                    </section>
                </aside>
            </div>
        </section>
    </v-col>

    <v-dialog v-model="teacherDialogOpen" persistent :max-width="teacherDialogMaxWidth" scrollable>
        <v-card class="crud-dialog-card ai-glass-panel">
            <div class="crud-dialog-head">
                <div>
                    <div class="admin-card-eyebrow" :class="{ 'crud-delete-eyebrow': action == 'delete_teacher' }">
                        {{ action == 'delete_teacher' ? 'Achtung' : 'Lehrer:in' }}
                    </div>
                    <div class="admin-card-title" style="margin-top: 4px">{{ teacherDialogTitle }}</div>
                </div>

                <v-btn icon="mdi-close" variant="text" rounded="lg" @click="abort" />
            </div>

            <v-card-text class="crud-dialog-body">
                <template v-if="action == 'create_teacher' || action == 'edit_teacher'">
                    <v-form ref="form" v-model="is_valid" @submit.prevent="saveTeacher(data)" class="mb-2 crud-form">
                        <div class="empty-state crud-form-section">
                            <v-row dense>
                                <v-col cols="12">
                                    <v-text-field
                                        autofocus
                                        v-model="data.short"
                                        label="Lehrer:in (Kurzzeichen)"
                                        :rules="[required(), maxLength(10)]"
                                        @input="data.short = data.short?.toUpperCase()" />
                                </v-col>
                                <v-col cols="12">
                                    <v-text-field v-model="data.last_name" label="Nachname" :rules="[required(), maxLength(255)]" />
                                </v-col>
                                <v-col cols="12">
                                    <v-text-field v-model="data.first_name" label="Vorname" :rules="[maxLength(255)]" />
                                </v-col>
                                <v-col cols="12">
                                    <v-text-field v-model="data.email" label="E-Mail" :rules="[required(), mail(), maxLength(255)]" />
                                </v-col>
                            </v-row>
                        </div>

                        <div class="crud-form-actions d-flex flex-row align-center justify-space-between mt-4">
                            <v-btn color="warning" variant="text" rounded="lg" @click="abort">Abbruch</v-btn>
                            <v-btn color="success" variant="flat" rounded="lg" type="submit">Speichern</v-btn>
                        </div>
                    </v-form>
                </template>

                <template v-else-if="action == 'delete_teacher'">
                    <v-form ref="form" v-model="is_valid" @submit.prevent="doDeleteTeachers(selected_teachers)" class="crud-form">
                        <div class="empty-state crud-delete-alert">
                            <div class="admin-card-eyebrow crud-delete-eyebrow">Achtung</div>
                            <div class="admin-card-title crud-delete-title">Lehrer:in löschen</div>
                            <div class="kpi-sub mt-2" v-if="selected_teachers.length == 1">
                                Es soll eine Lehrer:in gelöscht werden. Sind Sie sicher, dass Sie die markierte Lehrer:in löschen möchten?
                            </div>
                            <div class="kpi-sub mt-2" v-if="selected_teachers.length > 1">
                                Es sollen {{ selected_teachers.length }} Lehrer:innen gelöscht werden. Sind Sie sicher, dass Sie die markierten Lehrer:innen löschen möchten?
                            </div>
                        </div>

                        <div class="crud-form-actions d-flex flex-row align-center justify-space-between mt-4">
                            <v-btn color="success" variant="text" rounded="lg" @click="abort">Abbruch</v-btn>
                            <v-btn color="error" variant="flat" rounded="lg" type="submit" prepend-icon="mdi-delete">Löschen</v-btn>
                        </div>
                    </v-form>
                </template>
            </v-card-text>
        </v-card>
    </v-dialog>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import SearchField from '@/pages/components/SearchField.vue'
import Pagination from '@/pages/components/Pagination.vue'
import { useTeacherStore } from '@/stores/admin/TeacherStore'

export default {
    props: {
        hideBackButton: {
            type: Boolean,
            default: false,
        },
    },

    setup() {
        return useValidationRulesSetup()
    },

    components: { Pagination, SearchField },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.teacherStore = useTeacherStore()
        await this.teacherStore.index()
    },

    data() {
        return {
            adminStore: null,
            teacherStore: null,
            is_valid: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config', 'main_action']),
        ...mapWritableState(useTeacherStore, ['teachers', 'meta', 'selected_teachers', 'search_string', 'data', 'answer']),
        teacherDialogOpen: {
            get() {
                return ['create_teacher', 'edit_teacher', 'delete_teacher'].includes(this.action)
            },
            set(value) {
                if (!value) { this.action = '' }
            },
        },
        teacherDialogMaxWidth() {
            return this.action == 'delete_teacher' ? 640 : 640
        },
        teacherDialogTitle() {
            if (this.action == 'create_teacher') { return 'Neue:r Lehrer:in' }
            if (this.action == 'edit_teacher') { return 'Lehrer:in ändern' }
            if (this.action == 'delete_teacher') { return 'Löschen bestätigen' }
            return 'Lehrer:in'
        },
        totalTeachersCount() {
            const total = Number(this.meta?.total)
            return Number.isFinite(total) && total >= 0 ? total : this.teachers.length
        },
    },

    methods: {
        async abortReturn() {
            await this.teacherStore.index()
            this.main_action = 'teachers'
        },

        async saveTeacher(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) { return }

            if (data.id) {
                if (!(await this.teacherStore.update(data))) { return }
            } else {
                if (!(await this.teacherStore.store(data))) { return }
            }

            this.selected_teachers = []
            await this.teacherStore.index()
            this.data = {}
            this.action = ''
        },

        createTeacher() {
            this.data = { is_selectable: true }
            this.action = 'create_teacher'
        },

        deleteTeacher() {
            this.action = 'delete_teacher'
        },

        async doDeleteTeachers(data) {
            if (!(await this.teacherStore.deleteTeachers(data))) { return }
            this.selected_teachers = []
            await this.teacherStore.index()
            this.action = ''
        },

        editTeacher(teacher_id) {
            const teacher = this.teachers.find((s) => s.id === teacher_id)
            this.data = JSON.parse(JSON.stringify(teacher))
            this.action = 'edit_teacher'
        },

        abort() {
            this.action = ''
        },

        selectAll() {
            this.selected_teachers = this.teachers.map((item) => item.id)
        },

        unselectAll() {
            this.selected_teachers = []
        },

        isSelectedTeacher(id) {
            return this.selected_teachers.includes(id)
        },

        selectedTeacher(teacher_id) {
            return this.teachers.find((s) => s.id === teacher_id)
        },

        async toggleIsActive(user_id) {
            await this.teacherStore.toggleIsActive(user_id)
            await this.teacherStore.index(this.meta.current_page)
        },
    },
}
</script>

<style scoped src="../../../../../css/admin-index-page.css"></style>
<style scoped src="../../../../../css/admin-crud-panel.css"></style>
