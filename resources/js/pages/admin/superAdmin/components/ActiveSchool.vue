<template>
    <!-- NUR FÜR SUPER_ADMIN -->
    <v-col cols="12" md="6" xl="4" v-if="config.roles.includes('super_admin')">
        <ItsGridBox color="primary" title="Aktive Schule" class="w-100">
            <div class="d-flex flex-row align-start">
                <v-card tile flat color="transparent" class="w-100">
                    <v-card-title>{{ config.selected_school.long_name }}</v-card-title>
                    <v-card-subtitle>{{ config.selected_school.short_name }}</v-card-subtitle>
                    <v-card-text>{{ '✉️ ' + config.selected_school.email }}</v-card-text>
                    <v-card-text v-if="action == ''">
                        <its-menu-button title="Schule" subtitle="wechseln" icon="mdi-swap-horizontal" color="primary" @click="action = 'switch_school'" />
                    </v-card-text>
                    <v-card-text v-if="action == 'switch_school'">
                        <v-form ref="form" v-model="is_valid" @submit.prevent="doSwitch(selected_school_id)">
                            <v-card tile flat color="primary" class="text-h6 px-2">Schule wechseln</v-card>
                            <v-autocomplete
                                v-model="selected_school_id"
                                :items="switchable_schools"
                                item-title="long_name"
                                item-value="id"
                                label="Auswahl Schule"
                                v-if="switchable_schools" />

                            <v-card tile flat color="transparent" class="d-flex flex-row align-center justify-space-between mt-4">
                                <v-btn color="warning" flat tile @click="action = ''">Abbruch</v-btn>
                                <v-btn color="success" flat tile type="submit">Wechseln</v-btn>
                            </v-card>
                        </v-form>
                    </v-card-text>

                    <v-card-text v-if="action == ''">
                        <v-row>
                            <v-col cols="12">
                                <v-card tile flat color="primary" class="text-h6 px-2 mb-2">Lizenzen</v-card>
                                <div class="text-body-2" v-if="!school_licences || school_licences.length == 0">Keine gültigen Lizenzen</div>

                                <div v-if="school_licences && school_licences.length > 0">
                                    <div v-for="licence in school_licences" :key="licence.id" class="d-flex flex-row align-center justify-space-between text-body-1">
                                        <div>
                                            {{ '✅ ' + licence.name }}
                                        </div>
                                        <div class="d-flex flex-row align-center ga-2">
                                            <div>
                                                {{ licence.valid_until }}
                                            </div>
                                            <v-btn tile flat color="error" size="small" icon="mdi-delete" @click="deleteLicence(licence.school_licence_id)"></v-btn>
                                        </div>
                                    </div>
                                </div>
                            </v-col>
                        </v-row>

                        <v-row>
                            <v-col cols="12">
                                <its-menu-button title="Lizenz" subtitle="hinzufügen/ändern" icon="mdi-card-account-details" color="primary" @click="action = 'add_licence'" />
                            </v-col>
                        </v-row>
                    </v-card-text>

                    <v-card-text v-if="action == 'add_licence'">
                        <v-form ref="form" v-model="is_valid" @submit.prevent="doAddLicence(data)">
                            <v-card tile flat color="primary" class="text-h6 px-2">Lizenz hinzufügen</v-card>
                            <v-row>
                                <v-col cols="12">
                                    <v-autocomplete v-model="data.licence_id" :items="licences" item-title="name" item-value="id" label="Auswahl Lizenz" v-if="licences" />
                                </v-col>
                            </v-row>

                            <v-row dense>
                                <v-col cols="12">
                                    <v-text-field v-model="data.valid_until" label="Datum bis (JJJJ-MM-TT)" :rules="[date()]" />
                                </v-col>
                            </v-row>

                            <v-card tile flat color="transparent" class="d-flex flex-row align-center justify-space-between mt-4">
                                <v-btn color="warning" flat tile @click="action = ''">Abbruch</v-btn>
                                <v-btn color="success" flat tile type="submit">Hinzufügen/Ändern</v-btn>
                            </v-card>
                        </v-form>
                    </v-card-text>

                    <v-card-text v-if="action == ''">
                        <v-card tile flat color="primary" class="text-h6 px-2 mb-2">Admins</v-card>
                        <div class="text-body-2" v-if="!school_admins || school_admins.length == 0">Keine Admins zugeordnet</div>

                        <div v-if="school_admins && school_admins.length > 0">
                            <div v-for="admin in school_admins" :key="admin.id" class="d-flex flex-row align-start justify-space-between text-body-1 mb-1">
                                <div class="d-flex flex-column">
                                    <div>
                                        {{ admin.last_name + ' ' + admin.first_name + ' (' + admin.email + ')' }}
                                    </div>
                                    <div class="d-flex flex-row flex-wrap align-center ga-2">
                                        <div v-for="role in admin.roles" :key="role.id" class="text-body-2">{{ role }}</div>
                                    </div>
                                </div>
                                <v-btn tile flat color="warning" size="small" icon="mdi-delete" @click="deleteAdmin(admin)"></v-btn>
                            </div>
                        </div>

                        <its-menu-button title="Admin" subtitle="hinzufügen" icon="mdi-account-plus" color="primary" class="mt-4" @click="addAdmin" />
                    </v-card-text>

                    <v-card-text v-if="action == 'add_admin'">
                        <v-form ref="form" v-model="is_valid" @submit.prevent="doAddAdmin(data)">
                            <v-card tile flat color="primary" class="text-h6 px-2">Admin hinzufügen</v-card>

                            <v-row dense>
                                <v-col cols="12">
                                    <v-text-field autofocus v-model="data.last_name" label="Nachname" :rules="[required(), maxLength(255)]" />
                                </v-col>
                            </v-row>

                            <v-row dense>
                                <v-col cols="12">
                                    <v-text-field v-model="data.first_name" label="Vorname" :rules="[maxLength(255)]" />
                                </v-col>
                            </v-row>

                            <v-row dense>
                                <v-col cols="12">
                                    <v-text-field v-model="data.email" label="E-Mail" :rules="[mail()]" />
                                </v-col>
                            </v-row>
                            <v-row>
                                <v-col cols="12">
                                    <h3 class="text-h6 mb-2">Rollen auswählen:</h3>

                                    <!-- Loop through all roles -->
                                    <v-checkbox
                                        v-for="role in roles"
                                        :key="role.id"
                                        v-model="selected_roles"
                                        :label="role.name"
                                        :value="role.name"
                                        density="comfortable"
                                        color="primary"
                                        hide-details />
                                </v-col>
                            </v-row>

                            <v-card tile flat color="transparent" class="d-flex flex-row align-center justify-space-between mt-4">
                                <v-btn color="warning" flat tile @click="action = ''">Abbruch</v-btn>
                                <v-btn color="success" flat tile type="submit">Hinzufügen</v-btn>
                            </v-card>
                        </v-form>
                    </v-card-text>

                    <v-card-text v-if="action == 'delete_admin'">
                        <v-form ref="form" v-model="is_valid" @submit.prevent="doDeleteAdmin(admin, is_delete_complete)">
                            <v-card tile flat color="primary" class="text-h6 px-2">Admin löschen</v-card>

                            <div class="mt-4">
                                Es werden die Admin-Rechte oder der Benutzer
                                <span class="font-weight-bold">{{ admin.last_name + ' ' + admin.first_name }}</span>
                                gelöscht.
                            </div>
                            <div class="mt-2">Es werden nur die Rechte des Benutzers gelöscht, außer Sie klicken an, dass der komplette Benutzer gelöscht werden soll.</div>
                            <v-checkbox v-model="is_delete_complete" label="Löschen des kompletten Benutzers" color="error" />

                            <v-card tile flat color="transparent" class="d-flex flex-row align-center justify-space-between mt-4">
                                <v-btn color="success" flat tile @click="action = ''">Abbruch</v-btn>
                                <v-btn :color="is_delete_complete ? 'error' : 'warning'" flat tile type="submit">Löschen</v-btn>
                            </v-card>
                        </v-form>
                    </v-card-text>
                </v-card>
            </div>
        </ItsGridBox>
    </v-col>
    <v-col cols="12" md="6" xl="4" v-if="action == ''">
        <ItsGridBox color="primary" title="Lehrer" class="w-100">
            <div class="d-flex flex-row align-center justify-space-between text-body-1">
                <div class="font-weight-medium">Aktive Lehrer:</div>
                <div class="d-flex flex-row align-center ga-2">
                    <div>
                        {{ teachers.count_active }}
                    </div>
                </div>
            </div>

            <div class="d-flex flex-row align-center justify-space-between text-body-1">
                <div class="font-weight-medium">Lehrer in Liste:</div>
                <div class="d-flex flex-row align-center ga-2">
                    <div>
                        {{ teachers.count }}
                    </div>
                </div>
            </div>

            <v-card tile flat color="transparent">
                <v-card-text class="d-flex flex-row align-center ga-2">
                    <ItsMenuButton title="Lehrer" subtitle="verwalten" icon="mdi-school" color="primary" @click="main_action = 'teachers'" />
                    <ItsMenuButton title="Lehrerliste" subtitle="verwalten" icon="mdi-view-list" color="primary" @click="main_action = 'teachers_list'" />
                </v-card-text>
                <v-card-text class="text-body-2">Die Lehrerliste dient dazu, festzulegen, welche Personen sich am System als Lehrer:innen anmelden dürfen.</v-card-text>
            </v-card>
        </ItsGridBox>
    </v-col>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

import { useSchoolStore } from '@/stores/admin/SchoolStore'
import { useLicenceStore } from '@/stores/admin/LicenceStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsMenuButton, ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.schoolStore = useSchoolStore()
        this.licenceStore = useLicenceStore()

        if (this.config.roles.includes('super_admin')) {
            await this.schoolStore.loadSwitchableSchools()
            if (this.config.selected_school.id) await this.schoolStore.loadSchoolInfos(this.config.selected_school.id)
            await this.licenceStore.loadLicences()
            await this.adminStore.loadRoles()
        }
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            schoolStore: null,
            selected_school_id: null,
            is_valid: false,
            selected_licence_id: null,
            data: {},
            selected_roles: [],
            admin: null,
            is_delete_complete: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config', 'roles', 'main_action']),
        ...mapWritableState(useSchoolStore, ['selected_school', 'switchable_schools', 'school_licences', 'school_admins', 'teachers']),
        ...mapWritableState(useLicenceStore, ['licences']),
    },

    methods: {
        async doAddAdmin(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return

            if (this.selected_roles.length == 0) return

            if (!(await this.schoolStore.addAdmin(data, this.selected_roles))) return
            await this.schoolStore.loadSchoolInfos(this.config.selected_school.id)
            this.action = ''
        },
        addAdmin() {
            this.data = {}
            this.selected_roles = []
            this.action = 'add_admin'
        },

        deleteAdmin(admin) {
            this.admin = admin
            this.is_delete_complete = false
            this.action = 'delete_admin'
        },

        async doDeleteAdmin(admin, is_delete_complete) {
            if (!(await this.schoolStore.deleteAdmin(admin.id, is_delete_complete))) return
            await this.schoolStore.loadSchoolInfos(this.config.selected_school.id)
            this.action = ''
        },

        async deleteLicence(school_licence_id) {
            await this.schoolStore.deleteLicence(school_licence_id)
        },

        async doSwitch(school_id) {
            if (!school_id) return
            await this.schoolStore.switchSchool(school_id)
            await this.adminStore.loadConfig()
            await this.schoolStore.loadSchoolInfos(this.config.selected_school.id)

            this.action = ''
        },

        async doAddLicence(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return

            if (!(await this.schoolStore.addLicence(data))) return
            this.action = ''
        },
    },
}
</script>
