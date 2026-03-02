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
                    <div class="d-grid ga-3 mb-3">
                        <div class="empty-state crud-search-panel">
                            <SearchField :store="licenceStore" selected_field="selected_licences" />
                        </div>

                        <div class="d-flex flex-wrap ga-2" :disabled="action != ''">
                            <v-btn color="primary" variant="tonal" rounded="lg" class="text-caption" @click="selectAll">
                                Alle auswählen [{{ Math.max(0, licences.length - selected_licences.length) }}]
                            </v-btn>
                            <v-btn color="primary" variant="text" rounded="lg" class="text-caption" @click="unselectAll">
                                Alle abwählen [{{ selected_licences.length }}]
                            </v-btn>
                        </div>
                    </div>

                    <div class="empty-state pa-2" v-if="licences.length === 0">Keine Lizenzen gefunden.</div>
                    <div class="empty-state pa-2" v-else>
                        <v-list dense variant="flat" class="crud-list licences-list" select-strategy="leaf" v-model:selected="selected_licences" color="success-lighten-2">
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
                                            </div>
                                            <div class="licence-card__badges">
                                                <div class="licence-card__badge" :class="{ 'is-ok': licenceModelFor(item).school_licence_required === false }">
                                                    <span class="licence-card__badge-label">Schullizenz</span>
                                                    <span class="licence-card__badge-value">{{ licenceModelFor(item).school_licence_required ? 'Erforderlich' : 'Nicht nötig' }}</span>
                                                </div>
                                                <div class="licence-card__badge" :class="{ 'is-warning': isAnyUserLicenceRequired(licenceModelFor(item)) }">
                                                    <span class="licence-card__badge-label">Userlizenz</span>
                                                    <span class="licence-card__badge-value">{{ isAnyUserLicenceRequired(licenceModelFor(item)) ? 'Erforderlich' : 'Nicht nötig' }}</span>
                                                </div>
                                            </div>
                                        </header>

                                        <div class="licence-card__roles-block">
                                            <div class="licence-card__roles-title">Betroffene Rollen</div>
                                            <div class="licence-card__roles-list">
                                                <template v-if="sortedAffectedRoles(licenceModelFor(item)).length >= 1">
                                                    <v-chip
                                                        v-for="roleName in sortedAffectedRoles(licenceModelFor(item))"
                                                        :key="`licence-overview-role-${item.id}-${roleName}`"
                                                        size="small"
                                                        class="licence-card__role-chip"
                                                        :color="isRoleUserLicenceRequired(licenceModelFor(item), roleName) ? 'warning' : undefined"
                                                        :variant="isRoleUserLicenceRequired(licenceModelFor(item), roleName) ? 'flat' : 'outlined'">
                                                        {{ roleName }}
                                                    </v-chip>
                                                </template>
                                                <span v-else class="text-medium-emphasis">Keine Rollen hinterlegt</span>
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

                        <template v-if="selected_licences.length >= 1">
                            <v-divider class="crud-actions-divider" />
                            <div class="crud-actions-secondary">
                                <v-btn
                                    v-if="selected_licences.length == 1"
                                    block
                                    color="primary"
                                    variant="tonal"
                                    rounded="lg"
                                    prepend-icon="mdi-pencil"
                                    @click="editLicence(selected_licences[0])">
                                    Ändern
                                </v-btn>
                                <v-btn
                                    v-if="selected_licences.length == 1"
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
                                <v-text-field v-model="data.price_per_year" label="Kosten pro Jahr" :rules="[maxLength(255)]" />
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
                        <div class="kpi-sub mt-2" v-if="selected_licences.length > 1">
                            Es sollen {{ selected_licences.length }} Lizenzen gelöscht werden. Sind Sie sicher, dass Sie die markierten Lizenzen löschen möchten?
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
                            <div class="text-subtitle-1 mb-3">Schullizenz nötig</div>
                            <v-btn-toggle
                                :model-value="currentLicenceModel.school_licence_required"
                                mandatory
                                divided
                                color="primary"
                                @update:model-value="setSchoolLicenceRequired">
                                <v-btn :value="true">JA</v-btn>
                                <v-btn :value="false">NEIN</v-btn>
                            </v-btn-toggle>
                        </v-card>

                        <v-card variant="outlined" class="pa-4" v-if="currentLicenceModel">
                            <div class="text-subtitle-1 mb-3">Betroffene Rollen</div>
                            <div class="d-flex flex-row flex-wrap ga-2" v-if="availableRoles.length >= 1">
                                <v-chip
                                    v-for="role in availableRoles"
                                    :key="`licence-model-role-${role.id}`"
                                    clickable
                                    :color="isRoleAffected(role.name) ? 'primary' : undefined"
                                    :variant="isRoleAffected(role.name) ? 'flat' : 'outlined'"
                                    @click="toggleAffectedRole(role.name)">
                                    {{ role.name }}
                                </v-chip>
                            </div>
                            <div class="text-caption text-medium-emphasis" v-else>Keine Rollen verfügbar.</div>

                            <div class="text-caption text-medium-emphasis mt-3" v-if="currentLicenceModel.affected_roles.length >= 1">
                                Ausgewählt: {{ currentLicenceModel.affected_roles.join(', ') }}
                            </div>
                            <div class="text-caption text-medium-emphasis mt-3" v-else>Noch keine Rollen ausgewählt.</div>

                            <v-divider class="my-4"></v-divider>

                            <div class="text-subtitle-1 mb-3">Eigene Userlizenz notwendig</div>
                            <v-card
                                v-for="roleName in currentLicenceModel.affected_roles"
                                :key="`licence-model-role-setting-${roleName}`"
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
                                <div v-if="isUserLicenceRequiredForRole(roleName)" class="mt-3">
                                    <v-divider class="mb-3"></v-divider>
                                    <div class="d-flex flex-row align-center justify-space-between ga-2 mb-2">
                                        <div class="text-body-2">Pläne (Text + Preis pro Jahr)</div>
                                        <div class="d-flex flex-row flex-wrap align-center ga-2">
                                            <v-select
                                                v-if="copyablePlanSourceRoles(roleName).length >= 1"
                                                v-model="plan_copy_sources_by_role[roleName]"
                                                :items="copyablePlanSourceRoles(roleName)"
                                                label="Von Rolle kopieren"
                                                density="compact"
                                                hide-details
                                                style="min-width: 220px; max-width: 260px" />
                                            <v-btn
                                                v-if="plan_copy_sources_by_role[roleName]"
                                                size="small"
                                                color="secondary"
                                                variant="flat"
                                                prepend-icon="mdi-content-copy"
                                                @click="copyUserLicencePlansFromRole(roleName)">
                                                Kopieren
                                            </v-btn>
                                            <v-btn
                                                size="small"
                                                color="primary"
                                                variant="outlined"
                                                prepend-icon="mdi-plus"
                                                @click="addUserLicencePlanRow(roleName)">
                                                Zeile
                                            </v-btn>
                                        </div>
                                    </div>

                                    <v-card
                                        v-for="(plan, planIndex) in userLicencePlansForRole(roleName)"
                                        :key="`licence-model-plan-${roleName}-${planIndex}`"
                                        variant="outlined"
                                        class="pa-2 mb-2">
                                        <v-row dense>
                                            <v-col cols="12" md="7">
                                                <v-text-field
                                                    v-model="plan.text"
                                                    label="Text"
                                                    :rules="[required(), maxLength(255)]"
                                                    hide-details="auto"
                                                    density="compact" />
                                            </v-col>
                                            <v-col cols="12" md="4">
                                                <v-text-field
                                                    v-model="plan.price_per_year"
                                                    label="Preis pro Jahr"
                                                    :rules="[required(), decimalOrNull(), maxLength(255)]"
                                                    hide-details="auto"
                                                    density="compact" />
                                            </v-col>
                                            <v-col cols="12" md="1" class="d-flex align-center justify-end">
                                                <v-btn
                                                    icon="mdi-delete"
                                                    size="small"
                                                    color="error"
                                                    variant="text"
                                                    @click="removeUserLicencePlanRow(roleName, planIndex)" />
                                            </v-col>
                                        </v-row>
                                    </v-card>

                                    <div class="text-caption text-medium-emphasis" v-if="userLicencePlansForRole(roleName).length === 0">
                                        Noch kein Plan angelegt.
                                    </div>
                                </div>
                            </v-card>
                            <div class="text-caption text-medium-emphasis" v-if="currentLicenceModel.affected_roles.length === 0">
                                Wählen Sie zuerst eine oder mehrere Rollen aus.
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
import SearchField from '@/pages/components/SearchField.vue'
import Pagination from '@/pages/components/Pagination.vue'

// SPECIFIC

import { useLicenceStore } from '@/stores/admin/LicenceStore'
import { useRoleStore } from '@/stores/admin/RoleStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { Pagination, SearchField },

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
            plan_copy_sources_by_role: {},
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
            this.data = { is_selectable: true }
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
        clearSelection() {
            this.selected_licences = []
        },
        selectAll() {
            this.selected_licences = this.licences.map((item) => item.id)
        },
        unselectAll() {
            this.selected_licences = []
        },
        isSelectedLicence(id) {
            return this.selected_licences.includes(id)
        },
        setSchoolLicenceRequired(value) {
            if (typeof value !== 'boolean') return
            if (!this.currentLicenceModel) return
            this.currentLicenceModel.school_licence_required = value
        },
        isRoleAffected(roleName) {
            if (!this.currentLicenceModel) return false
            return this.currentLicenceModel.affected_roles.includes(roleName)
        },
        toggleAffectedRole(roleName) {
            if (!this.currentLicenceModel) return
            const roles = this.currentLicenceModel.affected_roles
            const index = roles.indexOf(roleName)
            if (index >= 0) {
                roles.splice(index, 1)
                delete this.currentLicenceModel.user_licence_required_by_role[roleName]
                delete this.currentLicenceModel.user_licence_plans_by_role[roleName]
                delete this.plan_copy_sources_by_role[roleName]
                return
            }
            roles.push(roleName)
            this.currentLicenceModel.user_licence_required_by_role[roleName] = false
            this.currentLicenceModel.user_licence_plans_by_role[roleName] = []
        },
        isUserLicenceRequiredForRole(roleName) {
            if (!this.currentLicenceModel) return false
            return !!this.currentLicenceModel.user_licence_required_by_role[roleName]
        },
        setUserLicenceRequiredForRole(roleName, value) {
            if (!this.currentLicenceModel) return
            if (typeof value !== 'boolean') return
            this.currentLicenceModel.user_licence_required_by_role[roleName] = value
            if (value && this.userLicencePlansForRole(roleName).length === 0) {
                this.addDefaultUserLicencePlanRow(roleName)
            }
        },
        userLicencePlansForRole(roleName) {
            if (!this.currentLicenceModel) return []
            if (!this.currentLicenceModel.user_licence_plans_by_role || typeof this.currentLicenceModel.user_licence_plans_by_role !== 'object') {
                this.currentLicenceModel.user_licence_plans_by_role = {}
            }
            if (!Array.isArray(this.currentLicenceModel.user_licence_plans_by_role[roleName])) {
                this.currentLicenceModel.user_licence_plans_by_role[roleName] = []
            }
            return this.currentLicenceModel.user_licence_plans_by_role[roleName]
        },
        createUserLicencePlan(text = '', price_per_year = '', id = null) {
            const plan = {
                text,
                price_per_year,
            }
            if (id !== null && id !== undefined) {
                plan.id = id
            }
            return plan
        },
        createEmptyUserLicencePlan() {
            return this.createUserLicencePlan('', '0')
        },
        createDefaultUserLicencePlan() {
            return {
                text: 'Standard',
                price_per_year: '0',
            }
        },
        addUserLicencePlanRow(roleName) {
            this.userLicencePlansForRole(roleName).push(this.createEmptyUserLicencePlan())
        },
        addDefaultUserLicencePlanRow(roleName) {
            this.userLicencePlansForRole(roleName).push(this.createDefaultUserLicencePlan())
        },
        removeUserLicencePlanRow(roleName, index) {
            const plans = this.userLicencePlansForRole(roleName)
            if (index < 0 || index >= plans.length) return
            plans.splice(index, 1)
        },
        copyablePlanSourceRoles(targetRoleName) {
            if (!this.currentLicenceModel) return []
            return (this.currentLicenceModel.affected_roles || [])
                .filter((roleName) => roleName !== targetRoleName)
                .filter((roleName) => this.isUserLicenceRequiredForRole(roleName))
                .filter((roleName) => this.userLicencePlansForRole(roleName).length >= 1)
        },
        copyUserLicencePlansFromRole(targetRoleName) {
            const sourceRoleName = this.plan_copy_sources_by_role[targetRoleName]
            if (!sourceRoleName || sourceRoleName === targetRoleName) return

            const sourcePlans = this.userLicencePlansForRole(sourceRoleName)
            const targetPlans = this.userLicencePlansForRole(targetRoleName)

            targetPlans.splice(
                0,
                targetPlans.length,
                ...sourcePlans.map((plan) =>
                    this.createUserLicencePlan(
                        typeof plan?.text === 'string' ? plan.text : '',
                        typeof plan?.price_per_year === 'string' ? plan.price_per_year : '0'
                    )
                )
            )

            if (targetPlans.length === 0) {
                targetPlans.push(this.createDefaultUserLicencePlan())
            }
        },
        licenceModelFor(licence) {
            return this.normalizeLicenceModel(licence?.licence_model || null)
        },
        isAnyUserLicenceRequired(licenceModel) {
            const map = licenceModel?.user_licence_required_by_role || {}
            return Object.values(map).some((value) => !!value)
        },
        isRoleUserLicenceRequired(licenceModel, roleName) {
            return !!licenceModel?.user_licence_required_by_role?.[roleName]
        },
        sortedAffectedRoles(licenceModel) {
            const roles = Array.isArray(licenceModel?.affected_roles) ? [...licenceModel.affected_roles] : []
            return roles.sort((a, b) => String(a).localeCompare(String(b), 'de'))
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
                user_licence_required_by_role[roleName] = !!rawMap[roleName]
            }

            const rawPlansByRole =
                licenceModel.user_licence_plans_by_role && typeof licenceModel.user_licence_plans_by_role === 'object'
                    ? licenceModel.user_licence_plans_by_role
                    : {}
            const user_licence_plans_by_role = {}
            for (const roleName of affected_roles) {
                const rawPlans = Array.isArray(rawPlansByRole[roleName]) ? rawPlansByRole[roleName] : []
                const normalizedPlans = rawPlans
                    .filter((plan) => plan && typeof plan === 'object')
                    .map((plan) =>
                        this.createUserLicencePlan(
                            (typeof plan.text === 'string' ? plan.text : (plan.text ?? '').toString()).trim(),
                            (typeof plan.price_per_year === 'string' ? plan.price_per_year : (plan.price_per_year ?? '').toString()).trim(),
                            Number.isInteger(Number(plan.id)) && Number(plan.id) > 0 ? Number(plan.id) : null
                        )
                    )
                    .filter((plan) => !(plan.text === '' && plan.price_per_year === ''))

                user_licence_plans_by_role[roleName] = normalizedPlans

                if (user_licence_required_by_role[roleName] && user_licence_plans_by_role[roleName].length === 0) {
                    user_licence_plans_by_role[roleName] = [this.createDefaultUserLicencePlan()]
                }
            }

            return {
                school_licence_required: typeof licenceModel.school_licence_required === 'boolean' ? licenceModel.school_licence_required : true,
                affected_roles,
                user_licence_required_by_role,
                user_licence_plans_by_role,
            }
        },
    },
}
</script>

<style scoped src="../../../../../css/admin-index-page.css"></style>
<style scoped src="../../../../../css/admin-crud-panel.css"></style>
<style scoped>
.licences-list :deep(.v-list-item__content) {
    overflow: visible;
}

.licence-card {
    border: 1px solid rgba(16, 38, 58, 0.1);
    border-radius: 14px;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(246, 250, 255, 0.92));
    padding: 12px;
    display: grid;
    gap: 10px;
}

.licences-list-item.is-selected .licence-card {
    border-color: rgba(57, 73, 171, 0.28);
    box-shadow: inset 0 0 0 1px rgba(57, 73, 171, 0.14);
}

.licence-card__header {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 12px;
}

.licence-card__title {
    font-size: 1rem;
    font-weight: 750;
    color: #10263a;
}

.licence-card__subtitle {
    margin-top: 3px;
    font-size: 0.82rem;
    color: rgba(16, 38, 58, 0.72);
    line-height: 1.35;
}

.licence-card__badges {
    display: grid;
    gap: 6px;
    min-width: 180px;
}

.licence-card__badge {
    border: 1px solid rgba(16, 38, 58, 0.12);
    border-radius: 10px;
    padding: 6px 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.9);
}

.licence-card__badge.is-ok {
    border-color: rgba(46, 164, 79, 0.24);
    background: rgba(236, 252, 243, 0.86);
}

.licence-card__badge.is-warning {
    border-color: rgba(245, 158, 11, 0.3);
    background: rgba(255, 251, 235, 0.9);
}

.licence-card__badge-label {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: rgba(16, 38, 58, 0.7);
}

.licence-card__badge-value {
    font-size: 0.76rem;
    font-weight: 700;
    color: #10263a;
}

.licence-card__roles-block {
    border-top: 1px dashed rgba(16, 38, 58, 0.14);
    padding-top: 8px;
}

.licence-card__roles-title {
    font-size: 0.74rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: rgba(16, 38, 58, 0.64);
    margin-bottom: 6px;
}

.licence-card__roles-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
}

.licence-card__role-chip {
    font-weight: 600;
}

@media (max-width: 860px) {
    .licence-card__header {
        grid-template-columns: 1fr;
    }

    .licence-card__badges {
        min-width: 0;
    }
}
</style>
