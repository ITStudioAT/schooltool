<template>
    <v-col cols="12" :xl="mainColXl" v-if="showOverviewCard">
        <section class="crud-shell admin-card ai-glass-panel" :class="{ 'is-disabled': action != '' }">
            <div class="admin-card-head crud-head mb-4">
                <div>
                    <div class="admin-card-eyebrow">Verwaltung</div>
                    <h2 class="admin-card-title crud-title">Lizenzvergaben</h2>
                </div>
                <div class="admin-kpi-grid crud-kpis">
                    <div class="kpi-card ai-glass-panel">
                        <div class="kpi-label">Schulen</div>
                        <div class="kpi-value">{{ totalSchoolsCount }}</div>
                    </div>
                </div>
            </div>
            <section class="admin-card ai-glass-panel crud-main-card pa-3">
                <SearchField :store="schoolStore" selected_field="selected_schools" />

                <div class="d-flex flex-row flex-wrap align-center ga-2 mt-2" :disabled="action != ''">
                    <v-btn
                        color="error"
                        slim
                        tile
                        class="text-caption"
                        :variant="expired_only ? 'flat' : 'outlined'"
                        :disabled="isInteractionLocked"
                        @click="toggleExpiredSchools">
                        Abgelaufen
                    </v-btn>
                    <v-btn color="primary" slim flat tile class="text-caption" @click="clearSelection" :disabled="isInteractionLocked" v-if="selected_schools.length >= 1">
                        Auswahl aufheben
                    </v-btn>
                </div>

                <v-list
                    dense
                    variant="flat"
                    class="assignment-schools-list"
                    select-strategy="leaf"
                    v-model:selected="selected_schools"
                    @update:selected="onSelectedSchoolsUpdate"
                    color="success-lighten-2">
                    <v-list-item
                        v-for="item in schools"
                        :key="item.id"
                        :value="item.id"
                        class="assignment-schools-item"
                        :class="{ 'is-selected': selectedSchoolId === item.id }">
                        <template #title>
                            <article class="licence-card">
                                <header class="licence-card__header">
                                    <div class="licence-card__title-wrap">
                                        <div class="licence-card__title">{{ item.long_name }}</div>
                                        <div class="licence-card__subtitle">{{ item.email || 'Keine E-Mail hinterlegt' }}</div>
                                    </div>
                                    <div class="licence-card__badges">
                                        <div class="licence-card__badge">
                                            <span class="licence-card__badge-label">Kürzel</span>
                                            <span class="licence-card__badge-value">{{ item.short_name || '-' }}</span>
                                        </div>
                                        <div class="licence-card__badge">
                                            <span class="licence-card__badge-label">Lizenzen</span>
                                            <span class="licence-card__badge-value">{{ Array.isArray(item.licences) ? item.licences.length : 0 }}</span>
                                        </div>
                                    </div>
                                </header>

                                <div class="licence-card__roles-block">
                                    <div class="licence-card__roles-title">Zugewiesene Lizenzen</div>
                                    <div class="licence-card__roles-list">
                                        <template v-if="item.licences && item.licences.length >= 1">
                                            <v-chip
                                                v-for="licence in sortedLicences(item.licences)"
                                                :key="`licence-assignment-${item.id}-${licence.id}`"
                                                size="small"
                                                class="licence-card__role-chip"
                                                :color="isSchoolLicenceNotNeeded(licence) ? 'success' : undefined"
                                                :variant="isSchoolLicenceNotNeeded(licence) ? 'flat' : 'outlined'">
                                                <v-avatar size="14" :color="isLicenceActive(licence) ? 'success' : 'error'" class="mr-1">
                                                    <v-icon size="10" color="white" :icon="isLicenceActive(licence) ? 'mdi-check' : 'mdi-close'" />
                                                </v-avatar>
                                                {{ licence.name }}
                                            </v-chip>
                                        </template>
                                        <span v-else class="text-medium-emphasis">Keine Lizenzen zugewiesen</span>
                                    </div>
                                </div>
                            </article>
                        </template>
                    </v-list-item>
                </v-list>

                <Pagination :meta="meta" :store="schoolStore" selected_field="selected_schools" />
            </section>
        </section>
    </v-col>

    <v-col cols="12" md="8" :xl="schoolDetailXl" v-if="selectedSchool">
        <section class="admin-card ai-glass-panel pa-4">
            <div class="admin-card-head mb-3 d-flex align-center justify-space-between ga-2">
                <div>
                    <div class="admin-card-eyebrow">Schule</div>
                    <h2 class="admin-card-title">{{ selectedSchool.long_name }}</h2>
                </div>
                <v-btn
                    color="primary"
                    variant="flat"
                    size="default"
                    prepend-icon="mdi-arrow-left"
                    class="text-none font-weight-bold"
                    :disabled="isInteractionLocked"
                    @click="clearSelection">
                    Zur Übersicht
                </v-btn>
            </div>
            <v-card variant="outlined" class="pa-4 mb-4">
                    <div class="text-subtitle-1 mb-3">Lizenz zuweisen</div>
                    <v-row dense>
                        <v-col cols="12">
                            <v-select
                                v-model="new_assignment.licence_id"
                                :items="assignableLicences"
                                item-title="name"
                                item-value="id"
                                label="Lizenz"
                                :disabled="isInteractionLocked"
                                clearable />
                        </v-col>
                        <v-col cols="12">
                            <v-date-input v-model="new_assignment.valid_until" label="Gültig bis" class="flex-grow-1" :disabled="isInteractionLocked" />
                        </v-col>
                    </v-row>
                    <v-card tile flat color="transparent" class="d-flex flex-row justify-end mt-2">
                        <v-btn color="success" flat tile :disabled="isInteractionLocked" @click="addLicenceToSelectedSchool">Hinzufügen</v-btn>
                    </v-card>
                </v-card>

                <v-card variant="outlined" class="pa-4 mb-4">
                    <div class="text-subtitle-1 mb-3">Zugewiesene Lizenzen</div>
                    <v-list dense variant="flat" class="assignment-licence-list" v-if="school_licences.length >= 1">
                        <v-list-item
                            v-for="licence in sortedLicences(school_licences)"
                            :key="`school-licence-${licence.school_licence_id}`"
                            class="assignment-licence-item">
                            <template #title>
                                <article class="licence-card licence-card--compact">
                                    <header class="licence-card__header">
                                        <div class="licence-card__title-wrap">
                                            <div class="d-flex flex-row align-center ga-1">
                                                <v-avatar size="16" :color="isLicenceActive(licence) ? 'success' : 'error'">
                                                    <v-icon size="11" color="white" :icon="isLicenceActive(licence) ? 'mdi-check' : 'mdi-close'" />
                                                </v-avatar>
                                                <div class="licence-card__title" :class="{ 'text-success': isSchoolLicenceNotNeeded(licence) }">{{ licence.name }}</div>
                                                <v-btn
                                                    size="x-small"
                                                    icon="mdi-pencil"
                                                    variant="text"
                                                    color="secondary"
                                                    :disabled="isInteractionLocked"
                                                    @click="openEditDateDialog(licence)" />
                                            </div>
                                            <div class="licence-card__subtitle">Gültig bis: {{ licence.valid_until || '-' }}</div>
                                        </div>
                                    </header>
                                    <div class="d-flex flex-row ga-2 flex-wrap">
                                        <v-btn
                                            size="small"
                                            flat
                                            tile
                                            color="primary"
                                            :disabled="isInteractionLocked"
                                            @click="openSchoolLicenceModel(licence)">
                                            Lizenzmodell
                                        </v-btn>
                                        <v-btn
                                            v-if="hasUserLicenceRequiredRole(licence)"
                                            size="small"
                                            flat
                                            tile
                                            color="primary"
                                            :disabled="isInteractionLocked"
                                            @click="openUserLicences(licence)">
                                            Benutzerlizenzen
                                        </v-btn>
                                        <v-btn
                                            size="small"
                                            flat
                                            tile
                                            color="warning"
                                            :disabled="isInteractionLocked"
                                            @click="removeSchoolLicence(licence.school_licence_id)">
                                            Entfernen
                                        </v-btn>
                                    </div>
                                </article>
                            </template>
                        </v-list-item>
                    </v-list>
                    <div class="text-caption text-medium-emphasis" v-else>Keine Lizenzen zugewiesen.</div>
                </v-card>
        </section>
    </v-col>

    <v-col cols="12" md="8" :xl="schoolDetailXl" v-if="selectedSchoolLicence && currentSchoolLicenceModel">
        <section class="admin-card ai-glass-panel pa-4">
            <div class="admin-card-head mb-3">
                <div>
                    <div class="admin-card-eyebrow">Lizenzmodell</div>
                    <h2 class="admin-card-title">{{ selectedSchoolLicence.name }}</h2>
                </div>
            </div>
            <div class="text-subtitle-2 mb-2">Schullizenz nötig</div>
                <v-btn-toggle
                    :model-value="currentSchoolLicenceModel.school_licence_required"
                    mandatory
                    divided
                    color="primary"
                    @update:model-value="setSchoolLicenceRequired">
                    <v-btn :value="true">JA</v-btn>
                    <v-btn :value="false">NEIN</v-btn>
                </v-btn-toggle>

                <v-divider class="my-4"></v-divider>

                <div class="text-subtitle-2 mb-2">Betroffene Rollen</div>
                <div class="d-flex flex-row flex-wrap ga-2">
                    <v-chip
                        v-for="role in availableRoles"
                        :key="`school-licence-role-${role.id}`"
                        clickable
                        :color="isRoleAffected(role.name) ? 'primary' : undefined"
                        :variant="isRoleAffected(role.name) ? 'flat' : 'outlined'"
                        @click="toggleAffectedRole(role.name)">
                        {{ role.name }}
                    </v-chip>
                </div>

                <v-divider class="my-4"></v-divider>

                <div class="text-subtitle-2 mb-2">Eigene Userlizenz notwendig</div>
                <v-card
                    v-for="roleName in currentSchoolLicenceModel.affected_roles"
                    :key="`school-licence-role-setting-${roleName}`"
                    variant="tonal"
                    class="pa-3 mb-2">
                    <div class="d-flex flex-row flex-wrap align-center justify-space-between ga-3">
                        <v-chip color="primary" variant="flat">{{ roleName }}</v-chip>
                        <v-btn-toggle
                            :model-value="isUserLicenceRequiredForRole(roleName)"
                            mandatory
                            divided
                            color="primary"
                            @update:model-value="setUserLicenceRequiredForRole(roleName, $event)">
                            <v-btn :value="true">JA</v-btn>
                            <v-btn :value="false">NEIN</v-btn>
                        </v-btn-toggle>
                    </div>
                </v-card>
                <div class="text-caption text-medium-emphasis" v-if="currentSchoolLicenceModel.affected_roles.length === 0">
                    Wählen Sie zuerst eine oder mehrere Rollen aus.
                </div>

            <div class="d-flex flex-row align-center justify-space-between mt-4">
                <v-btn color="warning" flat tile @click="closeSchoolLicenceModel">Abbrechen</v-btn>
                <v-btn color="success" flat tile @click="saveSchoolLicenceModel">Speichern</v-btn>
            </div>
        </section>
    </v-col>

    <v-col cols="12" md="8" :xl="schoolDetailXl" v-if="selectedUserLicencesSource">
        <section class="admin-card ai-glass-panel pa-4">
            <div class="admin-card-head mb-3">
                <div>
                    <div class="admin-card-eyebrow">Benutzerlizenzen</div>
                    <h2 class="admin-card-title">{{ selectedUserLicencesSource.name }}</h2>
                </div>
                <v-btn size="small" tile flat color="warning" @click="closeUserLicencesCard">Abbrechen</v-btn>
            </div>
            <div>
                <div class="text-body-2 mb-2">Rollen mit eigener Userlizenz</div>
                        <div class="d-flex flex-row flex-wrap ga-2 mb-3">
                            <v-chip
                                clickable
                                size="small"
                                :color="selected_user_licence_role_filters.length === 0 ? 'primary' : undefined"
                                :variant="selected_user_licence_role_filters.length === 0 ? 'flat' : 'outlined'"
                                @click="showAllUsersWithoutRoleFilter">
                                Alle
                            </v-chip>
                            <v-chip
                                v-for="roleName in userLicenceRoleNames"
                                :key="`school-licence-user-role-${roleName}`"
                                clickable
                                size="small"
                                :color="isUserRoleFilterActive(roleName) ? 'primary' : undefined"
                                :variant="isUserRoleFilterActive(roleName) ? 'flat' : 'outlined'"
                                @click="toggleUserRoleFilter(roleName)">
                                {{ roleName }}
                            </v-chip>
                            <span class="text-caption text-medium-emphasis" v-if="userLicenceRoleNames.length === 0">-</span>
                        </div>

                        <v-form @submit.prevent="searchSchoolLicenceUsers">
                            <div class="d-flex flex-row align-start">
                                <v-text-field
                                    clearable
                                    v-model="user_licence_user_search_string"
                                    label="Benutzer suchen"
                                    @click:clear="searchSchoolLicenceUsers" />
                                <v-btn
                                    flat
                                    tile
                                    class="mt-1 ml-2"
                                    color="primary"
                                    variant="outlined"
                                    icon="mdi-magnify"
                                    type="submit"
                                    @click="searchSchoolLicenceUsers" />
                            </div>
                        </v-form>

                        <div class="d-flex flex-row flex-wrap align-center ga-2 mt-2">
                            <v-btn
                                color="error"
                                slim
                                tile
                                class="text-caption"
                                :variant="user_licence_expired_only ? 'flat' : 'outlined'"
                                @click="toggleUserLicencesExpiredOnly">
                                Abgelaufen
                            </v-btn>
                        </div>

                    <v-list
                        dense
                        variant="elevated"
                        select-strategy="leaf"
                        v-model:selected="selected_user_licence_users"
                        @update:selected="onSelectedUserLicenceUsersUpdate"
                        color="success-lighten-2"
                        v-if="school_licence_users.length >= 1">
                        <v-list-item v-for="item in school_licence_users" :key="`school-licence-user-${item.id}`" :value="item.id">
                            <template #title>
                                <div class="d-flex flex-column ga-2 py-1">
                                    <div class="text-body-1">{{ item.last_name }} {{ item.first_name }}</div>
                                    <div class="text-caption text-medium-emphasis">{{ item.email }}</div>
                                    <div class="d-flex flex-row flex-wrap align-center ga-2">
                                        <span class="text-caption text-medium-emphasis">Rollen (Lizenzmodell):</span>
                                        <template v-if="userRoleEntriesFromLicenceModel(item).length >= 1">
                                            <v-chip
                                                v-for="roleEntry in userRoleEntriesFromLicenceModel(item)"
                                                :key="`school-licence-user-role-chip-${item.id}-${roleEntry.name}`"
                                                size="x-small"
                                                variant="outlined">
                                                <v-avatar size="14" :color="roleEntry.is_active ? 'success' : 'error'" class="mr-1">
                                                    <v-icon size="10" color="white" :icon="roleEntry.is_active ? 'mdi-check' : 'mdi-close'" />
                                                </v-avatar>
                                                {{ roleEntry.name }}
                                            </v-chip>
                                        </template>
                                        <span v-else class="text-caption text-medium-emphasis">-</span>
                                    </div>
                                </div>
                            </template>
                        </v-list-item>
                    </v-list>
                    <div class="text-caption text-medium-emphasis mb-3" v-else>Keine passenden Benutzer gefunden.</div>

                    <v-card tile flat color="transparent" v-if="school_licence_users_meta && school_licence_users_meta.total >= 1">
                        <div class="text-caption d-flex flex-row align-center justify-space-between">
                            <div>{{ userLicenceMetaInfoText }}</div>
                            <div>{{ userLicenceMetaPageText }}</div>
                        </div>

                        <div class="d-flex flex-row align-center justify-space-between">
                            <v-btn
                                flat
                                tile
                                class="mt-1 ml-2"
                                color="primary"
                                variant="outlined"
                                icon="mdi-page-first"
                                @click="firstSchoolLicenceUsersPage"
                                :disabled="school_licence_users_meta.current_page <= 1" />
                            <v-btn
                                flat
                                tile
                                class="mt-1 ml-2"
                                color="primary"
                                variant="outlined"
                                icon="mdi-page-previous-outline"
                                @click="prevSchoolLicenceUsersPage"
                                :disabled="school_licence_users_meta.current_page <= 1" />
                            <v-btn
                                flat
                                tile
                                class="mt-1 ml-2"
                                color="primary"
                                variant="outlined"
                                icon="mdi-page-next-outline"
                                @click="nextSchoolLicenceUsersPage"
                                :disabled="school_licence_users_meta.current_page >= school_licence_users_meta.last_page" />
                            <v-btn
                                flat
                                tile
                                class="mt-1 ml-2"
                                color="primary"
                                variant="outlined"
                                icon="mdi-page-last"
                                @click="lastSchoolLicenceUsersPage"
                                :disabled="school_licence_users_meta.current_page >= school_licence_users_meta.last_page" />
                        </div>
                    </v-card>
            </div>
        </section>
    </v-col>

    <v-dialog v-model="userLicenceUserDialogOpen" persistent max-width="980" scrollable>
        <section class="admin-card pa-4 user-licence-user-dialog-solid">
            <div class="admin-card-head mb-3">
                <div>
                    <div class="admin-card-eyebrow">Benutzer</div>
                    <h2 class="admin-card-title">{{ selectedUserLicenceUser ? `${selectedUserLicenceUser.last_name} ${selectedUserLicenceUser.first_name}` : 'Benutzer' }}</h2>
                </div>
            </div>
            <v-card variant="outlined" class="pa-4 mb-4">
                <template v-if="isSchoolLicenceNotNeeded(selectedUserLicencesSource)">
                    <div class="text-subtitle-1">Schullizenz nicht erforderlich</div>
                </template>
                <template v-else>
                    <div class="text-subtitle-1 mb-3">Schullizenz gültig bis</div>
                    <div class="text-body-1">{{ selectedUserLicenceValidUntilLabel }}</div>
                </template>
            </v-card>

            <v-card variant="outlined" class="pa-4 mb-4">
                <div class="text-subtitle-1 mb-3">Rollen aus Lizenzmodell</div>
                <div class="d-flex flex-row flex-wrap ga-2 mb-4">
                    <v-chip
                        v-for="roleEntry in selectedUserLicenceRoleEntries"
                        :key="`selected-user-role-toggle-${selectedUserLicenceUser.id}-${roleEntry.name}`"
                        clickable
                        :color="roleEntry.assigned ? 'primary' : undefined"
                        :variant="roleEntry.assigned ? 'flat' : 'outlined'"
                        @click="toggleSelectedUserRole(roleEntry.name)">
                        {{ roleEntry.name }}
                    </v-chip>
                    <span class="text-caption text-medium-emphasis" v-if="selectedUserLicenceRoleEntries.length === 0">-</span>
                </div>
                <div class="text-caption text-medium-emphasis mt-3" v-if="selectedAssignedUserLicenceRoleNames.length >= 1">
                    Ausgewählt: {{ selectedAssignedUserLicenceRoleNames.join(', ') }}
                </div>
                <div class="text-caption text-medium-emphasis mt-3" v-else>Noch keine Rolle ausgewählt.</div>
                <div class="text-caption text-medium-emphasis mt-2" v-if="selectedAssignedNoActivationRoleNames.length >= 1">
                    Keine Aktivierung nötig (Userlizenz = NEIN): {{ selectedAssignedNoActivationRoleNames.join(', ') }}
                </div>
            </v-card>

            <v-card variant="outlined" class="pa-4">
                <div class="text-subtitle-1 mb-3">Gültig bis je zugewiesene Rolle</div>
                <v-card
                    v-for="roleEntry in selectedAssignedUserLicenceRoleEntries"
                    :key="`selected-user-role-date-${selectedUserLicenceUser.id}-${roleEntry.name}`"
                    variant="tonal"
                    class="pa-3 mb-2">
                    <div class="d-flex flex-row flex-wrap align-center justify-space-between ga-3 mb-3">
                        <v-chip color="primary" variant="flat">
                            <v-avatar
                                size="14"
                                :color="isSelectedUserRoleAssignmentActive(roleEntry) ? 'success' : 'error'"
                                class="mr-1">
                                <v-icon
                                    size="10"
                                    color="white"
                                    :icon="isSelectedUserRoleAssignmentActive(roleEntry) ? 'mdi-check' : 'mdi-close'" />
                            </v-avatar>
                            {{ roleEntry.name }}
                        </v-chip>
                        <div class="text-caption" :class="isSelectedUserRoleAssignmentActive(roleEntry) ? 'text-success' : 'text-error'">
                            {{ selectedUserRoleStatusLabel(roleEntry) }}
                        </div>
                    </div>

                    <v-row dense>
                        <v-col cols="12" md="4">
                            <div class="text-caption text-medium-emphasis mb-1">Aktiviert</div>
                            <v-btn-toggle
                                :model-value="!!roleEntry.is_activated"
                                mandatory
                                divided
                                color="primary"
                                @update:model-value="setSelectedUserRoleActivation(roleEntry, $event)">
                                <v-btn :value="true">JA</v-btn>
                                <v-btn :value="false">NEIN</v-btn>
                            </v-btn-toggle>
                        </v-col>

                        <v-col cols="12" md="8">
                            <v-select
                                v-model="roleEntry.plan_id"
                                :items="rolePlanSelectItems(roleEntry)"
                                item-title="title"
                                item-value="value"
                                label="Plan"
                                clearable
                                :hint="rolePlanSelectItems(roleEntry).length === 0 ? 'Keine Pläne für diese Rolle konfiguriert.' : undefined"
                                persistent-hint
                                :disabled="!roleEntry.assigned || !roleEntry.is_activated"
                                hide-details="auto" />
                        </v-col>

                        <v-col cols="12">
                            <v-date-input
                                v-model="roleEntry.valid_until"
                                label="Gültig bis"
                                class="flex-grow-1"
                                hide-details />
                        </v-col>
                    </v-row>
                </v-card>
                <div class="text-caption text-medium-emphasis" v-if="selectedAssignedUserLicenceRoleEntries.length === 0 && selectedAssignedNoActivationRoleNames.length === 0">
                    Wählen Sie zuerst eine oder mehrere Rollen aus.
                </div>
                <div class="text-caption text-medium-emphasis" v-if="selectedAssignedUserLicenceRoleEntries.length === 0 && selectedAssignedNoActivationRoleNames.length >= 1">
                    Für die ausgewählten Rollen ist keine Aktivierung erforderlich.
                </div>

                <div class="d-flex flex-row align-center justify-space-between mt-4">
                    <v-btn color="warning" flat tile @click="closeSelectedUserLicenceCard">Abbrechen</v-btn>
                    <v-btn color="success" flat tile :disabled="!selectedUserLicenceUser" @click="saveSelectedUserLicenceRoles">Speichern</v-btn>
                </div>
            </v-card>
        </section>
    </v-dialog>

    <v-dialog v-model="edit_date_dialog" max-width="520">
        <v-card>
            <v-card-title>Lizenzdatum ändern</v-card-title>
            <v-card-text>
                <div class="text-body-2 mb-3" v-if="edit_school_licence">
                    {{ edit_school_licence.name }}
                </div>
                <v-checkbox v-model="edit_no_date" label="Kein Ablaufdatum" hide-details class="mb-2" />
                <v-date-input v-model="edit_valid_until" label="Gültig bis" :disabled="edit_no_date" class="flex-grow-1" />
            </v-card-text>
            <v-card-actions class="d-flex justify-space-between">
                <v-btn color="warning" variant="flat" @click="closeEditDateDialog">Abbrechen</v-btn>
                <v-btn color="success" variant="flat" @click="saveEditedDate">Speichern</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script>
import axios from 'axios'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolStore } from '@/stores/admin/SchoolStore'
import { useLicenceStore } from '@/stores/admin/LicenceStore'
import { useRoleStore } from '@/stores/admin/RoleStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { parseLocalDate } from '@/helpers/date'
import SearchField from '@/pages/components/SearchField.vue'
import Pagination from '@/pages/components/Pagination.vue'

export default {
    components: { SearchField, Pagination },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.schoolStore = useSchoolStore()
        this.licenceStore = useLicenceStore()
        this.roleStore = useRoleStore()

        this.selected_schools = []
        await Promise.all([this.schoolStore.index(), this.licenceStore.loadLicences(), this.roleStore.loadRoles()])
    },

    data() {
        return {
            adminStore: null,
            schoolStore: null,
            licenceStore: null,
            roleStore: null,
            model_lock_action: 'school_licence_model',
            selection_lock_action: 'school_licence_selection',
            selected_school_licence_id: null,
            selected_user_licences_school_licence_id: null,
            edit_date_dialog: false,
            edit_school_licence: null,
            edit_valid_until: '',
            edit_no_date: false,
            new_assignment: {
                licence_id: null,
                valid_until: '',
            },
            school_licence_models: {},
            user_licence_user_search_string: '',
            selected_user_licence_role_filters: [],
            selected_user_licence_users: [],
            user_licence_expired_only: false,
            user_licence_default_select_all_pending: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action']),
        ...mapWritableState(useSchoolStore, [
            'schools',
            'meta',
            'selected_schools',
            'expired_only',
            'school_licences',
            'school_licence_users',
            'school_licence_users_meta',
            'school_licence_users_roles',
            'school_licence_users_active_role_filters',
            'school_licence_users_role_statuses',
            'school_licence_user_role_details',
            'school_licence_user_role_details_valid_until',
        ]),
        ...mapWritableState(useLicenceStore, ['licences']),
        ...mapWritableState(useRoleStore, ['roles']),
        totalSchoolsCount() {
            return this.meta?.total ?? this.schools?.length ?? 0
        },
        showOverviewCard() {
            return !this.selectedSchool
        },
        mainColXl() {
            if (this.selectedSchool && !this.isUserLicencesOpen) {
                return 6
            }
            if (this.selectedUserLicenceUser) {
                return 4
            }
            if (this.selectedUserLicencesSource) {
                return 7
            }
            return 6
        },
        schoolDetailXl() {
            return 6
        },
        selectedSchoolId() {
            return this.selected_schools[0] ?? null
        },
        selectedSchool() {
            return this.schools.find((item) => item.id === this.selectedSchoolId) || null
        },
        assignableLicences() {
            const assigned = new Set((this.school_licences || []).map((item) => item.id))
            const all = Array.isArray(this.licences) ? this.licences : []
            return all
                .filter((licence) => !assigned.has(licence.id))
                .sort((a, b) => String(a?.name || '').localeCompare(String(b?.name || ''), 'de'))
        },
        selectedSchoolLicence() {
            return (this.school_licences || []).find((item) => item.school_licence_id === this.selected_school_licence_id) || null
        },
        currentSchoolLicenceModel() {
            if (!this.selected_school_licence_id) return null

            if (!this.school_licence_models[this.selected_school_licence_id]) {
                this.school_licence_models[this.selected_school_licence_id] = this.normalizeLicenceModel(this.selectedSchoolLicence?.licence_model || null)
            }

            return this.school_licence_models[this.selected_school_licence_id]
        },
        isSchoolLicenceModelOpen() {
            return !!this.selected_school_licence_id
        },
        isUserLicencesOpen() {
            return !!this.selected_user_licences_school_licence_id
        },
        isInteractionLocked() {
            return this.isSchoolLicenceModelOpen || this.isUserLicencesOpen
        },
        selectedUserLicencesSource() {
            if (!this.selected_user_licences_school_licence_id) return null
            return (this.school_licences || []).find((item) => item.school_licence_id === this.selected_user_licences_school_licence_id) || null
        },
        selectedUserLicenceUserId() {
            return this.selected_user_licence_users[0] ?? null
        },
        selectedUserLicenceUser() {
            if (!this.selectedUserLicenceUserId) return null
            return (this.school_licence_users || []).find((item) => item.id === this.selectedUserLicenceUserId) || null
        },
        userLicenceUserDialogOpen: {
            get() {
                return !!this.selectedUserLicenceUser
            },
            set(value) {
                if (!value) {
                    this.closeSelectedUserLicenceCard()
                }
            },
        },
        selectedUserLicenceRoleEntries() {
            return Array.isArray(this.school_licence_user_role_details) ? this.school_licence_user_role_details : []
        },
        selectedAssignedUserLicenceRoleEntries() {
            return this.selectedUserLicenceRoleEntries.filter((item) => !!item?.assigned && !!item?.user_licence_required)
        },
        selectedAssignedNoActivationRoleNames() {
            return this.sortedRoleNames(
                this.selectedUserLicenceRoleEntries
                    .filter((item) => !!item?.assigned && !item?.user_licence_required)
                    .map((item) => item?.name)
            )
        },
        selectedAssignedUserLicenceRoleNames() {
            return this.sortedRoleNames(this.selectedUserLicenceRoleEntries.filter((item) => !!item?.assigned).map((item) => item.name))
        },
        selectedUserLicenceValidUntilLabel() {
            return this.school_licence_user_role_details_valid_until || this.selectedUserLicencesSource?.valid_until || 'unbegrenzt'
        },
        availableRoles() {
            return this.roles || []
        },
        userLicenceRoleNames() {
            return this.sortedRoleNames(this.school_licence_users_roles || [])
        },
        userLicenceMetaInfoText() {
            const meta = this.school_licence_users_meta || {}
            const from = meta.from || 0
            const to = meta.to || 0
            const total = meta.total || 0
            return `${from} - ${to} von ${total}`
        },
        userLicenceMetaPageText() {
            const meta = this.school_licence_users_meta || {}
            const currentPage = meta.current_page || 1
            const lastPage = meta.last_page || 1
            return `Seite ${currentPage} von ${lastPage}`
        },
    },

    watch: {
        'new_assignment.valid_until'(val) {
            if (val && val instanceof Date) {
                this.new_assignment.valid_until = this.toDateString(val)
            }
        },
        edit_valid_until(val) {
            if (val && val instanceof Date) {
                this.edit_valid_until = this.toDateString(val)
            }
        },
        async selectedUserLicenceUserId() {
            await this.loadSelectedUserLicenceRoleDetails()
        },
    },

    methods: {
        async onSelectedSchoolsUpdate(value) {
            if (!Array.isArray(value)) {
                this.selected_schools = []
                this.school_licences = []
                this.closeSchoolLicenceModel()
                this.closeUserLicencesCard()
                return
            }
            if (value.length <= 1) {
                this.selected_schools = value
            } else {
                this.selected_schools = [value[value.length - 1]]
            }

            await this.loadSelectedSchoolInfos()
        },
        async loadSelectedSchoolInfos() {
            if (!this.selectedSchoolId) {
                this.school_licences = []
                this.closeSchoolLicenceModel()
                this.closeUserLicencesCard()
                return
            }

            await this.schoolStore.loadSchoolInfos(this.selectedSchoolId)
            this.closeSchoolLicenceModel()
            this.closeUserLicencesCard()
            this.new_assignment = {
                licence_id: null,
                valid_until: this.defaultValidUntil(),
            }
            this.bootstrapSchoolLicenceModels()
        },
        bootstrapSchoolLicenceModels() {
            const models = {}
            for (const schoolLicence of this.school_licences || []) {
                models[schoolLicence.school_licence_id] = this.normalizeLicenceModel(schoolLicence.licence_model || null)
            }
            this.school_licence_models = models
        },
        clearSelection() {
            this.selected_schools = []
            this.school_licences = []
            this.closeSchoolLicenceModel()
            this.closeUserLicencesCard()
        },
        async toggleExpiredSchools() {
            this.expired_only = !this.expired_only
            this.clearSelection()
            await this.schoolStore.index(1)
        },
        sortedLicences(licences) {
            const items = Array.isArray(licences) ? [...licences] : []
            return items.sort((a, b) => String(a?.name || '').localeCompare(String(b?.name || ''), 'de'))
        },
        sortedRoleNames(roleNames) {
            const items = Array.isArray(roleNames) ? [...roleNames] : []
            return items
                .map((roleName) => String(roleName || '').trim())
                .filter((roleName) => !!roleName)
                .sort((a, b) => a.localeCompare(b, 'de'))
        },
        userRoleEntriesFromLicenceModel(user) {
            const userRoles = this.sortedRoleNames(user?.roles || [])
            const modelRoles = new Set(this.userLicenceRoleNames)
            return userRoles
                .filter((roleName) => modelRoles.has(roleName))
                .map((roleName) => ({
                    name: roleName,
                    is_active: this.isUserRoleAssignmentActive(user?.id, roleName),
                }))
        },
        isUserRoleAssignmentActive(userId, roleName) {
            if (!userId || !roleName) return true

            const statusesByUser = this.school_licence_users_role_statuses || {}
            const userStatuses = statusesByUser[String(userId)] || statusesByUser[userId] || {}
            const roleStatus = userStatuses[roleName]
            if (!roleStatus || typeof roleStatus !== 'object') return true
            return roleStatus.is_active !== false
        },
        defaultValidUntil() {
            const date = new Date()
            date.setFullYear(date.getFullYear() + 1)
            return this.localDateKey(date)
        },
        toDateString(date) {
            const parsed = parseLocalDate(date)
            const year = parsed.getFullYear()
            const month = String(parsed.getMonth() + 1).padStart(2, '0')
            const day = String(parsed.getDate()).padStart(2, '0')
            return `${year}-${month}-${day}`
        },
        localDateKey(date = new Date()) {
            const year = date.getFullYear()
            const month = String(date.getMonth() + 1).padStart(2, '0')
            const day = String(date.getDate()).padStart(2, '0')
            return `${year}-${month}-${day}`
        },
        isSchoolLicenceNotNeeded(licence) {
            if (licence?.school_licence_required === false) return true

            const model = this.normalizeLicenceModel(licence?.licence_model || null)
            return model.school_licence_required === false
        },
        isLicenceActive(licence) {
            if (this.isSchoolLicenceNotNeeded(licence)) return true

            const validUntil = licence?.valid_until
            if (!validUntil) return true
            const normalized = validUntil instanceof Date ? this.toDateString(validUntil) : String(validUntil).slice(0, 10)
            return normalized >= this.localDateKey()
        },
        async addLicenceToSelectedSchool() {
            if (!this.selectedSchoolId) return
            if (!this.new_assignment.licence_id || !this.new_assignment.valid_until) return

            const data = {
                school_id: this.selectedSchoolId,
                licence_id: this.new_assignment.licence_id,
                valid_until: this.new_assignment.valid_until,
            }

            if (!(await this.schoolStore.addLicence(data))) return

            await this.schoolStore.index(this.meta?.current_page || 1)
            this.new_assignment = {
                licence_id: null,
                valid_until: this.defaultValidUntil(),
            }
            this.bootstrapSchoolLicenceModels()
        },
        async removeSchoolLicence(school_licence_id) {
            if (!(await this.schoolStore.deleteLicence(school_licence_id))) return

            if (this.selected_school_licence_id === school_licence_id) {
                this.closeSchoolLicenceModel()
            }
            if (this.selected_user_licences_school_licence_id === school_licence_id) {
                this.closeUserLicencesCard()
            }

            await this.schoolStore.index(this.meta?.current_page || 1)
            this.bootstrapSchoolLicenceModels()
        },
        openSchoolLicenceModel(schoolLicence) {
            this.selected_school_licence_id = schoolLicence.school_licence_id
            this.school_licence_models[this.selected_school_licence_id] = this.normalizeLicenceModel(schoolLicence.licence_model || null)
            this.syncInteractionLockAction()
        },
        closeSchoolLicenceModel() {
            this.selected_school_licence_id = null
            this.syncInteractionLockAction()
        },
        async saveSchoolLicenceModel() {
            if (!this.selected_school_licence_id || !this.currentSchoolLicenceModel) return

            const payload = this.normalizeLicenceModel(this.currentSchoolLicenceModel)
            if (!(await this.schoolStore.saveSchoolLicenceModel(this.selected_school_licence_id, payload))) return

            await this.schoolStore.index(this.meta?.current_page || 1)
            this.bootstrapSchoolLicenceModels()
            this.closeSchoolLicenceModel()
        },
        openEditDateDialog(licence) {
            this.edit_school_licence = licence || null
            this.edit_no_date = !licence?.valid_until
            this.edit_valid_until = licence?.valid_until || this.defaultValidUntil()
            this.edit_date_dialog = true
        },
        closeEditDateDialog() {
            this.edit_date_dialog = false
            this.edit_school_licence = null
            this.edit_valid_until = ''
            this.edit_no_date = false
        },
        async saveEditedDate() {
            if (!this.edit_school_licence || !this.selectedSchoolId) return
            if (!this.edit_no_date && !this.edit_valid_until) return

            const data = {
                school_id: this.selectedSchoolId,
                licence_id: this.edit_school_licence.id,
                valid_until: this.edit_no_date ? null : this.edit_valid_until,
            }

            if (!(await this.schoolStore.addLicence(data))) return

            await this.schoolStore.index(this.meta?.current_page || 1)
            this.bootstrapSchoolLicenceModels()
            this.closeEditDateDialog()
        },
        setSchoolLicenceRequired(value) {
            if (typeof value !== 'boolean') return
            if (!this.currentSchoolLicenceModel) return
            this.currentSchoolLicenceModel.school_licence_required = value
        },
        isRoleAffected(roleName) {
            if (!this.currentSchoolLicenceModel) return false
            return this.currentSchoolLicenceModel.affected_roles.includes(roleName)
        },
        toggleAffectedRole(roleName) {
            if (!this.currentSchoolLicenceModel) return
            const roles = this.currentSchoolLicenceModel.affected_roles
            const index = roles.indexOf(roleName)
            if (index >= 0) {
                roles.splice(index, 1)
                delete this.currentSchoolLicenceModel.user_licence_required_by_role[roleName]
                delete this.currentSchoolLicenceModel.user_licence_plans_by_role[roleName]
                return
            }
            roles.push(roleName)
            this.currentSchoolLicenceModel.user_licence_required_by_role[roleName] = false
            this.currentSchoolLicenceModel.user_licence_plans_by_role[roleName] = []
        },
        isUserLicenceRequiredForRole(roleName) {
            if (!this.currentSchoolLicenceModel) return false
            return !!this.currentSchoolLicenceModel.user_licence_required_by_role[roleName]
        },
        setUserLicenceRequiredForRole(roleName, value) {
            if (!this.currentSchoolLicenceModel) return
            if (typeof value !== 'boolean') return
            this.currentSchoolLicenceModel.user_licence_required_by_role[roleName] = value
        },
        hasUserLicenceRequiredRole(licence) {
            const model = this.normalizeLicenceModel(licence?.licence_model || null)
            return Object.values(model.user_licence_required_by_role || {}).some((value) => !!value)
        },
        async openUserLicences(licence) {
            this.selected_user_licences_school_licence_id = licence?.school_licence_id || null
            this.syncInteractionLockAction()
            this.user_licence_user_search_string = ''
            this.selected_user_licence_role_filters = []
            this.selected_user_licence_users = []
            this.user_licence_expired_only = false
            this.user_licence_default_select_all_pending = true
            await this.loadSchoolLicenceUsers(1)
        },
        closeUserLicencesCard() {
            this.selected_user_licences_school_licence_id = null
            this.user_licence_user_search_string = ''
            this.selected_user_licence_role_filters = []
            this.selected_user_licence_users = []
            this.user_licence_expired_only = false
            this.user_licence_default_select_all_pending = false
            this.school_licence_users = []
            this.school_licence_users_meta = []
            this.school_licence_users_roles = []
            this.school_licence_users_active_role_filters = []
            this.school_licence_users_role_statuses = {}
            this.school_licence_user_role_details = []
            this.school_licence_user_role_details_valid_until = null
            this.syncInteractionLockAction()
        },
        onSelectedUserLicenceUsersUpdate(value) {
            if (!Array.isArray(value)) {
                this.selected_user_licence_users = []
                return
            }
            if (value.length <= 1) {
                this.selected_user_licence_users = value
                return
            }
            this.selected_user_licence_users = [value[value.length - 1]]
        },
        isUserRoleFilterActive(roleName) {
            return this.selected_user_licence_role_filters.includes(roleName)
        },
        async toggleUserRoleFilter(roleName) {
            this.user_licence_default_select_all_pending = false
            const index = this.selected_user_licence_role_filters.indexOf(roleName)
            if (index >= 0) {
                this.selected_user_licence_role_filters.splice(index, 1)
            } else {
                this.selected_user_licence_role_filters.push(roleName)
            }

            await this.loadSchoolLicenceUsers(1)
        },
        async showAllUsersWithoutRoleFilter() {
            this.user_licence_default_select_all_pending = false
            this.selected_user_licence_role_filters = []
            await this.loadSchoolLicenceUsers(1)
        },
        async toggleUserLicencesExpiredOnly() {
            this.user_licence_default_select_all_pending = false
            this.user_licence_expired_only = !this.user_licence_expired_only
            this.selected_user_licence_users = []
            await this.loadSchoolLicenceUsers(1)
        },
        async loadSchoolLicenceUsers(page = 1) {
            if (!this.selected_user_licences_school_licence_id) return
            const success = await this.schoolStore.loadSchoolLicenceUsers(
                this.selected_user_licences_school_licence_id,
                page,
                this.user_licence_user_search_string,
                this.selected_user_licence_role_filters,
                this.user_licence_expired_only
            )

            if (!success) return

            if (
                this.user_licence_default_select_all_pending &&
                this.selected_user_licence_role_filters.length === 0 &&
                Array.isArray(this.school_licence_users_roles) &&
                this.school_licence_users_roles.length >= 1
            ) {
                this.user_licence_default_select_all_pending = false
                this.selected_user_licence_role_filters = [...this.school_licence_users_roles]
                await this.loadSchoolLicenceUsers(1)
                return
            }

            this.user_licence_default_select_all_pending = false
            this.selected_user_licence_role_filters = [...(this.school_licence_users_active_role_filters || [])]
            this.selected_user_licence_users = []
        },
        async loadSelectedUserLicenceRoleDetails() {
            if (!this.selected_user_licences_school_licence_id || !this.selectedUserLicenceUserId) {
                this.school_licence_user_role_details = []
                this.school_licence_user_role_details_valid_until = this.selectedUserLicencesSource?.valid_until || null
                return
            }

            const response = await this.schoolStore.loadSchoolLicenceUserRoles(
                this.selected_user_licences_school_licence_id,
                this.selectedUserLicenceUserId
            )

            if (!response) return
            this.normalizeSelectedUserRoleDetails()
        },
        toggleSelectedUserRole(roleName) {
            const roleEntry = this.selectedUserLicenceRoleEntries.find((item) => item.name === roleName)
            if (!roleEntry) return

            roleEntry.assigned = !roleEntry.assigned
            if (!roleEntry.assigned) {
                roleEntry.valid_until = null
                roleEntry.is_activated = false
                roleEntry.plan_id = null
                return
            }

            if (!roleEntry.user_licence_required) {
                roleEntry.valid_until = null
                roleEntry.is_activated = true
                roleEntry.plan_id = null
                return
            }

            if (!roleEntry.valid_until) {
                roleEntry.valid_until = this.defaultValidUntil()
            }
            if (!Number.isInteger(Number(roleEntry.plan_id)) || Number(roleEntry.plan_id) <= 0) {
                roleEntry.plan_id = this.getLowestCostPlanId(roleEntry)
            }
        },
        setSelectedUserRoleActivation(roleEntry, value) {
            if (!roleEntry) return
            if (!roleEntry.user_licence_required) return
            if (typeof value !== 'boolean') return
            roleEntry.is_activated = value
        },
        normalizePriceToNumber(rawPrice) {
            const raw = rawPrice == null ? '' : String(rawPrice).trim()
            if (!raw) return 0

            const lower = raw.toLowerCase()
            if (['kostenlos', 'gratis', 'free'].some((token) => lower.includes(token))) {
                return 0
            }

            let normalized = raw.replace(/\s+/g, '')
            if (normalized.includes(',') && normalized.includes('.')) {
                normalized = normalized.replace(/\./g, '').replace(/,/g, '.')
            } else {
                normalized = normalized.replace(/,/g, '.')
            }

            normalized = normalized.replace(/[^0-9.\-]/g, '')
            normalized = normalized.replace(/([0-9])\.(?=-|$)/g, '$1')
            normalized = normalized.replace(/([0-9])-(?=$)/g, '$1')

            const match = normalized.match(/-?\d+(?:\.\d+)?/)
            if (!match) return Number.NaN

            return Number(match[0])
        },
        getLowestCostPlanId(roleEntry) {
            const plans = Array.isArray(roleEntry?.plans) ? roleEntry.plans : []
            const normalizedPlans = plans
                .map((plan) => {
                    const id = Number(plan?.id)
                    if (!Number.isInteger(id) || id <= 0) return null
                    const price = this.normalizePriceToNumber(plan?.price_per_year ?? '')
                    return {
                        id,
                        price: Number.isFinite(price) ? price : Number.POSITIVE_INFINITY,
                    }
                })
                .filter(Boolean)

            if (normalizedPlans.length === 0) return null

            normalizedPlans.sort((a, b) => {
                if (a.price === b.price) return a.id - b.id
                return a.price - b.price
            })

            return normalizedPlans[0].id
        },
        async syncSelectedUserLicenceRolesToUserRoles(selectedRoleNames = null) {
            if (!this.selected_user_licences_school_licence_id || !this.selectedUserLicenceUserId) return false

            const notification = useNotificationStore()
            const normalizedSelectedRoleNames = Array.isArray(selectedRoleNames)
                ? this.sortedRoleNames(selectedRoleNames)
                : this.sortedRoleNames(
                    this.selectedUserLicenceRoleEntries
                        .filter((entry) => !!entry?.assigned)
                        .map((entry) => entry?.name)
                )

            try {
                const response = await axios.put(
                    `/api/admin/school_licences/${this.selected_user_licences_school_licence_id}/users/${this.selectedUserLicenceUserId}/spatie_roles`,
                    { role_names: normalizedSelectedRoleNames }
                )

                this.school_licence_user_role_details = response?.data?.roles || []
                this.school_licence_user_role_details_valid_until = response?.data?.school_licence_valid_until || this.school_licence_user_role_details_valid_until
                this.normalizeSelectedUserRoleDetails()

                const rawRoles = Array.isArray(response?.data?.user?.roles) ? response.data.user.roles : []
                const updatedRoleNames = this.sortedRoleNames(
                    rawRoles.map((role) => {
                        if (typeof role === 'string') return role
                        if (role && typeof role === 'object' && typeof role.name === 'string') return role.name
                        return ''
                    })
                )
                const listItem = (this.school_licence_users || []).find((item) => item.id === this.selectedUserLicenceUserId)
                if (listItem) {
                    listItem.roles = updatedRoleNames
                }

                return true
            } catch (error) {
                notification.notify({
                    status: error?.response?.status,
                    message: error?.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            }
        },
        buildSelectedUserRolePayload(roleEntries = null) {
            const entries = Array.isArray(roleEntries) ? roleEntries : this.selectedUserLicenceRoleEntries

            return entries.map((item) => ({
                name: item.name,
                assigned: !!item.assigned,
                valid_until: item.assigned ? this.normalizeRoleValidUntilForApi(item.valid_until) : null,
                is_activated: item.assigned ? !!item.is_activated : false,
                plan_id: item.assigned && Number.isInteger(Number(item.plan_id)) && Number(item.plan_id) > 0 ? Number(item.plan_id) : null,
            }))
        },
        rolePlanSelectItems(roleEntry) {
            const plans = Array.isArray(roleEntry?.plans) ? roleEntry.plans : []
            return plans
                .map((plan) => {
                    const id = Number(plan?.id)
                    if (!Number.isInteger(id) || id <= 0) return null
                    const text = String(plan?.text ?? '').trim() || `Plan ${id}`
                    const price = String(plan?.price_per_year ?? '').trim()
                    return {
                        value: id,
                        title: price ? `${text} (${price})` : text,
                    }
                })
                .filter(Boolean)
        },
        normalizeSelectedUserRoleDetails() {
            const details = Array.isArray(this.school_licence_user_role_details) ? this.school_licence_user_role_details : []
            const actualUserRoles = new Set(
                this.sortedRoleNames(this.selectedUserLicenceUser?.roles || [])
            )
            this.school_licence_user_role_details = details.map((entry) => {
                const planId = Number(entry?.plan_id)
                const roleName = String(entry?.name ?? '').trim()
                const userLicenceRequired = this.toBool(entry?.user_licence_required, true)
                const isUserRoleAssigned = actualUserRoles.has(roleName) && !!entry?.is_user_role_assigned
                return {
                    name: roleName,
                    assigned: userLicenceRequired ? (isUserRoleAssigned && !!entry?.assigned) : isUserRoleAssigned,
                    is_user_role_assigned: isUserRoleAssigned,
                    user_licence_required: userLicenceRequired,
                    valid_until:
                        userLicenceRequired
                            ? (entry?.valid_until instanceof Date
                                ? this.toDateString(entry.valid_until)
                                : (entry?.valid_until ? String(entry.valid_until).slice(0, 10) : null))
                            : null,
                    is_activated: userLicenceRequired ? (isUserRoleAssigned && !!entry?.is_activated) : true,
                    plan_id: userLicenceRequired && Number.isInteger(planId) && planId > 0 ? planId : null,
                    plans: userLicenceRequired && Array.isArray(entry?.plans) ? entry.plans : [],
                }
            })
        },
        normalizeRoleValidUntilForApi(value) {
            if (!value) return null
            if (value instanceof Date) return this.toDateString(value)
            return String(value).slice(0, 10) || null
        },
        isDateValueActive(value) {
            if (!value) return true
            const normalized = value instanceof Date ? this.toDateString(value) : String(value).slice(0, 10)
            return normalized >= this.localDateKey()
        },
        isSelectedUserRoleAssignmentActive(roleEntry) {
            if (!roleEntry?.assigned) return false
            if (!roleEntry?.user_licence_required) return true
            if (!roleEntry?.is_activated) return false
            if (!this.isDateValueActive(roleEntry?.valid_until)) return false
            if (!this.isSchoolLicenceNotNeeded(this.selectedUserLicencesSource) && !this.isLicenceActive(this.selectedUserLicencesSource)) {
                return false
            }
            return true
        },
        selectedUserRoleStatusLabel(roleEntry) {
            if (!roleEntry?.assigned) return 'Nicht zugewiesen'
            if (!roleEntry?.user_licence_required) return 'Keine Aktivierung nötig (Userlizenz = NEIN)'
            if (!roleEntry?.is_activated) return 'Deaktiviert'
            if (!this.isSchoolLicenceNotNeeded(this.selectedUserLicencesSource) && !this.isLicenceActive(this.selectedUserLicencesSource)) {
                return 'Schullizenz abgelaufen'
            }
            if (!this.isDateValueActive(roleEntry?.valid_until)) return 'Abgelaufen'
            return 'Aktiv'
        },
        closeSelectedUserLicenceCard() {
            this.selected_user_licence_users = []
            this.school_licence_user_role_details = []
            this.school_licence_user_role_details_valid_until = this.selectedUserLicencesSource?.valid_until || null
        },
        async saveSelectedUserLicenceRoles() {
            if (!this.selected_user_licences_school_licence_id || !this.selectedUserLicenceUserId) return

            const selectedUserId = this.selectedUserLicenceUserId
            const rolesPayload = this.buildSelectedUserRolePayload()
            const selectedRoleNames = rolesPayload
                .filter((item) => item.assigned)
                .map((item) => item.name)
            if (!(await this.syncSelectedUserLicenceRolesToUserRoles(selectedRoleNames))) return

            const response = await this.schoolStore.saveSchoolLicenceUserRoles(
                this.selected_user_licences_school_licence_id,
                this.selectedUserLicenceUserId,
                rolesPayload
            )

            if (!response) return
            this.normalizeSelectedUserRoleDetails()
            if (this.adminStore?.loadConfig) {
                await this.adminStore.loadConfig()
            }

            const currentPage = Number(this.school_licence_users_meta?.current_page || 1)
            await this.loadSchoolLicenceUsers(currentPage)
            if (selectedUserId) {
                this.closeSelectedUserLicenceCard()
            }
        },
        async searchSchoolLicenceUsers() {
            await this.loadSchoolLicenceUsers(1)
        },
        async firstSchoolLicenceUsersPage() {
            await this.loadSchoolLicenceUsers(1)
        },
        async prevSchoolLicenceUsersPage() {
            const currentPage = Number(this.school_licence_users_meta?.current_page || 1)
            await this.loadSchoolLicenceUsers(Math.max(1, currentPage - 1))
        },
        async nextSchoolLicenceUsersPage() {
            const currentPage = Number(this.school_licence_users_meta?.current_page || 1)
            const lastPage = Number(this.school_licence_users_meta?.last_page || 1)
            await this.loadSchoolLicenceUsers(Math.min(lastPage, currentPage + 1))
        },
        async lastSchoolLicenceUsersPage() {
            const lastPage = Number(this.school_licence_users_meta?.last_page || 1)
            await this.loadSchoolLicenceUsers(Math.max(1, lastPage))
        },
        syncInteractionLockAction() {
            if (this.isInteractionLocked) {
                this.action = this.model_lock_action
                return
            }
            if (this.selectedSchoolId) {
                this.action = this.selection_lock_action
                return
            }
            if ([this.model_lock_action, this.selection_lock_action].includes(this.action)) {
                this.action = ''
            }
        },
        normalizeLicenceModel(licenceModel) {
            const fallback = {
                school_licence_required: true,
                affected_roles: [],
                user_licence_required_by_role: {},
                user_licence_plans_by_role: {},
            }

            if (typeof licenceModel === 'string') {
                try {
                    licenceModel = JSON.parse(licenceModel)
                } catch (_) {
                    return fallback
                }
            }

            if (!licenceModel || typeof licenceModel !== 'object') return fallback

            const affectedRolesRaw = Array.isArray(licenceModel.affected_roles) ? licenceModel.affected_roles : []
            const affected_roles = []
            for (const roleName of affectedRolesRaw) {
                if (typeof roleName !== 'string') continue
                const trimmed = roleName.trim()
                if (!trimmed || affected_roles.includes(trimmed)) continue
                affected_roles.push(trimmed)
            }

            const rawMap =
                licenceModel.user_licence_required_by_role && typeof licenceModel.user_licence_required_by_role === 'object'
                    ? licenceModel.user_licence_required_by_role
                    : {}
            const user_licence_required_by_role = {}
            for (const roleName of affected_roles) {
                user_licence_required_by_role[roleName] = this.toBool(rawMap[roleName], false)
            }

            const rawPlansByRole =
                licenceModel.user_licence_plans_by_role && typeof licenceModel.user_licence_plans_by_role === 'object'
                    ? licenceModel.user_licence_plans_by_role
                    : {}
            const user_licence_plans_by_role = {}
            for (const roleName of affected_roles) {
                const rawPlans = Array.isArray(rawPlansByRole[roleName]) ? rawPlansByRole[roleName] : []
                user_licence_plans_by_role[roleName] = rawPlans
                    .filter((plan) => plan && typeof plan === 'object')
                    .map((plan) => ({
                        ...(Number.isInteger(Number(plan.id)) && Number(plan.id) > 0 ? { id: Number(plan.id) } : {}),
                        text: typeof plan.text === 'string' ? plan.text : (plan.text ?? '').toString(),
                        price_per_year:
                            typeof plan.price_per_year === 'string' ? plan.price_per_year : (plan.price_per_year ?? '').toString(),
                    }))
            }

            return {
                school_licence_required: this.toBool(licenceModel.school_licence_required, true),
                affected_roles,
                user_licence_required_by_role,
                user_licence_plans_by_role,
            }
        },
        toBool(value, fallback = false) {
            if (typeof value === 'boolean') return value
            if (typeof value === 'number') return value === 1
            if (typeof value === 'string') {
                const normalized = value.trim().toLowerCase()
                if (['1', 'true', 'yes', 'ja'].includes(normalized)) return true
                if (['0', 'false', 'no', 'nein'].includes(normalized)) return false
            }
            return fallback
        },
    },
}
</script>

<style scoped src="../../../../../css/admin-index-page.css"></style>
<style scoped src="../../../../../css/admin-crud-panel.css"></style>
<style scoped src="../../../../../css/admin-licence-cards.css"></style>
<style scoped>
.assignment-schools-list :deep(.v-list-item__content),
.assignment-licence-list :deep(.v-list-item__content) {
    overflow: visible;
}

.user-licence-user-dialog-solid {
    background: rgb(var(--v-theme-surface));
    border: 1px solid rgba(16, 38, 58, 0.12);
    box-shadow: 0 16px 40px rgba(16, 38, 58, 0.16);
}
</style>
