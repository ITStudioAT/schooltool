<template>
    <!-- NEUES ANGEBOT  -->
    <v-card-text>
        <v-card tile flat color="tutoring_card" max-width="600">
            <v-form ref="form" v-model="is_valid" @submit.prevent="doCreateOffer(data)">
                <v-card-title class="bg-tutoring_card_title mb-2">Nachhilfe anbieten</v-card-title>
                <!-- Nachanme, Vorname, E-Mail eingeben-->
                <v-card-text>
                    <v-card tile flat color="transparent">
                        <div>
                            <v-chip-group selected-class="text-success" column color="primary">
                                <v-chip color="primary" v-for="subject in subjects" :key="subject.id" @click="selectSubject(subject)">
                                    {{ subject.long_name + ' (' + subject.short_name + ')' }}
                                </v-chip>
                            </v-chip-group>
                        </div>
                        <div v-if="selectedSubject" class="mt-4">
                            <v-text-field autofocus flat rounded="0" v-model="data.title" label="Titel" :rules="[required(), maxLength(255)]" />
                            <v-textarea flat rounded="0" v-model="data.description" label="Beschreibe Deine Nachhilfe im Detail" :rules="[maxLength(1024)]" />
                        </div>

                        <div class="mt-4">
                            <label class="text-subtitle-2 mb-2 d-block">Für folgende Klassen ist Deine Nachhilfe gedacht:</label>

                            <div class="mb-3">
                                <div class="text-caption text-medium-emphasis mb-1">Unterstufe</div>
                                <v-row dense>
                                    <v-col v-for="classNumber in 4" :key="classNumber" cols="12" sm="6" md="3">
                                        <v-checkbox v-model="data.classes[classNumber]" :label="`${classNumber}. Klasse`" hide-details />
                                    </v-col>
                                </v-row>
                            </div>

                            <div>
                                <div class="text-caption text-medium-emphasis mb-1">Oberstufe</div>
                                <v-row dense>
                                    <v-col v-for="classNumber in 5" :key="classNumber + 4" cols="12" sm="6" md="3">
                                        <v-checkbox v-model="data.classes[classNumber + 4]" :label="`${classNumber + 4}. Klasse`" hide-details />
                                    </v-col>
                                </v-row>
                            </div>
                        </div>
                    </v-card>
                </v-card-text>

                <!-- ERROR-->
                <v-card-text v-if="error">
                    <v-alert type="error">{{ error?.response?.data?.message + ' (' + error?.response?.status + ')' }}</v-alert>
                </v-card-text>
                <!-- SCHLIESSEN/SPEICHERN-->
                <v-card-actions>
                    <div class="d-flex flex-row align-center justify-space-between w-100">
                        <its-menu-button subtitle="Abbruch" icon="mdi-close" color="warning" @click="action = ''" />
                        <its-menu-button subtitle="Speichern" icon="mdi-content-save" color="success" @click="updateProfile(data)" />
                    </div>
                </v-card-actions>
            </v-form>
        </v-card>
    </v-card-text>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useTutoringStore } from '@/stores/tutoring/TutoringStore'
import { useUserStore } from '@/stores/tutoring/UserStore'
import { useSubjectStore } from '@/stores/tutoring/SubjectStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },
    components: { ItsMenuButton, ItsGridBox },

    async beforeMount() {
        this.tutoringStore = useTutoringStore()
        this.userStore = useUserStore()
        this.subjectStore = useSubjectStore()
        await this.tutoringStore.loadAuth()
        await this.subjectStore.index()
        this.createOffer()
    },

    async mounted() {},

    unmounted() {},

    data() {
        return {
            tutoringStore: null,
            userStore: null,
            is_valid: false,
            is_password_visible: false,
            is_password_visible_confirm: false,
            selectedSubject: null,
            step: 0,
        }
    },

    computed: {
        ...mapWritableState(useTutoringStore, ['auth', 'action']),
        ...mapWritableState(useUserStore, ['error', 'data']),
        ...mapWritableState(useSubjectStore, ['subjects']),
    },

    watch: {},

    methods: {
        selectSubject(subject) {
            this.selectedSubject = subject
            this.data.title = 'Biete ' + subject.long_name + ' Nachhilfe'
        },
        createOffer() {
            this.data = {
                title: '',
                description: '',
                classes: { 1: false, 2: false, 3: false, 4: false, 5: false, 6: false, 7: false, 8: false, 9: false },
            }
        },

        async doCreateOffer(data) {},
    },
}
</script>
