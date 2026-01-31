<template>
    <!-- SETTINGS -->
    <v-col cols="12" md="6" xl="4">
        <ItsGridBox color="primary" title="Arbeiten und Bewertungen" icon="mdi-test-tube" class="w-100" :disabled="action != ''">
            <!-- NEUE ARBEIT ANLEGEN -->
            <v-card tile flat color="transparent" class="mt-4">
                <ItsMenuButton title="Arbeit" subtitle="anlegen" icon="mdi-plus-circle-multiple" color="primary" @click="newWork" />
            </v-card>

            <!-- ALLE ARBEITEN MIT NOTEN ANZEIGEN -->
            <v-list density="compact" class="bg-transparent">
                <v-list-item v-for="(work, index) in teaching_works" :key="index" class="px-0">
                    <div class="d-flex flex-column w-100">
                        <div class="d-flex flex-row align-center justify-space-between">
                            <div class="text-body-1 font-weight-medium">{{ work.short_name }} - {{ work.name }}</div>
                            <div class="d-flex flex-row align-center ga-1">
                                <v-btn flat tile size="x-small" color="warning" icon="mdi-delete" @click="startDelete(index)" v-if="delete_index !== index" />
                                <v-btn flat tile size="x-small" color="success" icon="mdi-delete-off" @click="delete_index = null" v-if="delete_index === index" />
                                <v-btn flat tile size="x-small" color="error" icon="mdi-delete" @click="deleteWork(index)" v-if="delete_index === index" />
                                <v-btn flat tile size="x-small" color="primary" icon="mdi-pencil" @click="editWork(index)" v-if="delete_index !== index" />
                            </div>
                        </div>
                        <div v-if="work.grades?.length" class="mt-1">
                            <v-chip v-for="(grade, idx) in sortedGrades(work.grades)" :key="idx" size="x-small" variant="outlined" class="mr-1 mb-1">
                                {{ grade.grade }}<span v-if="grade.name" class="ml-1">({{ grade.name }})</span><span v-if="grade.value" class="ml-1">= {{ grade.value }}</span>
                            </v-chip>
                        </div>
                    </div>
                    <v-divider class="mt-2" />
                </v-list-item>
            </v-list>
        </ItsGridBox>
    </v-col>

    <v-col cols="12" md="6" xl="4" v-if="action == 'teaching_work_new_or_edit'">
        <ItsGridBox color="primary" :title="edit_index !== null ? 'Arbeit ändern' : 'Neue Arbeit'" icon="mdi-test-tube" class="w-100">
            <!-- NEUE/EDIT ARBEIT -->
            <v-card tile flat color="transparent" class="w-100">
                <v-card-text>
                    <v-form ref="form" v-model="is_valid" @submit.prevent="save" class="mb-4">
                        <div class="text-caption text-text">Bitte geben Sie die Felder ein (* = Pflichtfeld)</div>
                        <v-text-field autofocus v-model="data.short_name" label="Kurzzeichen (z. B. SA für Schularbeit)*" :rules="[required(), maxLength(10)]" />
                        <v-text-field v-model="data.name" label="Bezeichnung *" :rules="[required(), maxLength(255)]" />

                        <!-- Noten -->
                        <div class="text-caption text-text mt-4">Noten</div>
                        <div class="d-flex flex-row align-center ga-2">
                            <v-text-field v-model="new_grade.grade" label="Note *" density="compact" hide-details style="max-width: 80px" />
                            <v-text-field v-model="new_grade.name" label="Bezeichnung" density="compact" hide-details />
                            <v-text-field v-model="new_grade.value" label="Wert" density="compact" hide-details style="max-width: 80px" @keyup.enter="addGrade" />
                            <v-btn color="primary" icon="mdi-plus" size="small" @click="addGrade" :disabled="!new_grade.grade" />
                        </div>
                        <v-chip-group class="mt-2" column>
                            <v-chip v-for="(grade, idx) in data.grades" :key="`${idx}-${grade.grade}-${grade.value}`" closable @click:close="removeGrade(idx)">
                                {{ grade.grade }}<span v-if="grade.name" class="ml-1 text-medium-emphasis">({{ grade.name }})</span><span v-if="grade.value" class="ml-1 text-medium-emphasis">= {{ grade.value }}</span>
                            </v-chip>
                        </v-chip-group>

                        <div class="d-flex flex-row align-center justify-space-between mt-4">
                            <v-btn color="warning" flat tile @click="abortNewWork">Abbruch</v-btn>
                            <v-btn color="success" flat tile type="submit" :disabled="!is_valid">Speichern</v-btn>
                        </div>
                    </v-form>
                </v-card-text>
            </v-card>
        </ItsGridBox>
    </v-col>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsGridBox, ItsMenuButton },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.teachingStore = useTeachingStore()
        await this.teachingStore.loadSettings()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            teachingStore: null,
            is_valid: false,
            data: {
                short_name: '',
                name: '',
                grades: [],
            },
            new_grade: { grade: '', name: '', value: '' },
            edit_index: null,
            delete_index: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapWritableState(useTeachingStore, ['settings']),
        teaching_works() {
            const works = this.settings?.teaching_works || []
            return [...works].sort((a, b) => (a.short_name || '').localeCompare(b.short_name || '', 'de'))
        },
    },

    watch: {
        'data.short_name'(val) {
            if (val && val !== val.toUpperCase()) {
                this.data.short_name = val.toUpperCase()
            }
        },
    },

    methods: {
        sortedGrades(grades) {
            if (!grades?.length) return []
            return [...grades].sort((a, b) => {
                const valA = parseFloat(String(a.value || '').replace(',', '.')) || 0
                const valB = parseFloat(String(b.value || '').replace(',', '.')) || 0
                // Both have no value (0) - keep original order
                if (valA === 0 && valB === 0) return 0
                // Only a has no value - put at end
                if (valA === 0) return 1
                // Only b has no value - put at end
                if (valB === 0) return -1
                // Both have values - sort ascending
                return valA - valB
            })
        },

        newWork() {
            this.data = { short_name: '', name: '', grades: [] }
            this.new_grade = { grade: '', name: '', value: '' }
            this.edit_index = null
            this.action = 'teaching_work_new_or_edit'
        },

        editWork(index) {
            const work = this.teaching_works[index]
            this.data = {
                ...work,
                grades: (work.grades || []).map((g) => ({ ...g })),
            }
            this.new_grade = { grade: '', name: '', value: '' }
            this.edit_index = index
            this.action = 'teaching_work_new_or_edit'
        },

        addGrade() {
            if (!this.new_grade.grade) return
            if (!this.data.grades) this.data.grades = []
            this.data.grades.push({
                grade: this.new_grade.grade.trim(),
                name: this.new_grade.name?.trim() || '',
                value: this.new_grade.value?.trim() || '',
            })
            this.new_grade = { grade: '', name: '', value: '' }
        },

        removeGrade(index) {
            const newGrades = [...this.data.grades]
            newGrades.splice(index, 1)
            this.data = { ...this.data, grades: newGrades }
        },

        abortNewWork() {
            this.action = ''
            this.edit_index = null
        },

        startDelete(index) {
            this.delete_index = index
        },

        async save() {
            const works = [...this.teaching_works]

            if (this.edit_index !== null) {
                works[this.edit_index] = { ...this.data }
            } else {
                works.push({ ...this.data })
            }

            await this.teachingStore.saveSettings({ teaching_works: works })
            this.action = ''
            this.edit_index = null
        },

        async deleteWork(index) {
            const works = [...this.teaching_works]
            works.splice(index, 1)

            await this.teachingStore.saveSettings({ teaching_works: works })
            this.delete_index = null
        },
    },
}
</script>
