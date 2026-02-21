<template>
    <v-col cols="12" md="6" xl="4">
        <its-grid-box color="primary" title="Lizenzen" class="w-100" :disabled="action != ''">
            <div class="d-flex flex-row align-start">
                <v-card tile flat color="transparent" class="w-100">
                    <v-card-text>
                        <!-- SEARCHFIELD -->
                        <SearchField :store="licenceStore" selected_field="selected_licences" />

                        <!-- Abwählen / Auswählen-->
                        <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap align-center ga-2 mt-2" :disabled="action != ''">
                            <v-btn color="primary" slim flat tile class="text-caption" @click="clearSelection" v-if="selected_licences.length >= 1">
                                Auswahl aufheben
                            </v-btn>
                        </v-card>

                        <!-- RECORDS -->
                        <v-list
                            dense
                            variant="elevated"
                            select-strategy="leaf"
                            v-model:selected="selected_licences"
                            @update:selected="onSelectedLicencesUpdate"
                            color="success-lighten-2">
                            <v-list-item v-for="item in licences" :key="item.id" :value="item.id">
                                <template v-slot:title>
                                    <div class="d-flex flex-column ga-2 py-1">
                                        <div>
                                            <div class="text-body-1">
                                                {{ item.name }}
                                            </div>
                                        </div>
                                        <div class="d-flex flex-row flex-wrap align-center ga-2 text-caption">
                                            <v-chip size="small" variant="outlined">
                                                Schullizenz nötig: {{ licenceModelFor(item).school_licence_required ? 'JA' : 'NEIN' }}
                                            </v-chip>
                                            <v-chip
                                                size="small"
                                                :color="isAnyUserLicenceRequired(licenceModelFor(item)) ? 'warning' : undefined"
                                                variant="outlined">
                                                Eigene Userlizenz: {{ isAnyUserLicenceRequired(licenceModelFor(item)) ? 'JA' : 'NEIN' }}
                                            </v-chip>
                                        </div>
                                        <div class="d-flex flex-row flex-wrap align-center ga-2 text-caption">
                                            <span class="text-medium-emphasis">Rollen:</span>
                                            <template v-if="sortedAffectedRoles(licenceModelFor(item)).length >= 1">
                                                <v-chip
                                                    v-for="roleName in sortedAffectedRoles(licenceModelFor(item))"
                                                    :key="`licence-overview-role-${item.id}-${roleName}`"
                                                    size="x-small"
                                                    :color="isRoleUserLicenceRequired(licenceModelFor(item), roleName) ? 'warning' : undefined"
                                                    :variant="isRoleUserLicenceRequired(licenceModelFor(item), roleName) ? 'flat' : 'outlined'">
                                                    {{ roleName }}
                                                </v-chip>
                                            </template>
                                            <span v-else class="text-medium-emphasis">-</span>
                                        </div>
                                    </div>
                                </template>
                            </v-list-item>
                        </v-list>

                        <!-- PAGINATION-->
                        <Pagination :meta="meta" :store="licenceStore" selected_field="selected_licences" />
                    </v-card-text>
                </v-card>

                <!-- MENÜ -->
                <v-card tile flat color="transparent" style="width: 150px" class="d-flex flex-column ga-2">
                    <!-- AUSWAHl EGAL -->
                    <div class="d-flex flex-column ga-2">
                        <v-btn block tile flat color="primary" class="text-caption" prepend-icon="mdi-plus" @click="createLicence">Hinzufügen</v-btn>
                    </div>
                    <!-- GENAU 1 ELEMENT AUSGEWÄHLT -->
                    <div class="d-flex flex-column ga-2" v-if="selected_licences.length == 1">
                        <v-btn block tile flat color="primary" class="text-caption" prepend-icon="mdi-pencil" @click="editLicence(selected_licences[0])">Ändern</v-btn>
                        <v-btn block tile flat color="primary" class="text-caption" prepend-icon="mdi-shape-outline" @click="openLicenceModel">Lizenzmodell</v-btn>
                    </div>
                    <!-- MINDEST 1 ELEMENT AUSGEWÄHLT -->
                    <div class="d-flex flex-column ga-2" v-if="selected_licences.length >= 1">
                        <v-btn block tile flat color="warning" class="text-caption" prepend-icon="mdi-delete" @click="deleteLicence">Löschen</v-btn>
                    </div>
                </v-card>
            </div>
        </its-grid-box>
    </v-col>

    <!-- NEUE LIZENZ / LIZENZ ÄNDERN -->
    <v-col cols="12" md="6" xl="4" v-if="action == 'create_licence' || action == 'edit_licence'">
        <its-grid-box color="primary" :title="data.id ? 'Lizenz ändern' : 'Neue Lizenz'" class="w-100">
            <v-form ref="form" v-model="is_valid" @submit.prevent="saveLicence(data)" class="mb-4">
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
                <v-row>
                    <v-col cols="12">
                        <v-card tile flat color="transparent" class="d-flex flex-row align-center justify-space-between" :disabled="is_uploading">
                            <v-btn color="warning" flat tile @click="abort">Abbruch</v-btn>
                            <v-btn color="success" flat tile type="submit">Speichern</v-btn>
                        </v-card>
                    </v-col>
                </v-row>
            </v-form>
        </its-grid-box>
    </v-col>

    <!-- Löschen -->
    <v-col cols="12" md="6" xl="4" v-if="action == 'delete_licence'">
        <its-grid-box color="primary" title="Löschen" class="w-100">
            <v-form ref="form" v-model="is_valid" @submit.prevent="doDeleteLicences(selected_licences)">
                <v-card tile flat color="transparent" class="text-body-1">
                    <div>Es soll eine Lizenz gelöscht werden. Sind Sie sicher, dass Sie die markierte Lizenz löschen möchten?</div>
                </v-card>
                <v-card tile flat color="transparent" class="d-flex flex-row align-center justify-space-between mt-4">
                    <v-btn color="success" flat tile @click="action = ''">Abbruch</v-btn>
                    <v-btn color="error" flat tile type="submit">Löschen</v-btn>
                </v-card>
            </v-form>
        </its-grid-box>
    </v-col>

    <!-- Lizenzmodell -->
    <v-col cols="12" md="6" xl="4" v-if="action == 'licence_model'">
        <its-grid-box color="primary" title="Lizenzmodell" class="w-100">
            <v-card tile flat color="transparent" class="pa-1">
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
                    </v-card>
                    <div class="text-caption text-medium-emphasis" v-if="currentLicenceModel.affected_roles.length === 0">
                        Wählen Sie zuerst eine oder mehrere Rollen aus.
                    </div>
                </v-card>

                <v-card tile flat color="transparent" class="d-flex flex-row align-center justify-space-between mt-4">
                    <v-btn color="warning" flat tile @click="closeLicenceModel">Abbrechen</v-btn>
                    <v-btn color="success" flat tile @click="saveCurrentLicenceModel">Speichern</v-btn>
                </v-card>
            </v-card>
        </its-grid-box>
    </v-col>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import SearchField from '@/pages/components/SearchField.vue'
import Pagination from '@/pages/components/Pagination.vue'

// SPECIFIC

import { useLicenceStore } from '@/stores/admin/LicenceStore'
import { useRoleStore } from '@/stores/admin/RoleStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { Pagination, SearchField, ItsGridBox },

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
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapWritableState(useLicenceStore, ['licences', 'meta', 'selected_licences', 'search_string', 'data']),
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
                return
            }
            roles.push(roleName)
            this.currentLicenceModel.user_licence_required_by_role[roleName] = false
        },
        isUserLicenceRequiredForRole(roleName) {
            if (!this.currentLicenceModel) return false
            return !!this.currentLicenceModel.user_licence_required_by_role[roleName]
        },
        setUserLicenceRequiredForRole(roleName, value) {
            if (!this.currentLicenceModel) return
            if (typeof value !== 'boolean') return
            this.currentLicenceModel.user_licence_required_by_role[roleName] = value
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

            return {
                school_licence_required: typeof licenceModel.school_licence_required === 'boolean' ? licenceModel.school_licence_required : true,
                affected_roles,
                user_licence_required_by_role,
            }
        },
    },
}
</script>
