<template>
    <v-col cols="12" md="6" xl="4">
        <ItsGridBox color="primary" title="Lehrer" class="w-100" :disabled="action != ''">
            <div class="d-flex flex-row align-start">
                <v-card tile flat color="transparent" class="w-100">
                    <v-card-text>
                        <!-- SEARCHFIELD -->
                        <SearchField :store="teacherStore" selected_field="selected_teachers" />

                        <!-- Abwählen / Auswählen-->
                        <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap align-center ga-2 mt-2" :disabled="action != ''">
                            <v-btn color="primary" slim flat tile class="text-caption" @click="selectAll">Alle auswählen [{{ teachers.length - selected_teachers.length }}]</v-btn>
                            <v-btn color="primary" slim flat tile class="text-caption" @click="unselectAll">Alle abwählen [{{ selected_teachers.length }}]</v-btn>
                        </v-card>
                        <!-- RECORDS -->
                        <v-list dense variant="elevated" select-strategy="leaf" v-model:selected="selected_teachers" color="success-lighten-2">
                            <v-list-item v-for="item in teachers" :key="item.id" :value="item.id">
                                <template v-slot:title>
                                    <v-row>
                                        <v-col cols="2">
                                            <v-icon color="error" size="small" icon="mdi-lock" v-if="!item.is_active" />
                                            {{ item.short }}
                                        </v-col>
                                        <v-col cols="10">
                                            {{ item.last_name + ' ' + item.first_name }}
                                        </v-col>
                                    </v-row>
                                </template>
                            </v-list-item>
                        </v-list>

                        <!-- PAGINATION-->
                        <Pagination :meta="meta" :store="teacherStore" selected_field="selected_teachers" />

                        <div class="mt-4">
                            <v-btn color="warning" flat tile @click="abortReturn">Zur Übersicht</v-btn>
                        </div>
                    </v-card-text>
                </v-card>
                <!-- MENÜ -->
                <v-card tile flat color="transparent" style="width: 150px" class="d-flex flex-column ga-2">
                    <!-- AUSWAHl EGAL -->
                    <div class="d-flex flex-column ga-2">
                        <v-btn block tile flat color="primary" class="text-caption" prepend-icon="mdi-plus" @click="createTeacher">Hinzufügen</v-btn>
                    </div>
                    <!-- GENAU 1 ELEMENT AUSGEWÄHLT -->
                    <div class="d-flex flex-column ga-2" v-if="selected_teachers.length == 1">
                        <v-btn block tile flat color="primary" class="text-caption" prepend-icon="mdi-pencil" @click="editTeacher(selected_teachers[0])">Ändern</v-btn>
                        <v-btn
                            block
                            tile
                            flat
                            color="error"
                            class="text-caption"
                            prepend-icon="mdi-lock"
                            @click="toggleIsActive(selected_teachers[0])"
                            v-if="selectedTeacher(selected_teachers[0]).is_active">
                            Sperren
                        </v-btn>
                        <v-btn
                            block
                            tile
                            flat
                            color="success"
                            class="text-caption"
                            prepend-icon="mdi-lock-open"
                            @click="toggleIsActive(selected_teachers[0])"
                            v-if="!selectedTeacher(selected_teachers[0]).is_active">
                            Entsperren
                        </v-btn>
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
                        <v-text-field
                            autofocus
                            v-model="data.short"
                            label="Lehrer:in (Kurzzeichen)"
                            :rules="[required(), maxLength(10)]"
                            @input="data.short = data.short?.toUpperCase()" />
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
    <v-col cols="12" md="6" xl="4" v-if="action == 'delete_teacher'">
        <its-grid-box color="primary" title="Löschen" class="w-100">
            <v-form ref="form" v-model="is_valid" @submit.prevent="doDeleteTeachers(selected_teachers)">
                <v-card tile flat color="transparent" class="text-body-1">
                    <div v-if="selected_teachers.length == 1">Es soll eine Lehrer:in gelöscht werden. Sind Sie sicher, dass Sie die markierte Lehrer:in löschen möchten?</div>
                    <div v-if="selected_teachers.length > 1">
                        Es sollen {{ selected_teachers.length }} Lehrer:inn gelöscht werden. Sind Sie sicher, dass Sie die markierten Lehrer:inn löschen möchten?
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

import { useTeacherStore } from '@/stores/admin/TeacherStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { Pagination, SearchField, ItsMenuButton, ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.teacherStore = useTeacherStore()
        await this.teacherStore.index()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            teacherStore: null,
            is_valid: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config', 'main_action']),
        ...mapWritableState(useTeacherStore, ['teachers', 'meta', 'selected_teachers', 'search_string', 'data', 'answer']),
    },

    methods: {
        async abortReturn() {
            await this.teacherStore.index()
            this.main_action = ''
        },

        async saveTeacher(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return

            if (data.id) {
                if (!(await this.teacherStore.update(data))) return
            } else {
                if (!(await this.teacherStore.store(data))) return
            }

            this.selected_teachers = []
            // await this.adminStore.loadConfig()
            await this.teacherStore.index()
            this.data = {}
            this.action = ''
        },

        createTeacher() {
            this.data = { is_selectable: true }
            this.action = 'create_teacher'
        },

        deleteTeacher() {
            this.action = 'delete_teacher'
        },

        async doDeleteTeachers(data) {
            if (!(await this.teacherStore.deleteTeachers(data))) return
            this.selected_teachers = []
            await this.teacherStore.index()
            this.action = ''
        },

        editTeacher(teacher_id) {
            const teacher = this.teachers.find((s) => s.id === teacher_id)
            this.data = JSON.parse(JSON.stringify(teacher))
            this.action = 'edit_teacher'
        },
        abort() {
            this.action = ''
        },

        selectAll() {
            this.selected_teachers = this.teachers.map((item) => item.id)
        },
        unselectAll() {
            this.selected_teachers = []
        },

        selectedTeacher(teacher) {
            return this.teachers.find((s) => s.id === teacher)
        },

        async toggleIsActive(user_id) {
            await this.teacherStore.toggleIsActive(user_id)
            await this.teacherStore.index(this.meta.current_page)
        },
    },
}
</script>
