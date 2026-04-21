<template>
    <div class="curricula-overview">
        <v-sheet rounded="xl" class="curricula-overview__toolbar pa-3 mb-3">
            <div class="curricula-overview__toolbar-inner">
                <v-text-field
                    v-model="searchInput"
                    density="compact"
                    hide-details
                    clearable
                    variant="solo-filled"
                    placeholder="Curricula suchen..."
                    prepend-inner-icon="mdi-magnify"
                    class="curricula-overview__search"
                    @update:modelValue="onSearchChanged" />
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
                                <span v-if="curriculum.description">{{ curriculum.description }} · </span>
                                <span class="curricula-overview__semester-badge">{{ curriculum.semester_count ?? 2 }} Semester</span>
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

                    <div v-if="!filteredImportedCurricula.length" class="curricula-overview__empty text-center py-6">
                        <v-icon size="40" color="primary" class="mb-2">mdi-tray-arrow-down</v-icon>
                        <div class="text-body-2 font-weight-medium">Noch keine importierten Curricula vorhanden.</div>
                        <div class="text-caption">Importiere ein Curriculum als getrennte Vorlage.</div>
                    </div>
                    <v-list v-else bg-color="transparent" density="compact" class="py-0">
                        <v-list-item
                            v-for="curriculum in filteredImportedCurricula"
                            :key="curriculum.id"
                            class="curricula-overview__item mb-2 px-3"
                            min-height="52"
                            rounded="lg">
                            <template #prepend>
                                <v-icon color="#2563eb" size="18" class="mr-2">mdi-tray-arrow-down</v-icon>
                            </template>
                            <v-list-item-title class="text-body-2 font-weight-bold">{{ curriculum.title }}</v-list-item-title>
                            <v-list-item-subtitle class="text-caption">
                                <span v-if="curriculum.description">{{ curriculum.description }} · </span>
                                <span class="curricula-overview__semester-badge">{{ curriculum.semester_count ?? 2 }} Semester</span>
                                <span v-if="curriculum.imported_at"> · importiert am {{ formatDateTime(curriculum.imported_at) }}</span>
                            </v-list-item-subtitle>
                            <template #append>
                                <div class="curricula-overview__import-actions">
                                    <v-btn
                                        icon="mdi-delete-outline"
                                        variant="tonal"
                                        color="warning"
                                        size="small"
                                        rounded="lg"
                                        class="mr-2"
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
                    <div class="text-body-2 font-weight-medium mb-1">Anzahl Semester</div>
                    <v-btn-toggle
                        v-model="form.semester_count"
                        mandatory
                        color="primary"
                        density="comfortable"
                        rounded="lg"
                        class="mb-1">
                        <v-btn :value="1" variant="outlined" class="text-none px-6">1 Semester</v-btn>
                        <v-btn :value="2" variant="outlined" class="text-none px-6">2 Semester</v-btn>
                    </v-btn-toggle>
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
            searchInput: '',
            searchTimer: null,
            currentPage: 1,
            dialogOpen: false,
            importDialogOpen: false,
            importLoading: false,
            editing: null,
            form: { title: '', description: '', semester_count: 2 },
            formErrors: {},
            saving: false,
            deleteDialogOpen: false,
            deleteTarget: null,
            deleteLoading: false,
            importedDeleteDialogOpen: false,
            importedDeleteTarget: null,
            importedDeleteLoadingId: null,
            takeoverLoadingId: null,
        }
    },

    computed: {
        ...mapState(useCurriculumStore, ['curricula', 'imported_curricula', 'meta']),
        filteredImportedCurricula() {
            const term = this.searchInput.trim().toLowerCase()
            if (!term) {
                return this.imported_curricula
            }

            return this.imported_curricula.filter((curriculum) => {
                const haystack = [curriculum.title, curriculum.description]
                    .filter(Boolean)
                    .join(' ')
                    .toLowerCase()

                return haystack.includes(term)
            })
        },
    },

    async beforeMount() {
        this.curriculumStore = useCurriculumStore()
        this.searchInput = this.curriculumStore.search
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
        onSearchChanged(value) {
            this.curriculumStore.search = value || ''
            if (this.searchTimer) clearTimeout(this.searchTimer)
            this.searchTimer = setTimeout(async () => {
                this.currentPage = 1
                await this.curriculumStore.index({ page: 1, search: this.curriculumStore.search })
            }, 300)
        },
        openCreateDialog() {
            this.editing = null
            this.form = { title: '', description: '', semester_count: 2 }
            this.formErrors = {}
            this.dialogOpen = true
        },
        openEditDialog(curriculum) {
            this.editing = curriculum
            this.form = {
                title: curriculum.title || '',
                description: curriculum.description || '',
                semester_count: curriculum.semester_count ?? 2,
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
        async takeOverImportedCurriculum(curriculum) {
            this.takeoverLoadingId = curriculum.id
            try {
                const result = await this.curriculumStore.adoptImportedCurriculum(curriculum.id)
                if (result) {
                    this.currentPage = 1
                    await this.curriculumStore.index({ page: 1, search: this.curriculumStore.search })
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
            this.importedDeleteLoadingId = this.importedDeleteTarget.id
            try {
                const ok = await this.curriculumStore.destroyImportedCurriculum(this.importedDeleteTarget.id)
                if (ok) {
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
                    semester_count: this.form.semester_count ?? 2,
                    free_weeks: this.editing?.free_weeks ?? [],
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
    gap: 12px;
    flex-wrap: wrap;
}

.curricula-overview__search {
    flex: 1 1 260px;
    min-width: 220px;
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
