<template>
    <v-col cols="12" md="6" xl="4">
        <ItsGridBox color="primary" title="Lehrerliste" class="w-100" :disabled="action != ''" v-if="teachers">
            <div class="d-flex flex-row align-start">
                <v-card tile flat color="transparent" class="w-100">
                    <v-card-text>
                        <!-- RECORDS   -->
                        <v-list density="compact" variant="elevated" select-strategy="leaf" v-model:selected="selected_teachers" color="success-lighten-2" v-if="is_upload != true">
                            <v-list-item v-for="item in teachers" :key="item.id" :value="item.id">
                                <template v-slot:title>
                                    <v-row>
                                        <v-col cols="2">
                                            {{ item.short }}
                                        </v-col>
                                        <v-col cols="10">
                                            {{ item.last_name + ' ' + item.first_name }}
                                        </v-col>
                                    </v-row>
                                </template>
                            </v-list-item>
                        </v-list>

                        <div v-if="is_upload == true">
                            <v-card-title>Upload einer Lehrer-Liste</v-card-title>
                            <v-card-subtitle v-if="!is_upload_finished">
                                <div>Es muss sich um eine Excel-Datei (*.xlsx) handeln.</div>
                                <div>
                                    Es werden die Spaltenüberschriften
                                    <i>Kurz</i>
                                    ,
                                    <i>Nachname</i>
                                    ,
                                    <i>Vorname</i>
                                    und
                                    <i>EMail</i>
                                    benötigt.
                                </div>
                            </v-card-subtitle>

                            <div v-if="!is_upload_finished">
                                <FileUpload path="/api/admin/teachers_list_upload" @fileUploadFinished="fileUploadFinished" @uploadStart="onUploadStart" class="mt-2" />

                                <div class="mt-4">
                                    <v-btn color="warning" flat tile @click="is_upload = false">Abbruch</v-btn>
                                </div>
                            </div>
                            <div class="mt-4" v-if="is_upload_finished">
                                <div>Die Datei ist hochgeladen und wir jetzt verarbeitet</div>
                                <div class="mt-4">
                                    <v-btn color="success" flat tile @click="uploadFinished">Fertig</v-btn>
                                </div>
                            </div>
                        </div>
                        <div v-if="!is_upload">
                            <v-alert type="info" title="Die Liste ist leer" text="Sie können jederzeit eine Liste importieren" v-if="teachers.length == 0" />

                            <div class="mt-4">
                                <v-btn color="warning" flat tile @click="abortReturn">Zur Übersicht</v-btn>
                            </div>
                        </div>
                    </v-card-text>
                </v-card>
                <!-- MENÜ -->
                <v-card tile flat color="transparent" style="width: 150px" class="d-flex flex-column ga-2" v-if="is_upload == false">
                    <!-- AUSWAHl EGAL -->
                    <div class="d-flex flex-column ga-2">
                        <v-btn block tile flat color="primary" class="text-caption" prepend-icon="mdi-refresh" @click="refresh">Aktualisierung</v-btn>
                        <v-btn block tile flat color="primary" class="text-caption" prepend-icon="mdi-import" @click="is_upload = true">Importieren</v-btn>
                        <v-btn block tile flat color="primary" class="text-caption" prepend-icon="mdi-plus" @click="">Hinzufügen</v-btn>
                    </div>
                    <!-- GENAU 1 ELEMENT AUSGEWÄHLT -->
                    <div class="d-flex flex-column ga-2">
                        <v-btn block tile flat color="primary" class="text-caption" prepend-icon="mdi-pencil" @click="" v-if="selected_teachers.legnth == 1">Ändern</v-btn>
                    </div>
                    <!-- MINDEST 1 ELEMENT AUSGEWÄHLT -->
                    <div class="d-flex flex-column ga-2">
                        <v-btn block tile flat color="warning" class="text-caption" prepend-icon="mdi-delete" @click="" v-if="selected_teachers.length >= 1">Löschen</v-btn>
                    </div>
                </v-card>
            </div>
        </ItsGridBox>
    </v-col>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import FileUpload from '@/pages/components/FileUpload.vue'

// SPECIFIC

import { useTeachersListStore } from '@/stores/admin/TeachersListStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsMenuButton, ItsGridBox, FileUpload },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.teachersListStore = useTeachersListStore()
        await this.teachersListStore.index()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            teacherStore: null,
            is_valid: false,
            is_upload: false,
            is_upload_finished: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config', 'main_action', 'pusher_count']),
        ...mapWritableState(useTeachersListStore, ['teachers', 'meta', 'selected_teachers', 'search_string', 'data', 'answer']),
    },

    methods: {
        async refresh() {
            await this.teachersListStore.index()
        },
        onUploadStart() {
            // Start Pusher
            if (this.config.is_auth) this.adminStore.initializeEcho()
        },

        fileUploadFinished(file) {
            this.is_upload_finished = true
        },
        uploadFinished() {
            this.is_upload_finished = false
            this.is_upload = false
        },

        abortReturn() {
            this.main_action = ''
        },
    },
}
</script>
