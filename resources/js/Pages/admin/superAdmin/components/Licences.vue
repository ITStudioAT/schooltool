<template>
    <v-col cols="12" md="4" xl="3">
        <its-grid-box color="primary" title="Lizenzen" class="w-100" :disabled="action != ''">
            <div class="d-flex flex-row align-start">
                <v-card tile flat color="transparent" class="w-100">
                    <v-card-text>
                        <!-- SEARCHFIELD -->
                        <SearchField :store="licenceStore" selected_field="selected_licences" />

                        <!-- Abwählen / Auswählen-->
                        <v-card
                            tile
                            flat
                            color="transparent"
                            class="d-flex flex-row flex-wrap align-center ga-2 mt-2"
                            :disabled="action != ''">
                            <v-btn color="primary" slim flat tile class="text-caption" @click="selectAll">
                                Alle auswählen [{{ selected_licences.length - selected_licences.length }}]
                            </v-btn>
                            <v-btn color="primary" slim flat tile class="text-caption" @click="unselectAll">
                                Alle abwählen [{{ selected_licences.length }}]
                            </v-btn>
                        </v-card>

                        <!-- RECORDS -->
                        <v-list
                            dense
                            variant="elevated"
                            select-strategy="leaf"
                            v-model:selected="selected_licences"
                            color="success-lighten-2">
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
                        <v-btn
                            block
                            tile
                            flat
                            color="primary"
                            class="text-caption"
                            prepend-icon="mdi-plus"
                            @click="createLicence">
                            Hinzufügen
                        </v-btn>
                    </div>
                    <!-- GENAU 1 ELEMENT AUSGEWÄHLT -->
                    <div class="d-flex flex-column ga-2" v-if="selected_licences.length == 1">
                        <v-btn
                            block
                            tile
                            flat
                            color="primary"
                            class="text-caption"
                            prepend-icon="mdi-pencil"
                            @click="editLicence(selected_licences[0])">
                            Ändern
                        </v-btn>
                    </div>
                    <!-- MINDEST 1 ELEMENT AUSGEWÄHLT -->
                    <div class="d-flex flex-column ga-2" v-if="selected_licences.length >= 1">
                        <v-btn block tile flat color="warning" class="text-caption" prepend-icon="mdi-delete">
                            Löschen
                        </v-btn>
                    </div>
                </v-card>
            </div>
        </its-grid-box>
    </v-col>
    <v-col cols="12" md="4" xl="3" v-if="action == 'create_school' || action == 'edit_school'">
        <its-grid-box color="primary" :title="data.id ? 'Lizenz ändern' : 'Neue Lizenz'" class="w-100">
            <v-form ref="form" v-model="is_valid" @submit.prevent="saveLicence(data)" class="mb-4">
                <v-row dense>
                    <v-col cols="12">
                        <v-text-field
                            autofocus
                            v-model="data.name"
                            label="Lizenz Bezeichnung"
                            :rules="[required(), maxLength(255)]" />
                    </v-col>
                    <v-col cols="12">
                        <v-text-field v-model="data.long_name" label="Kurze Beschreibung" :rules="[maxLength(255)]" />
                    </v-col>
                </v-row>
                <v-row>
                    <v-col cols="12">
                        <v-card
                            tile
                            flat
                            color="transparent"
                            class="d-flex flex-row align-center justify-space-between"
                            :disabled="is_uploading">
                            <v-btn color="warning" flat tile @click="abort">Abbruch</v-btn>
                            <v-btn color="success" flat tile type="submit">Speichern</v-btn>
                        </v-card>
                    </v-col>
                </v-row>
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
            this.action = 'create_school'
        },

        editLicence(school_id) {
            const school = this.licences.find((s) => s.id === school_id)
            this.data = JSON.parse(JSON.stringify(school))
            this.action = 'edit_school'
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
