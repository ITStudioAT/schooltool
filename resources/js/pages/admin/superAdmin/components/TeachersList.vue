<template>
    <v-col cols="12" md="6" xl="4">
        <ItsGridBox color="primary" title="Lehrerliste" subtitle="Diese Lehrer:innen dürfen sich am System registrieren" class="w-100" :disabled="action != ''" v-if="teachers">
            <div class="d-flex flex-row align-start">
                <v-card tile flat color="transparent" class="w-100">
                    <v-card-text>
                        <v-alert type="info" title="Hinweis">
                            Diese Liste dient dazu, festzulegen, welche Lehrer:innen berechtigt sind, sich am System zu registrieren.
                            <div class="text-caption">(Das entspricht nicht unbedingt den am System bereits tatsächlich registrierten Lehrer:inen)</div>

                            <div class="font-weight-bold mt-4">Es ist eine xlsx-Datei zu importieren.</div>
                            <div class="font-weight-bold">Diese Datei benötigt folgende Überschriften: Kurz, Nachname, Vorname, Email</div>
                        </v-alert>
                        <!-- Abwählen / Auswählen-->
                        <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap align-center ga-2 mt-2" v-if="is_upload == false">
                            <v-btn color="primary" slim flat tile class="text-caption" @click="selectAll">Alle auswählen [{{ teachers.length - selected_teachers.length }}]</v-btn>
                            <v-btn color="primary" slim flat tile class="text-caption" @click="unselectAll">Alle abwählen [{{ selected_teachers.length }}]</v-btn>
                        </v-card>
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
                                    <i>Email</i>
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
                        <v-btn block tile flat color="primary" class="text-caption" prepend-icon="mdi-plus" @click="createTeacher">Hinzufügen</v-btn>
                    </div>
                    <!-- GENAU 1 ELEMENT AUSGEWÄHLT -->
                    <div class="d-flex flex-column ga-2" v-if="selected_teachers.length == 1">
                        <v-btn block tile flat color="primary" class="text-caption" prepend-icon="mdi-pencil" @click="editTeacher(selected_teachers[0])">Ändern</v-btn>
                    </div>
                    <!-- MINDEST 1 ELEMENT AUSGEWÄHLT -->
                    <div class="d-flex flex-column ga-2" v-if="selected_teachers.length >= 1">
                        <v-btn block tile flat color="warning" class="text-caption" prepend-icon="mdi-delete" @click="deleteTeacher">Löschen</v-btn>
                    </div>
                </v-card>
            </div>
        </ItsGridBox>
    </v-col>

    <!-- EDIT TEACHER -->
    <v-col cols="12" md="6" xl="4" v-if="action == 'create_teacher' || action == 'edit_teacher'">
        <its-grid-box color="primary" :title="data.id ? 'Lehrer:in ändern' : 'Neue:r Lehrer:in'" class="w-100">
            <v-form ref="form" v-model="is_valid" @submit.prevent="saveTeacher(data)" class="mb-4">
                <v-row dense>
                    <v-col cols="12">
                        <v-text-field autofocus v-model="data.short" label="Kurzname" :rules="[required(), maxLength(10)]" />
                    </v-col>
                    <v-col cols="12">
                        <v-text-field v-model="data.last_name" label="Nachname" :rules="[required(), maxLength(255)]" />
                    </v-col>

                    <v-col cols="12">
                        <v-text-field v-model="data.first_name" label="Vorname" :rules="[maxLength(255)]" />
                    </v-col>

                    <v-col cols="12">
                        <v-text-field v-model="data.email" label="E-Mail" :rules="[required(), mail(), maxLength(255)]" />
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
    <v-col cols="12" md="6" xl="4" v-if="action == 'delete_teacher'">
        <its-grid-box color="primary" title="Löschen" class="w-100">
            <v-form ref="form" v-model="is_valid" @submit.prevent="doDeleteTeachers(selected_teachers)">
                <v-card tile flat color="transparent" class="text-body-1">
                    <div v-if="selected_teachers.length == 1">Es soll ein:e Lehrer:in gelöscht werden. Sind Sie sicher?</div>
                    <div v-if="selected_teachers.length > 1">Es sollen {{ selected_teachers.length }} Lehrer:innen gelöscht werden. Sind Sie sicher?</div>
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
            teachersListStore: null,
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
            this.main_action = 'teachers_overview'
        },

        selectAll() {
            this.selected_teachers = this.teachers.map((item) => item.id)
        },
        unselectAll() {
            this.selected_teachers = []
        },

        async saveTeacher(data) {
            if (this.is_uploading) return
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return

            if (data.id) {
                if (!(await this.teachersListStore.update(data))) return
            } else {
                if (!(await this.teachersListStore.store(data))) return
            }

            this.selected_teachers = []
            // await this.adminStore.loadConfig()
            await this.teachersListStore.index()
            this.data = {}
            this.action = ''
        },

        createTeacher() {
            this.data = { is_selectable: true }
            this.action = 'create_teacher'
        },

        editTeacher(teacher_id) {
            const teacher = this.teachers.find((s) => s.id === teacher_id)
            this.data = JSON.parse(JSON.stringify(teacher))
            this.action = 'edit_teacher'
        },
        abort() {
            this.action = ''
        },

        deleteTeacher() {
            this.action = 'delete_teacher'
        },

        async doDeleteTeachers(data) {
            if (!(await this.teachersListStore.deleteTeachers(data))) return
            this.selected_teachers = []
            await this.teachersListStore.index()
            this.action = ''
        },
    },
}
</script>
