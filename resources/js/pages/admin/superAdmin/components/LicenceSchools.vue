<template>
    <v-col cols="12" :xl="mainColXl">
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
                    <v-btn color="primary" slim flat tile class="text-caption" @click="clearSelection" :disabled="isInteractionLocked" v-if="selected_schools.length >= 1">
                        Auswahl aufheben
                    </v-btn>
                </div>

                <v-list
                    dense
                    variant="flat"
                    class="assignment-schools-list">
                    <v-list-item
                        v-for="item in schools"
                        :key="item.id"
                        class="assignment-schools-item">
                        <template #title>
                            <article class="licence-card">
                                <header class="licence-card__header">
                                    <div class="licence-card__title-wrap">
                                        <div class="licence-card__title">{{ item.long_name }}</div>
                                    </div>
                                    <v-btn
                                        size="small"
                                        variant="tonal"
                                        color="success"
                                        icon="mdi-plus"
                                        :disabled="isInteractionLocked"
                                        @click.stop="openEditDateDialog(item)" />
                                </header>

                                <div class="licence-card__roles-block">
                                    <div class="licence-card__roles-list assignment-school-licence-list">
                                        <template v-if="item.licences && item.licences.length >= 1">
                                            <div
                                                v-for="licence in sortedLicences(item.licences)"
                                                :key="`licence-assignment-${item.id}-${licence.id}`"
                                                class="assignment-school-licence-row">
                                                <div class="assignment-school-licence-row__name">
                                                    {{ licence.name }}
                                                    <div v-if="licence.school_licence_enabled" class="assignment-school-licence-row__valid-until" :class="{ 'is-expired': isValidUntilExpired(licence.valid_until) }">
                                                        <v-icon
                                                            size="13"
                                                            :icon="isValidUntilExpired(licence.valid_until) ? 'mdi-close-circle' : 'mdi-check-circle'"
                                                            :color="isValidUntilExpired(licence.valid_until) ? 'error' : 'success'"
                                                            class="mr-1" />
                                                        Schul-Lizenz gültig bis {{ formatValidUntil(licence.valid_until) }}
                                                    </div>
                                                    <div v-if="licence.admin_licence_enabled" class="assignment-school-licence-row__counter">
                                                        <v-icon size="13" icon="mdi-shield-crown-outline" :color="(licence.admin_licence_expired_count || 0) === 0 ? 'success' : undefined" class="mr-1" />
                                                        Admin-Lizenzen: {{ licence.admin_licence_count }}
                                                        (gültig: {{ licence.admin_licence_active_count || 0 }}, <span
                                                            class="assignment-school-licence-row__counter-expired"
                                                            :class="{ 'is-highlighted': (licence.admin_licence_expired_count || 0) > 0 }">abgelaufen: {{ licence.admin_licence_expired_count || 0 }}</span>)
                                                    </div>
                                                    <div v-if="licence.user_licence_enabled" class="assignment-school-licence-row__counter">
                                                        <v-icon size="13" icon="mdi-account-multiple" class="mr-1" />
                                                        Benutzer-Lizenzen: {{ licence.user_licence_count }}
                                                    </div>
                                                </div>
                                                <div class="assignment-school-licence-row__right">
                                                    <v-btn
                                                        v-if="licence.school_licence_enabled"
                                                        size="small"
                                                        variant="tonal"
                                                        color="primary"
                                                        prepend-icon="mdi-domain"
                                                        class="assignment-school-licence-row__action"
                                                        @click.stop="openEditLicenceDialog(item, licence, 'school')">
                                                        Schullizenz
                                                    </v-btn>
                                                    <v-btn
                                                        v-if="licence.admin_licence_enabled"
                                                        size="small"
                                                        variant="tonal"
                                                        color="warning"
                                                        prepend-icon="mdi-shield-crown-outline"
                                                        class="assignment-school-licence-row__action"
                                                        @click.stop="openEditLicenceDialog(item, licence, 'admin')">
                                                        Admin-Lizenzen
                                                    </v-btn>
                                                    <v-btn
                                                        v-if="licence.user_licence_enabled"
                                                        size="small"
                                                        variant="tonal"
                                                        color="success"
                                                        prepend-icon="mdi-account-multiple"
                                                        class="assignment-school-licence-row__action"
                                                        @click.stop="openEditLicenceDialog(item, licence, 'user')">
                                                        Benutzer-Lizenzen
                                                    </v-btn>
                                                </div>
                                            </div>
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
                                    <div class="d-flex flex-row flex-wrap align-center ga-2">
                                        <span class="text-caption text-medium-emphasis">Gesamtkosten:</span>
                                        <span class="text-caption font-weight-medium">{{ formatPrice(userLicenceListPriceTotal(item)) }}</span>
                                        <template v-if="userLicenceListStorageTotalGb(item) !== null">
                                            <span class="text-caption text-medium-emphasis">Gesamtspeicher:</span>
                                            <span class="text-caption font-weight-medium">{{ formatStorage(userLicenceListStorageTotalGb(item)) }}</span>
                                        </template>
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
            <v-card-title>{{ edit_school_licence ? 'Lizenzdatum ändern' : 'Lizenz hinzufügen' }}</v-card-title>
            <v-card-text>
                <div class="text-body-2 mb-3" v-if="edit_date_school">
                    {{ edit_date_school.long_name }}
                </div>
                <div class="text-body-2 mb-3" v-if="edit_school_licence">
                    {{ edit_school_licence.name }}
                </div>
                <v-select
                    v-else
                    v-model="edit_date_licence_id"
                    :items="availableEditDateLicences"
                    item-title="name"
                    item-value="id"
                    label="Lizenz"
                    class="mb-3"
                    hide-details="auto" />
                <div v-if="!edit_school_licence && availableEditDateLicences.length === 0" class="text-caption text-medium-emphasis mb-3">
                    Keine weitere Lizenz verfügbar.
                </div>
                <template v-if="edit_school_licence">
                    <v-checkbox v-model="edit_no_date" label="Kein Ablaufdatum" hide-details class="mb-2" />
                    <v-date-input v-model="edit_valid_until" label="Gültig bis" :disabled="edit_no_date" class="flex-grow-1" />
                </template>
            </v-card-text>
            <v-card-actions class="d-flex justify-space-between">
                <v-btn color="warning" variant="flat" @click="closeEditDateDialog">Abbrechen</v-btn>
                <v-btn color="success" variant="flat" :disabled="!edit_school_licence && !edit_date_licence_id" @click="saveEditedDate">Speichern</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

    <v-dialog v-model="edit_licence_dialog" persistent max-width="640">
        <v-card class="crud-dialog-card user-licence-user-dialog-solid">
            <div class="crud-dialog-head">
                <div>
                    <div class="admin-card-eyebrow">Lizenz bearbeiten</div>
                    <div class="admin-card-title" style="margin-top: 4px">
                        {{ edit_licence_school ? edit_licence_school.long_name : '' }}
                        <span v-if="edit_licence_item"> – {{ edit_licence_item.name }}</span>
                    </div>
                </div>
                <v-btn v-if="!editLicenceCloseLocked" icon="mdi-close" variant="text" rounded="lg" @click="closeEditLicenceDialog" />
            </div>

            <v-tabs v-model="edit_licence_tab" color="primary" class="edit-licence-tabs">
                <v-tab
                    v-if="edit_licence_item && edit_licence_item.school_licence_enabled"
                    value="school"
                    :disabled="isEditLicenceTabDisabled('school')"
                    prepend-icon="mdi-domain">
                    Schul-Lizenz
                </v-tab>
                <v-tab
                    v-if="edit_licence_item && edit_licence_item.admin_licence_enabled"
                    value="admin"
                    :disabled="isEditLicenceTabDisabled('admin')"
                    prepend-icon="mdi-shield-crown-outline">
                    Admin-Lizenzen
                </v-tab>
                <v-tab
                    v-if="edit_licence_item && edit_licence_item.user_licence_enabled"
                    value="user"
                    :disabled="isEditLicenceTabDisabled('user')"
                    prepend-icon="mdi-account-multiple">
                    Benutzer-Lizenzen
                </v-tab>
            </v-tabs>

            <v-divider />

            <v-card-text class="crud-dialog-body">
                <v-tabs-window v-model="edit_licence_tab">
                    <v-tabs-window-item value="school">
                        <div class="edit-licence-section-title">Laufzeit</div>
                        <div class="edit-licence-price-grid mb-4">
                            <div class="edit-licence-price-row" v-if="edit_licence_item && edit_licence_item.start_day_month">
                                <span class="edit-licence-price-label">Laufzeit</span>
                                <span class="edit-licence-price-value">
                                    {{ edit_licence_item.start_day_month }} – {{ edit_licence_item.end_day_month || dayBefore(edit_licence_item.start_day_month) }}
                                </span>
                            </div>
                            <div class="edit-licence-price-row">
                                <span class="edit-licence-price-label">Gültig bis</span>
                                <span class="d-flex align-center" style="gap: 8px;">
                                    <span class="edit-licence-price-value d-flex align-center" style="gap: 10px;">
                                        <v-icon
                                            size="13"
                                            :icon="isValidUntilExpired(edit_licence_item && edit_licence_item.valid_until) ? 'mdi-close-circle' : 'mdi-check-circle'"
                                            :color="isValidUntilExpired(edit_licence_item && edit_licence_item.valid_until) ? 'error' : 'success'" />
                                        {{ formatValidUntil(edit_licence_item && edit_licence_item.valid_until) }}
                                    </span>
                                    <v-menu>
                                        <template #activator="{ props }">
                                            <v-btn v-bind="props" size="x-small" variant="text" icon="mdi-pencil" density="compact" />
                                        </template>
                                        <v-list density="compact" min-width="240">
                                            <v-list-item
                                                prepend-icon="mdi-link-off"
                                                title="Schullizenz entfernen"
                                                base-color="error"
                                                @click="deleteLicenceFromDialog" />
                                            <v-list-item
                                                prepend-icon="mdi-calendar-end"
                                                :title="`bis Jahresende (${nextYearEndLabel(effectiveEndDayMonth)})`"
                                                @click="setValidUntilNextYearEnd" />
                                            <v-list-item
                                                prepend-icon="mdi-infinity"
                                                title="unendlich"
                                                @click="setValidUntilUnlimited" />
                                            <v-list-item
                                                prepend-icon="mdi-calendar-plus"
                                                title="ein Jahr verlängern"
                                                @click="extendValidUntilOneYear" />
                                            <v-list-item
                                                prepend-icon="mdi-calendar-clock"
                                                :title="`Teillizenz (${nextYearEndLabel(effectiveEndDayMonth)}, ${teillizenzPreisLabel})`"
                                                @click="setValidUntilNextYearEnd" />
                                        </v-list>
                                    </v-menu>
                                </span>
                            </div>
                            <div class="edit-licence-price-row">
                                <span class="edit-licence-price-label">Gesamtkosten</span>
                                <span class="edit-licence-price-value">{{ formatPrice(billingTotal) }}</span>
                            </div>
                            <div
                                v-if="schoolStorageTotalGb !== null"
                                class="edit-licence-price-row">
                                <span class="edit-licence-price-label">Gesamtspeicher</span>
                                <span class="edit-licence-price-value">{{ formatStorage(schoolStorageTotalGb) }}</span>
                            </div>
                        </div>

                        <div class="edit-licence-section-title">Preis-Informationen</div>
                        <div class="edit-licence-price-grid mb-4">
                            <div class="edit-licence-price-row">
                                <span class="edit-licence-price-label">Basis-Tarif / Jahr</span>
                                <span class="edit-licence-price-value">{{ formatPrice(edit_licence_item && edit_licence_item.school_price_per_year) }}</span>
                            </div>
                            <div class="edit-licence-price-row" v-if="edit_licence_item && edit_licence_item.school_included_storage_gb != null">
                                <span class="edit-licence-price-label">Inkl. Speicher</span>
                                <span class="edit-licence-price-value">{{ formatStorage(edit_licence_item.school_included_storage_gb) }}</span>
                            </div>
                            <div class="edit-licence-price-row" v-if="edit_licence_item && (edit_licence_item.school_extra_storage_step_gb != null || edit_licence_item.school_extra_storage_step_price != null)">
                                <span class="edit-licence-price-label">Zusatz-Speicher</span>
                                <span class="edit-licence-price-value">{{ formatPrice(edit_licence_item.school_extra_storage_step_price) }} / {{ formatStorage(edit_licence_item.school_extra_storage_step_gb) }}</span>
                            </div>
                        </div>

                        <div class="d-flex align-center justify-space-between mb-2">
                            <div class="edit-licence-section-title mb-0">Abrechnung dieser Schule</div>
                            <v-btn
                                v-if="!edit_school_billing_editing"
                                size="x-small"
                                variant="tonal"
                                color="primary"
                                icon="mdi-pencil"
                                @click="edit_school_billing_editing = true" />
                        </div>

                        <div class="edit-licence-price-grid" v-if="!edit_school_billing_editing">
                            <div class="edit-licence-price-row">
                                <span class="edit-licence-price-label">Verrechneter Preis / Jahr</span>
                                <span class="edit-licence-price-value">
                                    {{ overridePriceLabel(edit_school_charged_price, edit_licence_item?.school_price_per_year, 'Basis-Tarif') }}
                                </span>
                            </div>
                            <template v-if="edit_licence_item && (edit_licence_item.school_extra_storage_step_gb != null || edit_licence_item.school_extra_storage_step_price != null)">
                                <div class="edit-licence-price-row">
                                    <span class="edit-licence-price-label">Zusatz-Speicher Einheiten</span>
                                    <span class="edit-licence-price-value">{{ edit_school_extra_storage_units != null ? edit_school_extra_storage_units : '–' }}</span>
                                </div>
                                <div class="edit-licence-price-row">
                                    <span class="edit-licence-price-label">Preis je Einheit</span>
                                    <span class="edit-licence-price-value">
                                        {{ overridePriceLabel(edit_school_extra_storage_unit_price, edit_licence_item?.school_extra_storage_step_price, 'Basis-Tarif') }}
                                    </span>
                                </div>
                            </template>
                            <div class="edit-licence-price-row edit-licence-price-row--sum">
                                <span class="edit-licence-price-label">Gesamt / Jahr</span>
                                <span class="edit-licence-price-value">{{ formatPrice(billingTotal) }}</span>
                            </div>
                        </div>

                        <template v-if="edit_school_billing_editing">
                            <v-row dense>
                                <v-col cols="12">
                                    <v-text-field
                                        v-model="edit_school_charged_price"
                                        label="Verrechneter Preis / Jahr (€)"
                                        inputmode="numeric"
                                        clearable
                                        :placeholder="edit_licence_item ? editablePriceInputValue(edit_licence_item.school_price_per_year) : ''"
                                        hint="Leer lassen = Basis-Tarif, 0 = EUR 0"
                                        persistent-hint />
                                </v-col>
                                <template v-if="edit_licence_item && (edit_licence_item.school_extra_storage_step_gb != null || edit_licence_item.school_extra_storage_step_price != null)">
                                    <v-col cols="6">
                                        <v-text-field
                                            v-model="edit_school_extra_storage_units"
                                            label="Zusatz-Speicher Einheiten"
                                            inputmode="numeric"
                                            clearable
                                            :hint="edit_licence_item.school_extra_storage_step_gb ? `1 Einheit = ${edit_licence_item.school_extra_storage_step_gb} GB` : ''"
                                            persistent-hint />
                                    </v-col>
                                    <v-col cols="6">
                                        <v-text-field
                                            v-model="edit_school_extra_storage_unit_price"
                                            label="Preis je Einheit (€)"
                                            inputmode="numeric"
                                            clearable
                                            :placeholder="edit_licence_item ? editablePriceInputValue(edit_licence_item.school_extra_storage_step_price) : ''"
                                            hint="Leer lassen = Standardpreis, 0 = EUR 0"
                                            persistent-hint />
                                    </v-col>
                                </template>
                            </v-row>
                            <div class="d-flex justify-space-between mt-4">
                                <v-btn color="warning" variant="text" rounded="lg" @click="cancelSchoolBillingEdit">Abbrechen</v-btn>
                                <v-btn color="success" variant="flat" rounded="lg" @click="saveSchoolLicenceSchool">Speichern</v-btn>
                            </div>
                        </template>
                    </v-tabs-window-item>
                    <v-tabs-window-item value="admin">
                        <div class="edit-licence-section-title">Preis-Informationen</div>
                        <div class="edit-licence-price-grid mb-4">
                            <div class="edit-licence-price-row">
                                <span class="edit-licence-price-label">Basis-Tarif / Jahr</span>
                                <span class="edit-licence-price-value">{{ formatPrice(edit_licence_item && edit_licence_item.admin_price_per_year) }}</span>
                            </div>
                            <div class="edit-licence-price-row" v-if="edit_licence_item && edit_licence_item.admin_included_storage_gb != null">
                                <span class="edit-licence-price-label">Inkl. Speicher</span>
                                <span class="edit-licence-price-value">{{ formatStorage(edit_licence_item.admin_included_storage_gb) }}</span>
                            </div>
                            <div class="edit-licence-price-row" v-if="edit_licence_item && (edit_licence_item.admin_extra_storage_step_gb != null || edit_licence_item.admin_extra_storage_step_price != null)">
                                <span class="edit-licence-price-label">Zusatz-Speicher</span>
                                <span class="edit-licence-price-value">{{ formatPrice(edit_licence_item.admin_extra_storage_step_price) }} / {{ formatStorage(edit_licence_item.admin_extra_storage_step_gb) }}</span>
                            </div>
                        </div>

                        <div class="d-flex align-center justify-space-between mb-2">
                            <div class="edit-licence-section-title mb-0">Benutzer</div>
                            <v-btn v-if="!edit_admin_user_edit_id" size="small" variant="tonal" color="success" icon="mdi-plus" @click="openAdminAddMode" />
                        </div>

                        <div v-if="visibleAdminUsers.length" class="mb-2">
                            <div
                                v-for="user in visibleAdminUsers"
                                :key="`admin-user-${user.id}`"
                                class="admin-user-row"
                                :class="{ 'is-editing': edit_admin_user_edit_id === user.id }">
                                <div class="admin-user-row__main">
                                    <div class="admin-user-row__name">
                                        <span class="admin-user-row__title">
                                            <v-icon
                                                v-if="adminUserHasValidLicence(user)"
                                                size="14"
                                                color="success"
                                                icon="mdi-check-circle"
                                                class="admin-user-row__status-icon" />
                                            <span>{{ user.last_name }} {{ user.first_name }}</span>
                                        </span>
                                        <span class="admin-user-row__email">{{ user.email }}</span>
                                        <span class="admin-user-row__meta">{{ adminUserLicenceRuntimeLabel(user) }}</span>
                                        <span class="admin-user-row__meta" v-if="adminUserBillingTotalForUser(user) !== null || adminUserStorageTotalGbForUser(user) !== null">
                                            Gesamt / Jahr: {{ formatPrice(adminUserBillingTotalForUser(user)) }}
                                            <template v-if="adminUserStorageTotalGbForUser(user) !== null">
                                                &nbsp;Summe Speicher: {{ formatStorage(adminUserStorageTotalGbForUser(user)) }}
                                            </template>
                                        </span>
                                    </div>
                                    <div class="admin-user-row__actions">
                                        <span v-if="edit_admin_user_edit_id === user.id" class="admin-user-row__editing-hint">Benutzerlizenz wird bearbeitet</span>
                                        <v-btn
                                            v-if="adminUserIsAssigned(user)"
                                            size="x-small"
                                            variant="text"
                                            :color="edit_admin_user_edit_id === user.id ? 'primary' : 'default'"
                                            icon="mdi-pencil"
                                            :style="{ visibility: edit_admin_user_edit_id ? 'hidden' : 'visible' }"
                                            @click="toggleAdminUserEdit(user)" />
                                    </div>
                                </div>

                                <div v-if="edit_admin_user_edit_id === user.id" class="admin-user-edit">
                                    <div v-if="!edit_admin_user_price_editing" class="d-flex justify-end mb-2">
                                        <v-btn color="warning" variant="text" size="small" rounded="lg" prepend-icon="mdi-arrow-left" @click="closeAdminUserEdit">Zurück</v-btn>
                                    </div>
                                    <div class="edit-licence-price-grid mb-2">
                                        <div class="edit-licence-price-row">
                                            <span class="edit-licence-price-label">Gültig bis</span>
                                            <span class="d-flex align-center" style="gap: 8px;">
                                                <span class="edit-licence-price-value d-flex align-center" style="gap: 10px;">
                                                    <v-icon
                                                        size="13"
                                                        :icon="isValidUntilExpired(edit_admin_user_valid_until) ? 'mdi-close-circle' : 'mdi-check-circle'"
                                                        :color="isValidUntilExpired(edit_admin_user_valid_until) ? 'error' : 'success'" />
                                                    {{ formatValidUntil(edit_admin_user_valid_until) }}
                                                </span>
                                                <v-menu>
                                                    <template #activator="{ props }">
                                                        <v-btn v-bind="props" size="x-small" variant="text" icon="mdi-pencil" density="compact" />
                                                    </template>
                                                    <v-list density="compact" min-width="220">
                                                        <v-list-item
                                                            prepend-icon="mdi-link-off"
                                                            title="Lizenz entfernen"
                                                            base-color="error"
                                                            @click="removeAdminUserLicence(user)" />
                                                        <v-list-item
                                                            prepend-icon="mdi-calendar-end"
                                                            :title="`bis Jahresende (${nextYearEndLabel(effectiveEndDayMonth)})`"
                                                            @click="setAdminUserValidUntil(user, nextYearEndDate(effectiveEndDayMonth))" />
                                                        <v-list-item
                                                            prepend-icon="mdi-infinity"
                                                            title="unendlich"
                                                            @click="setAdminUserValidUntil(user, null)" />
                                                        <v-list-item
                                                            prepend-icon="mdi-calendar-plus"
                                                            title="ein Jahr verlängern"
                                                            @click="extendAdminUserValidUntilOneYear(user)" />
                                                        <v-list-item
                                                            prepend-icon="mdi-calendar-clock"
                                                            :title="`Teillizenz (${nextYearEndLabel(effectiveEndDayMonth)}, ${adminTeillizenzPreisLabel})`"
                                                            @click="applyAdminUserTeillizenz(user)" />
                                                    </v-list>
                                                </v-menu>
                                            </span>
                                        </div>

                                        <div class="edit-licence-price-row">
                                            <span class="edit-licence-price-label">Verrechneter Preis / Jahr</span>
                                            <span class="d-flex align-center" style="gap: 8px;">
                                                <span class="edit-licence-price-value">
                                                    {{ overridePriceLabel(edit_admin_user_charged_price, effectiveAdminBasePrice(), adminBillingDefaultLabel()) }}
                                                </span>
                                                <v-btn
                                                    v-if="!edit_admin_user_price_editing"
                                                    size="x-small"
                                                    variant="text"
                                                    icon="mdi-pencil"
                                                    density="compact"
                                                    @click="edit_admin_user_price_editing = true" />
                                            </span>
                                        </div>
                                        <template v-if="edit_licence_item && (edit_licence_item.admin_extra_storage_step_gb != null || edit_licence_item.admin_extra_storage_step_price != null)">
                                            <div class="edit-licence-price-row">
                                                <span class="edit-licence-price-label">Zusatz-Speicher Einheiten</span>
                                                <span class="edit-licence-price-value">
                                                    {{ overrideCountLabel(edit_admin_user_extra_storage_units, edit_licence_item?.admin_extra_storage_units, 'Tarif dieser Schule') }}
                                                </span>
                                            </div>
                                            <div class="edit-licence-price-row">
                                                <span class="edit-licence-price-label">Preis je Einheit</span>
                                                <span class="edit-licence-price-value">
                                                    {{ overridePriceLabel(edit_admin_user_extra_storage_unit_price, effectiveAdminUserExtraStorageUnitPrice(), adminBillingDefaultLabel()) }}
                                                </span>
                                            </div>
                                            <div class="edit-licence-price-row">
                                                <span class="edit-licence-price-label">Zusatz-Speicher gesamt</span>
                                                <span class="edit-licence-price-value">{{ formatPrice(adminUserExtraStorageTotal()) }}</span>
                                            </div>
                                            <div class="edit-licence-price-row">
                                                <span class="edit-licence-price-label">Summe Speicher</span>
                                                <span class="edit-licence-price-value">{{ formatStorage(adminUserStorageTotalGb()) }}</span>
                                            </div>
                                        </template>
                                        <div class="edit-licence-price-row edit-licence-price-row--sum">
                                            <span class="edit-licence-price-label">Gesamt / Jahr</span>
                                            <span class="edit-licence-price-value">{{ formatPrice(adminUserBillingTotal()) }}</span>
                                        </div>
                                    </div>

                                    <template v-if="edit_admin_user_price_editing">
                                        <v-text-field
                                            v-model="edit_admin_user_charged_price_draft"
                                            label="Verrechneter Preis / Jahr (€)"
                                            inputmode="numeric"
                                            density="compact"
                                            variant="outlined"
                                            clearable
                                            hint="Leer lassen = Standard-Tarif, 0 = EUR 0"
                                            persistent-hint
                                            class="mb-2" />
                                        <v-row
                                            v-if="edit_licence_item && (edit_licence_item.admin_extra_storage_step_gb != null || edit_licence_item.admin_extra_storage_step_price != null)"
                                            dense
                                            class="mb-1">
                                            <v-col cols="6">
                                                <v-text-field
                                                    v-model="edit_admin_user_extra_storage_units"
                                                    label="Zusatz-Speicher Einheiten"
                                                    inputmode="numeric"
                                                    density="compact"
                                                    variant="outlined"
                                                    clearable
                                                    :placeholder="edit_licence_item ? String(edit_licence_item.admin_extra_storage_units ?? '') : ''"
                                                    :hint="edit_licence_item?.admin_extra_storage_step_gb ? `1 Einheit = ${edit_licence_item.admin_extra_storage_step_gb} GB` : 'Leer lassen = Tarif dieser Schule'"
                                                    persistent-hint />
                                            </v-col>
                                            <v-col cols="6">
                                                <v-text-field
                                                    v-model="edit_admin_user_extra_storage_unit_price"
                                                    label="Preis je Einheit (€)"
                                                    inputmode="numeric"
                                                    density="compact"
                                                    variant="outlined"
                                                    clearable
                                                    :placeholder="effectiveAdminExtraStorageUnitPrice() != null ? editablePriceInputValue(effectiveAdminExtraStorageUnitPrice()) : ''"
                                                    hint="Leer lassen = Standardpreis, 0 = EUR 0"
                                                    persistent-hint />
                                            </v-col>
                                        </v-row>
                                        <div class="d-flex justify-space-between">
                                            <v-btn color="warning" variant="text" size="small" rounded="lg" @click="edit_admin_user_price_editing = false">Abbrechen</v-btn>
                                            <v-btn color="success" variant="flat" size="small" rounded="lg" @click="saveAdminUserBilling(user)">Speichern</v-btn>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-caption text-medium-emphasis mb-2">Keine Benutzer gefunden.</div>

                        <template v-if="edit_admin_add_mode">
                            <v-divider class="mb-3" />
                            <div class="edit-licence-section-title mb-2">Benutzer hinzufügen</div>
                            <div class="d-flex align-center ga-2 mb-2">
                                <v-text-field
                                    v-model="edit_admin_add_search"
                                    placeholder="Nachname..."
                                    density="compact"
                                    variant="outlined"
                                    clearable
                                    hide-details
                                    prepend-inner-icon="mdi-magnify"
                                    class="flex-grow-1"
                                    @keyup.enter="searchAdminUsersToAdd"
                                    @click:clear="edit_admin_add_results = []" />
                                <v-btn size="small" variant="tonal" color="primary" icon="mdi-magnify" @click="searchAdminUsersToAdd" />
                                <v-btn size="small" variant="text" color="warning" icon="mdi-close" @click="closeAdminAddMode" />
                            </div>
                            <v-list v-if="edit_admin_add_results.length" density="compact">
                                <v-list-item
                                    v-for="user in edit_admin_add_results"
                                    :key="`admin-add-${user.id}`"
                                    :subtitle="user.email">
                                    <template #title>
                                        <span>{{ user.last_name }} {{ user.first_name }}</span>
                                    </template>
                                    <template #append>
                                        <v-btn
                                            size="x-small"
                                            variant="tonal"
                                            color="success"
                                            icon="mdi-plus"
                                            @click="assignAdminUser(user)" />
                                    </template>
                                </v-list-item>
                            </v-list>
                            <div v-else-if="edit_admin_add_search" class="text-caption text-medium-emphasis">Keine Treffer.</div>
                        </template>
                    </v-tabs-window-item>
                    <v-tabs-window-item value="user">
                        <div class="edit-licence-section-title">Preis-Informationen</div>
                        <div class="edit-licence-price-grid">
                            <div class="edit-licence-price-row">
                                <span class="edit-licence-price-label">Basis-Tarif / Jahr</span>
                                <span class="edit-licence-price-value">{{ formatPrice(edit_licence_item && edit_licence_item.user_price_per_year) }}</span>
                            </div>
                            <div class="edit-licence-price-row" v-if="edit_licence_item && edit_licence_item.user_included_storage_gb != null">
                                <span class="edit-licence-price-label">Inkl. Speicher</span>
                                <span class="edit-licence-price-value">{{ formatStorage(edit_licence_item.user_included_storage_gb) }}</span>
                            </div>
                            <div class="edit-licence-price-row" v-if="edit_licence_item && (edit_licence_item.user_extra_storage_step_gb != null || edit_licence_item.user_extra_storage_step_price != null)">
                                <span class="edit-licence-price-label">Zusatz-Speicher</span>
                                <span class="edit-licence-price-value">{{ formatPrice(edit_licence_item.user_extra_storage_step_price) }} / {{ formatStorage(edit_licence_item.user_extra_storage_step_gb) }}</span>
                            </div>
                        </div>
                    </v-tabs-window-item>
                </v-tabs-window>
            </v-card-text>

            <v-card-actions class="d-flex pa-4 justify-space-between">
                <v-btn v-if="!(edit_licence_tab === 'admin' && edit_admin_user_edit_id)" color="warning" variant="text" rounded="lg" @click="closeEditLicenceDialog">Schließen</v-btn>
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
            selected_school_licence_id: null,
            selected_user_licences_school_licence_id: null,
            edit_date_dialog: false,
            edit_date_school: null,
            edit_date_licence_id: null,
            edit_school_licence: null,
            edit_licence_dialog: false,
            edit_licence_tab: null,
            edit_licence_school: null,
            edit_licence_item: null,
            edit_school_charged_price: null,
            edit_school_extra_storage_units: null,
            edit_school_extra_storage_unit_price: null,
            edit_school_billing_editing: false,
            edit_admin_users: [],
            edit_admin_users_role_statuses: {},
            edit_admin_add_mode: false,
            edit_admin_add_search: '',
            edit_admin_add_results: [],
            edit_admin_user_edit_id: null,
            edit_admin_user_valid_until: null,
            edit_admin_user_price_editing: false,
            edit_admin_user_charged_price: null,
            edit_admin_user_charged_price_draft: null,
            edit_admin_user_extra_storage_units: null,
            edit_admin_user_extra_storage_unit_price: null,
            edit_valid_until: '',
            edit_no_date: false,
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
        mainColXl() {
            return 11
        },
        schoolDetailXl() {
            return 6
        },
        selectedSchoolId() {
            return this.selected_schools[0] ?? null
        },
        editDateSchoolId() {
            return this.edit_date_school?.id ?? this.selectedSchoolId
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
        effectiveEndDayMonth() {
            if (!this.edit_licence_item) return null
            return this.edit_licence_item.end_day_month || this.dayBefore(this.edit_licence_item.start_day_month)
        },
        teillizenzPreisLabel() {
            if (!this.effectiveEndDayMonth) return ''
            const prorated = this.proratedYearPrice(
                this.edit_school_charged_price != null ? this.edit_school_charged_price : this.edit_licence_item?.school_price_per_year,
                this.effectiveEndDayMonth
            )
            if (prorated === null) {
                return `${this.remainingDaysUntil(this.effectiveEndDayMonth)} Tage`
            }
            return `${this.formatPrice(prorated)}`
        },
        adminTeillizenzPreisLabel() {
            if (!this.effectiveEndDayMonth) return ''
            const prorated = this.proratedYearPrice(
                this.edit_admin_user_charged_price != null ? this.edit_admin_user_charged_price : this.adminUserBillingTotal(),
                this.effectiveEndDayMonth
            )
            if (prorated === null) {
                return `${this.remainingDaysUntil(this.effectiveEndDayMonth)} Tage`
            }
            return `${this.formatPrice(prorated)}`
        },
        billingTotal() {
            const base =
                this.edit_school_charged_price != null
                    ? this.normalizePriceToNumber(this.edit_school_charged_price)
                    : this.edit_licence_item?.school_price_per_year != null
                      ? this.normalizePriceToNumber(this.edit_licence_item.school_price_per_year)
                      : 0
            const units = parseInt(this.edit_school_extra_storage_units) || 0
            const unitPrice =
                this.edit_school_extra_storage_unit_price !== null && this.edit_school_extra_storage_unit_price !== undefined && this.edit_school_extra_storage_unit_price !== ''
                    ? this.normalizePriceToNumber(this.edit_school_extra_storage_unit_price)
                    : this.edit_licence_item?.school_extra_storage_step_price != null
                      ? this.normalizePriceToNumber(this.edit_licence_item.school_extra_storage_step_price)
                      : 0
            return (isNaN(base) ? 0 : base) + units * (isNaN(unitPrice) ? 0 : unitPrice)
        },
        schoolStorageTotalGb() {
            const included =
                this.edit_licence_item?.school_included_storage_gb != null
                    ? parseInt(this.edit_licence_item.school_included_storage_gb, 10)
                    : null
            const stepGb =
                this.edit_licence_item?.school_extra_storage_step_gb != null
                    ? parseInt(this.edit_licence_item.school_extra_storage_step_gb, 10)
                    : null
            const units = parseInt(this.edit_school_extra_storage_units, 10) || 0

            const normalizedIncluded = included !== null && !isNaN(included) ? included : null
            const normalizedStepGb = stepGb !== null && !isNaN(stepGb) ? stepGb : null

            if (normalizedIncluded === null && normalizedStepGb === null) {
                return null
            }

            return (normalizedIncluded || 0) + units * (normalizedStepGb || 0)
        },
        availableRoles() {
            return this.roles || []
        },
        visibleAdminUsers() {
            const users = Array.isArray(this.edit_admin_users) ? this.edit_admin_users : []
            if (!this.edit_admin_user_edit_id) {
                return users
            }

            const editingUserId = Number(this.edit_admin_user_edit_id)
            return users.filter((user) => Number(user?.id) === editingUserId)
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
        availableEditDateLicences() {
            const allLicences = Array.isArray(this.licences) ? this.sortedLicences(this.licences) : []
            const assignedLicenceIds = new Set(
                Array.isArray(this.edit_date_school?.licences)
                    ? this.edit_date_school.licences
                        .map((licence) => Number(licence?.id))
                        .filter((licenceId) => Number.isInteger(licenceId) && licenceId > 0)
                    : []
            )

            if (this.edit_school_licence?.id) {
                assignedLicenceIds.delete(Number(this.edit_school_licence.id))
            }

            return allLicences.filter((licence) => !assignedLicenceIds.has(Number(licence?.id)))
        },
        editLicenceCloseLocked() {
            return !!(
                this.edit_admin_user_edit_id ||
                this.edit_school_billing_editing
            )
        },
        editLicenceTabLocked() {
            return !!(
                this.editLicenceCloseLocked ||
                this.edit_admin_add_mode
            )
        },
    },

    watch: {
        edit_licence_tab(val) {
            if (val === 'admin') {
                this.loadAdminUsers()
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
        userLicenceStatusesForUser(userId) {
            if (!userId) return {}

            const statusesByUser = this.school_licence_users_role_statuses || {}
            return statusesByUser[String(userId)] || statusesByUser[userId] || {}
        },
        userLicencePlanForRole(roleName, roleStatus, licenceSource = null) {
            const source = licenceSource || this.selectedUserLicencesSource || {}
            const model = this.normalizeLicenceModel(source?.licence_model || source)
            const plans = Array.isArray(model.user_licence_plans_by_role?.[roleName]) ? model.user_licence_plans_by_role[roleName] : []
            const planId = Number(roleStatus?.plan_id)

            if (Number.isInteger(planId) && planId > 0) {
                const selectedPlan = plans.find((plan) => Number(plan?.id) === planId)
                if (selectedPlan) {
                    return selectedPlan
                }
            }

            return plans.find((plan) => plan && typeof plan === 'object') || null
        },
        userLicenceSummaryEntries(user, licenceSource = null) {
            const source = licenceSource || this.selectedUserLicencesSource || {}
            const model = this.normalizeLicenceModel(source?.licence_model || source)
            const userStatuses = this.userLicenceStatusesForUser(user?.id)
            const userRoles = this.sortedRoleNames(user?.roles || [])

            return userRoles
                .filter((roleName) => !!model.user_licence_required_by_role?.[roleName])
                .map((roleName) => {
                    const roleStatus = userStatuses[roleName]

                    if (!roleStatus || roleStatus.assigned !== true) {
                        return null
                    }

                    const selectedPlan = this.userLicencePlanForRole(roleName, roleStatus, source)
                    const includedStorageGb = source?.user_included_storage_gb != null ? parseInt(source.user_included_storage_gb, 10) : null
                    const extraStorageStepGb = source?.user_extra_storage_step_gb != null ? parseInt(source.user_extra_storage_step_gb, 10) : null
                    const extraStorageUnits = roleStatus?.extra_storage_units != null ? parseInt(roleStatus.extra_storage_units, 10) : 0
                    const normalizedIncludedStorageGb = includedStorageGb !== null && !isNaN(includedStorageGb) ? includedStorageGb : null
                    const normalizedExtraStorageStepGb = extraStorageStepGb !== null && !isNaN(extraStorageStepGb) ? extraStorageStepGb : null

                    return {
                        roleName,
                        price:
                            roleStatus?.charged_price ?? selectedPlan?.price_per_year ?? source?.user_price_per_year ?? null,
                        storageGb:
                            normalizedIncludedStorageGb !== null || normalizedExtraStorageStepGb !== null
                                ? (normalizedIncludedStorageGb || 0) + (isNaN(extraStorageUnits) ? 0 : extraStorageUnits) * (normalizedExtraStorageStepGb || 0)
                                : null,
                    }
                })
                .filter(Boolean)
        },
        userLicenceListPriceTotal(user, licenceSource = null) {
            const entries = this.userLicenceSummaryEntries(user, licenceSource)
            let total = 0
            let hasPrice = false

            for (const entry of entries) {
                const normalizedPrice = this.normalizePriceToNumber(entry?.price)

                if (isNaN(normalizedPrice)) {
                    continue
                }

                total += normalizedPrice
                hasPrice = true
            }

            return hasPrice ? total : null
        },
        userLicenceListStorageTotalGb(user, licenceSource = null) {
            const entries = this.userLicenceSummaryEntries(user, licenceSource)
            let total = 0
            let hasStorage = false

            for (const entry of entries) {
                if (entry?.storageGb === null || entry?.storageGb === undefined || isNaN(entry.storageGb)) {
                    continue
                }

                total += entry.storageGb
                hasStorage = true
            }

            return hasStorage ? total : null
        },
        defaultValidUntil() {
            const date = new Date()
            date.setFullYear(date.getFullYear() + 1)
            return this.localDateKey(date)
        },
        remainingDaysUntil(endDayMonth) {
            if (!endDayMonth) return 0
            const yearEndStr = this.nextYearEndDate(endDayMonth)
            const yearEnd = new Date(`${yearEndStr}T00:00:00`)
            const today = new Date(`${this.localDateKey()}T00:00:00`)
            return Math.max(0, Math.round((yearEnd - today) / 86400000))
        },
        proratedYearPrice(rawPrice, endDayMonth) {
            const daysRemaining = this.remainingDaysUntil(endDayMonth)
            if (rawPrice === null || rawPrice === undefined || rawPrice === '') return null
            const fullYearPrice = this.normalizePriceToNumber(rawPrice)
            if (isNaN(fullYearPrice)) return null
            return Math.ceil((fullYearPrice * daysRemaining) / 365)
        },
        dayBefore(dayMonth) {
            // dayMonth format: "DD.MM." e.g. "01.08."
            const match = dayMonth && dayMonth.match(/^(\d{2})\.(\d{2})\.$/)
            if (!match) return '?'
            const date = new Date(2000, parseInt(match[2], 10) - 1, parseInt(match[1], 10))
            date.setDate(date.getDate() - 1)
            const d = String(date.getDate()).padStart(2, '0')
            const m = String(date.getMonth() + 1).padStart(2, '0')
            return `${d}.${m}.`
        },
        formatPrice(value) {
            if (value === null || value === undefined || value === '') return '–'
            const num = this.normalizePriceToNumber(value)
            if (isNaN(num)) return '–'
            return num % 1 === 0
                ? `€ ${num.toFixed(0)}`
                : `€ ${num.toFixed(2).replace('.', ',')}`
        },
        editablePriceInputValue(value) {
            if (value === null || value === undefined || value === '') return ''
            return String(value).trim().replace(/\./g, ',')
        },
        normalizedPriceInputValue(value) {
            if (value === null || value === undefined) return null
            const normalized = String(value).trim()
            if (!normalized) return null
            return normalized.replace(/,/g, '.')
        },
        overridePriceLabel(overridePrice, defaultPrice, defaultLabel = 'Basis-Tarif') {
            if (overridePrice !== null && overridePrice !== undefined && overridePrice !== '') {
                return this.formatPrice(overridePrice)
            }

            if (defaultPrice !== null && defaultPrice !== undefined && defaultPrice !== '') {
                return `${this.formatPrice(defaultPrice)} (${defaultLabel})`
            }

            return '–'
        },
        overrideCountLabel(overrideValue, defaultValue, defaultLabel = 'Standard') {
            if (overrideValue !== null && overrideValue !== undefined && overrideValue !== '') {
                return `${overrideValue}`
            }

            if (defaultValue !== null && defaultValue !== undefined && defaultValue !== '') {
                return `${defaultValue} (${defaultLabel})`
            }

            return '–'
        },
        formatStorage(value) {
            if (value === null || value === undefined || value === '') return '–'
            const num = parseInt(value, 10)
            if (isNaN(num)) return '–'
            return `${num} GB`
        },
        isEditLicenceTabDisabled(tab) {
            return this.editLicenceTabLocked && this.edit_licence_tab !== tab
        },
        async openEditLicenceDialog(school, licence, requestedTab = null) {
            this.edit_licence_school = school
            this.edit_licence_item = licence
            const allowedTabs = [
                licence.school_licence_enabled ? 'school' : null,
                licence.admin_licence_enabled ? 'admin' : null,
                licence.user_licence_enabled ? 'user' : null,
            ].filter(Boolean)
            this.edit_licence_tab = allowedTabs.includes(requestedTab)
                ? requestedTab
                : allowedTabs[0] || null
            this.edit_school_charged_price = this.editablePriceInputValue(licence.charged_school_price)
            this.edit_school_extra_storage_units = licence.extra_storage_units ?? null
            this.edit_school_extra_storage_unit_price = this.editablePriceInputValue(licence.extra_storage_unit_price)
            this.edit_school_billing_editing = false
            this.edit_licence_dialog = true
            if (this.edit_licence_tab === 'admin') {
                await this.loadAdminUsers()
            }
        },
        closeEditLicenceDialog() {
            this.edit_licence_dialog = false
            this.edit_licence_tab = null
            this.edit_licence_school = null
            this.edit_licence_item = null
            this.edit_school_charged_price = null
            this.edit_school_extra_storage_units = null
            this.edit_school_extra_storage_unit_price = null
            this.edit_school_billing_editing = false
            this.edit_admin_users = []
            this.edit_admin_users_role_statuses = {}
            this.edit_admin_add_mode = false
            this.edit_admin_add_search = ''
            this.edit_admin_add_results = []
            this.edit_admin_user_edit_id = null
            this.edit_admin_user_valid_until = null
            this.edit_admin_user_price_editing = false
            this.edit_admin_user_charged_price = null
            this.edit_admin_user_charged_price_draft = null
            this.edit_admin_user_extra_storage_units = null
            this.edit_admin_user_extra_storage_unit_price = null
        },
        cancelSchoolBillingEdit() {
            this.edit_school_charged_price = this.editablePriceInputValue(this.edit_licence_item?.charged_school_price)
            this.edit_school_extra_storage_units = this.edit_licence_item?.extra_storage_units ?? null
            this.edit_school_extra_storage_unit_price = this.editablePriceInputValue(this.edit_licence_item?.extra_storage_unit_price)
            this.edit_school_billing_editing = false
        },
        async refreshEditLicenceItem() {
            await this.schoolStore.index(this.meta?.current_page || 1)
            const school = this.schools.find((s) => s.id === this.edit_licence_school?.id)
            const licence = school?.licences?.find((l) => l.id === this.edit_licence_item?.id)
            if (licence) {
                this.edit_licence_item = licence
            }
        },
        async setLicenceValidUntil(valid_until) {
            if (!this.edit_licence_item || !this.edit_licence_school) return
            const data = {
                school_id: this.edit_licence_school.id,
                licence_id: this.edit_licence_item.id,
                valid_until,
            }
            if (!(await this.schoolStore.addLicence(data))) return
            await this.refreshEditLicenceItem()
        },
        nextYearEndDate(endDayMonth) {
            if (!endDayMonth) return this.defaultValidUntil()
            const cleaned = endDayMonth.replace(/\.$/, '')
            const parts = cleaned.split('.')
            if (parts.length < 2) return this.defaultValidUntil()
            const day = parseInt(parts[0], 10)
            const month = parseInt(parts[1], 10) - 1
            const today = new Date()
            let candidate = new Date(today.getFullYear(), month, day)
            if (candidate <= today) {
                candidate = new Date(today.getFullYear() + 1, month, day)
            }
            return this.localDateKey(candidate)
        },
        nextYearEndLabel(endDayMonth) {
            const dateStr = this.nextYearEndDate(endDayMonth)
            const [year, month, day] = dateStr.split('-')
            return `${day}.${month}.${String(year).slice(2)}`
        },
        async setValidUntilNextYearEnd() {
            await this.setLicenceValidUntil(this.nextYearEndDate(this.effectiveEndDayMonth))
        },
        async setValidUntilUnlimited() {
            await this.setLicenceValidUntil(null)
        },
        async extendValidUntilOneYear() {
            const current = this.edit_licence_item?.valid_until
            let newDate
            if (current) {
                const d = new Date(current)
                d.setFullYear(d.getFullYear() + 1)
                newDate = this.localDateKey(d)
            } else {
                newDate = this.defaultValidUntil()
            }
            await this.setLicenceValidUntil(newDate)
        },
        adminRolesFromLicenceModel(licence) {
            const model = this.normalizeLicenceModel(licence || null)
            return this.sortedRoleNames(model.admin_role_names || [])
        },
        async loadAdminUsers() {
            if (!this.edit_licence_item?.school_licence_id) return
            const adminRoles = this.adminRolesFromLicenceModel(this.edit_licence_item)
            try {
                this.adminStore.is_loading++
                const response = await axios.get(`/api/admin/school_licences/${this.edit_licence_item.school_licence_id}/users`, {
                    params: {
                        role_names: adminRoles,
                    },
                })
                this.edit_admin_users = response.data.data || []
                this.edit_admin_users_role_statuses = response.data.role_statuses_by_user || {}
            } catch {
                this.edit_admin_users = []
                this.edit_admin_users_role_statuses = {}
            } finally {
                this.adminStore.is_loading--
            }
        },
        adminUserIsAssigned(user) {
            const statuses = this.edit_admin_users_role_statuses[String(user.id)]
            if (!statuses || typeof statuses !== 'object') return false
            return Object.values(statuses).some((status) => status && typeof status === 'object' && status.assigned === true)
        },
        openAdminAddMode() {
            this.edit_admin_add_mode = true
            this.edit_admin_add_search = ''
            this.edit_admin_add_results = []
        },
        closeAdminAddMode() {
            this.edit_admin_add_mode = false
            this.edit_admin_add_search = ''
            this.edit_admin_add_results = []
        },
        closeAdminUserEdit() {
            this.edit_admin_user_edit_id = null
            this.edit_admin_user_valid_until = null
            this.edit_admin_user_price_editing = false
            this.edit_admin_user_charged_price = null
            this.edit_admin_user_charged_price_draft = null
            this.edit_admin_user_extra_storage_units = null
            this.edit_admin_user_extra_storage_unit_price = null
        },
        async searchAdminUsersToAdd() {
            if (!this.edit_licence_item?.school_licence_id) {
                this.edit_admin_add_results = []
                return
            }
            const adminRoles = this.adminRolesFromLicenceModel(this.edit_licence_item)
            try {
                this.adminStore.is_loading++
                const response = await axios.get(`/api/admin/school_licences/${this.edit_licence_item.school_licence_id}/users`, {
                    params: {
                        search_string: this.edit_admin_add_search.trim() || null,
                        role_names: adminRoles,
                        assigned_only: 0,
                    },
                })
                const roleStatusesByUser = response.data.role_statuses_by_user || {}
                this.edit_admin_add_results = (response.data.data || []).filter((user) => {
                    const statuses = roleStatusesByUser[String(user.id)]
                    if (!statuses || typeof statuses !== 'object') return true
                    return !Object.values(statuses).some((status) => status && typeof status === 'object' && status.assigned === true)
                })
            } catch {
                this.edit_admin_add_results = []
            } finally {
                this.adminStore.is_loading--
            }
        },
        async assignAdminUser(user) {
            if (!this.edit_licence_item?.school_licence_id) return
            try {
                this.adminStore.is_loading++
                const rolesResponse = await axios.get(
                    `/api/admin/school_licences/${this.edit_licence_item.school_licence_id}/users/${user.id}/roles`
                )
                const adminRoleNames = new Set(this.adminRolesFromLicenceModel(this.edit_licence_item))
                const roles = (rolesResponse.data.roles || []).map((role) => ({
                    ...role,
                    assigned: adminRoleNames.has(String(role?.name || '').trim()) ? true : !!role.assigned,
                }))
                await axios.put(
                    `/api/admin/school_licences/${this.edit_licence_item.school_licence_id}/users/${user.id}/roles`,
                    { roles }
                )
            } catch {
                useNotificationStore().notify({ message: 'Fehler beim Zuweisen.', type: 'error', timeout: 3000 })
                return
            } finally {
                this.adminStore.is_loading--
            }
            this.edit_admin_add_results = this.edit_admin_add_results.filter((candidate) => Number(candidate?.id) !== Number(user?.id))
            await this.loadAdminUsers()
            await this.refreshEditLicenceItem()
        },
        adminUserStatusForUser(user) {
            const statuses = this.edit_admin_users_role_statuses[String(user.id)]
            if (!statuses || typeof statuses !== 'object') return null
            return Object.values(statuses)[0] || null
        },
        adminUserHasValidLicence(user) {
            if (!this.adminUserIsAssigned(user)) return false
            const status = this.adminUserStatusForUser(user)
            return !this.isValidUntilExpired(status?.valid_until || null)
        },
        adminUserLicenceRuntimeLabel(user) {
            const status = this.adminUserStatusForUser(user)
            if (!status || !this.adminUserIsAssigned(user)) return 'Keine Benutzerlizenz'
            if (!status.valid_until) {
                return 'Benutzerlizenz läuft unbegrenzt'
            }

            const validUntil =
                status.valid_until instanceof Date
                    ? this.toDateString(status.valid_until)
                    : String(status.valid_until).slice(0, 10)

            if (!validUntil) {
                return 'Keine Benutzerlizenz'
            }

            const today = new Date(`${this.localDateKey()}T00:00:00`)
            const endDate = new Date(`${validUntil}T00:00:00`)
            const remainingDays = Math.ceil((endDate.getTime() - today.getTime()) / 86400000)

            if (remainingDays < 0) {
                return `Benutzerlizenz abgelaufen am ${this.formatValidUntil(validUntil)}`
            }
            if (remainingDays === 0) {
                return `Benutzerlizenz läuft heute ab (${this.formatValidUntil(validUntil)})`
            }
            if (remainingDays === 1) {
                return `Benutzerlizenz läuft noch 1 Tag (${this.formatValidUntil(validUntil)})`
            }
            return `Benutzerlizenz läuft noch ${remainingDays} Tage (${this.formatValidUntil(validUntil)})`
        },
        adminUserBasePriceForUser(user) {
            const status = this.adminUserStatusForUser(user)

            if (status?.charged_price !== null && status?.charged_price !== undefined && status?.charged_price !== '') {
                return status.charged_price
            }

            return this.effectiveAdminBasePrice()
        },
        adminUserExtraStorageUnitPriceForUser(user) {
            const status = this.adminUserStatusForUser(user)

            if (status?.extra_storage_unit_price !== null && status?.extra_storage_unit_price !== undefined && status?.extra_storage_unit_price !== '') {
                return status.extra_storage_unit_price
            }

            return this.effectiveAdminExtraStorageUnitPrice()
        },
        adminUserExtraStorageUnitsForUser(user) {
            const status = this.adminUserStatusForUser(user)

            if (status?.extra_storage_units !== null && status?.extra_storage_units !== undefined && status?.extra_storage_units !== '') {
                return status.extra_storage_units
            }

            return this.defaultAdminUserExtraStorageUnits()
        },
        adminUserBillingTotalForUser(user) {
            const basePrice = this.adminUserBasePriceForUser(user)
            const unitPrice = this.adminUserExtraStorageUnitPriceForUser(user)
            const units = this.adminUserExtraStorageUnitsForUser(user)
            const parsedBasePrice = basePrice !== null ? this.normalizePriceToNumber(basePrice) : 0
            const parsedUnitPrice = unitPrice !== null ? this.normalizePriceToNumber(unitPrice) : 0
            const parsedUnits = units !== null && units !== undefined && units !== '' ? parseInt(units, 10) : 0
            const hasBasePrice = basePrice !== null
            const hasExtraStorage = units !== null && units !== undefined && units !== '' && parsedUnits > 0

            if (!hasBasePrice && !hasExtraStorage) {
                return null
            }

            return (isNaN(parsedBasePrice) ? 0 : parsedBasePrice) + (isNaN(parsedUnits) ? 0 : parsedUnits) * (isNaN(parsedUnitPrice) ? 0 : parsedUnitPrice)
        },
        adminUserStorageTotalGbForUser(user) {
            const includedGb = this.edit_licence_item?.admin_included_storage_gb != null
                ? parseInt(this.edit_licence_item.admin_included_storage_gb, 10)
                : null
            const stepGb = this.edit_licence_item?.admin_extra_storage_step_gb != null
                ? parseInt(this.edit_licence_item.admin_extra_storage_step_gb, 10)
                : null
            const units = this.adminUserExtraStorageUnitsForUser(user)
            const parsedUnits = units !== null && units !== undefined && units !== '' ? parseInt(units, 10) : 0
            const normalizedIncludedGb = includedGb !== null && !isNaN(includedGb) ? includedGb : null
            const normalizedStepGb = stepGb !== null && !isNaN(stepGb) ? stepGb : null

            if (normalizedIncludedGb === null && normalizedStepGb === null) {
                return null
            }

            return (normalizedIncludedGb || 0) + (isNaN(parsedUnits) ? 0 : parsedUnits) * (normalizedStepGb || 0)
        },
        effectiveAdminBasePrice() {
            if (this.edit_licence_item?.charged_admin_price !== null && this.edit_licence_item?.charged_admin_price !== undefined && this.edit_licence_item?.charged_admin_price !== '') {
                return this.edit_licence_item.charged_admin_price
            }

            if (this.edit_licence_item?.admin_price_per_year !== null && this.edit_licence_item?.admin_price_per_year !== undefined && this.edit_licence_item?.admin_price_per_year !== '') {
                return this.edit_licence_item.admin_price_per_year
            }

            return null
        },
        effectiveAdminExtraStorageUnitPrice() {
            if (this.edit_licence_item?.admin_extra_storage_unit_price !== null && this.edit_licence_item?.admin_extra_storage_unit_price !== undefined && this.edit_licence_item?.admin_extra_storage_unit_price !== '') {
                return this.edit_licence_item.admin_extra_storage_unit_price
            }

            if (this.edit_licence_item?.admin_extra_storage_step_price !== null && this.edit_licence_item?.admin_extra_storage_step_price !== undefined && this.edit_licence_item?.admin_extra_storage_step_price !== '') {
                return this.edit_licence_item.admin_extra_storage_step_price
            }

            return null
        },
        effectiveAdminUserExtraStorageUnitPrice() {
            if (this.edit_admin_user_extra_storage_unit_price !== null && this.edit_admin_user_extra_storage_unit_price !== undefined && this.edit_admin_user_extra_storage_unit_price !== '') {
                return this.edit_admin_user_extra_storage_unit_price
            }

            return this.effectiveAdminExtraStorageUnitPrice()
        },
        defaultAdminUserExtraStorageUnits() {
            if (this.edit_licence_item?.admin_extra_storage_units !== null && this.edit_licence_item?.admin_extra_storage_units !== undefined && this.edit_licence_item?.admin_extra_storage_units !== '') {
                return this.edit_licence_item.admin_extra_storage_units
            }

            return null
        },
        effectiveAdminUserExtraStorageUnits() {
            if (this.edit_admin_user_extra_storage_units !== null && this.edit_admin_user_extra_storage_units !== undefined && this.edit_admin_user_extra_storage_units !== '') {
                return this.edit_admin_user_extra_storage_units
            }

            return this.defaultAdminUserExtraStorageUnits()
        },
        adminBillingTotal() {
            const basePrice = this.effectiveAdminBasePrice()
            const unitPrice = this.effectiveAdminExtraStorageUnitPrice()
            const units = this.edit_licence_item?.admin_extra_storage_units
            const parsedBasePrice = basePrice !== null ? this.normalizePriceToNumber(basePrice) : 0
            const parsedUnitPrice = unitPrice !== null ? this.normalizePriceToNumber(unitPrice) : 0
            const parsedUnits = units !== null && units !== undefined && units !== '' ? parseInt(units, 10) : 0
            const hasBasePrice = basePrice !== null
            const hasExtraStorage = units !== null && units !== undefined && units !== '' && parsedUnits > 0

            if (!hasBasePrice && !hasExtraStorage) {
                return null
            }

            return (isNaN(parsedBasePrice) ? 0 : parsedBasePrice) + (isNaN(parsedUnits) ? 0 : parsedUnits) * (isNaN(parsedUnitPrice) ? 0 : parsedUnitPrice)
        },
        adminUserBillingTotal() {
            const basePrice = this.effectiveAdminBasePrice()
            const unitPrice = this.effectiveAdminUserExtraStorageUnitPrice()
            const units = this.effectiveAdminUserExtraStorageUnits()
            const parsedBasePrice = basePrice !== null ? this.normalizePriceToNumber(basePrice) : 0
            const parsedUnitPrice = unitPrice !== null ? this.normalizePriceToNumber(unitPrice) : 0
            const parsedUnits = units !== null && units !== undefined && units !== '' ? parseInt(units, 10) : 0
            const hasBasePrice = basePrice !== null
            const hasExtraStorage = units !== null && units !== undefined && units !== '' && parsedUnits > 0

            if (!hasBasePrice && !hasExtraStorage) {
                return null
            }

            return (isNaN(parsedBasePrice) ? 0 : parsedBasePrice) + (isNaN(parsedUnits) ? 0 : parsedUnits) * (isNaN(parsedUnitPrice) ? 0 : parsedUnitPrice)
        },
        adminUserExtraStorageTotal() {
            const hasStorageOption =
                this.edit_licence_item &&
                (this.edit_licence_item.admin_extra_storage_step_gb != null || this.edit_licence_item.admin_extra_storage_step_price != null)

            if (!hasStorageOption) {
                return null
            }

            const unitPrice = this.effectiveAdminUserExtraStorageUnitPrice()
            const units = this.effectiveAdminUserExtraStorageUnits()
            const parsedUnitPrice = unitPrice !== null ? this.normalizePriceToNumber(unitPrice) : 0
            const parsedUnits = units !== null && units !== undefined && units !== '' ? parseInt(units, 10) : 0

            return (isNaN(parsedUnits) ? 0 : parsedUnits) * (isNaN(parsedUnitPrice) ? 0 : parsedUnitPrice)
        },
        adminUserStorageTotalGb() {
            const includedGb = this.edit_licence_item?.admin_included_storage_gb != null
                ? parseInt(this.edit_licence_item.admin_included_storage_gb, 10)
                : null
            const stepGb = this.edit_licence_item?.admin_extra_storage_step_gb != null
                ? parseInt(this.edit_licence_item.admin_extra_storage_step_gb, 10)
                : null
            const units = this.effectiveAdminUserExtraStorageUnits()
            const parsedUnits = units !== null && units !== undefined && units !== '' ? parseInt(units, 10) : 0

            const normalizedIncludedGb = includedGb !== null && !isNaN(includedGb) ? includedGb : null
            const normalizedStepGb = stepGb !== null && !isNaN(stepGb) ? stepGb : null

            if (normalizedIncludedGb === null && normalizedStepGb === null) {
                return null
            }

            return (normalizedIncludedGb || 0) + (isNaN(parsedUnits) ? 0 : parsedUnits) * (normalizedStepGb || 0)
        },
        adminBillingDefaultLabel() {
            if (
                (this.edit_licence_item?.charged_admin_price !== null && this.edit_licence_item?.charged_admin_price !== undefined && this.edit_licence_item?.charged_admin_price !== '') ||
                (this.edit_licence_item?.admin_extra_storage_units !== null && this.edit_licence_item?.admin_extra_storage_units !== undefined && this.edit_licence_item?.admin_extra_storage_units !== '') ||
                (this.edit_licence_item?.admin_extra_storage_unit_price !== null && this.edit_licence_item?.admin_extra_storage_unit_price !== undefined && this.edit_licence_item?.admin_extra_storage_unit_price !== '')
            ) {
                return 'Tarif dieser Schule'
            }

            return 'Basis-Tarif'
        },
        adminUserBillingDefaultLabel() {
            const userUnits = this.effectiveAdminUserExtraStorageUnits()
            const defaultUnits = this.defaultAdminUserExtraStorageUnits()
            const normalizedUserUnits = userUnits !== null && userUnits !== undefined && userUnits !== '' ? parseInt(userUnits, 10) : null
            const normalizedDefaultUnits = defaultUnits !== null && defaultUnits !== undefined && defaultUnits !== '' ? parseInt(defaultUnits, 10) : null

            if (normalizedUserUnits !== null && normalizedUserUnits !== normalizedDefaultUnits) {
                return 'Tarif mit Zusatz-Speicher'
            }

            return this.adminBillingDefaultLabel()
        },
        adminBillingPlaceholder() {
            const total = this.adminBillingTotal()

            return total !== null ? String(total) : ''
        },
        adminUserBillingPlaceholder() {
            const total = this.adminUserBillingTotal()

            return total !== null ? String(total) : ''
        },
        toggleAdminUserEdit(user) {
            if (this.edit_admin_user_edit_id === user.id) {
                this.closeAdminUserEdit()
                return
            }
            this.edit_admin_add_mode = false
            this.edit_admin_add_search = ''
            this.edit_admin_add_results = []
            const status = this.adminUserStatusForUser(user)
            this.edit_admin_user_edit_id = user.id
            this.edit_admin_user_valid_until = status?.valid_until || null
            this.edit_admin_user_charged_price = this.normalizedPriceInputValue(status?.charged_price)
            this.edit_admin_user_charged_price_draft = this.editablePriceInputValue(status?.charged_price ?? this.effectiveAdminBasePrice())
            this.edit_admin_user_extra_storage_units = status?.extra_storage_units ?? null
            this.edit_admin_user_extra_storage_unit_price = this.editablePriceInputValue(status?.extra_storage_unit_price)
            this.edit_admin_user_price_editing = false
        },
        async saveAdminUserRoles(user, patchFn) {
            if (!this.edit_licence_item?.school_licence_id) return false
            try {
                this.adminStore.is_loading++
                const rolesResponse = await axios.get(
                    `/api/admin/school_licences/${this.edit_licence_item.school_licence_id}/users/${user.id}/roles`
                )
                const roles = (rolesResponse.data.roles || []).map((role) => patchFn(role))
                await axios.put(
                    `/api/admin/school_licences/${this.edit_licence_item.school_licence_id}/users/${user.id}/roles`,
                    { roles }
                )
                return true
            } catch {
                useNotificationStore().notify({ message: 'Fehler beim Speichern.', type: 'error', timeout: 3000 })
                return false
            } finally {
                this.adminStore.is_loading--
            }
        },
        async setAdminUserValidUntil(user, valid_until) {
            const adminRoleNames = new Set(this.adminRolesFromLicenceModel(this.edit_licence_item))
            const ok = await this.saveAdminUserRoles(user, (role) => ({
                ...role,
                assigned: adminRoleNames.has(String(role?.name || '').trim()) ? true : !!role.assigned,
                valid_until: adminRoleNames.has(String(role?.name || '').trim()) ? valid_until : role.valid_until,
            }))
            if (!ok) return
            this.edit_admin_user_valid_until = valid_until
            await this.loadAdminUsers()
            // sync status for open edit panel
            const status = this.adminUserStatusForUser(user)
            this.edit_admin_user_valid_until = status?.valid_until || null
        },
        async extendAdminUserValidUntilOneYear(user) {
            const current = this.edit_admin_user_valid_until
            let newDate
            if (current) {
                const d = new Date(current)
                d.setFullYear(d.getFullYear() + 1)
                newDate = this.localDateKey(d)
            } else {
                newDate = this.defaultValidUntil()
            }
            await this.setAdminUserValidUntil(user, newDate)
        },
        async applyAdminUserTeillizenz(user) {
            const validUntil = this.nextYearEndDate(this.effectiveEndDayMonth)
            const chargedPrice = this.proratedYearPrice(
                this.edit_admin_user_charged_price != null ? this.edit_admin_user_charged_price : this.adminUserBillingTotal(),
                this.effectiveEndDayMonth
            )
            const adminRoleNames = new Set(this.adminRolesFromLicenceModel(this.edit_licence_item))
            const ok = await this.saveAdminUserRoles(user, (role) => ({
                ...role,
                assigned: adminRoleNames.has(String(role?.name || '').trim()) ? true : !!role.assigned,
                valid_until: adminRoleNames.has(String(role?.name || '').trim()) ? validUntil : role.valid_until,
                charged_price: adminRoleNames.has(String(role?.name || '').trim()) ? chargedPrice : role.charged_price,
                extra_storage_units: adminRoleNames.has(String(role?.name || '').trim()) ? this.effectiveAdminUserExtraStorageUnits() : role.extra_storage_units,
                extra_storage_unit_price: adminRoleNames.has(String(role?.name || '').trim()) ? this.effectiveAdminUserExtraStorageUnitPrice() : role.extra_storage_unit_price,
            }))
            if (!ok) return
            await this.loadAdminUsers()
            const status = this.adminUserStatusForUser(user)
            this.edit_admin_user_valid_until = status?.valid_until || null
            this.edit_admin_user_charged_price = this.normalizedPriceInputValue(status?.charged_price)
            this.edit_admin_user_charged_price_draft = this.editablePriceInputValue(status?.charged_price ?? this.effectiveAdminBasePrice())
            this.edit_admin_user_extra_storage_units = status?.extra_storage_units ?? null
            this.edit_admin_user_extra_storage_unit_price = this.editablePriceInputValue(status?.extra_storage_unit_price)
            this.edit_admin_user_price_editing = false
            await this.refreshEditLicenceItem()
        },
        async removeAdminUserLicence(user) {
            const adminRoleNames = new Set(this.adminRolesFromLicenceModel(this.edit_licence_item))
            const ok = await this.saveAdminUserRoles(user, (role) => ({
                ...role,
                assigned: adminRoleNames.has(String(role?.name || '').trim()) ? false : !!role.assigned,
                valid_until: adminRoleNames.has(String(role?.name || '').trim()) ? null : role.valid_until,
                charged_price: adminRoleNames.has(String(role?.name || '').trim()) ? null : role.charged_price,
                extra_storage_units: adminRoleNames.has(String(role?.name || '').trim()) ? null : role.extra_storage_units,
                extra_storage_unit_price: adminRoleNames.has(String(role?.name || '').trim()) ? null : role.extra_storage_unit_price,
            }))
            if (!ok) return
            this.closeAdminUserEdit()
            await this.loadAdminUsers()
            await this.refreshEditLicenceItem()
        },
        async saveAdminUserBilling(user) {
            const chargedPrice = this.normalizedPriceInputValue(this.edit_admin_user_charged_price_draft)
            const extraStorageUnits = this.edit_admin_user_extra_storage_units !== '' ? this.edit_admin_user_extra_storage_units : null
            const extraStorageUnitPrice = this.normalizedPriceInputValue(this.edit_admin_user_extra_storage_unit_price)
            const adminRoleNames = new Set(this.adminRolesFromLicenceModel(this.edit_licence_item))
            const ok = await this.saveAdminUserRoles(user, (role) => ({
                ...role,
                assigned: adminRoleNames.has(String(role?.name || '').trim()) ? true : !!role.assigned,
                charged_price: adminRoleNames.has(String(role?.name || '').trim()) ? chargedPrice : role.charged_price,
                extra_storage_units: adminRoleNames.has(String(role?.name || '').trim()) ? extraStorageUnits : role.extra_storage_units,
                extra_storage_unit_price: adminRoleNames.has(String(role?.name || '').trim()) ? extraStorageUnitPrice : role.extra_storage_unit_price,
            }))
            if (!ok) return
            this.edit_admin_user_price_editing = false
            await this.loadAdminUsers()
            const status = this.adminUserStatusForUser(user)
            this.edit_admin_user_charged_price = this.normalizedPriceInputValue(status?.charged_price)
            this.edit_admin_user_charged_price_draft = this.editablePriceInputValue(status?.charged_price ?? this.effectiveAdminBasePrice())
            this.edit_admin_user_extra_storage_units = status?.extra_storage_units ?? null
            this.edit_admin_user_extra_storage_unit_price = this.editablePriceInputValue(status?.extra_storage_unit_price)
            await this.refreshEditLicenceItem()
        },
        async deleteLicenceFromDialog() {
            const id = this.edit_licence_item?.school_licence_id
            if (!id) return
            this.closeEditLicenceDialog()
            await this.removeSchoolLicence(id)
        },
        async saveSchoolLicenceSchool() {
            if (!this.edit_licence_item?.school_licence_id) return
            const data = {
                charged_school_price: this.normalizedPriceInputValue(this.edit_school_charged_price),
                extra_storage_units: this.edit_school_extra_storage_units !== '' ? this.edit_school_extra_storage_units : null,
                extra_storage_unit_price: this.normalizedPriceInputValue(this.edit_school_extra_storage_unit_price),
            }
            if (!(await this.schoolStore.saveSchoolLicenceSchool(this.edit_licence_item.school_licence_id, data))) return
            this.edit_school_billing_editing = false
            await this.refreshEditLicenceItem()
        },
        formatValidUntil(dateStr) {
            if (!dateStr) return 'unbegrenzt'
            const [year, month, day] = dateStr.split('-')
            if (!year || !month || !day) return dateStr
            return `${day}.${month}.${year}`
        },
        isValidUntilExpired(dateStr) {
            if (!dateStr) return false
            return dateStr < this.localDateKey()
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

            const model = this.normalizeLicenceModel(licence || null)
            return model.school_licence_required === false || model.school_licence_enabled === false
        },
        isLicenceActive(licence) {
            if (this.isSchoolLicenceNotNeeded(licence)) return true

            const validUntil = licence?.valid_until
            if (!validUntil) return true
            const normalized = validUntil instanceof Date ? this.toDateString(validUntil) : String(validUntil).slice(0, 10)
            return normalized >= this.localDateKey()
        },
        schoolAssignableLicences(licences) {
            return this.sortedLicences(licences).filter((licence) => !this.isSchoolLicenceNotNeeded(licence))
        },
        hasSchoolLicenceAssignments(licences) {
            return this.schoolAssignableLicences(licences).length >= 1
        },
        countAssignedAdminLicences(licences) {
            return this.sortedLicences(licences).filter((licence) => this.hasAdminLicenceRequiredRole(licence)).length
        },
        hasAdminLicenceAssignments(licences) {
            return this.countAssignedAdminLicences(licences) >= 1
        },
        countAssignedUserLicences(licences) {
            return this.sortedLicences(licences).filter((licence) => this.hasUserLicenceRequiredRole(licence)).length
        },
        hasUserLicenceAssignments(licences) {
            return this.countAssignedUserLicences(licences) >= 1
        },
        hasAdminLicenceRequiredRole(licence) {
            const model = this.normalizeLicenceModel(licence || null)
            if (model.admin_licence_enabled || (model.admin_role_names || []).length >= 1) return true
            return Object.entries(model.user_licence_required_by_role || {}).some(
                ([roleName, isRequired]) => !!isRequired && this.looksLikeAdminRoleName(roleName)
            )
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
        openEditDateDialog(school, licence = null) {
            this.edit_date_school = school || null
            this.edit_school_licence = licence || null
            this.edit_date_licence_id = licence?.id ?? null
            this.edit_no_date = !licence?.valid_until
            this.edit_valid_until = licence?.valid_until || ''
            this.edit_date_dialog = true
        },
        closeEditDateDialog() {
            this.edit_date_dialog = false
            this.edit_date_school = null
            this.edit_date_licence_id = null
            this.edit_school_licence = null
            this.edit_valid_until = ''
            this.edit_no_date = false
        },
        async saveEditedDate() {
            const schoolId = this.editDateSchoolId
            const licenceId = this.edit_school_licence?.id ?? this.edit_date_licence_id
            if (!licenceId || !schoolId) return
            if (!this.edit_no_date && !this.edit_valid_until) return

            const data = {
                school_id: schoolId,
                licence_id: licenceId,
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
            const model = this.normalizeLicenceModel(licence || null)
            if (model.user_licence_enabled || (model.user_role_names || []).length >= 1) return true
            return Object.entries(model.user_licence_required_by_role || {}).some(
                ([roleName, isRequired]) => !!isRequired && !this.looksLikeAdminRoleName(roleName)
            )
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
            if (this.action === this.model_lock_action) {
                this.action = ''
            }
        },
        normalizeLicenceModel(licenceModel) {
            const fallback = {
                school_licence_required: true,
                school_licence_enabled: true,
                admin_licence_enabled: false,
                user_licence_enabled: false,
                admin_role_names: [],
                user_role_names: [],
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

            const source =
                licenceModel.licence_model && typeof licenceModel.licence_model === 'object'
                    ? {
                        ...licenceModel.licence_model,
                        school_licence_required:
                            Object.prototype.hasOwnProperty.call(licenceModel, 'school_licence_required')
                                ? licenceModel.school_licence_required
                                : licenceModel.licence_model.school_licence_required,
                        school_licence_enabled:
                            Object.prototype.hasOwnProperty.call(licenceModel, 'school_licence_enabled')
                                ? licenceModel.school_licence_enabled
                                : licenceModel.licence_model.school_licence_enabled,
                        admin_licence_enabled:
                            Object.prototype.hasOwnProperty.call(licenceModel, 'admin_licence_enabled')
                                ? licenceModel.admin_licence_enabled
                                : licenceModel.licence_model.admin_licence_enabled,
                        admin_role_names:
                            Object.prototype.hasOwnProperty.call(licenceModel, 'admin_role_names')
                                ? licenceModel.admin_role_names
                                : licenceModel.licence_model.admin_role_names,
                        user_licence_enabled:
                            Object.prototype.hasOwnProperty.call(licenceModel, 'user_licence_enabled')
                                ? licenceModel.user_licence_enabled
                                : licenceModel.licence_model.user_licence_enabled,
                        user_role_names:
                            Object.prototype.hasOwnProperty.call(licenceModel, 'user_role_names')
                                ? licenceModel.user_role_names
                                : licenceModel.licence_model.user_role_names,
                    }
                    : licenceModel

            const normalizeRoleNames = (roleNames) => {
                const items = Array.isArray(roleNames) ? roleNames : []
                return items
                    .map((roleName) => (typeof roleName === 'string' ? roleName.trim() : ''))
                    .filter((roleName) => !!roleName)
                    .filter((roleName, index, all) => all.indexOf(roleName) === index)
            }

            const affectedRolesRaw = Array.isArray(source.affected_roles) ? source.affected_roles : []
            const admin_role_names = normalizeRoleNames(source.admin_role_names)
            const user_role_names = normalizeRoleNames(source.user_role_names)
            const affected_roles = []
            for (const roleName of [...affectedRolesRaw, ...admin_role_names, ...user_role_names]) {
                if (typeof roleName !== 'string') continue
                const trimmed = roleName.trim()
                if (!trimmed || affected_roles.includes(trimmed)) continue
                affected_roles.push(trimmed)
            }

            const rawMap =
                source.user_licence_required_by_role && typeof source.user_licence_required_by_role === 'object'
                    ? source.user_licence_required_by_role
                    : {}
            const user_licence_required_by_role = {}
            for (const roleName of affected_roles) {
                user_licence_required_by_role[roleName] = this.toBool(rawMap[roleName], false)
            }

            const rawPlansByRole =
                source.user_licence_plans_by_role && typeof source.user_licence_plans_by_role === 'object'
                    ? source.user_licence_plans_by_role
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

            const requiredRoleNames = affected_roles.filter((roleName) => !!user_licence_required_by_role[roleName])
            const inferredAdminRoleNames = requiredRoleNames.filter((roleName) => this.looksLikeAdminRoleName(roleName))
            const inferredUserRoleNames = requiredRoleNames.filter((roleName) => !this.looksLikeAdminRoleName(roleName))
            const normalizedAdminRoleNames = admin_role_names.length >= 1 ? admin_role_names : inferredAdminRoleNames
            const normalizedUserRoleNames = user_role_names.length >= 1 ? user_role_names : inferredUserRoleNames

            return {
                school_licence_required: this.toBool(source.school_licence_required, true),
                school_licence_enabled: this.toBool(source.school_licence_enabled, this.toBool(source.school_licence_required, true)),
                admin_licence_enabled: this.toBool(source.admin_licence_enabled, normalizedAdminRoleNames.length > 0),
                user_licence_enabled: this.toBool(source.user_licence_enabled, normalizedUserRoleNames.length > 0),
                admin_role_names: normalizedAdminRoleNames,
                user_role_names: normalizedUserRoleNames,
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
        looksLikeAdminRoleName(roleName) {
            const normalized = String(roleName || '')
                .trim()
                .toLowerCase()

            return normalized.includes('admin') || normalized === 'super_admin'
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

.assignment-school-licence-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    width: 100%;
    padding: 10px 0;
    border-bottom: 1px solid rgba(16, 38, 58, 0.08);
}

.assignment-school-licence-row__right {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 8px;
    flex-shrink: 0;
    width: min(220px, 100%);
}

.assignment-school-licence-row__action {
    justify-content: flex-start;
    text-transform: none;
}

.assignment-school-licence-list {
    display: grid;
    gap: 0;
    align-items: stretch;
}

.assignment-school-licence-row:last-child {
    border-bottom: 0;
    padding-bottom: 0;
}

.assignment-school-licence-row:first-child {
    padding-top: 0;
}

.assignment-school-licence-row__name {
    font-size: 0.94rem;
    font-weight: 700;
    color: #10263a;
    line-height: 1.3;
    min-width: 0;
}

.assignment-schools-item :deep(.licence-card__title) {
    color: rgb(var(--v-theme-primary));
}

.assignment-school-licence-row__valid-until {
    display: flex;
    align-items: center;
    margin-top: 3px;
    font-size: 0.78rem;
    font-weight: 500;
    color: rgba(16, 38, 58, 0.6);
}

.assignment-school-licence-row__counter {
    display: flex;
    align-items: center;
    margin-top: 2px;
    font-size: 0.78rem;
    font-weight: 500;
    color: rgba(16, 38, 58, 0.6);
}

.assignment-school-licence-row__counter-expired.is-highlighted {
    color: rgb(var(--v-theme-error));
    font-weight: 700;
}

.edit-licence-tabs {
    border-bottom: 1px solid rgba(16, 38, 58, 0.1);
}

.edit-licence-section-title {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: rgba(16, 38, 58, 0.5);
    margin-bottom: 10px;
}

.edit-licence-price-grid {
    display: grid;
    gap: 2px;
    border: 1px solid rgba(16, 38, 58, 0.1);
    border-radius: 10px;
    overflow: hidden;
}

.edit-licence-price-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 9px 14px;
    background: rgba(255, 255, 255, 0.7);
    gap: 12px;
}

.edit-licence-price-row:nth-child(even) {
    background: rgba(246, 249, 255, 0.9);
}

.edit-licence-price-row--sum {
    border-top: 1px solid rgba(16, 38, 58, 0.15);
    margin-top: 2px;
    padding-top: 4px;
    background: transparent !important;
}

.admin-user-row {
    border-bottom: 1px solid rgba(16, 38, 58, 0.08);
    padding: 4px 0;
}

.admin-user-row.is-editing {
    border-bottom-color: rgba(79, 120, 196, 0.18);
}

.admin-user-row__main {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.admin-user-row__name {
    font-size: 0.875rem;
    line-height: 1.4;
}

.admin-user-row__title {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.admin-user-row__status-icon {
    flex-shrink: 0;
}

.admin-user-row__email {
    display: block;
    font-size: 0.78rem;
    color: rgba(16, 38, 58, 0.55);
}

.admin-user-row__meta {
    display: block;
    font-size: 0.76rem;
    color: rgba(16, 38, 58, 0.68);
}

.admin-user-row__editing-hint {
    display: inline-flex;
    align-items: center;
    padding: 3px 8px;
    border-radius: 999px;
    background: rgba(79, 120, 196, 0.12);
    color: rgb(79, 120, 196);
    font-size: 0.74rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    white-space: nowrap;
}

.admin-user-row__actions {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}

.admin-user-edit {
    padding: 8px 0 4px;
    border-top: 1px solid rgba(16, 38, 58, 0.06);
    margin-top: 4px;
}

.edit-licence-price-label {
    font-size: 0.84rem;
    color: rgba(16, 38, 58, 0.65);
}

.edit-licence-price-value {
    font-size: 0.9rem;
    font-weight: 700;
    color: #10263a;
    white-space: nowrap;
}

.user-licence-user-dialog-solid {
    background: rgb(var(--v-theme-surface));
    border: 1px solid rgba(16, 38, 58, 0.12);
    box-shadow: 0 16px 40px rgba(16, 38, 58, 0.16);
}
</style>
