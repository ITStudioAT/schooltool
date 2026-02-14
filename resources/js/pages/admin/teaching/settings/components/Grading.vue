<template>
    <ItsGridBox color="primary" title="Benotung" icon="mdi-numeric" class="w-100" :disabled="action != ''">
        <!-- HEADER ACTIONS -->
        <div class="d-flex flex-row align-center justify-end mt-2 ga-2">
            <v-btn v-if="!is_editing" icon="mdi-pencil" size="x-small" color="primary" variant="flat" @click="is_editing = true" />
            <v-btn v-if="is_editing" icon="mdi-check" size="x-small" color="success" variant="flat" :disabled="!isValid" @click="save" />
            <v-btn v-if="is_editing" icon="mdi-close" size="x-small" color="warning" variant="flat" @click="exitEditMode" />
        </div>

        <v-card tile flat color="transparent" class="mt-4">
            <v-card-text>
                <!-- SEMESTER ANZAHL -->
                <div class="text-caption text-medium-emphasis mb-2">Anzahl der Semester</div>
                <v-btn-toggle v-if="is_editing" v-model="data.semester_count" mandatory color="primary" class="mb-4">
                    <v-btn :value="1" size="small">1 Semester</v-btn>
                    <v-btn :value="2" size="small">2 Semester</v-btn>
                </v-btn-toggle>
                <div v-else class="text-body-1 mb-4">{{ data.semester_count }} Semester</div>

                <!-- GEWICHTUNG BEI 2 SEMESTERN -->
                <div v-if="data.semester_count === 2" class="mt-4">
                    <div class="text-caption text-medium-emphasis mb-2">Gewichtung der Semester</div>

                    <!-- EDIT MODE: SLIDERS -->
                    <div v-if="is_editing" class="d-flex flex-row align-center ga-4">
                        <div class="flex-grow-1">
                            <div class="text-body-2 mb-1">1. Semester</div>
                            <v-slider
                                v-model="data.semester_1_weight"
                                :max="100"
                                :min="0"
                                :step="5"
                                thumb-label
                                color="primary"
                                hide-details
                                @update:model-value="updateWeight1" />
                            <div class="text-center text-h6 mt-1">{{ data.semester_1_weight }}%</div>
                        </div>
                        <div class="flex-grow-1">
                            <div class="text-body-2 mb-1">2. Semester</div>
                            <v-slider
                                v-model="data.semester_2_weight"
                                :max="100"
                                :min="0"
                                :step="5"
                                thumb-label
                                color="error"
                                hide-details
                                @update:model-value="updateWeight2" />
                            <div class="text-center text-h6 mt-1">{{ data.semester_2_weight }}%</div>
                        </div>
                    </div>
                    <!-- VIEW MODE: DISPLAY ONLY -->
                    <div v-else class="d-flex flex-row align-center ga-4">
                        <div class="text-body-1">1. Semester: {{ data.semester_1_weight }}%</div>
                        <div class="text-body-1">2. Semester: {{ data.semester_2_weight }}%</div>
                    </div>

                    <!-- WARNUNG WENN NICHT 100% -->
                    <v-alert v-if="is_editing && totalSemesterWeight !== 100" type="warning" density="compact" class="mt-4">
                        Die Summe der Semester-Gewichtungen muss 100% ergeben (aktuell: {{ totalSemesterWeight }}%)
                    </v-alert>

                    <!-- BERECHNUNG DES 1. SEMESTERS FÜR JAHRESNOTE -->
                    <div class="mt-4">
                        <div class="text-caption text-medium-emphasis mb-2">Berechnung des 1. Semesters für die Jahresnote</div>
                        <v-radio-group v-if="is_editing" v-model="data.use_semester_grade_only" hide-details class="mt-2">
                            <v-radio :value="false" color="primary">
                                <template v-slot:label>
                                    <div class="text-body-2">
                                        <strong>Alle Werte aus dem 1. Semester</strong>
                                        <div class="text-caption text-medium-emphasis">Alle Kategorien und Arbeiten werden neu berechnet</div>
                                    </div>
                                </template>
                            </v-radio>
                            <v-radio :value="true" color="primary" class="mt-2">
                                <template v-slot:label>
                                    <div class="text-body-2">
                                        <strong>Nur die Semesternote</strong>
                                        <div class="text-caption text-medium-emphasis">Die bereits berechnete Note des 1. Semesters wird verwendet</div>
                                    </div>
                                </template>
                            </v-radio>
                        </v-radio-group>
                        <div v-else class="text-body-1">
                            {{ data.use_semester_grade_only ? 'Nur die Semesternote' : 'Alle Werte aus dem 1. Semester' }}
                        </div>
                    </div>
                </div>

                <v-divider class="my-6" />

                <!-- KATEGORIEN PRO SEMESTER -->
                <div class="text-caption text-medium-emphasis mb-2">Kategorien pro Semester</div>
                <div class="text-body-2 text-medium-emphasis mb-4">
                    Definieren Sie die Kategorien und weisen Sie Arbeiten zu.
                </div>

                <!-- KATEGORIEN LISTE -->
                <div v-if="data.categories?.length" class="mb-4">
                    <div
                        v-for="(category, index) in data.categories"
                        :key="index"
                        class="mb-3 pa-3 rounded"
                        style="background-color: rgba(var(--v-theme-primary), 0.05);">
                        <!-- KATEGORIE HEADER -->
                        <div class="d-flex align-center ga-2">
                            <!-- ANZEIGE MODUS -->
                            <template v-if="edit_index !== index">
                                <v-btn
                                    :icon="expanded_index === index ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                                    size="x-small"
                                    variant="text"
                                    @click="expanded_index = expanded_index === index ? null : index" />
                                <v-icon size="small" color="primary">mdi-folder-outline</v-icon>
                                <span class="text-body-1 flex-grow-1">{{ category.name }}</span>
                                <v-chip size="small" color="primary" variant="outlined">{{ category.weight }}%</v-chip>
                                <v-chip v-if="category.works?.length" size="x-small" color="success" variant="tonal">
                                    {{ category.works.length }} Arbeit(en)
                                </v-chip>
                                <template v-if="is_editing">
                                    <v-btn
                                        v-if="delete_index !== index"
                                        icon="mdi-pencil"
                                        size="x-small"
                                        color="primary"
                                        variant="text"
                                        @click="startEditCategory(index)" />
                                    <v-btn
                                        v-if="delete_index !== index"
                                        icon="mdi-delete"
                                        size="x-small"
                                        color="error"
                                        variant="text"
                                        @click="delete_index = index" />
                                    <v-btn
                                        v-if="delete_index === index"
                                        icon="mdi-delete-off"
                                        size="x-small"
                                        color="success"
                                        variant="text"
                                        @click="delete_index = null" />
                                    <v-btn
                                        v-if="delete_index === index"
                                        icon="mdi-delete"
                                        size="x-small"
                                        color="error"
                                        variant="flat"
                                        @click="removeCategory(index)" />
                                </template>
                            </template>
                            <!-- BEARBEITEN MODUS -->
                            <template v-else>
                                <v-text-field
                                    v-model="edit_category.name"
                                    density="compact"
                                    hide-details
                                    class="flex-grow-1" />
                                <v-text-field
                                    v-model="edit_category.weight"
                                    density="compact"
                                    hide-details
                                    type="number"
                                    step="5"
                                    style="max-width: 110px"
                                    @keyup.enter="saveEditCategory" />
                                <v-btn
                                    icon="mdi-check"
                                    size="x-small"
                                    color="success"
                                    variant="flat"
                                    :disabled="!edit_category.name || !edit_category.weight"
                                    @click="saveEditCategory" />
                                <v-btn
                                    icon="mdi-close"
                                    size="x-small"
                                    color="warning"
                                    variant="flat"
                                    @click="cancelEditCategory" />
                            </template>
                        </div>

                        <!-- ARBEITEN ZUWEISUNG (EXPANDIERT) -->
                        <div v-if="expanded_index === index" class="mt-3 pt-3" style="border-top: 1px solid rgba(var(--v-theme-primary), 0.1);">
                            <div class="text-caption text-medium-emphasis mb-2">Zugewiesene Arbeiten (Mittelwert wird berechnet)</div>

                            <!-- VERFÜGBARE ARBEITEN -->
                            <div v-if="teaching_works?.length" class="d-flex flex-wrap ga-2">
                                <v-chip
                                    v-for="work in teaching_works"
                                    :key="work.short_name"
                                    :color="isWorkAssigned(index, work.short_name) ? 'success' : 'default'"
                                    :variant="isWorkAssigned(index, work.short_name) ? 'flat' : 'outlined'"
                                    size="small"
                                    :class="is_editing ? 'cursor-pointer' : ''"
                                    @click="is_editing && toggleWork(index, work.short_name)">
                                    <v-icon v-if="isWorkAssigned(index, work.short_name)" start size="small">mdi-check</v-icon>
                                    {{ work.short_name }} - {{ work.name }}
                                    <span v-if="isWorkAssigned(index, work.short_name)" class="ml-1">
                                        ({{ getWorkFactor(index, work.short_name) }}%)
                                    </span>
                                </v-chip>
                            </div>
                            <div v-else class="text-body-2 text-medium-emphasis">
                                Keine Arbeiten definiert. Bitte zuerst unter "Arbeiten und Bewertungen" anlegen.
                            </div>

                            <!-- ZUGEWIESENE ARBEITEN MIT FAKTOR -->
                            <div v-if="getValidWorks(category.works)?.length" class="mt-4">
                                <div class="text-caption text-medium-emphasis mb-2">Gewichtung der Arbeiten</div>
                                <div class="d-flex flex-column ga-2">
                                    <div
                                        v-for="workItem in getValidWorks(category.works)"
                                        :key="workItem.short_name"
                                        class="d-flex align-center ga-2 pa-2 rounded"
                                        style="background-color: rgba(var(--v-theme-success), 0.1);">
                                        <span class="text-body-2 flex-grow-1">
                                            {{ workItem.short_name }} - {{ getWorkName(workItem.short_name) }}
                                        </span>
                                        <v-btn-toggle
                                            v-if="is_editing"
                                            :model-value="workItem.factor"
                                            mandatory
                                            density="compact"
                                            color="success"
                                            @update:model-value="(val) => setWorkFactor(index, workItem.short_name, val)">
                                            <v-btn :value="25" size="x-small">25%</v-btn>
                                            <v-btn :value="50" size="x-small">50%</v-btn>
                                            <v-btn :value="75" size="x-small">75%</v-btn>
                                            <v-btn :value="100" size="x-small">100%</v-btn>
                                        </v-btn-toggle>
                                        <v-chip v-else size="small" color="success" variant="outlined">{{ workItem.factor }}%</v-chip>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- NEUE KATEGORIE HINZUFÜGEN -->
                <div v-if="is_editing" class="d-flex align-center ga-2">
                    <v-text-field
                        v-model="new_category.name"
                        label="Kategorie (z.B. Schularbeit)"
                        density="compact"
                        hide-details
                        class="flex-grow-1" />
                    <v-text-field
                        v-model="new_category.weight"
                        label="Gewicht %"
                        density="compact"
                        hide-details
                        type="number"
                        step="5"
                        style="max-width: 130px"
                        @keyup.enter="addCategory" />
                    <v-btn
                        icon="mdi-plus"
                        size="small"
                        color="primary"
                        :disabled="!new_category.name || !new_category.weight"
                        @click="addCategory" />
                </div>

                <!-- WARNUNG WENN KATEGORIEN NICHT 100% -->
                <v-alert v-if="is_editing && data.categories?.length && totalCategoryWeight !== 100" type="warning" density="compact" class="mt-4">
                    Die Summe der Kategorie-Gewichtungen muss 100% ergeben (aktuell: {{ totalCategoryWeight }}%)
                </v-alert>

                <!-- VORSCHAU -->
                <v-card flat class="mt-6 pa-4 rounded-lg" style="background-color: rgba(var(--v-theme-primary), 0.08);">
                    <div class="text-caption text-medium-emphasis mb-3">Vorschau: So wird die Semesternote berechnet</div>

                    <!-- KATEGORIEN VORSCHAU -->
                    <div v-if="data.categories?.length" class="d-flex align-center ga-2 flex-wrap justify-center mb-4">
                        <template v-for="(category, index) in data.categories" :key="index">
                            <v-chip color="primary" variant="outlined" size="small">
                                {{ category.name }} × {{ category.weight }}%
                            </v-chip>
                            <v-icon v-if="index < data.categories.length - 1" size="small">mdi-plus</v-icon>
                        </template>
                        <v-icon size="small">mdi-equal</v-icon>
                        <v-chip color="primary" size="small">Semesternote</v-chip>
                    </div>

                    <v-divider v-if="data.categories?.length && data.semester_count === 2" class="my-3" />

                    <!-- SEMESTER VORSCHAU -->
                    <div class="text-caption text-medium-emphasis mb-3" v-if="data.semester_count === 2">Jahresnote</div>
                    <div v-if="data.semester_count === 1" class="d-flex align-center justify-center">
                        <v-chip color="success" size="large" class="px-6">
                            <v-icon start>mdi-school</v-icon>
                            Jahresnote = Semesternote
                        </v-chip>
                    </div>
                    <div v-else class="d-flex flex-column align-center">
                        <div class="d-flex align-center ga-2 flex-wrap justify-center">
                            <v-chip color="primary" variant="outlined">
                                <v-icon start size="small">mdi-numeric-1-circle</v-icon>
                                1. Sem × {{ data.semester_1_weight }}%
                            </v-chip>
                            <v-icon>mdi-plus</v-icon>
                            <v-chip color="error" variant="outlined">
                                <v-icon start size="small">mdi-numeric-2-circle</v-icon>
                                2. Sem × {{ data.semester_2_weight }}%
                            </v-chip>
                            <v-icon>mdi-equal</v-icon>
                            <v-chip color="success">
                                <v-icon start>mdi-school</v-icon>
                                Jahresnote
                            </v-chip>
                        </div>
                    </div>
                </v-card>

            </v-card-text>
        </v-card>
    </ItsGridBox>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    components: { ItsGridBox },

    props: {
        schemaId: {
            type: String,
            required: true,
        },
    },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.teachingStore = useTeachingStore()
        await this.teachingStore.loadSettings()
        this.initData()
    },

    data() {
        return {
            adminStore: null,
            teachingStore: null,
            is_editing: false,
            data: {
                semester_count: 2,
                semester_1_weight: 50,
                semester_2_weight: 50,
                use_semester_grade_only: false,
                categories: [],
            },
            new_category: { name: '', weight: '' },
            edit_category: { name: '', weight: '' },
            edit_index: null,
            delete_index: null,
            expanded_index: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapWritableState(useTeachingStore, ['settings']),
        totalSemesterWeight() {
            return this.data.semester_1_weight + this.data.semester_2_weight
        },
        totalCategoryWeight() {
            if (!this.data.categories?.length) return 0
            return this.data.categories.reduce((sum, cat) => sum + (parseInt(cat.weight) || 0), 0)
        },
        isValid() {
            if (this.data.semester_count === 2 && this.totalSemesterWeight !== 100) return false
            if (this.data.categories?.length && this.totalCategoryWeight !== 100) return false
            return true
        },
        teaching_works() {
            const schema = (this.settings?.teaching_schemas || []).find((s) => s.id === this.schemaId)
            return schema?.works || []
        },
    },

    watch: {
        schemaId() {
            this.initData()
            this.exitEditMode()
        },
        'data.semester_count'(val, oldVal) {
            if (val === 2 && oldVal === 1) {
                this.data.semester_1_weight = 40
                this.data.semester_2_weight = 60
            }
        },
        // When teaching_works changes (e.g., a work is deleted), clean up orphaned references
        teaching_works: {
            handler(newWorks) {
                if (!this.data.categories?.length) return
                const validShortNames = newWorks.map((w) => w.short_name)
                this.data.categories.forEach((cat) => {
                    if (cat.works?.length) {
                        cat.works = cat.works.filter((w) => validShortNames.includes(w.short_name))
                    }
                })
            },
            deep: true,
        },
    },

    methods: {
        exitEditMode() {
            this.is_editing = false
            this.delete_index = null
            this.edit_index = null
            this.edit_category = { name: '', weight: '' }
        },

        initData() {
            const schema = (this.settings?.teaching_schemas || []).find((s) => s.id === this.schemaId)
            const grading = schema?.grading || {}
            const validShortNames = this.teaching_works.map((w) => w.short_name)
            this.data = {
                semester_count: grading.semester_count || 2,
                semester_1_weight: grading.semester_1_weight ?? 50,
                semester_2_weight: grading.semester_2_weight ?? 50,
                use_semester_grade_only: Boolean(grading.use_semester_grade_only),
                categories: (grading.categories || []).map((c) => ({
                    ...c,
                    works: this.normalizeWorks(c.works).filter((w) => validShortNames.includes(w.short_name)),
                    calculation: c.calculation || 'mean',
                })),
            }
        },

        normalizeWorks(works) {
            if (!works) return []
            return works.map((w) => {
                if (typeof w === 'string') {
                    return { short_name: w, factor: 100 }
                }
                return { short_name: w.short_name, factor: w.factor ?? 100 }
            })
        },

        updateWeight1(val) {
            this.data.semester_2_weight = 100 - val
        },

        updateWeight2(val) {
            this.data.semester_1_weight = 100 - val
        },

        addCategory() {
            if (!this.new_category.name || !this.new_category.weight) return
            this.data.categories.push({
                name: this.new_category.name.trim(),
                weight: parseInt(this.new_category.weight) || 0,
                works: [],
                calculation: 'mean',
            })
            this.new_category = { name: '', weight: '' }
        },

        removeCategory(index) {
            this.data.categories = this.data.categories.filter((_, i) => i !== index)
            this.delete_index = null
        },

        startEditCategory(index) {
            const category = this.data.categories[index]
            this.edit_category = { name: category.name, weight: category.weight }
            this.edit_index = index
        },

        saveEditCategory() {
            if (!this.edit_category.name || !this.edit_category.weight) return
            this.data.categories = this.data.categories.map((cat, i) =>
                i === this.edit_index
                    ? { ...cat, name: this.edit_category.name.trim(), weight: parseInt(this.edit_category.weight) || 0 }
                    : cat
            )
            this.edit_index = null
            this.edit_category = { name: '', weight: '' }
        },

        cancelEditCategory() {
            this.edit_index = null
            this.edit_category = { name: '', weight: '' }
        },

        isWorkAssigned(categoryIndex, shortName) {
            const category = this.data.categories[categoryIndex]
            return category?.works?.some((w) => w.short_name === shortName) || false
        },

        getWorkFactor(categoryIndex, shortName) {
            const category = this.data.categories[categoryIndex]
            const work = category?.works?.find((w) => w.short_name === shortName)
            return work?.factor ?? 100
        },

        getWorkName(shortName) {
            const work = this.teaching_works.find((w) => w.short_name === shortName)
            return work?.name || shortName
        },

        getValidWorks(works) {
            if (!works?.length) return []
            // Filter out works that no longer exist in teaching_works
            return works.filter((w) => this.teaching_works.some((tw) => tw.short_name === w.short_name))
        },

        toggleWork(categoryIndex, shortName) {
            const category = this.data.categories[categoryIndex]
            if (!category.works) category.works = []

            const existingIndex = category.works.findIndex((w) => w.short_name === shortName)
            if (existingIndex >= 0) {
                category.works = category.works.filter((w) => w.short_name !== shortName)
            } else {
                category.works = [...category.works, { short_name: shortName, factor: 100 }]
            }
        },

        setWorkFactor(categoryIndex, shortName, factor) {
            const category = this.data.categories[categoryIndex]
            category.works = category.works.map((w) =>
                w.short_name === shortName ? { ...w, factor: factor } : w
            )
        },

        async save() {
            const cleanedCategories = this.data.categories.map((cat) => ({
                ...cat,
                works: this.getValidWorks(cat.works),
            }))

            const grading = {
                semester_count: this.data.semester_count,
                semester_1_weight: this.data.semester_count === 1 ? 100 : this.data.semester_1_weight,
                semester_2_weight: this.data.semester_count === 1 ? 0 : this.data.semester_2_weight,
                use_semester_grade_only: this.data.semester_count === 2 ? Boolean(this.data.use_semester_grade_only) : false,
                categories: cleanedCategories,
            }

            const schemas = [...(this.settings?.teaching_schemas || [])]
            const schemaIndex = schemas.findIndex((s) => s.id === this.schemaId)
            if (schemaIndex === -1) return

            schemas[schemaIndex] = { ...schemas[schemaIndex], grading }
            await this.teachingStore.saveSettings({ teaching_schemas: schemas })
            this.exitEditMode()
        },
    },
}
</script>

<style scoped>
.cursor-pointer {
    cursor: pointer;
}
</style>
