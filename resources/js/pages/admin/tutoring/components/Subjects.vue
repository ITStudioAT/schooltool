<template>
    <v-col cols="12" md="6" xl="4" v-if="data">
        <its-grid-box color="primary" title="Fächer" icon="mdi-television-shimmer" class="w-100">
            <div class="d-flex flex-row align-start">
                <v-card tile flat color="transparent" class="w-100">
                    <!-- ANZEIGE FÄCHER -->
                    <v-card-text class="text-body-1 d-flex flex-column ga-2" v-if="action == ''">
                        <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap ga-2 align-center w-100" :disabled="action_2 != ''">
                            <v-chip-group selected-class="text-primary" column>
                                <v-chip color="primary" v-for="subject in subjects" :key="subject.id" @click="selectSubject(subject)">
                                    {{ subject.long_name + ' (' + subject.short_name + ')' }}
                                </v-chip>
                            </v-chip-group>
                        </v-card>
                        <!-- Anzeige ausgewähltes Fach -->
                        <v-card tile flat color="transparent" class="d-flex flex-row align-center justify-space-between" :disabled="action_2 != ''" v-if="selected_subject">
                            <div>
                                <div class="text-body-1 font-weight-medium">{{ selected_subject.long_name + ' (' + selected_subject.short_name + ')' }}</div>
                                <div v-if="selected_subject.email_mentors?.length && selected_subject.must_be_accepted" class="mt-2">
                                    <div class="text-body-2 text-grey-darken-1 mb-1">Mentoren:</div>
                                    <div v-for="(mentor, index) in selected_subject.email_mentors" :key="index" class="text-body-2 ml-2">✉️ {{ mentor }}</div>
                                </div>
                            </div>
                            <div class="d-flex flex-row align-center ga-2">
                                <v-btn flat tile size="small" color="warning" icon="mdi-delete" @click="delete_level++" v-if="delete_level == 0" />
                                <v-btn flat tile size="small" color="success" icon="mdi-delete-off" @click="delete_level = 0" v-if="delete_level == 1" />
                                <v-btn flat tile size="small" color="error" icon="mdi-delete" @click="deleteSubject(selected_subject)" v-if="delete_level == 1" />
                                <v-btn flat tile size="small" color="primary" icon="mdi-pencil" @click="editSubject(selected_subject)" v-if="delete_level == 0" />
                            </div>
                        </v-card>

                        <v-card tile flat color="transparent" v-if="selected_subject && action_2 == 'edit_subject'">
                            <v-form ref="form" v-model="is_valid" @submit.prevent="updateSubject(data)">
                                <div class="text-h4">Fach ändern</div>
                                <v-text-field autofocus v-model="data.short_name" label="Kurzbezeichnung" :rules="[required(), maxLength(10)]" tabindex="1" />
                                <v-text-field v-model="data.long_name" label="Bezeichnung" :rules="[required(), maxLength(255)]" />
                                <v-checkbox v-model="data.must_be_accepted" label="Muss akzeptiert werden" />

                                <!-- ✅ Email Mentors als Array -->
                                <div class="text-body-2 mb-2">E-Mail Mentoren</div>
                                <template v-if="data.email_mentors && data.email_mentors.length > 0">
                                    <div v-for="(mentor, index) in data.email_mentors" :key="index" class="d-flex flex-row align-center ga-2 mb-2">
                                        <v-text-field v-model="data.email_mentors[index]" label="E-Mail Mentor" :rules="[mailOrNull(), maxLength(255)]" />
                                        <v-btn v-if="index === data.email_mentors.length - 1" tile flat icon="mdi-plus" color="primary" size="small" @click="addMentor(data)" />
                                        <v-btn tile flat icon="mdi-minus" color="error" size="small" @click="removeMentor(data, index)" />
                                    </div>
                                </template>

                                <!-- ✅ Button wenn Array leer ist -->
                                <v-btn v-else tile flat prepend-icon="mdi-plus" color="primary" size="small" @click="addMentor(data)" class="mb-4">E-Mail Mentor hinzufügen</v-btn>

                                <div class="d-flex flex-row align-center justify-space-between">
                                    <v-btn color="warning" flat tile @click="action_2 = ''">Abbruch</v-btn>
                                    <v-btn color="success" flat tile type="submit" tabindex="2">Speichern</v-btn>
                                </div>
                            </v-form>
                        </v-card>

                        <v-card tile flat color="transparent" class="mt-4" v-if="action_2 == ''">
                            <its-menu-button title="Fächer" subtitle="anlegen" icon="mdi-plus-circle-multiple" color="primary" @click="createSubjects" />
                        </v-card>
                    </v-card-text>

                    <!-- HINZUFÜGEN FÄCHER -->
                    <v-card-text v-if="action == 'add_subjects'">
                        <v-card-title>Neue Fächer hinzufügen</v-card-title>
                        <v-alert type="info">
                            <div>
                                Die Zeilen, in denen
                                <i>Abkürzung</i>
                                und
                                <i>Lange Bezeichnung</i>
                                angegeben sind, werden angelegt.
                            </div>
                            <div>E-Mail Mentor darf frei bleiben.</div>
                        </v-alert>
                        <v-form ref="form" v-model="is_valid" @submit.prevent="doCreateSubjects(my_subjects)">
                            <v-container>
                                <v-row>
                                    <v-col cols="2">Kurzbez.</v-col>
                                    <v-col cols="10">Bezeichnung</v-col>
                                    <v-col cols="10">E-Mail Mentor</v-col>
                                    <v-col cols="2">Akzeptiert</v-col>
                                </v-row>

                                <v-row v-for="(subject, i) in my_subjects" :key="i" dense class="border-md mb-2">
                                    <v-col cols="2">
                                        <v-text-field
                                            v-model="subject.short_name"
                                            label="Abkürzung"
                                            :rules="[maxLength(10)]"
                                            @input="subject.short_name = subject.short_name?.toUpperCase()" />
                                    </v-col>
                                    <v-col cols="10"><v-text-field v-model="subject.long_name" label="Lange Bezeichnung" :rules="[maxLength(255)]" /></v-col>
                                    <v-col cols="10">
                                        <div v-for="(mentor, index) in subject.email_mentors" :key="index" class="d-flex flex-row align-center ga-2">
                                            <v-text-field v-model="subject.email_mentors[index]" label="E-Mail Mentor:in" :rules="[mailOrNull(), maxLength(255)]" />

                                            <v-btn
                                                v-if="index === subject.email_mentors.length - 1"
                                                tile
                                                flat
                                                icon="mdi-plus"
                                                color="primary"
                                                size="small"
                                                @click="addMentor(subject)" />
                                            <!-- ✅ Übergebe subject -->

                                            <v-btn
                                                v-if="subject.email_mentors.length > 1"
                                                tile
                                                flat
                                                icon="mdi-minus"
                                                color="error"
                                                size="small"
                                                @click="removeMentor(subject, index)" />
                                            <!-- ✅ Übergebe subject und index -->
                                        </div>
                                    </v-col>
                                    <v-col cols="2"><v-checkbox v-model="subject.must_be_accepted" label="Akzept." /></v-col>
                                </v-row>
                            </v-container>

                            <div class="d-flex flex-row align-center justify-space-between">
                                <v-btn color="warning" flat tile @click="abortSubjects">Abbruch</v-btn>
                                <v-btn color="success" flat tile type="submit" tabindex="2">Speichern</v-btn>
                            </div>
                        </v-form>
                    </v-card-text>
                </v-card>
            </div>
        </its-grid-box>
    </v-col>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSubjectStore } from '@/stores/admin/tutoring/SubjectStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsMenuButton, ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.subjectStore = useSubjectStore()
        await this.subjectStore.index()
        this.action = ''
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            subjectStore: null,
            is_valid: false,
            my_subjects: [],
            selected_subject: null,
            delete_level: 0,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2']),
        ...mapWritableState(useSubjectStore, ['subjects', 'data']),
    },

    methods: {
        addMentor(subject) {
            if (!subject.email_mentors) {
                subject.email_mentors = []
            }
            subject.email_mentors.push('')
        },

        removeMentor(subject, index) {
            subject.email_mentors.splice(index, 1)
        },

        async deleteSubject(subject) {
            if (!(await this.subjectStore.deleteSubject(subject))) return
            this.selected_subject = null
            await this.subjectStore.index()
            this.delete_level = 0
        },

        async updateSubject(subject) {
            console.log(subject)
            if (!(await this.subjectStore.updateSubject(subject))) return

            await this.subjectStore.index()
            this.selected_subject = this.subjects.find((s) => s.id === subject.id)
            this.action_2 = ''
        },

        editSubject(subject) {
            this.data = {
                ...subject,
                email_mentors: subject.email_mentors ? [...subject.email_mentors] : [],
            }
            this.action_2 = 'edit_subject'
        },
        selectSubject(subject) {
            if (this.selected_subject != subject) {
                this.selected_subject = subject
            } else {
                this.selected_subject = null
            }
        },
        async doCreateSubjects(data) {
            if (!(await this.subjectStore.createSubjects(data))) return

            await this.subjectStore.index()
            this.selected_subject = null
            this.action = ''
        },

        createSubjects() {
            this.my_subjects = Array.from({ length: 5 }, () => ({
                short_name: '',
                long_name: '',
                email_mentors: [''],
            }))
            this.action = 'add_subjects'
        },
        abortSubjects() {
            this.action = ''
        },
    },
}
</script>
