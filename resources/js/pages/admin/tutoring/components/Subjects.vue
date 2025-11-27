<template>
    <v-col cols="12" md="6" xl="4" v-if="data">
        <its-grid-box color="primary" title="Fächer" icon="mdi-television-shimmer" class="w-100">
            <div class="d-flex flex-row align-start">
                <v-card tile flat color="transparent" class="w-100">
                    <!-- ANZEIGE EINSTELLUNGEN -->
                    <v-card-text class="text-body-1 d-flex flex-column ga-2" v-if="action == ''">
                        <div class="d-flex flex-row align-center justify-space-between w-100">
                            <div>Fächer</div>
                        </div>

                        <div class="mt-4">
                            <its-menu-button title="Fächer" subtitle="anlegen" icon="mdi-plus-circle-multiple" color="primary" @click="addSubjects" />
                        </div>
                    </v-card-text>

                    <!-- ÄNDERN EINSTELLUNGEN -->
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
                        <v-form ref="form" v-model="is_valid" @submit.prevent="doAddSubjects(subjects)">
                            <v-container>
                                <v-row>
                                    <v-col cols="2">Kurzbez.</v-col>
                                    <v-col cols="5">Bezeichnung</v-col>
                                    <v-col cols="5">E-Mail Mentor</v-col>
                                </v-row>

                                <v-row v-for="(subject, i) in subjects" :key="i">
                                    <v-col cols="2">
                                        <v-text-field
                                            v-model="subject.short_name"
                                            label="Abkürzung"
                                            :rules="[maxLength(10)]"
                                            @input="subject.short_name = subject.short_name?.toUpperCase()" />
                                    </v-col>
                                    <v-col cols="5"><v-text-field v-model="subject.long_name" label="Lange Bezeichnung" :rules="[maxLength(255)]" /></v-col>
                                    <v-col cols="5">
                                        <v-text-field v-model="subject.email_mentor" label="E-Mail Mentor" :rules="[mailOrNull(), maxLength(255)]" />
                                    </v-col>
                                </v-row>
                            </v-container>

                            <div class="d-flex flex-row align-center justify-space-between">
                                <v-btn color="warning" flat tile @click="abortSubjects">Abbruch</v-btn>
                                <v-btn color="success" flat tile type="submit" tabindex="2">Speichern</v-btn>
                            </div>
                        </v-form>
                    </v-card-text>
                    <v-card-text>
                        SUBJECTS:
                        {{ subjects }}
                    </v-card-text>
                    <v-card-text>
                        DATA:
                        {{ data }}
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
import { useSchoolToolStore } from '@/stores/admin/SchoolToolStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsMenuButton, ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.schoolToolStore = useSchoolToolStore()
        await this.schoolToolStore.loadConfig()
        this.action = ''
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            schoolToolStore: null,
            is_valid: false,
            subjects: [],
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action']),
        ...mapWritableState(useSchoolToolStore, ['data']),
    },

    methods: {
        async doAddSubjects(data) {
            console.log(data)
        },

        addSubjects() {
            this.subjects = Array.from({ length: 5 }, () => ({
                short_name: '',
                long_name: '',
                email_mentor: '',
            }))
            this.action = 'add_subjects'
        },
        abortSubjects() {
            this.action = ''
        },
    },
}
</script>
