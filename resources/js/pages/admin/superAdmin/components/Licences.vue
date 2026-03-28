<template>
    <v-col cols="12" :xl="licencesMainXlCols">
        <section class="crud-shell admin-card ai-glass-panel" :class="{ 'is-disabled': action != '' }">
            <div class="admin-card-head crud-head mb-4">
                <div>
                    <div class="admin-card-eyebrow">Verwaltung</div>
                    <h2 class="admin-card-title crud-title">Lizenzen</h2>
                </div>

                <div class="admin-kpi-grid crud-kpis">
                    <div class="kpi-card ai-glass-panel">
                        <div class="kpi-label">Gesamt</div>
                        <div class="kpi-value">{{ totalLicencesCount }}</div>
                    </div>
                </div>
            </div>

            <div class="crud-content-grid">
                <section class="admin-card ai-glass-panel crud-main-card pa-3">
                    <div class="empty-state pa-2" v-if="licences.length === 0">Keine Lizenzen gefunden.</div>
                    <div class="empty-state pa-2" v-else>
                        <v-list
                            dense
                            variant="flat"
                            class="crud-list licences-list"
                            select-strategy="leaf"
                            v-model:selected="selected_licences"
                            @update:selected="onSelectedLicencesUpdate"
                            color="success-lighten-2">
                            <v-list-item
                                v-for="item in licences"
                                :key="item.id"
                                :value="item.id"
                                class="crud-list-item licences-list-item"
                                :class="{ 'is-selected': isSelectedLicence(item.id) }">
                                <template #title>
                                    <article class="licence-card">
                                        <header class="licence-card__header">
                                            <div class="licence-card__title-wrap">
                                                <div class="licence-card__title">{{ item.name }}</div>
                                                <div class="licence-card__subtitle">{{ item.long_name || 'Keine Beschreibung hinterlegt' }}</div>
                                                <div v-if="overviewDateRangeLabel(item.start_day_month, item.end_day_month)" class="licence-card__subtitle">
                                                    {{ overviewDateRangeLabel(item.start_day_month, item.end_day_month) }}
                                                </div>
                                            </div>
                                            <div class="licence-card__type-icons">
                                                <v-tooltip text="Schullizenz" location="top">
                                                    <template #activator="{ props }">
                                                        <div
                                                            v-if="licenceModelFor(item).school_licence_enabled"
                                                            v-bind="props"
                                                            class="licence-card__type-icon">
                                                            <v-icon size="18" icon="mdi-domain" />
                                                        </div>
                                                    </template>
                                                </v-tooltip>
                                                <v-tooltip text="Adminlizenz" location="top">
                                                    <template #activator="{ props }">
                                                        <div
                                                            v-if="licenceModelFor(item).admin_licence_enabled"
                                                            v-bind="props"
                                                            class="licence-card__type-icon licence-card__type-icon--admin">
                                                            <v-icon size="18" icon="mdi-shield-crown-outline" />
                                                        </div>
                                                    </template>
                                                </v-tooltip>
                                                <v-tooltip text="Userlizenz" location="top">
                                                    <template #activator="{ props }">
                                                        <div
                                                            v-if="licenceModelFor(item).user_licence_enabled"
                                                            v-bind="props"
                                                            class="licence-card__type-icon licence-card__type-icon--user">
                                                            <v-icon size="18" icon="mdi-account-multiple" />
                                                        </div>
                                                    </template>
                                                </v-tooltip>
                                            </div>
                                        </header>

                                        <div class="licence-card__roles-block">
                                            <div class="licence-card__roles-row">
                                                <div class="licence-card__roles-title">Admin-Rollen</div>
                                                <div class="licence-card__roles-list">
                                                <template v-if="sortedRoleNames(licenceModelFor(item).admin_role_names).length >= 1">
                                                    <v-chip
                                                        v-for="roleName in sortedRoleNames(licenceModelFor(item).admin_role_names)"
                                                        :key="`licence-overview-admin-role-${item.id}-${roleName}`"
                                                        size="small"
                                                        class="licence-card__role-chip"
                                                        color="warning"
                                                        variant="flat">
                                                        {{ displayRoleName(roleName) }}
                                                    </v-chip>
                                                </template>
                                                <span v-else class="text-medium-emphasis">Keine Rollen hinterlegt</span>
                                                </div>
                                            </div>
                                            <div class="licence-card__roles-row mt-3">
                                                <div class="licence-card__roles-title">User-Rollen</div>
                                                <div class="licence-card__roles-list">
                                                <template v-if="sortedRoleNames(licenceModelFor(item).user_role_names).length >= 1">
                                                    <v-chip
                                                        v-for="roleName in sortedRoleNames(licenceModelFor(item).user_role_names)"
                                                        :key="`licence-overview-user-role-${item.id}-${roleName}`"
                                                        size="small"
                                                        class="licence-card__role-chip"
                                                        color="primary"
                                                        variant="flat">
                                                        {{ displayRoleName(roleName) }}
                                                    </v-chip>
                                                </template>
                                                <span v-else class="text-medium-emphasis">Keine Rollen hinterlegt</span>
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                </template>
                            </v-list-item>
                        </v-list>
                    </div>

                    <div class="empty-state crud-pagination mt-3 pa-3">
                        <Pagination :meta="safeMeta" :store="licenceStore" selected_field="selected_licences" />
                    </div>
                </section>

                <aside class="crud-side-stack">
                    <section class="admin-card ai-glass-panel">
                        <div class="admin-card-head mb-2">
                            <div>
                                <div class="admin-card-eyebrow">Aktionen</div>
                                <h3 class="admin-card-title">Lizenzen verwalten</h3>
                            </div>
                        </div>
                        <div class="kpi-sub" style="margin-top: -2px">Anlegen, ändern, modellieren oder löschen.</div>

                        <div class="crud-actions-primary">
                            <v-btn block color="primary" variant="flat" rounded="lg" prepend-icon="mdi-plus" @click="createLicence">
                                Hinzufügen
                            </v-btn>
                        </div>

                        <template v-if="selected_licences.length === 1">
                            <v-divider class="crud-actions-divider" />
                            <div class="crud-actions-secondary">
                                <v-btn
                                    block
                                    color="primary"
                                    variant="tonal"
                                    rounded="lg"
                                    prepend-icon="mdi-pencil"
                                    @click="editLicence(selected_licences[0])">
                                    Ändern
                                </v-btn>
                                <v-btn
                                    block
                                    color="primary"
                                    variant="tonal"
                                    rounded="lg"
                                    prepend-icon="mdi-shape-outline"
                                    @click="openLicenceModel">
                                    Lizenzmodell
                                </v-btn>
                                <v-btn
                                    block
                                    color="warning"
                                    variant="tonal"
                                    rounded="lg"
                                    class="crud-action-btn-offset"
                                    prepend-icon="mdi-delete"
                                    @click="deleteLicence">
                                    Löschen
                                </v-btn>
                            </div>
                        </template>
                    </section>
                </aside>
            </div>
        </section>
    </v-col>

    <v-dialog v-model="licenceDialogOpen" persistent :max-width="licenceDialogMaxWidth" scrollable>
        <v-card class="crud-dialog-card ai-glass-panel">
            <div class="crud-dialog-head">
                <div>
                    <div class="admin-card-eyebrow">Lizenz</div>
                    <div class="admin-card-title" style="margin-top: 4px">{{ licenceDialogTitle }}</div>
                </div>

                <v-btn icon="mdi-close" variant="text" rounded="lg" @click="abort" />
            </div>

            <v-card-text class="crud-dialog-body">
                <v-form ref="form" v-model="is_valid" @submit.prevent="saveLicence(data)" class="mb-2 crud-form">
                    <div class="empty-state crud-form-section">
                        <v-row dense>
                            <v-col cols="12">
                                <v-text-field autofocus v-model="data.name" label="Lizenz Bezeichnung" :rules="[required(), maxLength(255)]" />
                            </v-col>
                            <v-col cols="12">
                                <v-text-field v-model="data.long_name" label="Kurze Beschreibung" :rules="[maxLength(255)]" />
                            </v-col>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="data.price_per_year"
                                    label="Kosten pro Jahr"
                                    inputmode="numeric"
                                    :rules="[positiveIntegerOrNull(), maxLength(255)]" />
                            </v-col>
                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="data.start_day_month"
                                    label="Start-Datum"
                                    placeholder="TT.MM."
                                    maxlength="6"
                                    :rules="[required(), validDayMonth()]"
                                    @blur="normalizeLicenceDayMonthField('start_day_month')" />
                            </v-col>
                            <v-col cols="12" md="6">
                                <div class="text-caption text-medium-emphasis mt-2">
                                    End-Datum wird automatisch auf den Vortag gesetzt.
                                </div>
                            </v-col>
                        </v-row>
                    </div>

                    <div class="crud-form-actions d-flex flex-row align-center justify-space-between mt-4" :disabled="is_uploading">
                        <v-btn color="warning" variant="text" rounded="lg" @click="abort">Abbruch</v-btn>
                        <v-btn color="success" variant="flat" rounded="lg" type="submit">Speichern</v-btn>
                    </div>
                </v-form>
            </v-card-text>
        </v-card>
    </v-dialog>

    <v-dialog v-model="licenceDeleteDialogOpen" persistent max-width="640" scrollable>
        <v-card class="crud-dialog-card ai-glass-panel">
            <div class="crud-dialog-head">
                <div>
                    <div class="admin-card-eyebrow crud-delete-eyebrow">Achtung</div>
                    <div class="admin-card-title" style="margin-top: 4px">Löschen bestätigen</div>
                </div>
                <v-btn icon="mdi-close" variant="text" rounded="lg" @click="abort" />
            </div>

            <v-card-text class="crud-dialog-body">
                <v-form ref="deleteForm" v-model="is_valid" @submit.prevent="doDeleteLicences(selected_licences)" class="crud-form">
                    <div class="empty-state crud-delete-alert">
                        <div class="admin-card-eyebrow crud-delete-eyebrow">Achtung</div>
                        <div class="admin-card-title crud-delete-title">Lizenz löschen</div>
                        <div class="kpi-sub mt-2" v-if="selected_licences.length == 1">
                            Es soll eine Lizenz gelöscht werden. Sind Sie sicher, dass Sie die markierte Lizenz löschen möchten?
                        </div>
                    </div>

                    <div class="crud-form-actions d-flex flex-row align-center justify-space-between mt-4">
                        <v-btn color="success" variant="text" rounded="lg" @click="abort">Abbruch</v-btn>
                        <v-btn color="error" variant="flat" rounded="lg" type="submit" prepend-icon="mdi-delete">Löschen</v-btn>
                    </div>
                </v-form>
            </v-card-text>
        </v-card>
    </v-dialog>

    <v-dialog v-model="licenceModelDialogOpen" persistent :max-width="licenceModelDialogMaxWidth" scrollable>
        <v-card class="crud-dialog-card ai-glass-panel">
            <div class="crud-dialog-head">
                <div>
                    <div class="admin-card-eyebrow">Lizenzmodell</div>
                    <div class="admin-card-title" style="margin-top: 4px">{{ selectedLicence ? selectedLicence.name : 'Lizenzmodell' }}</div>
                </div>
                <v-btn icon="mdi-close" variant="text" rounded="lg" @click="closeLicenceModel" />
            </div>

            <v-card-text class="crud-dialog-body">
                <v-form ref="licenceModelForm" v-model="is_licence_model_valid" class="crud-form">
                    <div class="empty-state crud-form-section">
                        <v-alert type="info" variant="outlined" class="mb-4 text-subtitle-1" v-if="selectedLicence">
                            Ausgewählte Lizenz: <strong>{{ selectedLicence.name }}</strong>
                        </v-alert>

                        <v-card variant="outlined" class="pa-4 mb-4" v-if="currentLicenceModel">
                            <div class="text-subtitle-1 mb-3">Schullizenz</div>
                            <v-btn-toggle
                                :model-value="currentLicenceModel.school_licence_enabled"
                                mandatory
                                divided
                                color="primary"
                                @update:model-value="setSchoolLicenceEnabled">
                                <v-btn :value="true">JA</v-btn>
                                <v-btn :value="false">NEIN</v-btn>
                            </v-btn-toggle>

                            <v-text-field
                                v-if="currentLicenceModel.school_licence_enabled"
                                v-model="currentLicenceModel.school_price_per_year"
                                class="mt-4"
                                label="Basis-Tarif pro Jahr"
                                inputmode="numeric"
                                :rules="[positiveIntegerOrNull(), maxLength(255)]" />

                            <template v-if="currentLicenceModel.school_licence_enabled">
                                <v-checkbox
                                    v-model="currentLicenceModel.school_storage_enabled"
                                    class="mt-2"
                                    color="primary"
                                    density="compact"
                                    hide-details
                                    label="Speicherstaffel verwenden"
                                    @update:model-value="setStorageEnabled('school', $event)" />

                                <v-row dense class="mt-1">
                                    <template v-if="currentLicenceModel.school_storage_enabled">
                                    <v-col cols="12" md="4">
                                        <v-text-field
                                            v-model="currentLicenceModel.school_included_storage_gb"
                                            label="Inkl. Speicher (GB)"
                                            inputmode="numeric"
                                            :rules="[positiveIntegerOrNull(), maxLength(255)]" />
                                    </v-col>
                                    <v-col cols="12" md="4">
                                        <v-text-field
                                            v-model="currentLicenceModel.school_extra_storage_step_gb"
                                            label="Je weitere (GB)"
                                            inputmode="numeric"
                                            :rules="[positiveIntegerOrNull(), maxLength(255)]" />
                                    </v-col>
                                    <v-col cols="12" md="4">
                                        <v-text-field
                                            v-model="currentLicenceModel.school_extra_storage_step_price"
                                            label="Mehrpreis pro Jahr"
                                            inputmode="numeric"
                                            :rules="[positiveIntegerOrNull(), maxLength(255)]" />
                                    </v-col>
                                    </template>
                                </v-row>
                            </template>
                        </v-card>

                        <v-card variant="outlined" class="pa-4 mb-4" v-if="currentLicenceModel">
                            <div class="text-subtitle-1 mb-3">Admin-Lizenz</div>
                            <v-btn-toggle
                                :model-value="currentLicenceModel.admin_licence_enabled"
                                mandatory
                                divided
                                color="primary"
                                @update:model-value="setAdminLicenceEnabled">
                                <v-btn :value="true">JA</v-btn>
                                <v-btn :value="false">NEIN</v-btn>
                            </v-btn-toggle>

                            <template v-if="currentLicenceModel.admin_licence_enabled">
                                <v-text-field
                                    v-model="currentLicenceModel.admin_price_per_year"
                                    class="mt-4"
                                    label="Basis-Tarif pro Monat"
                                    inputmode="numeric"
                                    :rules="[positiveIntegerOrNull(), maxLength(255)]" />

                                <v-checkbox
                                    v-model="currentLicenceModel.admin_storage_enabled"
                                    class="mt-2"
                                    color="primary"
                                    density="compact"
                                    hide-details
                                    label="Speicherstaffel verwenden"
                                    @update:model-value="setStorageEnabled('admin', $event)" />

                                <v-row dense class="mt-1">
                                    <template v-if="currentLicenceModel.admin_storage_enabled">
                                    <v-col cols="12" md="4">
                                        <v-text-field
                                            v-model="currentLicenceModel.admin_included_storage_gb"
                                            label="Inkl. Speicher (GB)"
                                            inputmode="numeric"
                                            :rules="[positiveIntegerOrNull(), maxLength(255)]" />
                                    </v-col>
                                    <v-col cols="12" md="4">
                                        <v-text-field
                                            v-model="currentLicenceModel.admin_extra_storage_step_gb"
                                            label="Je weitere (GB)"
                                            inputmode="numeric"
                                            :rules="[positiveIntegerOrNull(), maxLength(255)]" />
                                    </v-col>
                                    <v-col cols="12" md="4">
                                        <v-text-field
                                            v-model="currentLicenceModel.admin_extra_storage_step_price"
                                            label="Mehrpreis pro Monat"
                                            inputmode="numeric"
                                            :rules="[positiveIntegerOrNull(), maxLength(255)]" />
                                    </v-col>
                                    </template>
                                </v-row>
                            </template>

                            <div class="text-subtitle-2 mt-4 mb-3">Zuordnung von Rollen</div>
                            <div class="d-flex flex-row flex-wrap ga-2" v-if="availableRoles.length >= 1">
                                <v-chip
                                    v-for="role in availableRoles"
                                    :key="`licence-model-admin-role-${role.id}`"
                                    clickable
                                    :color="isAdminRoleSelected(role.name) ? 'warning' : undefined"
                                    :variant="isAdminRoleSelected(role.name) ? 'flat' : 'outlined'"
                                    @click="toggleAdminRole(role.name)">
                                    {{ role.name }}
                                </v-chip>
                            </div>
                            <div class="text-caption text-medium-emphasis mt-3" v-if="currentLicenceModel.admin_role_names.length >= 1">
                                Ausgewählt: {{ formatSelectedRoleNames(currentLicenceModel.admin_role_names) }}
                            </div>
                            <div class="text-caption text-medium-emphasis mt-3" v-else>Noch keine Rollen ausgewählt.</div>
                        </v-card>

                        <v-card variant="outlined" class="pa-4" v-if="currentLicenceModel">
                            <div class="text-subtitle-1 mb-3">User-Lizenz</div>
                            <v-btn-toggle
                                :model-value="currentLicenceModel.user_licence_enabled"
                                mandatory
                                divided
                                color="primary"
                                @update:model-value="setUserLicenceEnabled">
                                <v-btn :value="true">JA</v-btn>
                                <v-btn :value="false">NEIN</v-btn>
                            </v-btn-toggle>

                            <template v-if="currentLicenceModel.user_licence_enabled">
                                <v-text-field
                                    v-model="currentLicenceModel.user_price_per_year"
                                    class="mt-4"
                                    label="Basis-Tarif pro Monat"
                                    inputmode="numeric"
                                    :rules="[positiveIntegerOrNull(), maxLength(255)]" />

                                <v-checkbox
                                    v-model="currentLicenceModel.user_storage_enabled"
                                    class="mt-2"
                                    color="primary"
                                    density="compact"
                                    hide-details
                                    label="Speicherstaffel verwenden"
                                    @update:model-value="setStorageEnabled('user', $event)" />

                                <v-row dense class="mt-1">
                                    <template v-if="currentLicenceModel.user_storage_enabled">
                                    <v-col cols="12" md="4">
                                        <v-text-field
                                            v-model="currentLicenceModel.user_included_storage_gb"
                                            label="Inkl. Speicher (GB)"
                                            inputmode="numeric"
                                            :rules="[positiveIntegerOrNull(), maxLength(255)]" />
                                    </v-col>
                                    <v-col cols="12" md="4">
                                        <v-text-field
                                            v-model="currentLicenceModel.user_extra_storage_step_gb"
                                            label="Je weitere (GB)"
                                            inputmode="numeric"
                                            :rules="[positiveIntegerOrNull(), maxLength(255)]" />
                                    </v-col>
                                    <v-col cols="12" md="4">
                                        <v-text-field
                                            v-model="currentLicenceModel.user_extra_storage_step_price"
                                            label="Mehrpreis pro Monat"
                                            inputmode="numeric"
                                            :rules="[positiveIntegerOrNull(), maxLength(255)]" />
                                    </v-col>
                                    </template>
                                </v-row>
                            </template>

                            <div class="text-subtitle-2 mt-4 mb-3">Zuordnung von Rollen</div>
                            <div class="d-flex flex-wrap ga-2 mb-3" v-if="availableRoles.length >= 1">
                                <v-btn
                                    color="primary"
                                    :variant="usesAllUserRoles() ? 'flat' : 'tonal'"
                                    rounded="lg"
                                    size="small"
                                    @click="selectAllUserRoles">
                                    Alle
                                </v-btn>
                            </div>
                            <div class="d-flex flex-row flex-wrap ga-2" v-if="availableRoles.length >= 1">
                                <v-chip
                                    v-for="role in availableRoles"
                                    :key="`licence-model-user-role-${role.id}`"
                                    clickable
                                    :color="isUserRoleSelected(role.name) ? 'primary' : undefined"
                                    :variant="isUserRoleSelected(role.name) ? 'flat' : 'outlined'"
                                    @click="toggleUserRole(role.name)">
                                    {{ role.name }}
                                </v-chip>
                            </div>
                            <div class="text-caption text-medium-emphasis mt-3" v-if="currentLicenceModel.user_role_names.length >= 1">
                                Ausgewählt: {{ formatSelectedRoleNames(currentLicenceModel.user_role_names) }}
                            </div>
                            <div class="text-caption text-medium-emphasis mt-3" v-else>Noch keine Rollen ausgewählt.</div>
                            <div class="text-caption text-medium-emphasis mt-3" v-if="availableRoles.length === 0">
                                Keine Rollen verfügbar.
                            </div>
                        </v-card>
                    </div>

                    <div class="crud-form-actions d-flex flex-row align-center justify-space-between mt-4">
                        <v-btn color="warning" variant="text" rounded="lg" @click="closeLicenceModel">Abbrechen</v-btn>
                        <v-btn color="success" variant="flat" rounded="lg" @click="saveCurrentLicenceModel">Speichern</v-btn>
                    </div>
                </v-form>
            </v-card-text>
        </v-card>
    </v-dialog>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import Pagination from '@/pages/components/Pagination.vue'

// SPECIFIC

import { useLicenceStore } from '@/stores/admin/LicenceStore'
import { useRoleStore } from '@/stores/admin/RoleStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { Pagination },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.licenceStore = useLicenceStore()
        this.roleStore = useRoleStore()
        await this.licenceStore.index()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            licenceStore: null,
            roleStore: null,
            is_valid: false,
            upload_file: null,
            is_uploading: false,
            licence_models: {},
            is_licence_model_valid: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapWritableState(useLicenceStore, ['licences', 'meta', 'selected_licences', 'search_string', 'data']),
        licenceDialogOpen: {
            get() {
                return ['create_licence', 'edit_licence'].includes(this.action)
            },
            set(value) {
                if (!value) this.action = ''
            },
        },
        licenceDialogMaxWidth() {
            return 760
        },
        licenceDialogTitle() {
            if (this.action == 'create_licence') return 'Neue Lizenz'
            if (this.action == 'edit_licence') return 'Lizenz ändern'
            return 'Lizenz'
        },
        licenceDeleteDialogOpen: {
            get() {
                return this.action === 'delete_licence'
            },
            set(value) {
                if (!value) this.action = ''
            },
        },
        licenceModelDialogOpen: {
            get() {
                return this.action === 'licence_model'
            },
            set(value) {
                if (!value) this.action = ''
            },
        },
        licenceModelDialogMaxWidth() {
            return 1100
        },
        licencesMainXlCols() {
            return 11
        },
        totalLicencesCount() {
            const total = Number(this.meta?.total)
            return Number.isFinite(total) && total >= 0 ? total : this.licences.length
        },
        safeMeta() {
            return {
                from: this.meta?.from ?? 0,
                to: this.meta?.to ?? 0,
                total: this.meta?.total ?? this.licences.length,
                current_page: this.meta?.current_page ?? 1,
                last_page: this.meta?.last_page ?? 1,
            }
        },
        selectedLicenceId() {
            return this.selected_licences[0] ?? null
        },
        selectedLicence() {
            return this.licences.find((item) => item.id === this.selectedLicenceId) || null
        },
        currentLicenceModel() {
            const licenceId = this.selectedLicenceId
            if (!licenceId) return null

            if (!this.licence_models[licenceId]) {
                this.licence_models[licenceId] = this.normalizeLicenceModel(this.selectedLicence?.licence_model || null)
            }

            return this.licence_models[licenceId]
        },
        availableRoles() {
            return this.roleStore?.roles ?? []
        },
    },

    methods: {
        deleteLicence() {
            this.action = 'delete_licence'
        },
        async doDeleteLicences(selected_licences) {
            if (!(await this.licenceStore.deleteLicence(selected_licences))) return
            this.selected_licences = []
            await this.licenceStore.index()
            this.action = ''
        },

        async saveLicence(data) {
            if (this.is_uploading) return
            this.is_valid = false
            this.normalizeLicenceDayMonthField('start_day_month')
            await this.$refs.form.validate()
            if (!this.is_valid) return

            if (data.id) {
                if (!(await this.licenceStore.update(data))) return
            } else {
                if (!(await this.licenceStore.store(data))) return
            }
            this.data = {}
            this.action = ''
        },

        createLicence() {
            this.data = {
                is_selectable: true,
                start_day_month: '',
            }
            this.action = 'create_licence'
        },

        editLicence(licence_id) {
            const licence = this.licences.find((s) => s.id === licence_id)
            this.data = JSON.parse(JSON.stringify(licence))
            this.action = 'edit_licence'
        },
        async openLicenceModel() {
            if (!this.selectedLicenceId) return
            this.action = 'licence_model'
            if (this.availableRoles.length === 0) {
                await this.roleStore.loadRoles()
            }
            this.licence_models[this.selectedLicenceId] = this.normalizeLicenceModel(this.selectedLicence?.licence_model || null)
        },
        closeLicenceModel() {
            this.action = ''
        },
        async saveCurrentLicenceModel() {
            if (!this.selectedLicenceId || !this.currentLicenceModel) return
            this.is_licence_model_valid = false
            const validationResult = await this.$refs.licenceModelForm?.validate()
            const isValid =
                typeof validationResult === 'object' && validationResult !== null && Object.prototype.hasOwnProperty.call(validationResult, 'valid')
                    ? !!validationResult.valid
                    : validationResult !== false
            this.is_licence_model_valid = isValid
            if (!isValid) return

            const payload = this.normalizeLicenceModel(this.currentLicenceModel)
            const saved = await this.licenceStore.saveLicenceModel(this.selectedLicenceId, payload)
            if (!saved) return

            this.licence_models[this.selectedLicenceId] = this.normalizeLicenceModel(saved.licence_model || null)
            this.closeLicenceModel()
        },
        abort() {
            this.action = ''
        },
        onSelectedLicencesUpdate(value) {
            if (!Array.isArray(value)) {
                this.selected_licences = []
                return
            }

            if (value.length <= 1) {
                this.selected_licences = value
                return
            }

            this.selected_licences = [value[value.length - 1]]
        },
        isSelectedLicence(id) {
            return this.selected_licences.includes(id)
        },
        setSchoolLicenceEnabled(value) {
            if (typeof value !== 'boolean') return
            if (!this.currentLicenceModel) return
            this.currentLicenceModel.school_licence_enabled = value
        },
        setAdminLicenceEnabled(value) {
            if (typeof value !== 'boolean') return
            if (!this.currentLicenceModel) return
            this.currentLicenceModel.admin_licence_enabled = value
        },
        setUserLicenceEnabled(value) {
            if (typeof value !== 'boolean') return
            if (!this.currentLicenceModel) return
            this.currentLicenceModel.user_licence_enabled = value
        },
        setStorageEnabled(layerName, value) {
            if (typeof value !== 'boolean') return
            if (!this.currentLicenceModel) return

            const normalizedLayerName = String(layerName ?? '').trim()
            if (!['school', 'admin', 'user'].includes(normalizedLayerName)) return

            this.currentLicenceModel[`${normalizedLayerName}_storage_enabled`] = value

            if (value) {
                return
            }

            this.currentLicenceModel[`${normalizedLayerName}_included_storage_gb`] = ''
            this.currentLicenceModel[`${normalizedLayerName}_extra_storage_step_gb`] = ''
            this.currentLicenceModel[`${normalizedLayerName}_extra_storage_step_price`] = ''
        },
        isAdminRoleSelected(roleName) {
            if (!this.currentLicenceModel) return false
            return this.currentLicenceModel.admin_role_names.includes(roleName)
        },
        toggleAdminRole(roleName) {
            if (!this.currentLicenceModel) return
            const roles = this.currentLicenceModel.admin_role_names
            const index = roles.indexOf(roleName)
            if (index >= 0) {
                roles.splice(index, 1)
                return
            }
            roles.push(roleName)
        },
        isUserRoleSelected(roleName) {
            if (!this.currentLicenceModel) return false
            if (this.usesAllUserRoles()) return true
            return this.currentLicenceModel.user_role_names.includes(roleName)
        },
        toggleUserRole(roleName) {
            if (!this.currentLicenceModel) return
            if (this.usesAllUserRoles()) {
                this.currentLicenceModel.user_role_names = []
            }
            const roles = this.currentLicenceModel.user_role_names
            const index = roles.indexOf(roleName)
            if (index >= 0) {
                roles.splice(index, 1)
                return
            }
            roles.push(roleName)
        },
        usesAllUserRoles() {
            if (!this.currentLicenceModel) return false
            return this.currentLicenceModel.user_role_names.includes('*')
        },
        selectAllUserRoles() {
            if (!this.currentLicenceModel) return
            this.currentLicenceModel.user_role_names = ['*']
        },
        licenceModelFor(licence) {
            return this.normalizeLicenceModel(licence?.licence_model || null)
        },
        hasVisibleOverviewLicenceBadges(licenceModel) {
            if (!licenceModel || typeof licenceModel !== 'object') {
                return false
            }

            return !!(licenceModel.school_licence_enabled || licenceModel.admin_licence_enabled || licenceModel.user_licence_enabled)
        },
        sortedRoleNames(roleNames) {
            const roles = Array.isArray(roleNames) ? [...roleNames] : []
            if (roles.includes('*')) {
                return ['*']
            }
            return roles.sort((a, b) => String(a).localeCompare(String(b), 'de'))
        },
        displayRoleName(roleName) {
            return roleName === '*' ? 'Alle' : roleName
        },
        formatSelectedRoleNames(roleNames) {
            return this.sortedRoleNames(roleNames)
                .map((roleName) => this.displayRoleName(roleName))
                .join(', ')
        },
        toBool(value, fallback = false) {
            if (typeof value === 'boolean') {
                return value
            }

            if (typeof value === 'string') {
                const normalized = value.trim().toLowerCase()
                if (['1', 'true', 'yes', 'ja'].includes(normalized)) {
                    return true
                }
                if (['0', 'false', 'no', 'nein'].includes(normalized)) {
                    return false
                }
            }

            if (typeof value === 'number') {
                return value !== 0
            }

            return fallback
        },
        positiveIntegerOrNull() {
            return (value) => {
                const normalized = String(value ?? '').trim()
                return normalized === '' || /^[1-9][0-9]*$/.test(normalized) || 'Es muss sich um eine positive ganze Zahl handeln oder leer sein.'
            }
        },
        validDayMonth() {
            return (value) => this.isValidDayMonthValue(value) || 'Das Datum muss im Format TT.MM. angegeben werden.'
        },
        normalizeLicenceDayMonthField(fieldName) {
            if (!this.data || typeof this.data !== 'object') return
            this.data[fieldName] = this.normalizeDayMonthDisplayValue(this.data[fieldName])
        },
        normalizeDayMonthDisplayValue(value) {
            const parsed = this.parseDayMonthValue(value)
            if (!parsed) {
                return String(value ?? '').trim()
            }

            return `${String(parsed.day).padStart(2, '0')}.${String(parsed.month).padStart(2, '0')}.`
        },
        isValidDayMonthValue(value) {
            return this.parseDayMonthValue(value) !== null
        },
        parseDayMonthValue(value) {
            if (typeof value !== 'string') {
                return null
            }

            const normalized = value.trim()
            const matches = normalized.match(/^(0[1-9]|[12][0-9]|3[01])\.(0[1-9]|1[0-2])\.?$/)
            if (!matches) {
                return null
            }

            const day = Number(matches[1])
            const month = Number(matches[2])
            const maxDay = new Date(2001, month, 0).getDate()
            if (day > maxDay) {
                return null
            }

            return { day, month }
        },
        overviewPriceLabel(priceValue, period = 'year') {
            const normalized = String(priceValue ?? '').trim()
            if (normalized === '') {
                return ''
            }

            return period === 'month' ? `EUR ${normalized} / Monat` : `EUR ${normalized} / Jahr`
        },
        overviewDateRangeLabel(startDayMonth, endDayMonth) {
            const normalizedStartDayMonth = String(startDayMonth ?? '').trim()
            const normalizedEndDayMonth = String(endDayMonth ?? '').trim()
            const resolvedEndDayMonth =
                normalizedEndDayMonth !== '' ? normalizedEndDayMonth : this.derivePreviousDayMonthDisplayValue(startDayMonth)

            if (normalizedStartDayMonth === '' && resolvedEndDayMonth === '') {
                return ''
            }

            if (normalizedStartDayMonth !== '' && resolvedEndDayMonth !== '') {
                return `Zeitraum: ${normalizedStartDayMonth} - ${resolvedEndDayMonth}`
            }

            if (normalizedStartDayMonth !== '') {
                return `Zeitraum: ${normalizedStartDayMonth}`
            }

            return `Zeitraum: ${resolvedEndDayMonth}`
        },
        derivePreviousDayMonthDisplayValue(value) {
            const parsed = this.parseDayMonthValue(value)
            if (!parsed) {
                return ''
            }

            const currentDate = new Date(2001, parsed.month - 1, parsed.day)
            currentDate.setDate(currentDate.getDate() - 1)

            return `${String(currentDate.getDate()).padStart(2, '0')}.${String(currentDate.getMonth() + 1).padStart(2, '0')}.`
        },
        overviewStorageTariffLabel(layerName, licenceModel) {
            const normalizedLayerName = String(layerName ?? '').trim()
            if (!normalizedLayerName || !licenceModel || typeof licenceModel !== 'object') {
                return ''
            }

            const includedStorage = String(licenceModel[`${normalizedLayerName}_included_storage_gb`] ?? '').trim()
            const extraStorageStep = String(licenceModel[`${normalizedLayerName}_extra_storage_step_gb`] ?? '').trim()
            const extraStorageStepPrice = String(licenceModel[`${normalizedLayerName}_extra_storage_step_price`] ?? '').trim()

            if (includedStorage === '' || extraStorageStep === '' || extraStorageStepPrice === '') {
                return ''
            }

            return `inkl. ${includedStorage} GB, + EUR ${extraStorageStepPrice} / ${extraStorageStep} GB`
        },
        hasStorageTariffConfigured(layerName, licenceModel) {
            const normalizedLayerName = String(layerName ?? '').trim()
            if (!normalizedLayerName || !licenceModel || typeof licenceModel !== 'object') {
                return false
            }

            return [
                `${normalizedLayerName}_included_storage_gb`,
                `${normalizedLayerName}_extra_storage_step_gb`,
                `${normalizedLayerName}_extra_storage_step_price`,
            ].some((fieldName) => String(licenceModel[fieldName] ?? '').trim() !== '')
        },
        normalizeLicenceModel(licenceModel) {
            const fallback = {
                school_licence_enabled: true,
                school_price_per_year: '',
                school_storage_enabled: false,
                school_included_storage_gb: '',
                school_extra_storage_step_gb: '',
                school_extra_storage_step_price: '',
                admin_licence_enabled: false,
                admin_price_per_year: '',
                admin_role_names: [],
                admin_storage_enabled: false,
                admin_included_storage_gb: '',
                admin_extra_storage_step_gb: '',
                admin_extra_storage_step_price: '',
                user_licence_enabled: false,
                user_price_per_year: '',
                user_role_names: [],
                user_storage_enabled: false,
                user_included_storage_gb: '',
                user_extra_storage_step_gb: '',
                user_extra_storage_step_price: '',
            }

            if (typeof licenceModel === 'string') {
                try {
                    licenceModel = JSON.parse(licenceModel)
                } catch (_) {
                    return fallback
                }
            }

            if (!licenceModel || typeof licenceModel !== 'object') return fallback

            const normalizeRoleNames = (roleNames) => {
                const normalizedRoles = []
                for (const roleName of Array.isArray(roleNames) ? roleNames : []) {
                    if (typeof roleName !== 'string') continue
                    const trimmed = roleName.trim()
                    if (!trimmed || normalizedRoles.includes(trimmed)) continue
                    if (trimmed === '*') {
                        return ['*']
                    }
                    normalizedRoles.push(trimmed)
                }
                return normalizedRoles
            }

            if ('school_licence_enabled' in licenceModel || 'admin_licence_enabled' in licenceModel || 'user_licence_enabled' in licenceModel) {
                const normalizedSchoolIncludedStorage = typeof licenceModel.school_included_storage_gb === 'string'
                    ? licenceModel.school_included_storage_gb
                    : (licenceModel.school_included_storage_gb ?? '').toString()
                const normalizedSchoolExtraStorageStep = typeof licenceModel.school_extra_storage_step_gb === 'string'
                    ? licenceModel.school_extra_storage_step_gb
                    : (licenceModel.school_extra_storage_step_gb ?? '').toString()
                const normalizedSchoolExtraStorageStepPrice = typeof licenceModel.school_extra_storage_step_price === 'string'
                    ? licenceModel.school_extra_storage_step_price
                    : (licenceModel.school_extra_storage_step_price ?? '').toString()
                const normalizedAdminIncludedStorage = typeof licenceModel.admin_included_storage_gb === 'string'
                    ? licenceModel.admin_included_storage_gb
                    : (licenceModel.admin_included_storage_gb ?? '').toString()
                const normalizedAdminExtraStorageStep = typeof licenceModel.admin_extra_storage_step_gb === 'string'
                    ? licenceModel.admin_extra_storage_step_gb
                    : (licenceModel.admin_extra_storage_step_gb ?? '').toString()
                const normalizedAdminExtraStorageStepPrice = typeof licenceModel.admin_extra_storage_step_price === 'string'
                    ? licenceModel.admin_extra_storage_step_price
                    : (licenceModel.admin_extra_storage_step_price ?? '').toString()
                const normalizedUserIncludedStorage = typeof licenceModel.user_included_storage_gb === 'string'
                    ? licenceModel.user_included_storage_gb
                    : (licenceModel.user_included_storage_gb ?? '').toString()
                const normalizedUserExtraStorageStep = typeof licenceModel.user_extra_storage_step_gb === 'string'
                    ? licenceModel.user_extra_storage_step_gb
                    : (licenceModel.user_extra_storage_step_gb ?? '').toString()
                const normalizedUserExtraStorageStepPrice = typeof licenceModel.user_extra_storage_step_price === 'string'
                    ? licenceModel.user_extra_storage_step_price
                    : (licenceModel.user_extra_storage_step_price ?? '').toString()

                return {
                    school_licence_enabled: this.toBool(licenceModel.school_licence_enabled, true),
                    school_price_per_year:
                        typeof licenceModel.school_price_per_year === 'string'
                            ? licenceModel.school_price_per_year
                            : (licenceModel.school_price_per_year ?? '').toString(),
                    school_storage_enabled: this.hasStorageTariffConfigured('school', {
                        school_included_storage_gb: normalizedSchoolIncludedStorage,
                        school_extra_storage_step_gb: normalizedSchoolExtraStorageStep,
                        school_extra_storage_step_price: normalizedSchoolExtraStorageStepPrice,
                    }),
                    school_included_storage_gb: normalizedSchoolIncludedStorage,
                    school_extra_storage_step_gb: normalizedSchoolExtraStorageStep,
                    school_extra_storage_step_price: normalizedSchoolExtraStorageStepPrice,
                    admin_licence_enabled: this.toBool(licenceModel.admin_licence_enabled, false),
                    admin_price_per_year:
                        typeof licenceModel.admin_price_per_year === 'string'
                            ? licenceModel.admin_price_per_year
                            : (licenceModel.admin_price_per_year ?? '').toString(),
                    admin_role_names: normalizeRoleNames(licenceModel.admin_role_names),
                    admin_storage_enabled: this.hasStorageTariffConfigured('admin', {
                        admin_included_storage_gb: normalizedAdminIncludedStorage,
                        admin_extra_storage_step_gb: normalizedAdminExtraStorageStep,
                        admin_extra_storage_step_price: normalizedAdminExtraStorageStepPrice,
                    }),
                    admin_included_storage_gb: normalizedAdminIncludedStorage,
                    admin_extra_storage_step_gb: normalizedAdminExtraStorageStep,
                    admin_extra_storage_step_price: normalizedAdminExtraStorageStepPrice,
                    user_licence_enabled: this.toBool(licenceModel.user_licence_enabled, false),
                    user_price_per_year:
                        typeof licenceModel.user_price_per_year === 'string'
                            ? licenceModel.user_price_per_year
                            : (licenceModel.user_price_per_year ?? '').toString(),
                    user_role_names: normalizeRoleNames(licenceModel.user_role_names),
                    user_storage_enabled: this.hasStorageTariffConfigured('user', {
                        user_included_storage_gb: normalizedUserIncludedStorage,
                        user_extra_storage_step_gb: normalizedUserExtraStorageStep,
                        user_extra_storage_step_price: normalizedUserExtraStorageStepPrice,
                    }),
                    user_included_storage_gb: normalizedUserIncludedStorage,
                    user_extra_storage_step_gb: normalizedUserExtraStorageStep,
                    user_extra_storage_step_price: normalizedUserExtraStorageStepPrice,
                }
            }

            const affectedRoles = normalizeRoleNames(licenceModel.affected_roles)
            const rawMap =
                licenceModel.user_licence_required_by_role && typeof licenceModel.user_licence_required_by_role === 'object'
                    ? licenceModel.user_licence_required_by_role
                    : {}
            const requiredRoleNames = affectedRoles.filter((roleName) => !!rawMap[roleName])
            const adminRoleNames = requiredRoleNames.filter((roleName) => this.looksLikeAdminRoleName(roleName))
            const userRoleNames = requiredRoleNames.filter((roleName) => !this.looksLikeAdminRoleName(roleName))

            return {
                school_licence_enabled: this.toBool(licenceModel.school_licence_required, true),
                school_price_per_year: '',
                school_storage_enabled: false,
                school_included_storage_gb: '',
                school_extra_storage_step_gb: '',
                school_extra_storage_step_price: '',
                admin_licence_enabled: adminRoleNames.length > 0,
                admin_price_per_year: '',
                admin_role_names: adminRoleNames,
                admin_storage_enabled: false,
                admin_included_storage_gb: '',
                admin_extra_storage_step_gb: '',
                admin_extra_storage_step_price: '',
                user_licence_enabled: userRoleNames.length > 0,
                user_price_per_year: '',
                user_role_names: userRoleNames,
                user_storage_enabled: false,
                user_included_storage_gb: '',
                user_extra_storage_step_gb: '',
                user_extra_storage_step_price: '',
            }
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
.licences-list :deep(.v-list-item__content) {
    overflow: visible;
}

.licence-card__type-icons {
    display: flex;
    align-items: flex-start;
    gap: 6px;
}

.licence-card__type-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid rgba(79, 120, 196, 0.28);
    background: rgba(79, 120, 196, 0.1);
    color: rgb(79, 120, 196);
}

.licence-card__type-icon--admin {
    border-color: rgba(180, 110, 20, 0.28);
    background: rgba(180, 110, 20, 0.08);
    color: rgb(180, 110, 20);
}

.licence-card__type-icon--user {
    border-color: rgba(46, 140, 100, 0.28);
    background: rgba(46, 140, 100, 0.08);
    color: rgb(46, 140, 100);
}

.licence-card__roles-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 12px;
}

.licence-card__roles-row .licence-card__roles-title {
    margin-bottom: 0;
    flex: 0 0 auto;
}

.licence-card__roles-row .licence-card__roles-list {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    flex: 1 1 280px;
    min-width: 0;
}
</style>
