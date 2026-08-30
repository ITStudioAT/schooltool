<template>
    <v-col cols="12" xl="11">
        <section class="crud-shell admin-card ai-glass-panel">
            <div class="admin-card-head crud-head mb-4">
                <div>
                    <div class="admin-card-eyebrow">Schülerstundenpläne</div>
                    <h2 class="admin-card-title crud-title">Lehrerliste</h2>
                </div>

                <div class="admin-kpi-grid crud-kpis">
                    <div class="kpi-card ai-glass-panel">
                        <div class="kpi-label">Gesamt</div>
                        <div class="kpi-value">{{ totalTeachersCount }}</div>
                    </div>
                </div>
            </div>

            <div class="crud-content-grid teachers-read-only-grid">
                <section class="admin-card ai-glass-panel crud-main-card pa-3">
                    <div class="empty-state crud-search-panel mb-3">
                        <SearchField :store="teachersListStore" selected_field="selected_teachers" />
                    </div>

                    <div class="teacher-bulk-actions mb-3">
                        <v-btn
                            color="success"
                            variant="tonal"
                            prepend-icon="mdi-lock-open-outline"
                            :loading="isBulkActionPending(true)"
                            :disabled="isBulkActionRunning && !isBulkActionPending(true)"
                            size="small"
                            density="comfortable"
                            rounded="lg"
                            @click="setAllTeachersActive(true)">
                            Alle aktiv
                        </v-btn>
                        <v-btn
                            color="error"
                            variant="outlined"
                            prepend-icon="mdi-lock-outline"
                            :loading="isBulkActionPending(false)"
                            :disabled="isBulkActionRunning && !isBulkActionPending(false)"
                            size="small"
                            density="comfortable"
                            rounded="lg"
                            @click="setAllTeachersActive(false)">
                            Alle inaktiv
                        </v-btn>
                    </div>

                    <div v-if="teachers.length === 0" class="empty-state pa-2">Keine Lehrer gefunden.</div>
                    <div v-else class="empty-state pa-2">
                        <v-list density="compact" variant="flat" class="crud-list">
                            <v-list-item
                                v-for="teacher in teachers"
                                :key="teacher.id"
                                class="crud-list-item">
                                <template #title>
                                    <div class="person-row crud-item-row teacher-row">
                                        <div class="person-body" style="min-width: 0">
                                            <div class="person-name d-flex align-center ga-1">
                                                <span>
                                                    {{ teacher.last_name }} {{ teacher.first_name }}<span v-if="teacher.short"> ({{ teacher.short }})</span>
                                                </span>
                                            </div>
                                            <div class="person-roles">{{ teacher.email || '-' }}</div>
                                        </div>

                                        <div class="teacher-row-actions" @click.stop>
                                            <div class="teacher-action-line">
                                                <v-btn
                                                    color="primary"
                                                    variant="outlined"
                                                    prepend-icon="mdi-pencil-outline"
                                                    :loading="isTeacherActionPending(teacher, 'edit')"
                                                    size="small"
                                                    density="comfortable"
                                                    rounded="lg"
                                                    @click.stop="openEditDialog(teacher)">
                                                    Bearbeiten
                                                </v-btn>
                                                <v-btn
                                                    :color="teacher.is_active ? 'success' : 'error'"
                                                    :variant="teacher.is_active ? 'tonal' : 'outlined'"
                                                    :prepend-icon="teacher.is_active ? 'mdi-lock-open-outline' : 'mdi-lock-outline'"
                                                    :loading="isTeacherActionPending(teacher, 'status')"
                                                    :readonly="teacher.user_id === null"
                                                    :disabled="teacher.user_id !== null && !teacher.can_toggle_active"
                                                    size="small"
                                                    density="comfortable"
                                                    rounded="lg"
                                                    @click.stop="toggleTeacherActive(teacher)">
                                                    {{ teacher.is_active ? 'Aktiv' : 'Inaktiv' }}
                                                </v-btn>
                                            </div>

                                            <template v-if="teacher.is_active">
                                                <div class="teacher-action-line">
                                                    <v-btn
                                                        :color="isSelectedTimetableRole(teacher, 'studentstimetables_admin') ? 'primary' : undefined"
                                                        :variant="isSelectedTimetableRole(teacher, 'studentstimetables_admin') ? 'flat' : 'outlined'"
                                                        prepend-icon="mdi-shield-crown-outline"
                                                        :loading="isTeacherActionPending(teacher, 'studentstimetables_admin')"
                                                        :disabled="!teacher.can_manage_timetable_role"
                                                        size="small"
                                                        density="comfortable"
                                                        rounded="lg"
                                                        @click.stop="assignTimetableRole(teacher, 'studentstimetables_admin')">
                                                        TT-Admin
                                                    </v-btn>
                                                    <v-btn
                                                        :color="isSelectedTimetableRole(teacher, 'studentstimetables_moderator') ? 'primary' : undefined"
                                                        :variant="isSelectedTimetableRole(teacher, 'studentstimetables_moderator') ? 'flat' : 'outlined'"
                                                        prepend-icon="mdi-shield-account-outline"
                                                        :loading="isTeacherActionPending(teacher, 'studentstimetables_moderator')"
                                                        :disabled="!teacher.can_manage_timetable_role"
                                                        size="small"
                                                        density="comfortable"
                                                        rounded="lg"
                                                        @click.stop="assignTimetableRole(teacher, 'studentstimetables_moderator')">
                                                        TT-Moderator
                                                    </v-btn>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </v-list-item>
                        </v-list>
                    </div>

                    <div class="empty-state crud-pagination mt-3 pa-3">
                        <Pagination
                            :meta="meta"
                            :store="teachersListStore"
                            selected_field="selected_teachers" />
                    </div>
                </section>
            </div>
        </section>

        <v-dialog v-model="editDialog" max-width="640" persistent>
            <v-card rounded="xl">
                <v-card-title class="d-flex align-center ga-2 pa-5 pb-2">
                    <v-icon icon="mdi-account-edit-outline" color="primary" />
                    Lehrkraft bearbeiten
                </v-card-title>

                <v-card-text class="pa-5 pt-3">
                    <v-form @submit.prevent="saveTeacher">
                        <v-row>
                            <v-col cols="12" sm="4">
                                <v-text-field
                                    v-model="editForm.short"
                                    label="Kürzel"
                                    maxlength="10"
                                    :error-messages="action_errors.short"
                                    variant="outlined"
                                    density="comfortable" />
                            </v-col>
                            <v-col cols="12" sm="8">
                                <v-text-field
                                    v-model="editForm.last_name"
                                    label="Nachname"
                                    :error-messages="action_errors.last_name"
                                    variant="outlined"
                                    density="comfortable"
                                    required />
                            </v-col>
                            <v-col cols="12" sm="6">
                                <v-text-field
                                    v-model="editForm.first_name"
                                    label="Vorname"
                                    :error-messages="action_errors.first_name"
                                    variant="outlined"
                                    density="comfortable" />
                            </v-col>
                            <v-col cols="12" sm="6">
                                <v-text-field
                                    v-model="editForm.email"
                                    label="E-Mail"
                                    type="email"
                                    :error-messages="action_errors.email"
                                    variant="outlined"
                                    density="comfortable"
                                    required />
                            </v-col>
                        </v-row>

                        <div class="d-flex flex-wrap justify-end ga-2 mt-2">
                            <v-btn
                                variant="text"
                                :disabled="isEditPending"
                                @click="closeEditDialog">
                                Abbrechen
                            </v-btn>
                            <v-btn
                                color="primary"
                                variant="flat"
                                prepend-icon="mdi-content-save-outline"
                                type="submit"
                                :loading="isEditPending"
                                :disabled="!canSaveTeacher">
                                Speichern
                            </v-btn>
                        </div>
                    </v-form>
                </v-card-text>
            </v-card>
        </v-dialog>
    </v-col>
</template>

<script>
import { mapWritableState } from 'pinia'
import Pagination from '@/pages/components/Pagination.vue'
import SearchField from '@/pages/components/SearchField.vue'
import { useStudentsTimetablesTeachersListStore } from '@/stores/admin/studentsTimetables/TeachersListStore'

export default {
    components: { Pagination, SearchField },

    async beforeMount() {
        this.teachersListStore = useStudentsTimetablesTeachersListStore()
        await this.teachersListStore.index()
    },

    data() {
        return {
            teachersListStore: null,
            editDialog: false,
            editingTeacher: null,
            editForm: {
                short: '',
                last_name: '',
                first_name: '',
                email: '',
            },
        }
    },

    computed: {
        ...mapWritableState(useStudentsTimetablesTeachersListStore, ['teachers', 'meta', 'pending_action', 'action_errors']),
        totalTeachersCount() {
            const total = Number(this.meta?.total)

            return Number.isFinite(total) && total >= 0 ? total : this.teachers.length
        },
        isBulkActionRunning() {
            return this.pending_action?.startsWith('bulk:') ?? false
        },
        isEditPending() {
            return this.editingTeacher !== null && this.pending_action === `edit:${this.editingTeacher.id}`
        },
        canSaveTeacher() {
            return this.editForm.last_name.trim() !== '' && this.editForm.email.trim() !== ''
        },
    },

    methods: {
        hasRole(teacher, role) {
            return Array.isArray(teacher.roles) && teacher.roles.includes(role)
        },

        isSelectedTimetableRole(teacher, role) {
            return this.hasRole(teacher, role)
        },

        isTeacherActionPending(teacher, action) {
            let actionKey = `tt-role:${teacher.id}:${action}`

            if (action === 'status') {
                actionKey = `status:${teacher.id}`
            }

            if (action === 'edit') {
                actionKey = `edit:${teacher.id}`
            }

            return this.pending_action === actionKey
        },

        isBulkActionPending(isActive) {
            return this.pending_action === `bulk:${isActive ? 'active' : 'inactive'}`
        },

        async setAllTeachersActive(isActive) {
            await this.teachersListStore.setAllActive(isActive)
        },

        async toggleTeacherActive(teacher) {
            if (teacher.user_id !== null) {
                await this.teachersListStore.toggleActive(teacher.user_id, teacher.id)

                return
            }

            await this.teachersListStore.activateTeacher(teacher.teacher_id, teacher.id)
        },

        async assignTimetableRole(teacher, role) {
            const nextRole = this.isSelectedTimetableRole(teacher, role) ? null : role
            let userId = teacher.user_id

            if (userId === null) {
                const activatedTeacher = await this.teachersListStore.activateTeacher(teacher.teacher_id, teacher.id)

                if (!activatedTeacher?.user_id) { return }

                userId = activatedTeacher.user_id
            }

            await this.teachersListStore.setRole(userId, nextRole, teacher.id, role)
        },

        openEditDialog(teacher) {
            this.editingTeacher = teacher
            this.editForm = {
                short: teacher.short ?? '',
                last_name: teacher.last_name ?? '',
                first_name: teacher.first_name ?? '',
                email: teacher.email ?? '',
            }
            this.action_errors = {}
            this.editDialog = true
        },

        closeEditDialog() {
            this.editDialog = false
            this.editingTeacher = null
            this.action_errors = {}
        },

        async saveTeacher() {
            if (!this.editingTeacher || !this.canSaveTeacher) { return }

            const updatedTeacher = await this.teachersListStore.updateTeacher(this.editingTeacher, {
                short: this.editForm.short,
                last_name: this.editForm.last_name,
                first_name: this.editForm.first_name,
                email: this.editForm.email,
            })

            if (updatedTeacher) {
                this.closeEditDialog()
            }
        },
    },
}
</script>

<style scoped src="@/../css/admin-index-page.css"></style>
<style scoped src="@/../css/admin-crud-panel.css"></style>
<style scoped>
.teachers-read-only-grid {
    grid-template-columns: minmax(0, 1fr);
}

.teacher-row {
    align-items: flex-start;
    gap: 12px;
    justify-content: space-between;
}

.teacher-bulk-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
}

.teacher-row-actions {
    display: flex;
    flex: 0 0 auto;
    flex-direction: column;
    gap: 8px;
    align-items: flex-end;
}

.teacher-action-line {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
}

@media (max-width: 720px) {
    .teacher-row {
        flex-direction: column;
    }

    .teacher-row-actions {
        align-items: flex-start;
        width: 100%;
    }

    .teacher-action-line {
        justify-content: flex-start;
    }
}
</style>
