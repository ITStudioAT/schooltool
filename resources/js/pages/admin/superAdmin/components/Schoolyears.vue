<template>
    <v-col cols="12" md="6" xl="4">
        <its-grid-box color="primary" title="Schuljahre" class="w-100" :disabled="action != ''">
            <div class="d-flex flex-row align-start">
                <v-card tile flat color="transparent" class="w-100">
                    <v-card-text>
                        <!-- SEARCHFIELD -->
                        <SearchField20 :store="schoolyearStore" index_method="indexPaginate" selected_field="selected_schoolyear" />

                        <!-- Abwählen / Auswählen-->
                        <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap align-center ga-2 mt-2" :disabled="action != ''">
                            <v-btn color="primary" slim flat tile class="text-caption" @click="selectAll">
                                Alle auswählen [{{ schoolyears.length - selected_schoolyears.length }}]
                            </v-btn>
                            <v-btn color="primary" slim flat tile class="text-caption" @click="unselectAll">Alle abwählen [{{ selected_schoolyears.length }}]</v-btn>
                        </v-card>

                        <!-- RECORDS -->
                        <v-list dense variant="elevated" select-strategy="leaf" v-model:selected="selected_schoolyears" color="success-lighten-2">
                            <v-list-item v-for="item in schoolyears" :key="item.id" :value="item.id">
                                <template v-slot:title>
                                    <div class="d-flex flex-row align-center justify-space-between">
                                        <div>
                                            <div class="text-body-1">
                                                {{ item.name }}
                                            </div>
                                            <div class="text-caption" v-if="item.from || item.until">
                                                {{ formatRange(item) }}
                                            </div>
                                        </div>
                                        <v-icon v-if="item.is_active" size="small" color="success" icon="mdi-check-circle" />
                                    </div>
                                </template>
                            </v-list-item>
                        </v-list>

                        <!-- PAGINATION-->
                        <Pagination20 :meta="meta" :store="schoolyearStore" index_method="indexPaginate" selected_field="selected_schoolyear" />
                    </v-card-text>
                </v-card>

                <!-- MENÜ -->
                <v-card tile flat color="transparent" style="width: 150px" class="d-flex flex-column ga-2">
                    <!-- AUSWAHl EGAL -->
                    <div class="d-flex flex-column ga-2">
                        <v-btn block tile flat color="primary" class="text-caption" prepend-icon="mdi-plus" @click="createSchoolyear">Hinzufügen</v-btn>
                    </div>
                    <!-- GENAU 1 ELEMENT AUSGEWÄHLT -->
                    <div class="d-flex flex-column ga-2" v-if="selected_schoolyears.length == 1">
                        <v-btn block tile flat color="primary" class="text-caption" prepend-icon="mdi-pencil" @click="editSchoolyear(selected_schoolyears[0])">Ändern</v-btn>
                    </div>
                    <!-- MINDEST 1 ELEMENT AUSGEWÄHLT -->
                    <div class="d-flex flex-column ga-2" v-if="selected_schoolyears.length >= 1">
                        <v-btn block tile flat color="warning" class="text-caption" prepend-icon="mdi-delete" @click="deleteSchoolyear">Löschen</v-btn>
                    </div>
                </v-card>
            </div>
        </its-grid-box>
    </v-col>

    <!-- NEUES SCHULJAHR / SCHULJAHR ÄNDERN -->
    <v-col cols="12" md="6" xl="4" v-if="action == 'create_schoolyear' || action == 'edit_schoolyear'">
        <its-grid-box color="primary" :title="data.id ? 'Schuljahr ändern' : 'Neues Schuljahr'" class="w-100">
            <v-form ref="form" v-model="is_valid" @submit.prevent="saveSchoolyear(data)" class="mb-4">
                <v-row dense>
                    <v-col cols="12">
                        <v-text-field autofocus v-model="data.name" label="Bezeichnung" :rules="[required(), maxLength(255)]" />
                    </v-col>
                    <v-col cols="12">
                        <v-text-field v-model="data.from" label="Beginn des Schuljahres (jjjj-mm-tt)" :rules="[dateOrNull()]" />
                    </v-col>
                    <v-col cols="12">
                        <v-text-field v-model="data.until" label="Ende des Schuljahres (jjjj-mm-tt)" :rules="[dateOrNull()]" />
                    </v-col>
                    <v-col cols="12">
                        <v-text-field v-model="data.sem_2_start" label="Beginn des 2. Semesters (jjjj-mm-tt)" :rules="[dateOrNull()]" />
                    </v-col>
                </v-row>
                <v-row>
                    <v-col cols="12">
                        <v-card tile flat color="transparent" class="d-flex flex-row align-center justify-space-between">
                            <v-btn color="warning" flat tile @click="abort">Abbruch</v-btn>
                            <v-btn color="success" flat tile type="submit">Speichern</v-btn>
                        </v-card>
                    </v-col>
                </v-row>
            </v-form>
        </its-grid-box>
    </v-col>

    <!-- Löschen -->
    <v-col cols="12" md="6" xl="4" v-if="action == 'delete_schoolyear'">
        <its-grid-box color="primary" title="Löschen" class="w-100">
            <v-form ref="form" v-model="is_valid" @submit.prevent="doDeleteSchoolyears(selected_schoolyears)">
                <v-card tile flat color="transparent" class="text-body-1">
                    <div v-if="selected_schoolyears.length == 1">Es soll ein Schuljahr gelöscht werden. Sind Sie sicher, dass Sie das markierte Schuljahr löschen möchten?</div>
                    <div v-if="selected_schoolyears.length > 1">
                        Es sollen {{ selected_schoolyears.length }} Schuljahre gelöscht werden. Sind Sie sicher, dass Sie die markierten Schuljahre löschen möchten?
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
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

import { useSchoolyearStore } from '@/stores/admin/SchoolyearStore'
import Pagination20 from '@/pages/components/Pagination20.vue'
import SearchField20 from '@/pages/components/SearchField20.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsGridBox, Pagination20, SearchField20 },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.schoolyearStore = useSchoolyearStore()
        await this.schoolyearStore.indexPaginate()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            schoolyearStore: null,
            is_valid: false,
            selected_schoolyears: [],
            data: {},
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapWritableState(useSchoolyearStore, ['schoolyears', 'meta']),
    },

    methods: {
        formatRange(item) {
            const from = item.from || ''
            const until = item.until || ''
            if (from && until) return `${from} - ${until}`
            if (from) return `ab ${from}`
            if (until) return `bis ${until}`
            return ''
        },

        async saveSchoolyear(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return

            if (data.id) {
                if (!(await this.schoolyearStore.update(data))) return
            } else {
                if (!(await this.schoolyearStore.store(data))) return
            }

            await this.schoolyearStore.index()
            this.data = {}
            this.action = ''
        },

        createSchoolyear() {
            this.data = {}
            this.action = 'create_schoolyear'
        },

        deleteSchoolyear() {
            this.action = 'delete_schoolyear'
        },

        async doDeleteSchoolyears(data) {
            for (const schoolyear_id of data) {
                const schoolyear = this.schoolyears.find((s) => s.id === schoolyear_id)
                if (!schoolyear) continue
                this.schoolyearStore.selected_schoolyear = schoolyear
                if (!(await this.schoolyearStore.destroy(schoolyear))) return
            }
            this.selected_schoolyears = []
            await this.schoolyearStore.index()
            this.action = ''
        },

        editSchoolyear(schoolyear_id) {
            const schoolyear = this.schoolyears.find((s) => s.id === schoolyear_id)
            this.data = JSON.parse(JSON.stringify(schoolyear))
            this.action = 'edit_schoolyear'
        },

        abort() {
            this.action = ''
        },

        selectAll() {
            this.selected_schoolyears = this.schoolyears.map((item) => item.id)
        },
        unselectAll() {
            this.selected_schoolyears = []
        },
    },
}
</script>
