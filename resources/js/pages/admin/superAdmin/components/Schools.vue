<template>
    <v-col cols="12" md="6" xl="4">
        <its-grid-box color="primary" title="Schulen" class="w-100" :disabled="action != ''">
            <div class="d-flex flex-row align-start">
                <v-card tile flat color="transparent" class="w-100">
                    <v-card-text>
                        <!-- SEARCHFIELD -->
                        <SearchField :store="schoolStore" selected_field="selected_schools" />

                        <!-- Abwählen / Auswählen-->
                        <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap align-center ga-2 mt-2" :disabled="action != ''">
                            <v-btn color="primary" slim flat tile class="text-caption" @click="selectAll">Alle auswählen [{{ schools.length - selected_schools.length }}]</v-btn>
                            <v-btn color="primary" slim flat tile class="text-caption" @click="unselectAll">Alle abwählen [{{ selected_schools.length }}]</v-btn>
                        </v-card>

                        <!-- RECORDS -->
                        <v-list dense variant="elevated" select-strategy="leaf" v-model:selected="selected_schools" color="success-lighten-2">
                            <v-list-item v-for="item in schools" :key="item.id" :value="item.id">
                                <template v-slot:title>
                                    <div class="d-flex flex-row align-center justify-space-between">
                                        <div>
                                            <div class="text-body-1">
                                                {{ item.long_name }}
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </v-list-item>
                        </v-list>

                        <!-- PAGINATION-->
                        <Pagination :meta="meta" :store="schoolStore" selected_field="selected_schools" />
                    </v-card-text>
                </v-card>

                <!-- MENÜ -->
                <v-card tile flat color="transparent" style="width: 150px" class="d-flex flex-column ga-2">
                    <!-- AUSWAHl EGAL -->
                    <div class="d-flex flex-column ga-2">
                        <v-btn block tile flat color="primary" class="text-caption" prepend-icon="mdi-plus" @click="createSchool">Hinzufügen</v-btn>
                    </div>
                    <!-- GENAU 1 ELEMENT AUSGEWÄHLT -->
                    <div class="d-flex flex-column ga-2" v-if="selected_schools.length == 1">
                        <v-btn block tile flat color="primary" class="text-caption" prepend-icon="mdi-pencil" @click="editSchool(selected_schools[0])">Ändern</v-btn>
                    </div>
                    <!-- MINDEST 1 ELEMENT AUSGEWÄHLT -->
                    <div class="d-flex flex-column ga-2" v-if="selected_schools.length >= 1">
                        <v-btn block tile flat color="warning" class="text-caption" prepend-icon="mdi-delete" @click="deleteSchool">Löschen</v-btn>
                    </div>
                </v-card>
            </div>
        </its-grid-box>
    </v-col>
    <v-col cols="12" md="6" xl="4" v-if="action == 'create_school' || action == 'edit_school'">
        <its-grid-box color="primary" :title="data.id ? 'Schule ändern' : 'Neue Schule'" class="w-100">
            <v-form ref="form" v-model="is_valid" @submit.prevent="saveSchool(data)" class="mb-4">
                <v-row dense>
                    <v-col cols="12">
                        <v-text-field autofocus v-model="data.long_name" label="Schule (langer Name)" :rules="[required(), maxLength(255)]" />
                    </v-col>
                    <v-col cols="12">
                        <v-text-field v-model="data.short_name" label="Schule (kurzer Name)" :rules="[required(), maxLength(255)]" />
                    </v-col>

                    <v-col cols="12">
                        <v-text-field v-model="data.email" label="E-Mail" :rules="[required(), mail(), maxLength(255)]" />
                    </v-col>

                    <v-col cols="12">
                        <v-checkbox hide-details v-model="data.is_selectable" label="Auswählbar" />
                    </v-col>
                    <v-col cols="12">
                        <div class="text-body-1">Logo:</div>
                        <!-- Upload-Logo -->
                        <div v-if="data.upload_file">
                            <img :src="`/storage${data.upload_file}`" alt="Logo" height="60px" class="pl-2" />
                        </div>

                        <!-- Logo existiert und kein Upload-Logo-->
                        <div v-if="data.logo && !data.upload_file" class="d-flex flex-row align-center justify-space-between ga-2">
                            <img :src="'/storage/images/' + data.logo + '?t=' + Date.now()" alt="Logo" height="60px" class="pl-2" />
                            <v-btn tile flat color="error" class="text-caption" prepend-icon="mdi-delete" @click="removeLogo">Löschen</v-btn>
                        </div>

                        <div class="text-body-1" v-if="!data.logo && !data.upload_file">Kein Logo hochgeladen</div>

                        <FileUpload path="/api/admin/schools_upload/uploadLogo" @fileUploadFinished="fileUploadFinished" @uploadStart="onUploadStart" class="mt-2" />
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
    <v-col cols="12" md="6" xl="4" v-if="action == 'delete_school'">
        <its-grid-box color="primary" title="Löschen" class="w-100">
            <v-form ref="form" v-model="is_valid" @submit.prevent="doDeleteSchools(selected_schools)">
                <v-card tile flat color="transparent" class="text-body-1">
                    <div v-if="selected_schools.length == 1">Es soll eine Schule gelöscht werden. Sind Sie sicher, dass Sie die markierte Schule löschen möchten?</div>
                    <div v-if="selected_schools.length > 1">
                        Es sollen {{ selected_schools.length }} Schulen gelöscht werden. Sind Sie sicher, dass Sie die markierten Schulen löschen möchten?
                    </div>
                </v-card>
                <v-card tile flat color="transparent" class="d-flex flex-row align-center justify-space-between mt-4">
                    <v-btn color="success" flat tile @click="action = ''">Abbruch</v-btn>
                    <v-btn color="error" flat tile type="submit">Löschen</v-btn>
                </v-card>
            </v-form>
        </its-grid-box>
    </v-col>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import SearchField from '@/pages/components/SearchField.vue'
import Pagination from '@/pages/components/Pagination.vue'
import FileUpload from '@/pages/components/FileUpload.vue'

// SPECIFIC

import { useSchoolStore } from '@/stores/admin/SchoolStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { Pagination, SearchField, ItsMenuButton, ItsGridBox, FileUpload },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.schoolStore = useSchoolStore()
        await this.schoolStore.index()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            schoolStore: null,
            is_valid: false,
            upload_file: null,
            is_uploading: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapWritableState(useSchoolStore, ['schools', 'meta', 'selected_schools', 'search_string', 'data', 'answer']),
    },

    methods: {
        onUploadStart() {
            this.is_uploading = true
        },

        fileUploadFinished(file) {
            this.is_uploading = false
            this.data.upload_file = '/temp/' + this.config?.user?.id + '/' + file.name + '?t=' + Date.now()
        },

        removeLogo() {
            this.data.upload_file = null
            this.data.logo = null
        },

        async saveSchool(data) {
            if (this.is_uploading) return
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return

            if (data.id) {
                if (!(await this.schoolStore.update(data))) return
            } else {
                if (!(await this.schoolStore.store(data))) return
            }

            this.selected_schools = []
            // await this.adminStore.loadConfig()
            await this.schoolStore.index()
            this.data = {}
            this.action = ''
        },

        createSchool() {
            this.data = { is_selectable: true }
            this.action = 'create_school'
        },

        deleteSchool() {
            this.action = 'delete_school'
        },

        async doDeleteSchools(data) {
            if (!(await this.schoolStore.deleteSchools(data))) return
            this.selected_schools = []
            await this.schoolStore.index()
            this.action = ''
        },

        editSchool(school_id) {
            const school = this.schools.find((s) => s.id === school_id)
            this.data = JSON.parse(JSON.stringify(school))
            this.action = 'edit_school'
        },
        abort() {
            this.action = ''
        },

        selectAll() {
            this.selected_schools = this.schools.map((item) => item.id)
        },
        unselectAll() {
            this.selected_schools = []
        },
    },
}
</script>
