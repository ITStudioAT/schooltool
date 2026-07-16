<template>
    <div class="curricula-overview">
        <v-sheet rounded="xl" class="curricula-overview__toolbar pa-3 mb-3">
            <div class="curricula-overview__toolbar-inner">
                <v-btn
                    color="success"
                    variant="flat"
                    rounded="xl"
                    prepend-icon="mdi-plus"
                    @click="openCreateDialog">
                    Neues Curriculum
                </v-btn>
            </div>
        </v-sheet>

        <v-row class="ma-0">
            <v-col cols="12" md="6" class="pa-0 pr-md-2 pb-3">
                <v-sheet rounded="xl" class="curricula-overview__list pa-4 h-100">
                    <div class="d-flex align-center justify-space-between ga-3 mb-3">
                        <div>
                            <div class="text-subtitle-1 font-weight-bold">Eigene Curricula</div>
                            <div class="text-caption curricula-overview__muted">Deine persönlichen Curricula für den Unterricht.</div>
                        </div>
                    </div>

                    <div v-if="curriculumStore.is_loading && !curricula.length" class="curricula-overview__empty text-center py-6">
                        <v-progress-circular indeterminate color="primary" size="28" class="mb-2" />
                        <div>Curricula werden geladen...</div>
                    </div>
                    <div v-else-if="!curricula.length" class="curricula-overview__empty text-center py-6">
                        <v-icon size="40" color="primary" class="mb-2">mdi-book-education-outline</v-icon>
                        <div class="text-body-2 font-weight-medium">Noch keine Curricula vorhanden.</div>
                        <div class="text-caption">Lege ein erstes Curriculum an.</div>
                    </div>
                    <v-list v-else bg-color="transparent" density="compact" class="py-0">
                        <v-list-item
                            v-for="curriculum in curricula"
                            :key="curriculum.id"
                            class="curricula-overview__item mb-2 px-3"
                            min-height="44"
                            rounded="lg"
                            style="cursor: pointer"
                            @click="$emit('select', curriculum)">
                            <template #prepend>
                                <v-icon color="#a5b4fc" size="18" class="mr-2">mdi-book-education-outline</v-icon>
                            </template>
                            <v-list-item-title class="text-body-2 font-weight-bold">{{ curriculum.title }}</v-list-item-title>
                            <v-list-item-subtitle class="text-caption">
                                <span v-if="curriculum.description">{{ curriculum.description }}</span>
                            </v-list-item-subtitle>
                            <template #append>
                                <div class="curricula-overview__item-actions" @click.stop>
                                    <v-btn
                                        icon="mdi-pencil-outline"
                                        variant="tonal"
                                        color="primary"
                                        size="x-small"
                                        class="mr-1"
                                        title="Bearbeiten"
                                        @click.stop="openEditDialog(curriculum)" />
                                    <v-btn
                                        icon="mdi-delete-outline"
                                        variant="tonal"
                                        color="warning"
                                        size="x-small"
                                        title="Löschen"
                                        @click.stop="askDelete(curriculum)" />
                                </div>
                            </template>
                        </v-list-item>
                    </v-list>

                    <div v-if="meta.last_page > 1" class="curricula-overview__pagination d-flex justify-center mt-4">
                        <v-pagination
                            v-model="currentPage"
                            :length="meta.last_page"
                            :total-visible="7"
                            rounded="circle"
                            @update:modelValue="changePage" />
                    </div>

                    <div v-if="meta.total > 0" class="curricula-overview__total text-caption text-center mt-2">
                        {{ meta.total }} Curriculum{{ meta.total === 1 ? '' : 'a' }} insgesamt
                    </div>
                </v-sheet>
            </v-col>

            <v-col cols="12" md="6" class="pa-0 pl-md-2 pb-3">
                <v-sheet rounded="xl" class="curricula-overview__list pa-4 h-100">
                    <div class="d-flex align-center justify-space-between ga-3 mb-3">
                        <div>
                            <div class="text-subtitle-1 font-weight-bold">Importierte Curricula</div>
                            <div class="text-caption curricula-overview__muted">Getrennt von deinen eigenen Curricula, aber als Vorlage übernehmbar.</div>
                        </div>
                        <v-btn
                            color="primary"
                            variant="flat"
                            rounded="xl"
                            prepend-icon="mdi-import"
                            @click="openImportDialog">
                            Curriculum importieren
                        </v-btn>
                    </div>

                    <div v-if="!imported_curricula.length" class="curricula-overview__empty text-center py-6">
                        <v-icon size="40" color="primary" class="mb-2">mdi-tray-arrow-down</v-icon>
                        <div class="text-body-2 font-weight-medium">Noch keine importierten Curricula vorhanden.</div>
                        <div class="text-caption">Importiere ein Curriculum als getrennte Vorlage.</div>
                    </div>
                    <v-list v-else bg-color="transparent" density="compact" class="py-0">
                        <template v-for="curriculum in imported_curricula" :key="curriculum.id">
                            <v-list-item
                                class="curricula-overview__item mb-2 px-3"
                                min-height="52"
                                rounded="lg">
                                <template #prepend>
                                    <v-icon color="#2563eb" size="18" class="mr-2">mdi-tray-arrow-down</v-icon>
                                </template>
                                <v-list-item-title class="text-body-2 font-weight-bold">{{ curriculum.title }}</v-list-item-title>
                                <v-list-item-subtitle class="text-caption">
                                    <span v-if="curriculum.description">{{ curriculum.description }} · </span>
                                    <span v-if="curriculum.imported_at">importiert am {{ formatDateTime(curriculum.imported_at) }}</span>
                                </v-list-item-subtitle>
                                <template #append>
                                    <div class="curricula-overview__import-actions">
                                        <v-btn
                                            variant="text"
                                            color="primary"
                                            size="small"
                                            rounded="lg"
                                            :prepend-icon="expandedImportedId === curriculum.id ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
                                            @click.stop="toggleImportedPreview(curriculum.id)">
                                            {{ expandedImportedId === curriculum.id ? 'Ausblenden' : 'Vorschau' }}
                                        </v-btn>
                                        <v-btn
                                            icon="mdi-delete-outline"
                                            variant="tonal"
                                            color="warning"
                                            size="small"
                                            rounded="lg"
                                            title="Importiertes Curriculum löschen"
                                            :disabled="importedDeleteLoadingId === curriculum.id"
                                            @click.stop="askDeleteImportedCurriculum(curriculum)" />
                                        <v-btn
                                            variant="tonal"
                                            color="primary"
                                            size="small"
                                            rounded="lg"
                                            prepend-icon="mdi-account-arrow-right-outline"
                                            :loading="takeoverLoadingId === curriculum.id"
                                            :disabled="Boolean(curriculum.adopted_curriculum_id) || importedDeleteLoadingId === curriculum.id"
                                            @click.stop="takeOverImportedCurriculum(curriculum)">
                                            Übernehmen
                                        </v-btn>
                                    </div>
                                </template>
                            </v-list-item>
                            <v-expand-transition>
                                <div
                                    v-if="expandedImportedId === curriculum.id"
                                    class="curricula-overview__import-preview-wrap mb-2">
                                    <div class="curricula-overview__import-preview">
                                        <div class="text-caption font-weight-medium mb-2">
                                            {{ importedTopics(curriculum).length }} Themen · {{ importedUnitCount(curriculum) }} Einheiten
                                        </div>
                                        <div v-if="!importedTopics(curriculum).length" class="text-caption">
                                            Keine Themen im importierten Curriculum.
                                        </div>
                                        <div v-else class="curricula-overview__import-preview-topics">
                                            <div
                                                v-for="(topic, topicIndex) in importedTopics(curriculum)"
                                                :key="topic.id || `topic-${curriculum.id}-${topicIndex}`"
                                                class="curricula-overview__import-preview-topic pa-2 mb-2">
                                                <div>
                                                <div class="text-body-2 font-weight-medium">
                                                    {{ topicIndex + 1 }}. {{ topic.title || 'Ohne Titel' }}
                                                </div>
                                                </div>
                                                <div
                                                    v-if="importedTopicUnits(topic).length"
                                                    class="curricula-overview__import-preview-units mt-2">
                                                    <div
                                                        v-for="(unit, unitIndex) in importedTopicUnits(topic)"
                                                        :key="unit.id || `topic-${topicIndex}-unit-${unitIndex}`"
                                                        class="curricula-overview__import-preview-unit text-caption">
                                                        <span class="font-weight-medium">{{ topicIndex + 1 }}.{{ unitIndex + 1 }} {{ unit.title || 'Ohne Titel' }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </v-expand-transition>
                        </template>
                    </v-list>
                </v-sheet>
            </v-col>
        </v-row>

        <v-dialog v-model="dialogOpen" max-width="560" persistent>
            <v-card rounded="xl">
                <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                    <v-icon color="primary" size="22">mdi-book-education-outline</v-icon>
                    {{ editing ? 'Curriculum bearbeiten' : 'Neues Curriculum' }}
                </v-card-title>
                <v-card-text class="px-4">
                    <v-text-field
                        v-model="form.title"
                        label="Titel"
                        variant="outlined"
                        density="comfortable"
                        :error-messages="formErrors.title"
                        autofocus
                        class="mb-2" />
                    <v-textarea
                        v-model="form.description"
                        label="Beschreibung"
                        variant="outlined"
                        density="comfortable"
                        rows="3"
                        auto-grow
                        :error-messages="formErrors.description"
                        class="mb-2" />
                </v-card-text>
                <v-card-actions class="px-4 pb-4">
                    <v-btn variant="tonal" :disabled="saving" @click="closeDialog">Abbrechen</v-btn>
                    <v-spacer />
                    <v-btn color="primary" variant="flat" :loading="saving" @click="saveForm">
                        {{ editing ? 'Speichern' : 'Anlegen' }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="importDialogOpen" max-width="560" persistent>
            <v-card rounded="xl">
                <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                    <v-icon color="primary" size="22">mdi-import</v-icon>
                    Curriculum importieren
                </v-card-title>
                <v-card-text class="px-4">
                    <div class="text-body-2 mb-3">
                        Importiere eine exportierte Curriculum-JSON-Datei. Das Curriculum bleibt getrennt von deinen persönlichen Curricula.
                    </div>
                    <file-pond
                        ref="importPond"
                        name="file"
                        :allow-multiple="false"
                        :instant-upload="true"
                        :allow-replace="true"
                        :allow-revert="false"
                        :allow-remove="true"
                        :allow-file-type-validation="true"
                        :accepted-file-types="['application/json', 'text/json', '.json']"
                        :max-files="1"
                        :label-idle="'<strong>JSON-Datei hierher ziehen oder <i>klicken</i></strong>'"
                        :label-file-processing-complete="'Importiert'"
                        :server="{ process: importPondProcess }" />
                </v-card-text>
                <v-card-actions class="px-4 pb-4">
                    <v-btn variant="tonal" :disabled="importLoading" @click="closeImportDialog">Abbrechen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="deleteDialogOpen" max-width="420" persistent>
            <v-card rounded="xl">
                <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                    <v-icon color="error" size="20">mdi-delete-outline</v-icon>
                    Curriculum löschen
                </v-card-title>
                <v-card-text class="px-4">
                    Soll das Curriculum <strong>„{{ deleteTarget?.title }}"</strong> wirklich gelöscht werden?
                </v-card-text>
                <v-card-actions class="px-4 pb-4">
                    <v-btn variant="tonal" :disabled="deleteLoading" @click="deleteDialogOpen = false">Abbrechen</v-btn>
                    <v-spacer />
                    <v-btn color="error" variant="flat" :loading="deleteLoading" @click="confirmDelete">Löschen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="importedDeleteDialogOpen" max-width="420" persistent>
            <v-card rounded="xl">
                <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                    <v-icon color="error" size="20">mdi-delete-outline</v-icon>
                    Importiertes Curriculum löschen
                </v-card-title>
                <v-card-text class="px-4">
                    Soll das importierte Curriculum <strong>„{{ importedDeleteTarget?.title }}”</strong> wirklich gelöscht werden?
                </v-card-text>
                <v-card-actions class="px-4 pb-4">
                    <v-btn
                        variant="tonal"
                        :disabled="importedDeleteLoadingId !== null"
                        @click="importedDeleteDialogOpen = false">
                        Abbrechen
                    </v-btn>
                    <v-spacer />
                    <v-btn
                        color="error"
                        variant="flat"
                        :loading="importedDeleteLoadingId === importedDeleteTarget?.id"
                        @click="confirmDeleteImportedCurriculum">
                        Löschen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import vueFilePond from 'vue-filepond/dist/vue-filepond.js'
import 'filepond/dist/filepond.min.css'
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type'
import { mapState } from 'pinia'
import { useCurriculumStore } from '@/stores/admin/teaching/CurriculumStore'

const FilePond = vueFilePond(FilePondPluginFileValidateType)

export default {
    name: 'TeachingCurriculaOverview',
    emits: ['select'],
    components: { FilePond },

    data() {
        return {
            curriculumStore: null,
            currentPage: 1,
            dialogOpen: false,
            importDialogOpen: false,
            importLoading: false,
            editing: null,
            form: { title: '', description: '' },
            formErrors: {},
            saving: false,
            deleteDialogOpen: false,
            deleteTarget: null,
            deleteLoading: false,
            importedDeleteDialogOpen: false,
            importedDeleteTarget: null,
            importedDeleteLoadingId: null,
            takeoverLoadingId: null,
            expandedImportedId: null,
        }
    },

    computed: {
        ...mapState(useCurriculumStore, ['curricula', 'imported_curricula', 'meta']),
    },

    async beforeMount() {
        this.curriculumStore = useCurriculumStore()
        this.currentPage = this.curriculumStore.meta.current_page || 1
        await Promise.all([
            this.curriculumStore.index({ page: this.currentPage }),
            this.curriculumStore.loadImportedCurricula(),
        ])
        this.currentPage = this.curriculumStore.meta.current_page || 1
    },

    methods: {
        async changePage(page) {
            this.currentPage = page
            await this.curriculumStore.index({ page })
        },
        openCreateDialog() {
            this.editing = null
            this.form = { title: '', description: '' }
            this.formErrors = {}
            this.dialogOpen = true
        },
        openEditDialog(curriculum) {
            this.editing = curriculum
            this.form = {
                title: curriculum.title || '',
                description: curriculum.description || '',
            }
            this.formErrors = {}
            this.dialogOpen = true
        },
        closeDialog() {
            if (this.saving) return
            this.dialogOpen = false
        },
        openImportDialog() {
            this.importDialogOpen = true
        },
        closeImportDialog(force = false) {
            if (this.importLoading && !force) return
            this.importDialogOpen = false
            if (this.$refs.importPond?.removeFiles) {
                this.$refs.importPond.removeFiles()
            }
        },
        importPondProcess(fieldName, file, metadata, load, error, progress, abort) {
            this.importLoading = true

            this.curriculumStore.importCurriculum(file)
                .then((result) => {
                    progress(true, 1, 1)

                    if (!result) {
                        error('Import fehlgeschlagen')
                        return
                    }

                    load(String(result.id ?? file.name ?? 'imported-curriculum'))
                    this.closeImportDialog(true)
                })
                .catch(() => {
                    error('Import fehlgeschlagen')
                })
                .finally(() => {
                    this.importLoading = false
                })

            return {
                abort: () => {
                    this.importLoading = false
                    abort()
                },
            }
        },
        toggleImportedPreview(curriculumId) {
            this.expandedImportedId = this.expandedImportedId === curriculumId ? null : curriculumId
        },
        importedTopics(curriculum) {
            if (!Array.isArray(curriculum?.topics)) {
                return []
            }

            return curriculum.topics.filter((topic) => topic && typeof topic === 'object')
        },
        importedTopicUnits(topic) {
            if (!Array.isArray(topic?.units)) {
                return []
            }

            return topic.units.filter((unit) => unit && typeof unit === 'object')
        },
        topicHasUnitDateAssignments(topic) {
            return this.importedTopicUnits(topic)
                .some((unit) => {
                    const assignmentType = String(unit?.assignment_type || 'none')

                    return assignmentType === 'month' || assignmentType === 'weeks'
                })
        },
        shouldShowTopicAssignmentChip(topic) {
            const assignmentType = String(topic?.assignment_type || 'none')
            if (assignmentType !== 'none') {
                return true
            }

            return !this.topicHasUnitDateAssignments(topic)
        },
        importedUnitCount(curriculum) {
            return this.importedTopics(curriculum)
                .reduce((count, topic) => count + this.importedTopicUnits(topic).length, 0)
        },
        compactImportKeys(values, maxVisible = 3) {
            const normalized = values
                .filter(Boolean)
                .map((value) => String(value).trim())
                .filter((value) => value !== '')

            if (!normalized.length) {
                return ''
            }

            if (normalized.length <= maxVisible) {
                return normalized.join(', ')
            }

            return `${normalized.slice(0, maxVisible).join(', ')} +${normalized.length - maxVisible}`
        },
        isoWeekFromDateKey(dateKey) {
            const normalized = String(dateKey || '').trim()
            const match = normalized.match(/^(\d{4})-(\d{2})-(\d{2})$/)
            if (!match) {
                return null
            }

            const year = Number(match[1])
            const monthIndex = Number(match[2]) - 1
            const day = Number(match[3])
            const date = new Date(Date.UTC(year, monthIndex, day))

            if (Number.isNaN(date.getTime())) {
                return null
            }

            const dayOfWeek = (date.getUTCDay() + 6) % 7
            date.setUTCDate(date.getUTCDate() - dayOfWeek + 3)

            const firstThursday = new Date(Date.UTC(date.getUTCFullYear(), 0, 4))
            const firstThursdayDay = (firstThursday.getUTCDay() + 6) % 7
            firstThursday.setUTCDate(firstThursday.getUTCDate() - firstThursdayDay + 3)

            return 1 + Math.round((date.getTime() - firstThursday.getTime()) / 604800000)
        },
        monthLabelFromDateKey(dateKey) {
            const normalized = String(dateKey || '').trim()
            const match = normalized.match(/^(\d{4})-(\d{2})-\d{2}$/)
            if (!match) {
                return null
            }

            const month = Number(match[2])
            const monthLabels = {
                1: 'Jan',
                2: 'Feb',
                3: 'März',
                4: 'Apr',
                5: 'Mai',
                6: 'Juni',
                7: 'Juli',
                8: 'Aug',
                9: 'Sept',
                10: 'Okt',
                11: 'Nov',
                12: 'Dez',
            }

            return monthLabels[month] || null
        },
        formatIsoWeekRanges(weeks) {
            if (!weeks.length) {
                return ''
            }

            const ranges = []
            let start = weeks[0]
            let previous = weeks[0]

            for (let index = 1; index < weeks.length; index++) {
                const current = weeks[index]
                if (current === previous + 1) {
                    previous = current
                    continue
                }

                ranges.push(start === previous ? `KW ${start}` : `KW ${start}-${previous}`)
                start = current
                previous = current
            }

            ranges.push(start === previous ? `KW ${start}` : `KW ${start}-${previous}`)

            return ranges.join(', ')
        },
        formatImportAssignment(item) {
            const assignmentType = String(item?.assignment_type || 'none')

            if (assignmentType === 'all_weeks') {
                return 'Alle Wochen'
            }

            if (assignmentType === 'month') {
                const monthKeys = Array.isArray(item?.month_keys)
                    ? item.month_keys
                    : [item?.month_key]
                const summary = this.compactImportKeys(monthKeys)

                return summary ? `Monate: ${summary}` : 'Monat'
            }

            if (assignmentType === 'weeks') {
                const weekKeys = Array.isArray(item?.week_keys) ? item.week_keys : []
                const monthLabels = [...new Set(
                    weekKeys
                        .map((weekKey) => this.monthLabelFromDateKey(weekKey))
                        .filter((monthLabel) => Boolean(monthLabel))
                )]
                const isoWeeks = [...new Set(
                    weekKeys
                        .map((weekKey) => this.isoWeekFromDateKey(weekKey))
                        .filter((week) => Number.isInteger(week))
                )]
                    .sort((a, b) => a - b)

                const firstMonth = monthLabels[0] || null
                const lastMonth = monthLabels[monthLabels.length - 1] || null
                const monthSummary = firstMonth && lastMonth
                    ? (firstMonth === lastMonth ? firstMonth : `${firstMonth}-${lastMonth}`)
                    : null
                const kwSummary = this.formatIsoWeekRanges(isoWeeks)

                if (!monthSummary && !kwSummary) {
                    return 'Wochen'
                }

                if (!monthSummary) {
                    return kwSummary
                }

                if (!kwSummary) {
                    return monthSummary
                }

                return firstMonth === lastMonth
                    ? `${monthSummary} - ${kwSummary}`
                    : `${monthSummary}: ${kwSummary}`
            }

            return 'Keine feste Zuweisung'
        },
        async takeOverImportedCurriculum(curriculum) {
            this.takeoverLoadingId = curriculum.id
            try {
                const result = await this.curriculumStore.adoptImportedCurriculum(curriculum.id)
                if (result) {
                    this.currentPage = 1
                    await this.curriculumStore.index({ page: 1 })
                    this.$emit('select', result)
                }
            } finally {
                this.takeoverLoadingId = null
            }
        },
        askDeleteImportedCurriculum(curriculum) {
            this.importedDeleteTarget = curriculum
            this.importedDeleteDialogOpen = true
        },
        async confirmDeleteImportedCurriculum() {
            if (!this.importedDeleteTarget) return
            const importedDeleteId = this.importedDeleteTarget.id
            this.importedDeleteLoadingId = importedDeleteId
            try {
                const ok = await this.curriculumStore.destroyImportedCurriculum(importedDeleteId)
                if (ok) {
                    if (this.expandedImportedId === importedDeleteId) {
                        this.expandedImportedId = null
                    }
                    this.importedDeleteDialogOpen = false
                    this.importedDeleteTarget = null
                }
            } finally {
                this.importedDeleteLoadingId = null
            }
        },
        async saveForm() {
            this.formErrors = {}
            if (!this.form.title.trim()) {
                this.formErrors = { title: 'Titel ist erforderlich.' }
                return
            }
            this.saving = true
            try {
                const payload = {
                    title: this.form.title.trim(),
                    description: this.form.description?.trim() || null,
                    topics: this.editing?.topics ?? [],
                }
                const result = this.editing
                    ? await this.curriculumStore.update(this.editing.id, payload)
                    : await this.curriculumStore.store(payload)
                if (result) {
                    this.dialogOpen = false
                    await this.curriculumStore.index({ page: this.editing ? this.currentPage : 1 })
                    if (!this.editing) {
                        this.currentPage = 1
                    }
                }
            } finally {
                this.saving = false
            }
        },
        askDelete(curriculum) {
            this.deleteTarget = curriculum
            this.deleteDialogOpen = true
        },
        async confirmDelete() {
            if (!this.deleteTarget) return
            this.deleteLoading = true
            try {
                const ok = await this.curriculumStore.destroy(this.deleteTarget.id)
                if (ok) {
                    this.deleteDialogOpen = false
                    this.deleteTarget = null
                    const targetPage = this.curricula.length === 1 && this.currentPage > 1
                        ? this.currentPage - 1
                        : this.currentPage
                    this.currentPage = targetPage
                    await this.curriculumStore.index({ page: targetPage })
                }
            } finally {
                this.deleteLoading = false
            }
        },
        formatDateTime(value) {
            if (!value) return ''

            const date = new Date(value)
            if (Number.isNaN(date.getTime())) return ''

            return new Intl.DateTimeFormat('de-AT', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            }).format(date)
        },
    },
}
</script>

<style scoped>
.curricula-overview {
    max-width: 1180px;
}

.curricula-overview__toolbar {
    border: 1px solid rgba(15, 23, 42, 0.12);
    background:
        radial-gradient(circle at top right, rgba(37, 99, 235, 0.18), transparent 52%),
        linear-gradient(150deg, rgba(255, 255, 255, 0.95), rgba(241, 245, 249, 0.93));
    box-shadow:
        0 10px 24px rgba(15, 23, 42, 0.11),
        inset 0 1px 0 rgba(255, 255, 255, 0.7);
    color: #0f172a;
}

.curricula-overview__toolbar-inner {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    flex-wrap: wrap;
}

.curricula-overview__list {
    border: 1px solid rgba(15, 23, 42, 0.12);
    background:
        radial-gradient(circle at top right, rgba(37, 99, 235, 0.18), transparent 52%),
        linear-gradient(150deg, rgba(255, 255, 255, 0.95), rgba(241, 245, 249, 0.93));
    box-shadow:
        0 10px 24px rgba(15, 23, 42, 0.11),
        inset 0 1px 0 rgba(255, 255, 255, 0.7);
    color: #0f172a;
}

.curricula-overview__item {
    background: rgba(255, 255, 255, 0.84);
    border: 1px solid rgba(15, 23, 42, 0.08);
}

.curricula-overview__item-actions {
    display: inline-flex;
    align-items: center;
}

.curricula-overview__import-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 8px;
}

.curricula-overview__import-preview {
    border: 1px solid rgba(37, 99, 235, 0.22);
    background: rgba(219, 234, 254, 0.45);
    border-radius: 10px;
    padding: 10px 12px;
}

.curricula-overview__import-preview-wrap {
    width: 100%;
}

.curricula-overview__import-preview-topics {
    display: flex;
    flex-direction: column;
}

.curricula-overview__import-preview-topic {
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: rgba(255, 255, 255, 0.75);
    border-radius: 8px;
}

.curricula-overview__import-preview-unit {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 10px;
    padding: 2px 0;
}

.curricula-overview__import-preview-unit-meta {
    color: #475569;
}

.curricula-overview__empty {
    color: #1e293b;
}

.curricula-overview__empty .text-caption,
.curricula-overview__muted,
.curricula-overview__total {
    color: #475569;
}

.curricula-overview__semester-badge {
    color: #4338ca;
    font-weight: 600;
}
</style>
