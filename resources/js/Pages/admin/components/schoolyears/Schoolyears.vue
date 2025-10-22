<template>
    <v-row class="w-100">
        <v-col cols="12">
            <its-grid-box color="primary" title="Schuljahre" class="h-100 w-100" :disabled="action != ''">
                <div class="d-flex flex-wrap flex-row align-center ga-2">
                    <its-menu-button
                        :title="schoolyear.name"
                        :color="schoolyear.id == selected_schoolyear?.id ? 'success' : 'primary'"
                        @click="setActiveSchoolyear(schoolyear)"
                        v-for="schoolyear in schoolyears" />
                </div>
                <template
                    v-slot:title
                    v-if="config?.user?.roles.some((role) => ['super_admin', 'admin'].includes(role))">
                    <div class="d-flex flex-row align-center justify-space-between w-100">
                        <div class="mr-4">Schuljahre</div>
                        <div class="d-flex flex-row align-center">
                            <v-btn flat tile icon="mdi-plus" color="primary" @click="create" />
                            <div class="d-flex flex-row align-center" v-if="selected_schoolyear">
                                <v-btn flat tile icon="mdi-pencil" color="primary" @click="edit(selected_schoolyear)" />
                                <v-btn flat tile icon color="primary" @click="remove()">
                                    <v-icon icon="mdi-delete" color="warning"></v-icon>
                                </v-btn>
                            </div>
                        </div>
                    </div>
                </template>
            </its-grid-box>
        </v-col>

        <!-- ÄNDERN/ANLEGEN EINES SCHULJAHRES -->
        <v-col cols="12" sm="6" md="4" xl="3" v-if="action == 'edit_schoolyear' || action == 'create_schoolyear'">
            <its-grid-box
                color="primary"
                :title="data.id ? selected_schoolyear.name : 'Neues Schuljahr anlegen'"
                class="h-100 w-100">
                <v-form ref="form" v-model="is_valid" @submit.prevent="save(data)" class="mb-4">
                    <v-text-field
                        autofocus
                        v-model="data.name"
                        label="Bezeichnung"
                        :rules="[required(), maxLength(255)]" />

                    <v-text-field
                        v-model="data.from"
                        label="Beginn des Schuljahres (jjjj-mm-tt)"
                        :rules="[dateOrNull()]" />
                    <v-text-field
                        v-model="data.until"
                        label="Ende des Schuljahres (jjjj-mm-tt)"
                        :rules="[dateOrNull()]" />
                    <v-text-field
                        v-model="data.sem_2_start"
                        label="Beginn des 2. Semesters  (jjjj-mm-tt)"
                        :rules="[dateOrNull()]" />

                    <div class="d-flex flex-row align-center justify-space-between">
                        <v-btn color="warning" slim flat @click="abort">Abbruch</v-btn>
                        <v-btn color="success" slim flat type="submit">Speichern</v-btn>
                    </div>
                </v-form>
            </its-grid-box>
        </v-col>

        <!-- LÖSCHEN EINES SCHULJAHRES -->
        <v-col cols="12" sm="6" md="4" xl="3" v-if="action == 'remove_schoolyear'">
            <its-grid-box color="primary" :title="selected_schoolyear?.name" class="h-100 w-100">
                <v-form ref="form" v-model="is_valid" @submit.prevent="destroy(selected_schoolyear)" class="mb-4">
                    <div class="text-h6">Soll dieses Schuljahr wirklich gelöscht werden?</div>

                    <div class="d-flex flex-row align-center justify-space-between mt-4">
                        <v-btn color="success" slim flat @click="abort">Abbruch</v-btn>
                        <v-btn color="error" slim flat type="submit">Löschen</v-btn>
                    </div>
                </v-form>
            </its-grid-box>
        </v-col>
    </v-row>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'

import { useSchoolyearStore } from '@/stores/admin/SchoolyearStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsMenuButton, ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.schoolyearStore = useSchoolyearStore()
        if (this.schoolyears.length == 0) await this.schoolyearStore.index()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            schoolyearStore: null,

            is_valid: false,
            data: {},
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, [
            'config',
            'selected_school',
            'selected_schoolyear',
            'selected_register',
            'action',
        ]),
        ...mapWritableState(useSchoolyearStore, ['schoolyears']),
    },

    methods: {
        async save(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            var answer = false
            if (data.id) {
                answer = await this.schoolyearStore.update(data)
            } else {
                answer = await this.schoolyearStore.store(data)
            }
            if (answer) this.action = ''
            this.selected_schoolyear = this.schoolyearStore.selected_schoolyear
        },

        async destroy(data) {
            var answer = false
            answer = await this.schoolyearStore.destroy(data)

            if (answer) {
                this.selected_register = null
                this.selected_schoolyear = null
                this.action = ''
            }
        },

        abort() {
            this.action = ''
            this.data = {}
        },
        edit(selected_schoolyear) {
            this.data = JSON.parse(JSON.stringify(selected_schoolyear))
            this.action = 'edit_schoolyear'
        },
        remove() {
            this.action = 'remove_schoolyear'
        },

        create() {
            this.data = {}
            this.action = 'create_schoolyear'
        },

        async setActiveSchoolyear(schoolyear) {
            await this.schoolyearStore.setActiveSchoolyear(schoolyear.id)
            this.selected_schoolyear = schoolyear
            this.selected_register = null
        },
    },
}
</script>
