<template>
    <ItsGridBox variant="overview" color="primary" title="Benotung" icon="mdi-numeric" class="w-100" :disabled="action != ''">
        <template #header-actions>
            <div class="d-flex flex-row align-center ga-2">
                <v-btn v-if="is_editing && !isLocalSemesterCountEditing && !isLocalSemesterWeightEditing && !isLocalSemesterInclusionEditing" icon="mdi-check" size="x-small" color="success" variant="flat" :disabled="!isCurrentEditValid" @click="save" />
                <v-btn v-if="is_editing && !isLocalSemesterCountEditing && !isLocalSemesterWeightEditing && !isLocalSemesterInclusionEditing" icon="mdi-close" size="x-small" color="warning" variant="flat" @click="exitEditMode" />
            </div>
        </template>

        <v-card tile flat color="transparent" class="mt-4">
            <v-card-text>
                <!-- SEMESTER ANZAHL -->
                <div class="grading-config-box mb-4">
                    <div class="grading-section-heading mb-2">
                        <div class="text-caption text-medium-emphasis">Anzahl der Semester</div>
                        <div class="d-flex align-center ga-2">
                            <v-btn
                                v-if="isLocalSemesterCountEditing"
                                icon="mdi-check"
                                size="x-small"
                                color="success"
                                variant="flat"
                                :disabled="!isCurrentEditValid"
                                @click="save" />
                            <v-btn
                                v-if="isLocalSemesterCountEditing"
                                icon="mdi-close"
                                size="x-small"
                                color="warning"
                                variant="flat"
                                @click="exitEditMode" />
                            <v-btn
                                v-if="!is_editing"
                                icon="mdi-pencil"
                                size="x-small"
                                color="primary"
                                variant="text"
                                @click="startSemesterCountEdit" />
                        </div>
                    </div>
                    <v-btn-toggle v-if="isSemesterCountEditing" v-model="data.semester_count" mandatory color="primary">
                        <v-btn :value="1" size="small">1 Semester</v-btn>
                        <v-btn :value="2" size="small">2 Semester</v-btn>
                    </v-btn-toggle>
                    <div v-else class="text-body-1">{{ data.semester_count }} Semester</div>
                </div>

                <!-- GEWICHTUNG BEI 2 SEMESTERN -->
                <div v-if="data.semester_count === 2" class="grading-config-box mb-4">
                    <div class="grading-section-heading mb-2">
                        <div class="text-caption text-medium-emphasis">Gewichtung der Semester</div>
                        <div class="d-flex align-center ga-2">
                            <v-btn
                                v-if="isLocalSemesterWeightEditing"
                                icon="mdi-check"
                                size="x-small"
                                color="success"
                                variant="flat"
                                :disabled="!isCurrentEditValid"
                                @click="save" />
                            <v-btn
                                v-if="isLocalSemesterWeightEditing"
                                icon="mdi-close"
                                size="x-small"
                                color="warning"
                                variant="flat"
                                @click="exitEditMode" />
                            <v-btn
                                v-if="!is_editing"
                                icon="mdi-pencil"
                                size="x-small"
                                color="primary"
                                variant="text"
                                @click="startSemesterWeightEdit" />
                        </div>
                    </div>

                    <!-- EDIT MODE: SLIDERS -->
                    <div v-if="isSemesterWeightEditing" class="d-flex flex-row align-center ga-4">
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
                    <!-- VIEW MODE: GRAPHICAL DISPLAY -->
                    <div v-else class="semester-weight-visual">
                        <div class="semester-weight-legend">
                            <div class="semester-weight-legend-item">
                                <v-chip size="small" color="primary" variant="flat">1. Semester</v-chip>
                                <span class="text-body-2 font-weight-medium">{{ data.semester_1_weight }}%</span>
                            </div>
                            <div class="semester-weight-legend-item semester-weight-legend-item--align-end">
                                <span class="text-body-2 font-weight-medium">{{ data.semester_2_weight }}%</span>
                                <v-chip size="small" color="error" variant="flat">2. Semester</v-chip>
                            </div>
                        </div>
                        <div class="semester-weight-bar" aria-label="Gewichtung der Semester">
                            <div
                                class="semester-weight-bar__segment semester-weight-bar__segment--first"
                                :style="{ width: `${data.semester_1_weight}%` }" />
                            <div
                                class="semester-weight-bar__segment semester-weight-bar__segment--second"
                                :style="{ width: `${data.semester_2_weight}%` }" />
                        </div>
                    </div>

                    <!-- WARNUNG WENN NICHT 100% -->
                    <v-alert v-if="isSemesterWeightEditing && totalSemesterWeight !== 100" type="warning" density="compact" class="mt-4">
                        Die Summe der Semester-Gewichtungen muss 100% ergeben (aktuell: {{ totalSemesterWeight }}%)
                    </v-alert>
                </div>

                <!-- BERECHNUNG DES 1. SEMESTERS FÜR JAHRESNOTE -->
                <div v-if="data.semester_count === 2" class="grading-config-box mb-4">
                    <div class="grading-section-heading mb-2">
                        <div class="text-caption text-medium-emphasis">Einbeziehung des 1. Semesters für die Jahresnote</div>
                        <div class="d-flex align-center ga-2">
                            <v-btn
                                v-if="isLocalSemesterInclusionEditing"
                                icon="mdi-check"
                                size="x-small"
                                color="success"
                                variant="flat"
                                :disabled="!isCurrentEditValid"
                                @click="save" />
                            <v-btn
                                v-if="isLocalSemesterInclusionEditing"
                                icon="mdi-close"
                                size="x-small"
                                color="warning"
                                variant="flat"
                                @click="exitEditMode" />
                            <v-btn
                                v-if="!is_editing"
                                icon="mdi-pencil"
                                size="x-small"
                                color="primary"
                                variant="text"
                                @click="startSemesterInclusionEdit" />
                        </div>
                    </div>
                    <v-radio-group v-if="isSemesterInclusionEditing" v-model="data.use_semester_grade_only" hide-details class="mt-2">
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

                <v-divider class="my-6" />

                <div class="grading-categories-section mb-4">
                    <!-- KATEGORIEN PRO SEMESTER -->
                    <div class="grading-categories-heading mb-4">
                        <div class="text-caption text-medium-emphasis">Kategorien pro Semester</div>
                        <v-btn
                            size="small"
                            color="primary"
                            variant="text"
                            prepend-icon="mdi-plus"
                            @click="startAddCategory">
                            Kategorie
                        </v-btn>
                    </div>

                    <!-- KATEGORIEN LISTE -->
                    <div v-if="data.categories?.length" class="mb-4">
                        <div
                            v-for="(category, index) in data.categories"
                            :key="index"
                            class="grading-category-item mb-3 pa-3 rounded"
                            style="background-color: rgba(var(--v-theme-primary), 0.05);">
                            <!-- KATEGORIE HEADER -->
                            <div class="grading-category-header">
                                <div class="grading-category-weight">
                                    <span :class="{ 'grading-category-weight--invalid': totalCategoryWeight !== 100 }">
                                        {{ category.weight }}%
                                    </span>
                                </div>
                                <div class="grading-category-main">
                                    <v-icon size="small" color="primary">mdi-folder-outline</v-icon>
                                    <span class="text-body-1 grading-category-title">{{ category.name }}</span>
                                </div>
                                <div class="grading-category-meta">
                                    <v-chip v-if="category.require_all_entries" size="x-small" color="primary" variant="tonal">
                                        Jede Arbeit in dieser Kategorie ist verpflichtend
                                    </v-chip>
                                    <v-chip v-if="category.category_evaluation_enabled" size="x-small" color="info" variant="flat">
                                        Anzeige der Bewertung der Kategorie
                                    </v-chip>
                                </div>
                                <div class="grading-category-actions">
                                    <v-btn
                                        v-if="delete_index !== index"
                                        icon="mdi-pencil"
                                        size="x-small"
                                        color="primary"
                                        variant="text"
                                        @click="startEditCategory(index)" />
                                    <template v-if="isCategoryEditing">
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
                                </div>
                            </div>

                            <div class="grading-category-details mt-3 pt-3">
                                <!-- ZUGEWIESENE ARBEITEN MIT FAKTOR -->
                                <div v-if="getValidWorks(category.works)?.length">
                                    <div class="text-caption text-medium-emphasis mb-2">Wert der Arbeiten</div>
                                    <div class="d-flex flex-column ga-2">
                                        <div
                                            v-for="workItem in getValidWorks(category.works)"
                                            :key="workItem.short_name"
                                            class="d-flex align-center ga-2 pa-2 rounded"
                                            style="background-color: rgba(var(--v-theme-success), 0.1);">
                                            <span class="text-body-2 flex-grow-1">
                                                {{ workItem.short_name }} - {{ getWorkName(workItem.short_name) }}
                                            </span>
                                            <v-chip size="small" color="success" variant="outlined">{{ workItem.factor }}%</v-chip>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-body-2 text-medium-emphasis">
                                    Keine Arbeiten definiert. Bitte zuerst unter "Arbeiten und Bewertungen" anlegen.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- WARNUNG WENN KATEGORIEN NICHT 100% -->
                    <v-alert v-if="isCategoryEditing && data.categories?.length && totalCategoryWeight !== 100" type="warning" density="compact" class="mt-4">
                        Die Summe der Kategorie-Gewichtungen muss 100% ergeben (aktuell: {{ totalCategoryWeight }}%)
                    </v-alert>
                </div>

                <v-dialog v-model="category_dialog_open" persistent max-width="760">
                    <v-card>
                        <v-card-title class="d-flex align-center justify-space-between">
                            <span>{{ category_dialog_mode === 'create' ? 'Kategorie erstellen' : 'Kategorie bearbeiten' }}</span>
                            <v-btn icon="mdi-close" variant="text" @click="cancelCategoryDialog" />
                        </v-card-title>
                        <v-card-text>
                            <div class="d-flex flex-column ga-4">
                                <v-text-field
                                    v-model="category_form.name"
                                    label="Kategorie (z.B. Schularbeit)"
                                    density="comfortable"
                                    hide-details />
                                <div>
                                    <div class="text-caption text-medium-emphasis mb-2">Gewicht %</div>
                                    <v-slider
                                        v-model="category_form.weight"
                                        :min="0"
                                        :max="100"
                                        :step="1"
                                        color="primary"
                                        thumb-label />
                                    <div class="text-center text-h6">{{ category_form.weight }}%</div>
                                </div>
                                <v-checkbox
                                    v-model="category_form.require_all_entries"
                                    density="comfortable"
                                    hide-details
                                    color="warning"
                                    label="Jede Arbeit in dieser Kategorie ist verpflichtend" />
                                <v-checkbox
                                    v-model="category_form.category_evaluation_enabled"
                                    density="comfortable"
                                    hide-details
                                    color="secondary"
                                    label="Anzeige der Bewertung der Kategorie" />
                                <div>
                                    <div class="text-caption text-medium-emphasis mb-2">Arbeiten in dieser Kategorie</div>
                                    <div v-if="teaching_works?.length" class="d-flex flex-column ga-2">
                                        <div
                                            v-for="work in teaching_works"
                                            :key="work.short_name"
                                            class="grading-dialog-work-item pa-3 rounded">
                                            <div class="d-flex flex-wrap align-center ga-3">
                                                <v-checkbox
                                                    :model-value="isDialogWorkSelected(work.short_name)"
                                                    hide-details
                                                    density="compact"
                                                    color="primary"
                                                    :label="`${work.short_name} - ${work.name}`"
                                                    @update:model-value="(value) => updateDialogWorkSelection(work.short_name, value)" />
                                                <v-btn-toggle
                                                    :model-value="getDialogWorkFactor(work.short_name)"
                                                    mandatory
                                                density="compact"
                                                color="success"
                                                :disabled="!isDialogWorkSelected(work.short_name)"
                                                @update:model-value="(value) => setDialogWorkFactor(work.short_name, value)">
                                                    <v-btn :value="0" size="x-small">0%</v-btn>
                                                    <v-btn :value="25" size="x-small">25%</v-btn>
                                                    <v-btn :value="33" size="x-small">33%</v-btn>
                                                    <v-btn :value="50" size="x-small">50%</v-btn>
                                                    <v-btn :value="66" size="x-small">66%</v-btn>
                                                    <v-btn :value="75" size="x-small">75%</v-btn>
                                                    <v-btn :value="100" size="x-small">100%</v-btn>
                                                </v-btn-toggle>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else class="text-body-2 text-medium-emphasis">
                                        Keine Arbeiten definiert. Bitte zuerst unter "Arbeiten und Bewertungen" anlegen.
                                    </div>
                                </div>
                            </div>
                        </v-card-text>
                        <v-card-actions class="justify-end">
                            <v-btn color="warning" variant="text" @click="cancelCategoryDialog">Abbrechen</v-btn>
                            <v-btn color="success" variant="flat" :disabled="!isCategoryDialogValid" @click="saveCategoryDialog">
                                Speichern
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>

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
            edit_index: null,
            delete_index: null,
            editing_section: null,
            category_dialog_open: false,
            category_dialog_mode: 'create',
            category_form: { name: '', weight: 0, require_all_entries: false, category_evaluation_enabled: false, works: [] },
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
        isGlobalEditing() {
            return this.is_editing && this.editing_section === 'global'
        },
        isSemesterCountEditing() {
            return this.is_editing && ['global', 'semester_count'].includes(this.editing_section)
        },
        isLocalSemesterCountEditing() {
            return this.is_editing && this.editing_section === 'semester_count'
        },
        isSemesterWeightEditing() {
            return this.is_editing && ['global', 'semester_weight'].includes(this.editing_section)
        },
        isLocalSemesterWeightEditing() {
            return this.is_editing && this.editing_section === 'semester_weight'
        },
        isSemesterInclusionEditing() {
            return this.is_editing && ['global', 'semester_inclusion'].includes(this.editing_section)
        },
        isLocalSemesterInclusionEditing() {
            return this.is_editing && this.editing_section === 'semester_inclusion'
        },
        isCategoryEditing() {
            return this.is_editing && ['global', 'category'].includes(this.editing_section)
        },
        isValid() {
            if (this.data.semester_count === 2 && this.totalSemesterWeight !== 100) return false
            if (this.data.categories?.length && this.totalCategoryWeight !== 100) return false
            return true
        },
        isCurrentEditValid() {
            if (this.editing_section === 'semester_weight') return this.totalSemesterWeight === 100
            if (this.editing_section === 'category') {
                return !this.data.categories?.length || this.totalCategoryWeight === 100
            }
            return this.isValid
        },
        isCategoryDialogValid() {
            return Boolean(this.category_form.name.trim() && this.category_form.weight > 0)
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
            this.editing_section = null
            this.category_dialog_open = false
            this.category_dialog_mode = 'create'
            this.category_form = this.emptyCategoryForm()
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
                    category_evaluation_enabled: Boolean(c.category_evaluation_enabled),
                    require_all_entries: Object.prototype.hasOwnProperty.call(c || {}, 'require_all_entries')
                        ? Boolean(c.require_all_entries)
                        : this.inferCategoryRequireAllEntries(c?.works),
                    calculation: c.calculation || 'mean',
                })),
            }
        },

        inferCategoryRequireAllEntries(works) {
            const normalized = this.normalizeWorks(works)
            if (!normalized.length) return false
            return normalized.some((workItem) => {
                const work = this.teaching_works.find((entry) => entry.short_name === workItem.short_name)
                return Boolean(work?.require_all_entries)
            })
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

        removeCategory(index) {
            this.data.categories = this.data.categories.filter((_, i) => i !== index)
            this.delete_index = null
        },

        emptyCategoryForm() {
            return { name: '', weight: 0, require_all_entries: false, category_evaluation_enabled: false, works: [] }
        },

        startSemesterCountEdit() {
            this.is_editing = true
            this.editing_section = 'semester_count'
            this.delete_index = null
        },

        startSemesterWeightEdit() {
            this.is_editing = true
            this.editing_section = 'semester_weight'
            this.delete_index = null
        },

        startSemesterInclusionEdit() {
            this.is_editing = true
            this.editing_section = 'semester_inclusion'
            this.delete_index = null
        },

        startAddCategory() {
            this.is_editing = true
            this.editing_section = 'category'
            this.edit_index = null
            this.delete_index = null
            this.category_dialog_mode = 'create'
            this.category_form = this.emptyCategoryForm()
            this.category_dialog_open = true
        },

        startEditCategory(index) {
            const category = this.data.categories[index]
            this.is_editing = true
            this.editing_section = 'category'
            this.delete_index = null
            this.category_dialog_mode = 'edit'
            this.category_form = {
                name: category.name,
                weight: category.weight,
                require_all_entries: Boolean(category.require_all_entries),
                category_evaluation_enabled: Boolean(category.category_evaluation_enabled),
                works: this.getValidWorks(this.normalizeWorks(category.works)).map((work) => ({ ...work })),
            }
            this.edit_index = index
            this.category_dialog_open = true
        },

        cancelCategoryDialog() {
            this.category_dialog_open = false
            this.category_dialog_mode = 'create'
            this.category_form = this.emptyCategoryForm()
            this.edit_index = null
        },

        saveCategoryDialog() {
            if (!this.isCategoryDialogValid) return

            const categoryPayload = {
                name: this.category_form.name.trim(),
                weight: parseInt(this.category_form.weight) || 0,
                require_all_entries: Boolean(this.category_form.require_all_entries),
                category_evaluation_enabled: Boolean(this.category_form.category_evaluation_enabled),
                works: this.getValidWorks(this.category_form.works).map((work) => ({
                    short_name: work.short_name,
                    factor: work.factor ?? 100,
                })),
            }

            if (this.category_dialog_mode === 'create') {
                this.data.categories.push({
                    ...categoryPayload,
                    works: [],
                    calculation: 'mean',
                })
            } else if (this.edit_index !== null) {
                this.data.categories = this.data.categories.map((cat, index) =>
                    index === this.edit_index
                        ? {
                            ...cat,
                            ...categoryPayload,
                        }
                        : cat
                )
            }

            this.cancelCategoryDialog()
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

        isDialogWorkSelected(shortName) {
            return this.category_form.works?.some((work) => work.short_name === shortName) || false
        },

        getDialogWorkFactor(shortName) {
            const work = this.category_form.works?.find((entry) => entry.short_name === shortName)
            return work?.factor ?? 100
        },

        updateDialogWorkSelection(shortName, selected) {
            if (!selected) {
                this.category_form.works = (this.category_form.works || []).filter((work) => work.short_name !== shortName)
                return
            }

            if (this.isDialogWorkSelected(shortName)) {
                return
            }

            this.category_form.works = [
                ...(this.category_form.works || []),
                { short_name: shortName, factor: 100 },
            ]
        },

        setDialogWorkFactor(shortName, factor) {
            this.category_form.works = (this.category_form.works || []).map((work) =>
                work.short_name === shortName ? { ...work, factor: factor } : work
            )
        },

        async save() {
            const cleanedCategories = this.data.categories.map((cat) => ({
                ...cat,
                works: this.getValidWorks(cat.works),
                require_all_entries: Boolean(cat.require_all_entries),
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

.grading-config-box {
    border: 1px solid rgba(var(--v-theme-primary), 0.2);
    border-radius: 12px;
    background: rgba(var(--v-theme-primary), 0.04);
    padding: 14px 16px;
}

.grading-categories-section {
    border: 1px solid rgba(var(--v-theme-primary), 0.2);
    border-radius: 16px;
    background: rgba(var(--v-theme-primary), 0.05);
    padding: 18px 16px;
}

.grading-categories-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.grading-section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.grading-category-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    width: 100%;
}

.grading-category-item {
    border: 1px solid rgba(var(--v-theme-primary), 0.12);
}

.grading-category-weight {
    font-size: 2rem;
    font-weight: 800;
    line-height: 1;
    color: rgb(var(--v-theme-primary));
    flex: 0 0 auto;
    min-width: 84px;
}

.grading-category-weight--invalid {
    color: rgb(var(--v-theme-error));
}

.grading-category-main {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    flex: 1 1 200px;
}

.grading-category-title {
    min-width: 0;
    overflow-wrap: anywhere;
    font-weight: 600;
}

.grading-category-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    flex: 1 1 auto;
}

.grading-category-actions {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-left: auto;
}

.grading-category-details {
    border-top: 1px solid rgba(var(--v-theme-primary), 0.1);
}

.grading-dialog-work-item {
    border: 1px solid rgba(var(--v-theme-primary), 0.12);
    background: rgba(var(--v-theme-primary), 0.04);
}

.semester-weight-visual {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.semester-weight-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.semester-weight-legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.semester-weight-legend-item--align-end {
    margin-left: auto;
}

.semester-weight-bar {
    display: flex;
    width: 100%;
    min-height: 16px;
    overflow: hidden;
    border-radius: 999px;
    background: rgba(var(--v-theme-on-surface), 0.08);
}

.semester-weight-bar__segment {
    min-width: 0;
    transition: width 0.2s ease;
}

.semester-weight-bar__segment--first {
    background: rgb(var(--v-theme-primary));
}

.semester-weight-bar__segment--second {
    background: rgb(var(--v-theme-error));
}
</style>
