<template>
    <div class="super-admin-page is-overview">
        <div class="super-admin-bg">
            <div class="super-admin-bg-image"></div>
            <div class="super-admin-bg-glow super-admin-bg-glow-left"></div>
            <div class="super-admin-bg-glow super-admin-bg-glow-right"></div>
        </div>

        <v-container fluid class="ma-0 w-100 pa-2 super-admin-page-inner">
            <header class="super-admin-header">
                <div class="super-admin-brand">
                    <div class="super-admin-brand-badge">
                        <v-icon size="20" color="white">mdi-account-group-outline</v-icon>
                    </div>
                    <div>
                        <div class="super-admin-brand-eyebrow">Verwaltung</div>
                        <h1 class="super-admin-brand-title">Gruppen</h1>
                        <p class="super-admin-brand-subtitle">
                            Schulgruppen, Materialiengruppen und Eigene Gruppen verwalten. Löschen ist nur möglich, wenn keine Mitglieder zugeordnet sind.
                        </p>
                    </div>
                </div>

                <div class="super-admin-header-meta">
                    <div class="super-admin-meta-pill" v-if="config?.selected_school?.long_name || config?.selected_school?.short_name">
                        <span>Schule</span>
                        <strong>{{ config?.selected_school?.long_name || config?.selected_school?.short_name }}</strong>
                    </div>
                    <div class="super-admin-meta-pill">
                        <span>Gruppen</span>
                        <strong>{{ groups.length }}</strong>
                    </div>
                </div>
            </header>

            <div class="super-admin-overview-shell super-admin-overview-shell--active">
                <section class="sa-card sa-card-school mb-3">
                    <div class="sa-card-head">
                        <div>
                            <div class="sa-card-eyebrow">Übersicht</div>
                            <h2 class="sa-card-title">Gruppentypen & Rechte</h2>
                        </div>
                        <v-btn flat color="primary" prepend-icon="mdi-refresh" :loading="isBusy" @click="loadGroups">
                            Aktualisieren
                        </v-btn>
                    </div>

                    <div class="sa-kpi-grid">
                        <div class="sa-kpi-card" v-for="section in groupSections" :key="`kpi-${section.type}`">
                            <div class="sa-kpi-label">{{ section.label }}</div>
                            <div class="sa-kpi-value">{{ groupsByType(section.type).length }}</div>
                            <div class="sa-kpi-sub">
                                {{ canManageType(section.type) ? 'bearbeitbar' : 'nur sichtbar' }}
                            </div>
                        </div>
                    </div>
                </section>

                <div class="groups-cards-shell">
                    <v-row class="w-100 ma-0 groups-cards-row" dense>
                    <v-col cols="12" md="6" xl="4" v-for="section in groupSections" :key="section.type">
                    <section class="sa-card h-100">
                        <div class="sa-card-head">
                            <div>
                                <div class="sa-card-eyebrow">{{ section.eyebrow }}</div>
                                <h2 class="sa-card-title">{{ section.label }}</h2>
                                <div class="sa-item-sub mt-1">{{ section.permissionText }}</div>
                            </div>
                            <v-chip
                                :color="canManageType(section.type) ? 'success' : 'warning'"
                                variant="flat"
                                size="small">
                                {{ canManageType(section.type) ? 'bearbeitbar' : 'keine Bearbeitung' }}
                            </v-chip>
                        </div>

                        <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-2">
                            <div class="d-flex flex-wrap ga-2">
                                <v-btn
                                    v-if="canManageType(section.type)"
                                    flat
                                    color="primary"
                                    prepend-icon="mdi-plus"
                                    @click="openCreateDialog(section.type)">
                                    Gruppe anlegen
                                </v-btn>
                                <v-btn
                                    v-if="canManageType(section.type) && selectedGroupByType(section.type)"
                                    flat
                                    color="secondary"
                                    prepend-icon="mdi-account-plus-outline"
                                    @click="openAssignUsersDialog(selectedGroupByType(section.type))">
                                    Benutzer zuordnen
                                </v-btn>
                            </div>
                        </div>

                        <div class="sa-empty" v-if="groupsByType(section.type).length === 0">
                            Keine Gruppen vorhanden.
                        </div>

                        <div class="groups-table-wrap" v-else>
                            <v-table density="compact" class="groups-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="group in groupsByType(section.type)"
                                        :key="group.id"
                                        class="groups-row"
                                        :class="{ 'is-selected': isSelectedGroup(section.type, group.id) }"
                                        @click="selectGroup(section.type, group)">
                                        <td>
                                            <div class="d-flex align-center justify-space-between flex-wrap ga-2">
                                                <div class="d-inline-flex align-center flex-wrap ga-2">
                                                    <div class="font-weight-bold text-body-2">{{ group.name }}</div>
                                                    <v-chip
                                                        :color="group.members_count > 0 ? 'warning' : 'secondary'"
                                                        variant="flat"
                                                        size="x-small">
                                                        {{ group.members_count }}
                                                    </v-chip>
                                                </div>
                                                <div class="d-inline-flex align-center ga-1">
                                                    <v-btn
                                                        v-if="canManageType(section.type)"
                                                        icon="mdi-pencil"
                                                        size="small"
                                                        variant="text"
                                                        color="primary"
                                                        :title="`${group.name} bearbeiten`"
                                                        @click.stop="openEditDialog(group)" />
                                                    <v-btn
                                                        v-if="canManageType(section.type)"
                                                        icon="mdi-delete"
                                                        size="small"
                                                        variant="text"
                                                        color="warning"
                                                        :disabled="group.members_count > 0"
                                                        :title="group.members_count > 0 ? 'Nur ohne Mitglieder löschbar' : `${group.name} löschen`"
                                                        @click.stop="openDeleteDialog(group)" />
                                                </div>
                                            </div>
                                            <div class="text-caption text-medium-emphasis groups-desc-cell">
                                                {{ group.description || 'Keine Beschreibung' }}
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </v-table>
                        </div>
                    </section>
                    </v-col>
                    </v-row>
                </div>
            </div>
        </v-container>

        <v-dialog v-model="editDialog.open" max-width="680" persistent>
            <v-card class="ai-glass-panel">
                <v-card-title class="d-flex justify-space-between align-center">
                    <div>
                        <div class="text-caption text-medium-emphasis">{{ currentTypeLabel(editDialog.form.type) }}</div>
                        <div>{{ editDialog.mode === 'create' ? 'Gruppe anlegen' : 'Gruppe bearbeiten' }}</div>
                    </div>
                    <v-btn icon="mdi-close" variant="text" @click="closeEditDialog" />
                </v-card-title>

                <v-card-text>
                    <v-form ref="groupForm" v-model="editDialog.valid" @submit.prevent="saveGroup">
                        <v-text-field
                            v-model="editDialog.form.name"
                            label="Name"
                            autofocus
                            :rules="[requiredRule]"
                            maxlength="255"
                            counter />
                        <v-textarea
                            v-model="editDialog.form.description"
                            label="Beschreibung"
                            rows="4"
                            auto-grow
                            maxlength="5000"
                            counter />
                    </v-form>
                </v-card-text>

                <v-card-actions class="d-flex justify-space-between">
                    <v-btn color="warning" variant="text" @click="closeEditDialog">Abbrechen</v-btn>
                    <v-btn color="success" variant="flat" :loading="isBusy" @click="saveGroup">
                        {{ editDialog.mode === 'create' ? 'Erstellen' : 'Speichern' }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="deleteDialog.open" max-width="560">
            <v-card class="ai-glass-panel">
                <v-card-title>Gruppe löschen</v-card-title>
                <v-card-text v-if="deleteDialog.group">
                    <div class="text-body-1">
                        Soll die Gruppe <strong>{{ deleteDialog.group.name }}</strong> wirklich gelöscht werden?
                    </div>
                    <div class="text-caption text-medium-emphasis mt-2">
                        Gruppentyp: {{ currentTypeLabel(deleteDialog.group.type) }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                        Mitglieder: {{ deleteDialog.group.members_count }}
                    </div>
                    <v-alert
                        v-if="deleteDialog.group.members_count > 0"
                        type="warning"
                        variant="tonal"
                        class="mt-3">
                        Löschen ist nur möglich, wenn die Gruppe keine Mitglieder enthält.
                    </v-alert>
                </v-card-text>
                <v-card-actions class="d-flex justify-space-between">
                    <v-btn color="secondary" variant="text" @click="closeDeleteDialog">Abbrechen</v-btn>
                    <v-btn
                        color="error"
                        variant="flat"
                        :disabled="!deleteDialog.group || deleteDialog.group.members_count > 0"
                        :loading="isBusy"
                        @click="deleteGroup">
                        Löschen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="assignUsersDialog.open" max-width="760" persistent>
            <v-card class="ai-glass-panel">
                <v-card-title class="d-flex justify-space-between align-center">
                    <div>
                        <div class="text-caption text-medium-emphasis">
                            {{ currentTypeLabel(assignUsersDialog.group?.type) }}
                        </div>
                        <div>Benutzer zuordnen</div>
                    </div>
                    <v-btn icon="mdi-close" variant="text" @click="closeAssignUsersDialog" />
                </v-card-title>
                <v-card-text>
                    <div v-if="assignUsersDialog.group" class="text-body-2 mb-2">
                        Gruppe: <strong>{{ assignUsersDialog.group.name }}</strong>
                    </div>
                    <div class="d-grid ga-3">
                        <section class="groups-assign-section">
                            <div class="d-flex justify-space-between align-center flex-wrap ga-2">
                                <div>
                                    <div class="admin-card-eyebrow">Zugeordnete Benutzer</div>
                                    <div class="text-caption text-medium-emphasis">
                                        {{ assignUsersDialog.members.length }} Benutzer in dieser Gruppe
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap ga-2">
                                    <v-btn
                                        flat
                                        color="secondary"
                                        prepend-icon="mdi-refresh"
                                        :loading="assignUsersDialog.membersLoading"
                                        @click="loadAssignedMembers">
                                        Aktualisieren
                                    </v-btn>
                                    <v-btn
                                        flat
                                        :color="assignUsersDialog.membersExpanded ? 'secondary' : 'primary'"
                                        :prepend-icon="assignUsersDialog.membersExpanded ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                                        @click="assignUsersDialog.membersExpanded = !assignUsersDialog.membersExpanded">
                                        {{ assignUsersDialog.membersExpanded ? 'Einklappen' : 'Aufklappen' }}
                                    </v-btn>
                                </div>
                            </div>

                            <div v-if="assignUsersDialog.membersExpanded" class="mt-3">
                                <div class="sa-empty" v-if="assignUsersDialog.membersLoading">
                                    Lade zugeordnete Benutzer ...
                                </div>
                                <div class="sa-empty" v-else-if="assignUsersDialog.members.length === 0">
                                    Keine Benutzer zugeordnet.
                                </div>
                                <div class="groups-assign-list" v-else>
                                    <div class="groups-assign-list-item" v-for="member in assignUsersDialog.members" :key="`group-member-${member.id}`">
                                        <div class="min-w-0">
                                            <div class="font-weight-bold text-body-2">{{ member.name }}</div>
                                            <div class="text-caption text-medium-emphasis">{{ member.email }}</div>
                                            <div class="text-caption text-medium-emphasis" v-if="member.schoolclass">Klasse: {{ member.schoolclass }}</div>
                                        </div>
                                        <v-btn
                                            flat
                                            color="warning"
                                            size="small"
                                            prepend-icon="mdi-account-remove"
                                            :loading="isBusy && Number(assignUsersDialog.removingUserId) === Number(member.id)"
                                            @click="removeAssignedMember(member)">
                                            Entfernen
                                        </v-btn>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="groups-assign-section">
                            <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-2">
                                <div>
                                    <div class="admin-card-eyebrow">1. Benutzer suchen</div>
                                    <div class="text-caption text-medium-emphasis">Benutzer der aktuellen Schule suchen und direkt zuordnen.</div>
                                </div>
                                <v-btn
                                    flat
                                    color="primary"
                                    prepend-icon="mdi-refresh"
                                    :loading="assignUsersDialog.userSearchLoading"
                                    @click="searchAssignableUsers">
                                    Aktualisieren
                                </v-btn>
                            </div>

                            <div class="d-flex flex-wrap ga-2 align-start mb-2">
                                <v-text-field
                                    v-model="assignUsersDialog.userSearchString"
                                    label="Benutzer suchen"
                                    hide-details
                                    density="comfortable"
                                    class="flex-grow-1"
                                    @keyup.enter="searchAssignableUsers" />
                                <v-btn
                                    flat
                                    color="primary"
                                    prepend-icon="mdi-magnify"
                                    :loading="assignUsersDialog.userSearchLoading"
                                    @click="searchAssignableUsers">
                                    Suchen
                                </v-btn>
                            </div>

                            <div class="sa-empty" v-if="!assignUsersDialog.userSearchHasRun">
                                Bitte zuerst einen Benutzer suchen.
                            </div>
                            <div class="sa-empty" v-else-if="assignUsersDialog.userSearchResults.length === 0">
                                Keine Benutzer gefunden.
                            </div>
                            <div class="groups-assign-list" v-else>
                                <div class="groups-assign-list-item" v-for="user in assignUsersDialog.userSearchResults" :key="`assign-user-${user.id}`">
                                    <div class="min-w-0">
                                        <div class="font-weight-bold text-body-2">{{ user.name }}</div>
                                        <div class="text-caption text-medium-emphasis">{{ user.email }}</div>
                                        <div class="text-caption text-medium-emphasis" v-if="user.schoolclass">Klasse: {{ user.schoolclass }}</div>
                                    </div>
                                    <div class="d-flex flex-wrap ga-1 justify-end">
                                        <v-chip
                                            v-if="user.already_member"
                                            color="secondary"
                                            variant="flat"
                                            size="x-small">
                                            schon in Gruppe
                                        </v-chip>
                                        <v-btn
                                            flat
                                            color="success"
                                            size="small"
                                            prepend-icon="mdi-account-plus"
                                            :loading="isBusy"
                                            @click="assignUsersToCurrentGroup([user.id])">
                                            Zuordnen
                                        </v-btn>
                                        <v-btn
                                            @click="assignUsersToCurrentGroup([user.id])">
                                            Zuordnen
                                        </v-btn>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="groups-assign-section">
                            <div class="admin-card-eyebrow">2. Aus anderer Gruppe übernehmen</div>
                            <div class="text-caption text-medium-emphasis mb-2">Mitglieder einer anderen Gruppe in diese Gruppe übernehmen.</div>
                            <div class="d-flex flex-wrap ga-2 align-start">
                                <v-select
                                    v-model="assignUsersDialog.sourceGroupId"
                                    :items="assignUsersDialog.sourceGroups"
                                    item-title="display_name"
                                    item-value="id"
                                    label="Quellgruppe"
                                    density="comfortable"
                                    hide-details
                                    class="flex-grow-1"
                                    :loading="assignUsersDialog.sourceGroupsLoading" />
                                <v-btn
                                    flat
                                    color="primary"
                                    prepend-icon="mdi-account-multiple-plus"
                                    :disabled="!assignUsersDialog.sourceGroupId"
                                    :loading="isBusy"
                                    @click="assignFromOtherGroup">
                                    Übernehmen
                                </v-btn>
                            </div>
                        </section>
                    </div>
                </v-card-text>
                <v-card-actions class="d-flex justify-space-between">
                    <v-btn color="warning" variant="text" @click="closeAssignUsersDialog">Schließen</v-btn>
                    <v-btn color="success" variant="flat" disabled>Speichern</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import axios from 'axios'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export default {
    async beforeMount() {
        this.adminStore = useAdminStore()
        this.notificationStore = useNotificationStore()
        await this.loadGroups()
    },

    data() {
        return {
            adminStore: null,
            notificationStore: null,
            isBusy: false,
            groups: [],
            apiPermissions: {},
            groupSections: [
                {
                    type: 'school',
                    eyebrow: 'System',
                    label: 'Schulgruppen',
                    permissionText: 'Bearbeitung nur durch super_admin und admin.',
                },
                {
                    type: 'materials',
                    eyebrow: 'Materialientool',
                    label: 'Materialiengruppen',
                    permissionText: 'Bearbeitung durch super_admin, admin und materials_admin.',
                },
                {
                    type: 'own',
                    eyebrow: 'Persönlich',
                    label: 'Eigene Gruppen',
                    permissionText: 'Bearbeitung durch super_admin, admin, materials_admin und materials_moderator.',
                },
            ],
            selectedGroupIdsByType: {
                school: null,
                materials: null,
                own: null,
            },
            editDialog: {
                open: false,
                mode: 'create',
                valid: false,
                form: {
                    id: null,
                    type: 'own',
                    name: '',
                    description: '',
                },
            },
            deleteDialog: {
                open: false,
                group: null,
            },
            assignUsersDialog: {
                open: false,
                group: null,
                membersExpanded: true,
                members: [],
                membersLoading: false,
                removingUserId: null,
                userSearchString: '',
                userSearchResults: [],
                userSearchHasRun: false,
                userSearchLoading: false,
                sourceGroups: [],
                sourceGroupsLoading: false,
                sourceGroupId: null,
            },
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'is_loading']),
        userRoles() {
            return Array.isArray(this.config?.roles) ? this.config.roles : []
        },
        requiredRule() {
            return (v) => (!!String(v || '').trim() ? true : 'Pflichtfeld')
        },
    },

    methods: {
        hasAnyRole(roles) {
            return roles.some((role) => this.userRoles.includes(role))
        },

        fallbackCanManageType(type) {
            if (this.hasAnyRole(['super_admin', 'admin'])) return true
            if (type === 'materials' && this.userRoles.includes('materials_admin')) return true
            if (type === 'own' && this.hasAnyRole(['materials_admin', 'materials_moderator'])) return true
            return false
        },

        canManageType(type) {
            if (Object.prototype.hasOwnProperty.call(this.apiPermissions || {}, type)) {
                return !!this.apiPermissions[type]
            }
            return this.fallbackCanManageType(type)
        },

        groupsByType(type) {
            return this.groups.filter((group) => group.type === type)
        },

        selectedGroupByType(type) {
            const selectedId = this.selectedGroupIdsByType?.[type] ?? null
            if (!selectedId) return null
            return this.groups.find((group) => group.type === type && Number(group.id) === Number(selectedId)) || null
        },

        currentTypeLabel(type) {
            return this.groupSections.find((section) => section.type === type)?.label || type
        },

        notifyError(error, fallbackMessage = 'Fehler passiert.') {
            this.notificationStore?.notify({
                status: error?.response?.status || 500,
                message: error?.response?.data?.message || fallbackMessage,
                type: 'error',
                timeout: this.config?.timeout,
            })
        },

        notifySuccess(message) {
            this.notificationStore?.notify({
                message,
                type: 'success',
                timeout: 2500,
            })
        },

        async loadGroups() {
            this.isBusy = true
            this.is_loading++
            try {
                const response = await axios.get('/api/admin/groups')
                this.groups = Array.isArray(response.data?.data) ? response.data.data : []
                this.apiPermissions = response.data?.meta?.permissions || {}
                this.cleanupSelectedGroups()
                this.refreshAssignDialogGroupReference()
            } catch (error) {
                this.groups = []
                this.apiPermissions = {}
                this.selectedGroupIdsByType = { school: null, materials: null, own: null }
                this.refreshAssignDialogGroupReference()
                this.notifyError(error, 'Gruppen konnten nicht geladen werden.')
            } finally {
                this.is_loading--
                this.isBusy = false
            }
        },

        openCreateDialog(type) {
            if (!this.canManageType(type)) return
            this.editDialog.mode = 'create'
            this.editDialog.form = { id: null, type, name: '', description: '' }
            this.editDialog.valid = false
            this.editDialog.open = true
        },

        openEditDialog(group) {
            if (!this.canManageType(group.type)) return
            this.editDialog.mode = 'edit'
            this.editDialog.form = {
                id: group.id,
                type: group.type,
                name: group.name || '',
                description: group.description || '',
            }
            this.editDialog.valid = false
            this.editDialog.open = true
        },

        closeEditDialog() {
            this.editDialog.open = false
        },

        async saveGroup() {
            if (this.isBusy) return

            const formEl = this.$refs.groupForm
            if (formEl?.validate) {
                const result = await formEl.validate()
                const valid = typeof result === 'object' ? result.valid : this.editDialog.valid
                if (!valid) return
            }

            const payload = {
                type: this.editDialog.form.type,
                name: String(this.editDialog.form.name || '').trim(),
                description: String(this.editDialog.form.description || '').trim() || null,
            }

            this.isBusy = true
            this.is_loading++
            try {
                if (this.editDialog.mode === 'create') {
                    await axios.post('/api/admin/groups', payload)
                    this.notifySuccess('Gruppe erstellt.')
                } else {
                    await axios.put(`/api/admin/groups/${this.editDialog.form.id}`, {
                        name: payload.name,
                        description: payload.description,
                    })
                    this.notifySuccess('Gruppe gespeichert.')
                }

                this.closeEditDialog()
                await this.loadGroups()
            } catch (error) {
                this.notifyError(error, 'Gruppe konnte nicht gespeichert werden.')
            } finally {
                this.is_loading--
                this.isBusy = false
            }
        },

        openDeleteDialog(group) {
            this.deleteDialog.group = group
            this.deleteDialog.open = true
        },

        closeDeleteDialog() {
            this.deleteDialog.open = false
            this.deleteDialog.group = null
        },

        async deleteGroup() {
            const group = this.deleteDialog.group
            if (!group || this.isBusy || Number(group.members_count || 0) > 0) return

            this.isBusy = true
            this.is_loading++
            try {
                await axios.delete(`/api/admin/groups/${group.id}`)
                this.notifySuccess('Gruppe gelöscht.')
                this.closeDeleteDialog()
                await this.loadGroups()
            } catch (error) {
                this.notifyError(error, 'Gruppe konnte nicht gelöscht werden.')
            } finally {
                this.is_loading--
                this.isBusy = false
            }
        },
        selectGroup(type, group) {
            if (!group || group.type !== type) return
            const currentId = this.selectedGroupIdsByType[type]
            this.selectedGroupIdsByType[type] = Number(currentId) === Number(group.id) ? null : group.id
        },
        isSelectedGroup(type, groupId) {
            return Number(this.selectedGroupIdsByType?.[type] || 0) === Number(groupId)
        },
        cleanupSelectedGroups() {
            const next = { ...this.selectedGroupIdsByType }
            for (const section of this.groupSections) {
                const type = section.type
                const selectedId = next[type]
                if (!selectedId) continue
                const exists = this.groups.some((group) => group.type === type && Number(group.id) === Number(selectedId))
                if (!exists) next[type] = null
            }
            this.selectedGroupIdsByType = next
        },
        openAssignUsersDialog(group) {
            if (!group) return
            this.assignUsersDialog.group = group
            this.assignUsersDialog.membersExpanded = true
            this.assignUsersDialog.members = []
            this.assignUsersDialog.membersLoading = false
            this.assignUsersDialog.removingUserId = null
            this.assignUsersDialog.userSearchString = ''
            this.assignUsersDialog.userSearchResults = []
            this.assignUsersDialog.userSearchHasRun = false
            this.assignUsersDialog.sourceGroups = []
            this.assignUsersDialog.sourceGroupId = null
            this.assignUsersDialog.open = true
            this.loadAssignedMembers()
            this.loadAssignableGroups()
        },
        closeAssignUsersDialog() {
            this.assignUsersDialog.open = false
            this.assignUsersDialog.group = null
            this.assignUsersDialog.membersExpanded = true
            this.assignUsersDialog.members = []
            this.assignUsersDialog.membersLoading = false
            this.assignUsersDialog.removingUserId = null
            this.assignUsersDialog.userSearchString = ''
            this.assignUsersDialog.userSearchResults = []
            this.assignUsersDialog.userSearchHasRun = false
            this.assignUsersDialog.sourceGroups = []
            this.assignUsersDialog.sourceGroupId = null
        },
        refreshAssignDialogGroupReference() {
            if (!this.assignUsersDialog?.group?.id) return
            const nextGroup = this.groups.find((row) => Number(row.id) === Number(this.assignUsersDialog.group.id)) || null
            this.assignUsersDialog.group = nextGroup
            if (!nextGroup) {
                this.closeAssignUsersDialog()
            }
        },
        async loadAssignedMembers() {
            const group = this.assignUsersDialog.group
            if (!group?.id) return
            this.assignUsersDialog.membersLoading = true
            try {
                const response = await axios.get(`/api/admin/groups/${group.id}/members`)
                this.assignUsersDialog.members = Array.isArray(response.data?.data) ? response.data.data : []
            } catch (error) {
                this.assignUsersDialog.members = []
                this.notifyError(error, 'Zugeordnete Benutzer konnten nicht geladen werden.')
            } finally {
                this.assignUsersDialog.membersLoading = false
            }
        },
        async removeAssignedMember(member) {
            const group = this.assignUsersDialog.group
            if (!group?.id || !member?.id || this.isBusy) return

            this.isBusy = true
            this.is_loading++
            this.assignUsersDialog.removingUserId = member.id
            try {
                await axios.delete(`/api/admin/groups/${group.id}/members/${member.id}`)
                this.notifySuccess('Benutzer entfernt.')
                await this.loadGroups()
                await this.loadAssignedMembers()
                if (this.assignUsersDialog.userSearchHasRun) {
                    await this.searchAssignableUsers()
                }
                await this.loadAssignableGroups()
            } catch (error) {
                this.notifyError(error, 'Benutzer konnte nicht entfernt werden.')
            } finally {
                this.assignUsersDialog.removingUserId = null
                this.is_loading--
                this.isBusy = false
            }
        },
        async searchAssignableUsers() {
            const group = this.assignUsersDialog.group
            if (!group?.id) return
            const searchString = String(this.assignUsersDialog.userSearchString || '').trim()
            if (searchString === '') {
                this.assignUsersDialog.userSearchHasRun = false
                this.assignUsersDialog.userSearchResults = []
                return
            }
            this.assignUsersDialog.userSearchLoading = true
            try {
                const response = await axios.get(`/api/admin/groups/${group.id}/assignable-users`, {
                    params: { search_string: searchString },
                })
                this.assignUsersDialog.userSearchHasRun = true
                this.assignUsersDialog.userSearchResults = Array.isArray(response.data?.data) ? response.data.data : []
            } catch (error) {
                this.assignUsersDialog.userSearchHasRun = true
                this.assignUsersDialog.userSearchResults = []
                this.notifyError(error, 'Benutzer konnten nicht geladen werden.')
            } finally {
                this.assignUsersDialog.userSearchLoading = false
            }
        },
        async loadAssignableGroups() {
            const group = this.assignUsersDialog.group
            if (!group?.id) return
            this.assignUsersDialog.sourceGroupsLoading = true
            try {
                const response = await axios.get(`/api/admin/groups/${group.id}/assignable-groups`)
                const rows = Array.isArray(response.data?.data) ? response.data.data : []
                this.assignUsersDialog.sourceGroups = rows.map((row) => ({
                    ...row,
                    display_name: `${this.currentTypeLabel(row.type)} | ${row.name} (${row.members_count})`,
                }))
                if (!this.assignUsersDialog.sourceGroups.some((row) => Number(row.id) === Number(this.assignUsersDialog.sourceGroupId))) {
                    this.assignUsersDialog.sourceGroupId = null
                }
            } catch (error) {
                this.assignUsersDialog.sourceGroups = []
                this.assignUsersDialog.sourceGroupId = null
                this.notifyError(error, 'Quellgruppen konnten nicht geladen werden.')
            } finally {
                this.assignUsersDialog.sourceGroupsLoading = false
            }
        },
        async assignUsersToCurrentGroup(userIds = []) {
            const group = this.assignUsersDialog.group
            if (!group?.id || this.isBusy || !Array.isArray(userIds) || userIds.length === 0) return

            this.isBusy = true
            this.is_loading++
            try {
                const response = await axios.post(`/api/admin/groups/${group.id}/assign-users`, {
                    user_ids: userIds,
                })
                const newCount = Number(response.data?.meta?.new_count ?? 0)
                this.notifySuccess(newCount > 0 ? `${newCount} Benutzer zugeordnet.` : 'Benutzer waren bereits zugeordnet.')
                await this.loadGroups()
                await this.loadAssignedMembers()
                await this.searchAssignableUsers()
                await this.loadAssignableGroups()
            } catch (error) {
                this.notifyError(error, 'Benutzer konnten nicht zugeordnet werden.')
            } finally {
                this.is_loading--
                this.isBusy = false
            }
        },
        async assignFromOtherGroup() {
            const group = this.assignUsersDialog.group
            const sourceGroupId = this.assignUsersDialog.sourceGroupId
            if (!group?.id || !sourceGroupId || this.isBusy) return

            this.isBusy = true
            this.is_loading++
            try {
                const response = await axios.post(`/api/admin/groups/${group.id}/assign-from-group`, {
                    source_group_id: sourceGroupId,
                })
                const newCount = Number(response.data?.meta?.new_count ?? 0)
                this.notifySuccess(newCount > 0 ? `${newCount} Benutzer aus Gruppe übernommen.` : (response.data?.message || 'Keine neuen Benutzer übernommen.'))
                await this.loadGroups()
                await this.loadAssignedMembers()
                await this.searchAssignableUsers()
                await this.loadAssignableGroups()
            } catch (error) {
                this.notifyError(error, 'Benutzer konnten nicht aus der Gruppe übernommen werden.')
            } finally {
                this.is_loading--
                this.isBusy = false
            }
        },
    },
}
</script>

<style scoped src="../../../../css/admin-superadmin-overview-shell.css"></style>
<style scoped src="../../../../css/admin-superadmin-overview-cards.css"></style>

<style scoped>
.groups-cards-shell {
    width: calc(100% + ((100vw - 100%) / 2));
    max-width: none;
    margin-left: 0;
    margin-right: 0;
    padding: 0;
}

.groups-cards-row {
    display: flex;
    flex-wrap: wrap;
    align-items: stretch;
    justify-content: flex-start;
}

.groups-table-wrap {
    border-radius: 12px;
    border: 1px solid rgba(16, 38, 58, 0.08);
    background: rgba(255, 255, 255, 0.72);
    overflow: hidden;
}

.groups-table :deep(table) {
    background: transparent !important;
}

.groups-table :deep(th) {
    white-space: nowrap;
    font-size: 0.75rem;
    color: rgba(16, 38, 58, 0.86);
}

.groups-table :deep(td) {
    vertical-align: middle;
}

.groups-table :deep(.groups-row) {
    cursor: pointer;
    transition: background-color 0.12s ease;
}

.groups-table :deep(.groups-row:hover) {
    background: rgba(57, 73, 171, 0.04);
}

.groups-table :deep(.groups-row.is-selected) {
    background: rgba(57, 73, 171, 0.08);
}

.groups-desc-cell {
    width: 100%;
    max-width: none;
    white-space: normal;
    line-height: 1.25;
}

.groups-assign-section {
    border-radius: 12px;
    border: 1px solid rgba(16, 38, 58, 0.08);
    background: rgba(255, 255, 255, 0.62);
    padding: 12px;
}

.groups-assign-list {
    display: grid;
    gap: 8px;
}

.groups-assign-list-item {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    border-radius: 10px;
    border: 1px solid rgba(16, 38, 58, 0.08);
    background: rgba(255, 255, 255, 0.72);
    padding: 8px 10px;
}

.min-w-0 {
    min-width: 0;
}

@media (max-width: 1200px) {
    .groups-cards-shell {
        width: 100%;
        max-width: 100%;
    }
}

@media (max-width: 720px) {
    .groups-assign-list-item {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>
