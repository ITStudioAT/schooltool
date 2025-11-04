<template>
    <v-col cols="12" md="4" xl="3">
        <its-grid-box color="primary" title="Lizenzen" class="w-100" :disabled="action != ''">
            <div class="d-flex flex-row align-start">
                <v-card tile flat color="transparent" class="w-100">
                    <v-card-text>
                        <!-- SEARCHFIELD -->
                        <SearchField :store="licenceStore" selected_field="selected_licences" />

                        <!-- Abwählen / Auswählen-->
                        <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap align-center ga-2 mt-2" :disabled="action != ''">
                            <v-btn color="primary" slim flat tile class="text-caption" @click="selectAll">
                                Alle auswählen [{{ selected_licences.length - selected_licences.length }}]
                            </v-btn>
                            <v-btn color="primary" slim flat tile class="text-caption" @click="unselectAll">Alle abwählen [{{ selected_licences.length }}]</v-btn>
                        </v-card>

                        <!-- RECORDS -->
                        <v-list dense variant="elevated" select-strategy="leaf" v-model:selected="selected_licences" color="success-lighten-2">
                            <v-list-item v-for="item in licences" :key="item.id" :value="item.id">
                                <template v-slot:title>
                                    <div class="d-flex flex-row align-center justify-space-between">
                                        <div>
                                            <div class="text-body-1">
                                                {{ item.name }}
                                            </div>
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
    <v-col cols="12" md="4" xl="3" v-if="action == 'create_licence' || action == 'edit_licence'">
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
    <v-col cols="12" md="4" xl="3" v-if="action == 'delete_licence'">
        <its-grid-box color="primary" title="Löschen" class="w-100">
            <v-form ref="form" v-model="is_valid" @submit.prevent="doDeleteLicences(selected_licences)">
                <v-card tile flat color="transparent" class="text-body-1">
                    <div v-if="selected_licences.length == 1">Es soll eine Lizenz gelöscht werden. Sind Sie sicher, dass Sie die markierte Lizenz löschen möchten?</div>
                    <div v-if="selected_licences.length > 1">
                        Es sollen {{ selected_licences.length }} Lizenzen gelöscht werden. Sind Sie sicher, dass Sie die markierten Lizenzen löschen möchten?
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

// SPECIFIC

import { useLicenceStore } from '@/stores/admin/LicenceStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { Pagination, SearchField, ItsMenuButton, ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.licenceStore = useLicenceStore()
        await this.licenceStore.index()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            licenceStore: null,
            is_valid: false,
            upload_file: null,
            is_uploading: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapWritableState(useLicenceStore, ['licences', 'meta', 'selected_licences', 'search_string', 'data']),
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
        abort() {
            this.action = ''
        },

        selectAll() {
            this.selected_licences = this.licences.map((item) => item.id)
        },
        unselectAll() {
            this.selected_licences = []
        },
    },
}
</script>
