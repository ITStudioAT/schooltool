<template>
    <v-col cols="12" md="10" xl="8" v-if="subjects">
        <section class="admin-card ai-glass-panel tutoring-subjects-card" :class="{ 'is-disabled': action != '' && action != 'add_subjects' }">
            <div class="admin-card-head mb-3">
                <div class="tov-card-heading">
                    <div class="admin-card-eyebrow">Nachhilfe</div>
                    <h2 class="admin-card-title">Fächer</h2>
                </div>
            </div>

            <div class="kpi-sub mb-4">Verwalte Nachhilfe-Fächer, Freigabe-Regeln und Mentor:innen-E-Mails.</div>

            <!-- ANZEIGE FÄCHER -->
            <template v-if="action == ''">
                <div class="tutoring-subjects-layout">
                    <section class="tutoring-subjects-panel" :class="{ 'is-disabled': action_2 != '' }">
                        <div class="tutoring-subjects-panel__head">
                            <div class="tutoring-subjects-panel__title">Fachauswahl</div>
                            <div class="tutoring-subjects-panel__meta">{{ subjects.length }} Fächer</div>
                        </div>

                        <div class="tutoring-subjects-chip-wrap">
                            <v-chip-group column>
                                <v-chip
                                    v-for="subject in subjects"
                                    :key="subject.id"
                                    rounded="pill"
                                    :variant="selected_subject?.id === subject.id ? 'flat' : 'tonal'"
                                    :color="selected_subject?.id === subject.id ? 'primary' : 'secondary'"
                                    class="tutoring-subject-chip"
                                    @click="selectSubject(subject)">
                                    {{ subject.long_name + ' (' + subject.short_name + ')' }}
                                </v-chip>
                            </v-chip-group>
                        </div>
                    </section>

                    <section class="tutoring-subjects-panel" v-if="selected_subject">
                        <div class="tutoring-subjects-panel__head">
                            <div class="tutoring-subjects-panel__title">Ausgewähltes Fach</div>
                            <div class="tutoring-subjects-status-badges">
                                <span class="tutoring-subjects-badge" :class="selected_subject.must_be_accepted ? 'is-yes' : 'is-no'">
                                    {{ selected_subject.must_be_accepted ? 'Freigabe nötig' : 'Auto' }}
                                </span>
                            </div>
                        </div>

                        <div class="tutoring-subjects-detail">
                            <div class="tutoring-subjects-detail__title">
                                {{ selected_subject.long_name + ' (' + selected_subject.short_name + ')' }}
                            </div>

                            <div class="tutoring-subjects-mentor-block" v-if="selected_subject.email_mentors?.length && selected_subject.must_be_accepted">
                                <div class="tutoring-subjects-mentor-block__label">Mentor:innen</div>
                                <div class="tutoring-subjects-mentor-list">
                                    <div v-for="(mentor, index) in selected_subject.email_mentors" :key="index" class="tutoring-subjects-mentor-item">
                                        <v-icon size="14" icon="mdi-email-outline" />
                                        <span>{{ mentor }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tutoring-subjects-card-menu" :class="{ 'is-disabled': action_2 != '' }">
                            <v-btn color="primary" rounded="lg" prepend-icon="mdi-pencil" @click="editSubject(selected_subject)" v-if="delete_level == 0">Bearbeiten</v-btn>
                            <v-btn color="warning" rounded="lg" prepend-icon="mdi-delete" @click="delete_level++" v-if="delete_level == 0">Löschen</v-btn>
                            <v-btn color="success" rounded="lg" prepend-icon="mdi-delete-off" @click="delete_level = 0" v-if="delete_level == 1">Abbruch</v-btn>
                            <v-btn color="error" rounded="lg" prepend-icon="mdi-delete" @click="deleteSubject(selected_subject)" v-if="delete_level == 1">Löschen bestätigen</v-btn>
                        </div>
                    </section>

                    <section class="tutoring-subjects-panel" v-if="selected_subject && action_2 == 'edit_subject'">
                        <div class="tutoring-subjects-panel__head">
                            <div class="tutoring-subjects-panel__title">Fach ändern</div>
                        </div>

                        <v-form ref="form" v-model="is_valid" class="tutoring-subjects-form" @submit.prevent="updateSubject(data)">
                            <v-text-field autofocus v-model="data.short_name" label="Kurzbezeichnung" :rules="[required(), maxLength(10)]" tabindex="1" />
                            <v-text-field v-model="data.long_name" label="Bezeichnung" :rules="[required(), maxLength(255)]" />
                            <v-checkbox v-model="data.must_be_accepted" label="Muss akzeptiert werden" />

                            <div class="tutoring-subjects-mentor-editor">
                                <div class="tutoring-subjects-mentor-block__label">E-Mail Mentor:innen</div>

                                <template v-if="data.email_mentors && data.email_mentors.length > 0">
                                    <div v-for="(mentor, index) in data.email_mentors" :key="index" class="tutoring-subjects-mentor-edit-row">
                                        <v-text-field
                                            v-model="data.email_mentors[index]"
                                            label="E-Mail Mentor:in"
                                            :rules="[mailOrNull(), maxLength(255)]" />
                                        <v-btn
                                            v-if="index === data.email_mentors.length - 1"
                                            type="button"
                                            icon="mdi-plus"
                                            color="primary"
                                            variant="tonal"
                                            size="small"
                                            @click="addMentor(data)" />
                                        <v-btn
                                            type="button"
                                            icon="mdi-minus"
                                            color="error"
                                            variant="tonal"
                                            size="small"
                                            @click="removeMentor(data, index)" />
                                    </div>
                                </template>

                                <v-btn
                                    v-else
                                    type="button"
                                    prepend-icon="mdi-plus"
                                    color="primary"
                                    variant="tonal"
                                    rounded="lg"
                                    size="small"
                                    @click="addMentor(data)">
                                    E-Mail Mentor hinzufügen
                                </v-btn>
                            </div>

                            <div class="tutoring-subjects-card-menu">
                                <v-btn color="warning" rounded="lg" prepend-icon="mdi-close" @click="action_2 = ''">Abbruch</v-btn>
                                <v-btn color="success" rounded="lg" prepend-icon="mdi-content-save" type="submit" tabindex="2">Speichern</v-btn>
                            </div>
                        </v-form>
                    </section>

                    <div class="tutoring-subjects-card-menu" v-if="action_2 == ''">
                        <v-btn color="primary" variant="tonal" rounded="lg" prepend-icon="mdi-plus-circle-multiple" @click="createSubjects">
                            Fächer anlegen
                        </v-btn>
                    </div>
                </div>
            </template>

            <!-- HINZUFÜGEN FÄCHER -->
            <template v-if="action == 'add_subjects'">
                <div class="tutoring-subjects-layout">
                    <section class="tutoring-subjects-panel">
                        <div class="tutoring-subjects-panel__head">
                            <div class="tutoring-subjects-panel__title">Neue Fächer hinzufügen</div>
                        </div>

                        <div class="tutoring-subjects-info">
                            <div>Zeilen mit <strong>Abkürzung</strong> und <strong>Bezeichnung</strong> werden angelegt.</div>
                            <div>E-Mail Mentor:in darf leer bleiben.</div>
                        </div>

                        <v-form ref="form" v-model="is_valid" class="tutoring-subjects-form" @submit.prevent="doCreateSubjects(my_subjects)">
                            <div v-for="(subject, i) in my_subjects" :key="i" class="tutoring-subjects-create-row">
                                <div class="tutoring-subjects-create-row__head">
                                    <div class="tutoring-subjects-create-row__title">Fach {{ i + 1 }}</div>
                                </div>

                                <div class="tutoring-subjects-create-grid">
                                    <v-text-field
                                        v-model="subject.short_name"
                                        label="Abkürzung"
                                        :rules="[maxLength(10)]"
                                        @input="subject.short_name = subject.short_name?.toUpperCase()" />

                                    <v-text-field v-model="subject.long_name" label="Lange Bezeichnung" :rules="[maxLength(255)]" />

                                    <div class="tutoring-subjects-mentor-editor">
                                        <div class="tutoring-subjects-mentor-block__label">E-Mail Mentor:innen</div>
                                        <div v-for="(mentor, index) in subject.email_mentors" :key="index" class="tutoring-subjects-mentor-edit-row">
                                            <v-text-field v-model="subject.email_mentors[index]" label="E-Mail Mentor:in" :rules="[mailOrNull(), maxLength(255)]" />

                                            <v-btn
                                                v-if="index === subject.email_mentors.length - 1"
                                                type="button"
                                                icon="mdi-plus"
                                                color="primary"
                                                variant="tonal"
                                                size="small"
                                                @click="addMentor(subject)" />

                                            <v-btn
                                                v-if="subject.email_mentors.length > 1"
                                                type="button"
                                                icon="mdi-minus"
                                                color="error"
                                                variant="tonal"
                                                size="small"
                                                @click="removeMentor(subject, index)" />
                                        </div>
                                    </div>

                                    <v-checkbox v-model="subject.must_be_accepted" label="Muss akzeptiert werden" />
                                </div>
                            </div>

                            <div class="tutoring-subjects-card-menu">
                                <v-btn color="warning" rounded="lg" prepend-icon="mdi-close" @click="abortSubjects">Abbruch</v-btn>
                                <v-btn color="success" rounded="lg" prepend-icon="mdi-content-save" type="submit" tabindex="2">Speichern</v-btn>
                            </div>
                        </v-form>
                    </section>
                </div>
            </template>
        </section>
    </v-col>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSubjectStore } from '@/stores/admin/tutoring/SubjectStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: {},

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
            if (!(await this.subjectStore.updateSubject(subject))) return

            await this.subjectStore.index()
            this.selected_subject = this.subjects.find((s) => s.id === subject.id)
            this.delete_level = 0
            this.action_2 = ''
        },

        editSubject(subject) {
            this.delete_level = 0
            this.data = {
                ...subject,
                email_mentors: subject.email_mentors ? [...subject.email_mentors] : [],
            }
            this.action_2 = 'edit_subject'
        },
        selectSubject(subject) {
            this.delete_level = 0
            if (this.action_2) this.action_2 = ''
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
            this.delete_level = 0
            this.action = ''
        },

        createSubjects() {
            this.delete_level = 0
            this.action_2 = ''
            this.my_subjects = Array.from({ length: 5 }, () => ({
                short_name: '',
                long_name: '',
                email_mentors: [''],
                must_be_accepted: false,
            }))
            this.action = 'add_subjects'
        },
        abortSubjects() {
            this.delete_level = 0
            this.action = ''
        },
    },
}
</script>
<style scoped src="@/../css/admin-index-page.css"></style>
<style scoped src="@/../css/admin-tutoring-overview-cards.css"></style>
<style scoped src="@/../css/admin-tutoring-subjects-card.css"></style>
