<template>
    <!-- MENÜ FÜR SETTINGS -->
    <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap ga-2 w-100 my-2 ml-1">
        <its-menu-button
            subtitle="Grundeinstellungen"
            :icon="show_basic_settings ? 'mdi-eye' : 'mdi-eye-off'"
            :color="show_basic_settings ? 'success' : 'secondary'"
            @click="show_basic_settings = !show_basic_settings" />
        <its-menu-button
            subtitle="Benotungsschemas"
            :icon="show_schemas ? 'mdi-eye' : 'mdi-eye-off'"
            :color="show_schemas ? 'success' : 'secondary'"
            @click="show_schemas = !show_schemas" />
    </v-card>

    <!-- Grundeinstellungen -->
    <v-col cols="12" md="6" xl="4" v-if="show_basic_settings">
        <ItsGridBox color="primary" title="Grundeinstellungen" icon="mdi-cog" class="w-100">
            <BasicSettings />
        </ItsGridBox>
    </v-col>

    <!-- Schema CRUD -->
    <v-col cols="12" md="6" xl="4" v-if="show_schemas">
        <ItsGridBox color="primary" title="Benotungsschemas" icon="mdi-book-cog" class="w-100">
            <div class="d-flex flex-wrap ga-2 mt-2">
                <v-chip
                    v-for="schema in schemas"
                    :key="schema.id"
                    :color="selected_schema_id === schema.id ? 'primary' : 'secondary'"
                    variant="flat"
                    class="cursor-pointer"
                    @click="selectSchema(schema.id)">
                    {{ schema.name }}
                </v-chip>
                <v-btn icon="mdi-plus" size="small" color="primary" variant="tonal" @click="newSchema" />
            </div>

            <!-- Schema bearbeiten -->
            <div v-if="selected_schema_id" class="d-flex flex-row align-center mt-2 ga-2">
                <v-btn v-if="!is_renaming && !selectedSchemaIsStandard" flat tile size="small" color="primary" prepend-icon="mdi-pencil" @click="startRename">Umbenennen</v-btn>
                <v-btn
                    v-if="!is_renaming && !is_deleting && !selectedSchemaIsStandard && !selectedSchemaInUse"
                    flat
                    tile
                    size="small"
                    color="warning"
                    prepend-icon="mdi-delete"
                    @click="is_deleting = true">
                    Löschen
                </v-btn>
                <v-btn v-if="is_deleting" flat tile size="small" color="success" prepend-icon="mdi-delete-off" @click="is_deleting = false">Abbruch</v-btn>
                <v-btn v-if="is_deleting" flat tile size="small" color="error" prepend-icon="mdi-delete" @click="deleteSchema">Endgültig löschen</v-btn>
                <v-spacer />
                <v-btn v-if="is_renaming" icon="mdi-check" size="x-small" color="success" variant="flat" @click="saveRename" />
                <v-btn v-if="is_renaming" icon="mdi-close" size="x-small" color="warning" variant="flat" @click="is_renaming = false" />
            </div>
            <v-text-field v-if="is_renaming" v-model="rename_value" label="Name" density="compact" hide-details autofocus class="mt-2" @keyup.enter="saveRename" />

            <!-- Schema-Inhalte -->
            <template v-if="selected_schema_id">
                <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap ga-2 w-100 my-2 ml-1">
                    <ItsMenuButton
                        subtitle="Arbeiten & Noten"
                        :icon="show_works ? 'mdi-eye' : 'mdi-eye-off'"
                        :color="show_works ? 'success' : 'secondary'"
                        @click="show_works = !show_works" />
                    <ItsMenuButton
                        subtitle="Benotung"
                        :icon="show_grading ? 'mdi-eye' : 'mdi-eye-off'"
                        :color="show_grading ? 'success' : 'secondary'"
                        @click="show_grading = !show_grading" />
                </v-card>

                <v-col cols="12" v-if="show_works">
                    <WorksAndGrades :schema-id="selected_schema_id" />
                </v-col>
                <v-col cols="12" v-if="show_grading">
                    <Grading :schema-id="selected_schema_id" />
                </v-col>
            </template>

            <v-col v-else-if="!schemas.length" cols="12">
                <v-alert type="info" variant="tonal">Noch kein Benotungsschema vorhanden. Erstellen Sie ein neues Schema, um Arbeiten und Benotung zu konfigurieren.</v-alert>
            </v-col>
        </ItsGridBox>
    </v-col>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import WorksAndGrades from './components/WorksAndGrades.vue'
import Grading from './components/Grading.vue'
import BasicSettings from './components/BasicSettings.vue'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    components: { WorksAndGrades, Grading, BasicSettings, ItsMenuButton, ItsGridBox },

    async beforeMount() {
        this.teachingStore = useTeachingStore()
        this.courseStore = useCourseStore()
        await Promise.all([this.teachingStore.loadSettings(), this.courseStore.index()])
        if (this.schemas.length) {
            this.selected_schema_id = this.schemas[0].id
        }
    },

    data() {
        return {
            teachingStore: null,
            courseStore: null,
            selected_schema_id: null,
            show_basic_settings: true,
            show_schemas: true,
            show_works: true,
            show_grading: true,
            is_renaming: false,
            rename_value: '',
            is_deleting: false,
        }
    },

    computed: {
        ...mapWritableState(useTeachingStore, ['settings']),
        ...mapWritableState(useCourseStore, ['courses']),
        schemas() {
            return this.settings?.teaching_schemas || []
        },
        selectedSchemaInUse() {
            return (this.courses || []).some((c) => c.teaching_schema_id === this.selected_schema_id)
        },
        selectedSchemaIsStandard() {
            const schema = this.schemas.find((s) => s.id === this.selected_schema_id)
            return schema?.name === 'Standard'
        },
    },

    methods: {
        selectSchema(id) {
            this.selected_schema_id = id
            this.is_renaming = false
            this.is_deleting = false
        },

        async newSchema() {
            const standard = this.schemas.find((s) => s.name === 'Standard')
            const schemas = [...this.schemas]
            const newId = crypto.randomUUID()
            schemas.push({
                id: newId,
                name: 'Neues Schema',
                works: JSON.parse(JSON.stringify(standard?.works || [])),
                grading: JSON.parse(JSON.stringify(standard?.grading || {})),
            })
            await this.teachingStore.saveSettings({ teaching_schemas: schemas })
            this.selected_schema_id = newId
        },

        startRename() {
            const schema = this.schemas.find((s) => s.id === this.selected_schema_id)
            if (!schema) return
            this.rename_value = schema.name
            this.is_renaming = true
            this.is_deleting = false
        },

        async saveRename() {
            if (!this.rename_value.trim()) return
            const schemas = this.schemas.map((s) => (s.id === this.selected_schema_id ? { ...s, name: this.rename_value.trim() } : s))
            await this.teachingStore.saveSettings({ teaching_schemas: schemas })
            this.is_renaming = false
        },

        async deleteSchema() {
            const schemas = this.schemas.filter((s) => s.id !== this.selected_schema_id)
            await this.teachingStore.saveSettings({ teaching_schemas: schemas })
            this.selected_schema_id = schemas.length ? schemas[0].id : null
            this.is_deleting = false
        },
    },
}
</script>
