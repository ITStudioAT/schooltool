<template>
    <ItsGridBox color="primary" title="Meine Fächer" icon="mdi-invoice-list" class="w-100" text="tralala">
        <!-- ALLE KURSE ANZEIGEN -->

        <v-card tile flat color="transparent" class="w-100" v-if="action == ''">
            <div class="w-100 text-right">
                <v-btn color="success" text="Neues Fach" prepend-icon="mdi-plus" @click="newCourse" />
            </div>
            <v-list v-if="courses.length > 0" class="w-100 bg-transparent">
                <v-list-item v-for="course in courses" :key="course.id" class="px-0">
                    <template #title>
                        <span class="font-weight-bold">{{ course.title }}</span>
                    </template>
                    <template #subtitle>
                        <div class="d-flex flex-wrap ga-1 mt-1">
                            <v-chip v-for="cls in course.classes" :key="cls" size="small" variant="tonal">
                                {{ cls }}
                            </v-chip>
                        </div>
                    </template>
                    <template #append>
                        <v-btn icon="mdi-pencil" variant="text" size="small" @click="editCourse(course)" />
                    </template>
                </v-list-item>
            </v-list>
            <div v-else class="text-body-2 text-medium-emphasis pa-4">Keine Fächer vorhanden.</div>
        </v-card>

        <!-- NEUER KURS-->
        <v-card tile flat color="transparent" class="w-100" v-if="action == 'teaching_course_new_or_edit'">
            <v-card-title v-if="!data.id">Neues Fach</v-card-title>
            <v-card-title v-if="data.id">Fach ändern</v-card-title>
            <v-card-text>
                <v-form ref="form" v-model="is_valid" @submit.prevent="save(data)" class="mb-4">
                    <div class="text-caption text-text">Bitte geben Sie die Felder ein (* = Pflichtfeld)</div>
                    <v-text-field autofocus v-model="data.title" label="Bezeichnung *" :rules="[required(), maxLength(255)]" />

                    <div class="text-caption text-text mt-4">Klassen auswählen</div>
                    <v-chip-group v-model="data.classes" multiple column>
                        <v-chip v-for="cls in classes" :key="cls" :value="cls" filter variant="outlined">
                            {{ cls }}
                        </v-chip>
                    </v-chip-group>

                    <div class="d-flex flex-row align-center justify-space-between mt-4">
                        <v-btn color="warning" flat tile @click="abortNewCourse">Abbruch</v-btn>
                        <v-btn color="success" flat tile type="submit" v-if="data.title && data?.classes?.length > 0">Speichern</v-btn>
                    </div>
                </v-form>
            </v-card-text>
        </v-card>
    </ItsGridBox>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsGridBox, ItsMenuButton },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.courseStore = useCourseStore()
        this.courseStore.index()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            courseStore: null,
            is_valid: false,
            data: {
                selected_classes: [],
            },
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapWritableState(useCourseStore, ['courses', 'classes']),
    },

    watch: {},

    methods: {
        async save(data) {
            if (data.id) {
                await this.courseStore.update(data)
            } else {
                await this.courseStore.store(data)
            }
            await this.courseStore.index()
            this.action = ''
        },

        newCourse() {
            this.data = {}
            this.action = 'teaching_course_new_or_edit'
        },
        editCourse(course) {
            this.data = { ...course }
            this.action = 'teaching_course_new_or_edit'
        },
        abortNewCourse() {
            this.action = ''
        },
    },
}
</script>
